/*
 * ESP32-CYD Nurse Notification Display Firmware
 * Part of Billing Hospital Integration
 *
 * This firmware turns the ESP32-CYD into a passive notification display.
 * It connects to the hospital WiFi (configured via captive portal) and
 * listens for notifications pushed from the nurse dashboard over WebSocket.
 *
 * Features:
 * - Captive portal WiFi configuration (AP mode, web setup page)
 * - Connects to hospital WiFi and WebSocket server (ws://server:8080)
 * - Listens for esp32.notification events broadcast from the nurse side
 * - Displays notifications on the CYD TFT screen
 * - Stores config in Preferences (NVS)
 *
 * Required libraries:
 * - TFT_eSPI (https://github.com/Bodmer/TFT_eSPI)
 * - ArduinoJson (https://arduinojson.org/)
 * - WebSockets by Links2004 (https://github.com/Links2004/arduinoWebSockets)
 */

#include <WiFi.h>
#include <WebSocketsClient.h>
#include <DNSServer.h>
#include <Preferences.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <TFT_eSPI.h>

// ============================================================================
// Hardware configuration for ESP32-CYD
// ============================================================================
#define TFT_MOSI 23
#define TFT_MISO 19
#define TFT_SCLK 18
#define TFT_CS   5
#define TFT_DC   16
#define TFT_RST  17
#define TFT_BL   4

// ============================================================================
// Captive portal configuration
// ============================================================================
const byte DNS_PORT = 53;
IPAddress apIP(192, 168, 4, 1);
const char* AP_SSID = "CYD-Nurse-Setup";
const char* AP_PASSWORD = "";

// ============================================================================
// Defaults
// ============================================================================
const char* DEFAULT_SERVER_URL = "ws://192.168.1.100:8080";

// ============================================================================
// Globals
// ============================================================================
TFT_eSPI tft = TFT_eSPI();
Preferences preferences;
WebSocketsClient webSocket;
DNSServer dnsServer;
WebServer httpServer(80);

struct DeviceConfig {
  char wifi_ssid[64];
  char wifi_password[64];
  char server_url[128];
  char device_name[64];
  char mac_address[18];
  int  department_id;
  bool configured;
};

DeviceConfig config;

unsigned long lastReconnectAttempt = 0;
const unsigned long RECONNECT_INTERVAL = 5000;
bool wsConnected = false;
bool displayNeedsRedraw = true;

// Notification auto-return timing
const unsigned long NOTIFICATION_DISPLAY_MS = 7000;
unsigned long notificationStartTime = 0;
bool notificationActive = false;

// ============================================================================
// TFT Helpers
// ============================================================================
void initDisplay() {
  tft.init();
  tft.setRotation(1);
  tft.fillScreen(TFT_BLACK);
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.setTextSize(2);
  tft.setCursor(10, 10);
  tft.println("ESP32-CYD Nurse Display");
  tft.setTextSize(1);
  tft.println("Initializing...");
}

void drawHeader(const char* title) {
  tft.fillRect(0, 0, tft.width(), 30, TFT_BLUE);
  tft.setTextColor(TFT_WHITE);
  tft.setTextSize(2);
  tft.setCursor(5, 6);
  tft.print(title);
}

void drawStatusBar(bool connected, const char* ip) {
  int y = 32;
  tft.fillRect(0, y, tft.width(), 20, TFT_BLACK);
  tft.setTextColor(connected ? TFT_GREEN : TFT_RED, TFT_BLACK);
  tft.setTextSize(1);
  tft.setCursor(5, y + 4);
  tft.print(connected ? "WS: Connected" : "WS: Disconnected");
  if (connected && ip) {
    tft.setCursor(tft.width() - 140, y + 4);
    tft.print(ip);
  }
}

void clearContentArea() {
  tft.fillRect(0, 54, tft.width(), tft.height() - 54, TFT_BLACK);
}

void drawNotification(String title, String body, String sender, String timeStr) {
  tft.fillScreen(TFT_BLACK);
  drawHeader("Nurse Notification");
  drawStatusBar(wsConnected, WiFi.localIP().toString().c_str());

  tft.setTextColor(TFT_CYAN, TFT_BLACK);
  tft.setTextSize(2);
  tft.setCursor(10, 60);
  tft.print(title);

  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.setTextSize(2);
  tft.setCursor(10, 95);
  tft.print(body);

  if (sender.length() > 0) {
    tft.setTextColor(TFT_GREY, TFT_BLACK);
    tft.setTextSize(1);
    tft.setCursor(10, 140);
    tft.print("From: ");
    tft.print(sender);
  }

  if (timeStr.length() > 0) {
    tft.setCursor(10, 155);
    tft.print(timeStr);
  }

  tft.setTextColor(TFT_GREY, TFT_BLACK);
  tft.setTextSize(1);
  tft.setCursor(10, tft.height() - 15);
  tft.print("Auto-return in 7s");

  notificationActive = true;
  notificationStartTime = millis();
  displayNeedsRedraw = false;
}

void drawWaitingScreen() {
  tft.fillScreen(TFT_BLACK);
  drawHeader("Nurse Notification Display");
  drawStatusBar(wsConnected, WiFi.localIP().toString().c_str());

  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.setTextSize(2);
  tft.setCursor(10, 60);
  tft.print("Device: ");
  tft.println(config.device_name);

  tft.setTextSize(1);
  tft.setTextColor(TFT_CYAN, TFT_BLACK);
  tft.setCursor(10, 100);
  tft.print("Department ID: ");
  tft.println(config.department_id);

  tft.setTextColor(TFT_GREY, TFT_BLACK);
  tft.setCursor(10, 130);
  tft.print("Listening for notifications...");
  tft.setCursor(10, 145);
  tft.print("Send a notification from the nurse dashboard.");
}

void drawCaptivePortal() {
  tft.fillScreen(TFT_BLACK);
  drawHeader("Captive Portal");
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.setTextSize(2);
  tft.setCursor(10, 50);
  tft.print("SSID: ");
  tft.println(AP_SSID);
  tft.setTextSize(1);
  tft.print("Connect to this WiFi");
  tft.print("and open:");
  tft.println("http://192.168.4.1");
}

// ============================================================================
// Preferences / Config
// ============================================================================
void loadConfig() {
  preferences.begin("cyd-config", false);

  memset(&config, 0, sizeof(config));

  size_t len = preferences.getBytes("wifi_ssid", config.wifi_ssid, sizeof(config.wifi_ssid));
  if (len == 0) strcpy(config.wifi_ssid, "");

  len = preferences.getBytes("wifi_password", config.wifi_password, sizeof(config.wifi_password));
  if (len == 0) strcpy(config.wifi_password, "");

  len = preferences.getBytes("server_url", config.server_url, sizeof(config.server_url));
  if (len == 0) strcpy(config.server_url, DEFAULT_SERVER_URL);

  len = preferences.getBytes("device_name", config.device_name, sizeof(config.device_name));
  if (len == 0) strcpy(config.device_name, "CYD-Nurse-Display");

  len = preferences.getBytes("mac_address", config.mac_address, sizeof(config.mac_address));
  if (len == 0) {
    String mac = WiFi.macAddress();
    mac.toUpperCase();
    strcpy(config.mac_address, mac.c_str());
  }

  config.department_id = preferences.getInt("department_id", 0);
  config.configured = preferences.getBool("configured", false);

  preferences.end();
}

void saveConfig() {
  preferences.begin("cyd-config", false);
  preferences.putBytes("wifi_ssid", config.wifi_ssid, strlen(config.wifi_ssid) + 1);
  preferences.putBytes("wifi_password", config.wifi_password, strlen(config.wifi_password) + 1);
  preferences.putBytes("server_url", config.server_url, strlen(config.server_url) + 1);
  preferences.putBytes("device_name", config.device_name, strlen(config.device_name) + 1);
  preferences.putBytes("mac_address", config.mac_address, strlen(config.mac_address) + 1);
  preferences.putInt("department_id", config.department_id);
  preferences.putBool("configured", config.configured);
  preferences.end();
}

// ============================================================================
// WiFi Connection
// ============================================================================
void connectWiFi() {
  if (strlen(config.wifi_ssid) == 0) {
    return;
  }

  WiFi.mode(WIFI_STA);
  WiFi.begin(config.wifi_ssid, config.wifi_password);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 15000) {
    delay(500);
  }
}

void startCaptivePortal() {
  WiFi.mode(WIFI_AP);
  WiFi.softAPConfig(apIP, apIP, IPAddress(255, 255, 255, 0));
  WiFi.softAP(AP_SSID, AP_PASSWORD);

  dnsServer.start(DNS_PORT, "*", apIP);

  httpServer.on("/", HTTP_GET, handleRoot);
  httpServer.on("/config", HTTP_POST, handleConfig);
  httpServer.on("/info", HTTP_GET, handleInfo);
  httpServer.onNotFound(handleRoot);

  httpServer.begin();

  drawCaptivePortal();
}

void handleRoot() {
  String html = R"rawliteral(
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ESP32-CYD Setup</title>
  <style>
    body { font-family: sans-serif; max-width: 400px; margin: 40px auto; padding: 20px; background: #f0f0f0; }
    .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { font-size: 20px; margin-top: 0; color: #333; }
    label { display: block; margin: 12px 0 6px; font-weight: 600; color: #555; font-size: 14px; }
    input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
    button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 20px; }
    button:hover { background: #0056b3; }
    .hint { font-size: 12px; color: #888; margin-top: 4px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>ESP32-CYD Nurse Display Setup</h1>
    <form id="configForm">
      <label>WiFi SSID</label>
      <input type="text" name="wifi_ssid" required>
      <label>WiFi Password</label>
      <input type="password" name="wifi_password">
      <label>WebSocket Server URL</label>
      <input type="url" name="server_url" value="ws://192.168.1.100:8080" required>
      <div class="hint">Example: ws://192.168.1.100:8080</div>
      <label>Device Name</label>
      <input type="text" name="device_name" value="CYD-Nurse-Display">
      <label>Department ID</label>
      <input type="number" name="department_id" value="2" min="1" required>
      <div class="hint">2 = Nursing Station (from hospital admin)</div>
      <button type="submit">Save & Connect</button>
    </form>
  </div>
  <script>
    document.getElementById('configForm').onsubmit = async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const data = Object.fromEntries(form.entries());
      const res = await fetch('/config', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
      });
      if (res.ok) {
        alert('Saved! Device will restart and connect.');
      } else {
        alert('Save failed.');
      }
    };
  </script>
</body>
</html>
)rawliteral";
  httpServer.send(200, "text/html", html);
}

void handleConfig() {
  if (httpServer.method() != HTTP_POST) {
    httpServer.send(405, "text/plain", "Method not allowed");
    return;
  }

  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, httpServer.getArg("plain"));
  if (err) {
    httpServer.send(400, "text/plain", "Invalid JSON");
    return;
  }

  strncpy(config.wifi_ssid, doc["wifi_ssid"] | "", sizeof(config.wifi_ssid) - 1);
  strncpy(config.wifi_password, doc["wifi_password"] | "", sizeof(config.wifi_password) - 1);
  strncpy(config.server_url, doc["server_url"] | DEFAULT_SERVER_URL, sizeof(config.server_url) - 1);
  strncpy(config.device_name, doc["device_name"] | "CYD-Nurse-Display", sizeof(config.device_name) - 1);
  config.department_id = doc["department_id"] | 2;
  config.configured = true;

  saveConfig();

  httpServer.send(200, "application/json", "{\"status\":\"ok\"}");

  delay(1000);
  ESP.restart();
}

void handleInfo() {
  String json = "{";
  json += "\"ssid\":\"" + String(config.wifi_ssid) + "\",";
  json += "\"configured\":" + String(config.configured ? "true" : "false") + ",";
  json += "\"department_id\":" + String(config.department_id) + ",";
  json += "\"mac\":\"" + String(config.mac_address) + "\"";
  json += "}";
  httpServer.send(200, "application/json", json);
}

// ============================================================================
// WebSocket Event Handler
// ============================================================================
void webSocketEvent(WStype_t type, uint8_t * payload, size_t length) {
  switch (type) {
    case WStype_DISCONNECTED:
      wsConnected = false;
      Serial.println("[WS] Disconnected");
      displayNeedsRedraw = true;
      break;

    case WStype_CONNECTED:
      wsConnected = true;
      Serial.println("[WS] Connected to server");
      displayNeedsRedraw = true;
      break;

    case WStype_TEXT: {
      String msg = String((char*)payload);
      Serial.printf("[WS] Received: %s\n", msg.c_str());

      StaticJsonDocument<2048> doc;
      DeserializationError err = deserializeJson(doc, msg);
      if (err) {
        Serial.println("[WS] Failed to parse JSON");
        return;
      }

      const char* event = doc["event"] | "";
      if (strcmp(event, "esp32.notification") != 0) {
        return;
      }

      JsonObject payloadObj = doc["payload"];
      if (payloadObj.isNull()) {
        return;
      }

      int targetDeviceId = payloadObj["device_id"] | 0;
      int targetDeptId = payloadObj["department_id"] | 0;

      // Only display if this notification is for this device OR for this device's department
      bool shouldDisplay = false;
      if (targetDeviceId > 0) {
        // We don't store device_id in config (it's assigned by server), but we check department match
        // If a specific device is targeted and it's not this device's department, skip
        if (targetDeptId == config.department_id || config.department_id == 0) {
          shouldDisplay = true;
        }
      } else if (targetDeptId == config.department_id || config.department_id == 0) {
        shouldDisplay = true;
      }

      if (!shouldDisplay) {
        return;
      }

      const char* title = payloadObj["title"] | "Notification";
      const char* message = payloadObj["message"] | "";
      const char* sender = payloadObj["sent_by_name"] | "";
      const char* sentAt = payloadObj["sent_at"] | "";

      drawNotification(title, message, sender, sentAt);
      break;
    }

    case WStype_BIN:
    case WStype_ERROR:
    case WStype_FRAGMENT_TEXT_START:
    case WStype_FRAGMENT_BIN_START:
    case WStype_FRAGMENT:
    case WStype_FRAGMENT_FIN:
      break;
  }
}

void connectWebSocket() {
  if (strlen(config.server_url) == 0) return;

  String url = config.server_url;
  // Parse ws://host:port
  url.replace("ws://", "");
  url.replace("wss://", "");

  int portPos = url.indexOf(':');
  int pathPos = url.indexOf('/');

  String host;
  int port = 80;
  String path = "/";

  if (portPos != -1) {
    host = url.substring(0, portPos);
    String rest = url.substring(portPos + 1);
    if (pathPos != -1) {
      port = rest.substring(0, pathPos - portPos - 1).toInt();
      path = rest.substring(pathPos - portPos - 1);
    } else {
      port = rest.toInt();
    }
  } else if (pathPos != -1) {
    host = url.substring(0, pathPos);
    path = url.substring(pathPos);
  } else {
    host = url;
  }

  Serial.printf("[WS] Connecting to %s:%d%s\n", host.c_str(), port, path.c_str());
  webSocket.begin(host, port, path);
  webSocket.onEvent(webSocketEvent);
  webSocket.setReconnectInterval(5000);
}

// ============================================================================
// Setup / Loop
// ============================================================================
void setup() {
  Serial.begin(115200);

  initDisplay();

  loadConfig();

  if (!config.configured || strlen(config.wifi_ssid) == 0) {
    startCaptivePortal();
  } else {
    connectWiFi();
    if (WiFi.status() == WL_CONNECTED) {
      connectWebSocket();
    }
    drawWaitingScreen();
  }
}

void loop() {
  if (!config.configured || strlen(config.wifi_ssid) == 0) {
    dnsServer.processNextRequest();
    httpServer.handleClient();
    return;
  }

  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    if (WiFi.status() == WL_CONNECTED && !wsConnected) {
      connectWebSocket();
    }
  }

  webSocket.loop();

  unsigned long now = millis();

  // Auto-return to waiting screen after notification display duration
  if (notificationActive && now - notificationStartTime >= NOTIFICATION_DISPLAY_MS) {
    notificationActive = false;
    drawWaitingScreen();
  }

  if (!wsConnected && now - lastReconnectAttempt >= RECONNECT_INTERVAL) {
    lastReconnectAttempt = now;
    connectWebSocket();
  }

  if (!notificationActive && displayNeedsRedraw) {
    displayNeedsRedraw = false;
    if (WiFi.status() == WL_CONNECTED && wsConnected) {
      drawWaitingScreen();
    }
  }

  delay(100);
}
