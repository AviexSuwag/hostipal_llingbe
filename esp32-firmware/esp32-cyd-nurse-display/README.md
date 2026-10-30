# ESP32-CYD Nurse Station Display Firmware

This firmware runs on the ESP32-CYD (Cheap Yellow Display) and integrates with the Billing Hospital system to show nurse notifications and department queues on the TFT screen.

## Features

- **Captive Portal WiFi Setup**: On first boot, the ESP32 creates a WiFi access point (`CYD-Nurse-Setup`) with a web page to configure hospital WiFi credentials.
- **Preferences Storage**: WiFi SSID/password, API URL, device name, MAC address, API key, and department ID are stored in ESP32 NVS (Preferences).
- **API Polling**: Periodically polls `api/esp32-queue.php` for the department's service request queue.
- **Check-in**: Periodically calls `api/esp32-checkin.php` to record device presence.
- **TFT Display**: Shows queue items and notifications on the CYD's ILI9341 screen.

## Hardware

- ESP32-CYD board (ESP32 + ILI9341 TFT display)
- TFT_eSPI library (configured for CYD pins)

## Required Arduino Libraries

Install via Arduino Library Manager or PlatformIO:

| Library | Purpose |
|---------|---------|
| TFT_eSPI by Bodmer | TFT display driver |
| ArduinoJson by Benoit Blanchon | JSON parsing |

## PlatformIO

```ini
[env:esp32-cyd]
platform = espressif32
board = esp32dev
framework = arduino
lib_deps =
  bodmer/TFT_eSPI@^2.5.40
  bblanchon/ArduinoJson@^6.21.2
```

## TFT_eSPI Configuration

You must configure `User_Setup.h` in the TFT_eSPI library for the CYD board. A minimal setup for the ILI9341-based CYD:

```cpp
#define ILI9341_DRIVER
#define TFT_MOSI 23
#define TFT_MISO 19
#define TFT_SCLK 18
#define TFT_CS   5
#define TFT_DC   16
#define TFT_RST  17
#define LOAD_GLCD
#define LOAD_FONT2
#define LOAD_FONT4
#define LOAD_FONT6
#define LOAD_FONT7
#define LOAD_FONT8
#define LOAD_GFXFF
#define SPI_FREQUENCY 27000000
#define SPI_READ_FREQUENCY 6000000
#define SPI_TOUCH_FREQUENCY 2500000
```

## Setup

1. **Deploy the hospital backend** — ensure the migration `database/migrations/001_esp32_devices.sql` is applied to your MariaDB database.
2. **Register the device** — in the Nurse portal, go to **ESP32 Displays** and register a new device. Note the MAC address and the generated API key.
3. **Flash the firmware** — upload the sketch to your ESP32-CYD.
4. **Captive Portal** — on first boot, connect to WiFi `CYD-Nurse-Setup` and open `http://192.168.4.1` to enter your hospital WiFi credentials, API URL, and department ID.
5. **The device reboots**, connects to hospital WiFi, registers itself with the API (if no API key is stored), and begins polling for queue data.

## API Endpoints Used

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/esp32-devices.php?operation=create` | POST | Session (nurse/admin) | Register device, returns API key |
| `/api/esp32-checkin.php` | POST | X-API-Key header | Record device check-in |
| `/api/esp32-queue.php?department_id=N` | GET | X-API-Key header | Fetch department queue |

## Display Behavior

- **Not configured**: Shows captive portal instructions (SSID and IP `192.168.4.1`).
- **Connecting**: Shows "Connecting to WiFi..." message.
- **Connected + Queue**: Shows list of pending service requests with patient name, department, and status.
- **API Error**: Shows red error notification.
