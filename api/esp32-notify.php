<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/ws_notify.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_api_role(1, 3);

require_once __DIR__ . '/../config/connection.php';

$json = file_get_contents('php://input');
$parameters = json_decode((string)$json, true);
if (!is_array($parameters)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$deviceId = (int)($parameters['device_id'] ?? 0);
$departmentId = (int)($parameters['department_id'] ?? 0);
$title = trim((string)($parameters['title'] ?? ''));
$message = trim((string)($parameters['message'] ?? ''));

if ($title === '' && $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title or message is required.']);
    exit;
}

if ($deviceId > 0) {
    $stmt = $pdo->prepare(
        'SELECT device_id, device_name, department_id, is_active
         FROM `esp32_devices`
         WHERE device_id = ? LIMIT 1'
    );
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();
    if (!$device) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'ESP32 device not found.']);
        exit;
    }
    if ((int)$device['is_active'] !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ESP32 device is inactive.']);
        exit;
    }
    $departmentId = (int)$device['department_id'];
    $targetDeviceName = $device['device_name'];
} else {
    if ($departmentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'device_id or department_id is required.']);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT department_id FROM `department` WHERE department_id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$departmentId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Department not found.']);
        exit;
    }
    $targetDeviceName = null;
}

$payload = [
    'notification_id' => uniqid('not_', true),
    'title' => $title,
    'message' => $message,
    'device_id' => $deviceId > 0 ? $deviceId : null,
    'target_device_name' => $targetDeviceName,
    'department_id' => $departmentId,
    'sent_by_user_id' => (int)($_SESSION['user']['user_id'] ?? 0),
    'sent_by_name' => trim($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'] ?? ''),
    'sent_at' => date('Y-m-d H:i:s'),
];

ws_broadcast('esp32.notification', $payload, [1, 2, 3, 4, 5], $departmentId);

echo json_encode([
    'success' => true,
    'message' => 'Notification sent.',
    'notification_id' => $payload['notification_id'],
    'department_id' => $departmentId,
    'device_id' => $deviceId > 0 ? $deviceId : null,
]);
