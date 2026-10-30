<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connection.php';

define('ESP32_API_KEY_HEADER', 'X-API-Key');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Missing API key.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT device_id, device_name, department_id, is_active
     FROM `esp32_devices`
     WHERE api_key = ? LIMIT 1'
);
$stmt->execute([$apiKey]);
$device = $stmt->fetch();
if (!$device) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key.']);
    exit;
}

if ((int)$device['is_active'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Device is inactive.']);
    exit;
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$stmt = $pdo->prepare(
    'UPDATE `esp32_devices`
     SET last_seen = NOW(), ip_address = ?
     WHERE device_id = ?'
);
$stmt->execute([$ipAddress, (int)$device['device_id']]);

echo json_encode([
    'success' => true,
    'device_id' => (int)$device['device_id'],
    'device_name' => $device['device_name'],
    'department_id' => (int)$device['department_id'],
    'message' => 'Check-in recorded.',
]);
