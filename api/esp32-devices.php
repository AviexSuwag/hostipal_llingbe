<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_api_role(1, 3);

require_once __DIR__ . '/../config/connection.php';

class Esp32Device
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT d.device_id, d.device_name, d.mac_address, d.department_id,
                    d.api_key, d.is_active, d.last_seen, d.ip_address,
                    d.created_at, d.updated_at,
                    dep.department_name, dep.department_code
             FROM `esp32_devices` d
             INNER JOIN `department` dep ON dep.department_id = d.department_id
             ORDER BY d.device_name'
        );
        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $deviceName = trim((string)($data['device_name'] ?? ''));
        $macAddress = strtoupper(trim((string)($data['mac_address'] ?? '')));
        $departmentId = (int)($data['department_id'] ?? 0);

        if ($deviceName === '') {
            throw new RuntimeException('Device name is required.');
        }
        if (!preg_match('/^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/', $macAddress)) {
            throw new RuntimeException('Invalid MAC address format (e.g. AA:BB:CC:DD:EE:FF).');
        }
        if ($departmentId <= 0) {
            throw new RuntimeException('A valid department must be selected.');
        }

        $apiKey = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            'INSERT INTO `esp32_devices`
                (device_name, mac_address, department_id, api_key, is_active)
             VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$deviceName, $macAddress, $departmentId, $apiKey]);

        return [
            'device_id' => (int)$this->pdo->lastInsertId(),
            'device_name' => $deviceName,
            'mac_address' => $macAddress,
            'department_id' => $departmentId,
            'api_key' => $apiKey,
        ];
    }

    public function update(int $deviceId, array $data): array
    {
        $deviceName = trim((string)($data['device_name'] ?? ''));
        $departmentId = (int)($data['department_id'] ?? 0);
        $isActive = !empty($data['is_active']) ? 1 : 0;

        if ($deviceName === '') {
            throw new RuntimeException('Device name is required.');
        }
        if ($departmentId <= 0) {
            throw new RuntimeException('A valid department must be selected.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE `esp32_devices`
             SET device_name = ?, department_id = ?, is_active = ?
             WHERE device_id = ?'
        );
        $stmt->execute([$deviceName, $departmentId, $isActive, $deviceId]);

        return ['success' => true];
    }

    public function delete(int $deviceId): array
    {
        $stmt = $this->pdo->prepare('DELETE FROM `esp32_devices` WHERE device_id = ?');
        $stmt->execute([$deviceId]);
        return ['success' => true];
    }

    public function toggleActive(int $deviceId): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `esp32_devices`
             SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
             WHERE device_id = ?'
        );
        $stmt->execute([$deviceId]);

        $stmt = $this->pdo->prepare('SELECT is_active FROM `esp32_devices` WHERE device_id = ?');
        $stmt->execute([$deviceId]);
        $isActive = (bool)$stmt->fetchColumn();

        return ['success' => true, 'is_active' => $isActive];
    }

    public function regenerateApiKey(int $deviceId): array
    {
        $apiKey = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'UPDATE `esp32_devices` SET api_key = ? WHERE device_id = ?'
        );
        $stmt->execute([$apiKey, $deviceId]);
        return ['success' => true, 'api_key' => $apiKey];
    }
}

try {
    $esp32 = new Esp32Device($pdo);
    $operation = $_GET['operation'] ?? $_POST['operation'] ?? '';
    $json = $_SERVER['REQUEST_METHOD'] === 'GET'
        ? ($_GET['json'] ?? '')
        : ($_POST['json'] ?? '');
    $parameters = $json === '' ? [] : json_decode((string)$json, true);
    if ($json !== '' && !is_array($parameters)) {
        api_json(400, ['success' => false, 'message' => 'Invalid JSON parameters.']);
    }
    $parameters = is_array($parameters) ? $parameters : [];

    switch ($operation) {
        case 'list':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                api_json(405, ['success' => false, 'message' => 'Use GET for this operation.']);
            }
            api_json(200, ['success' => true, 'data' => $esp32->list()]);

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_json(405, ['success' => false, 'message' => 'Use POST for this operation.']);
            }
            $_POST['json'] = json_encode($parameters);
            $result = $esp32->create($parameters);
            api_json(201, ['success' => true, 'data' => $result]);

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_json(405, ['success' => false, 'message' => 'Use POST for this operation.']);
            }
            $deviceId = (int)($parameters['device_id'] ?? $_GET['id'] ?? 0);
            if ($deviceId <= 0) {
                api_json(400, ['success' => false, 'message' => 'Missing device id.']);
            }
            $_POST['json'] = json_encode($parameters);
            $esp32->update($deviceId, $parameters);
            api_json(200, ['success' => true, 'message' => 'Device updated.']);

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                api_json(405, ['success' => false, 'message' => 'Use DELETE for this operation.']);
            }
            $deviceId = (int)($_GET['id'] ?? $parameters['device_id'] ?? 0);
            if ($deviceId <= 0) {
                api_json(400, ['success' => false, 'message' => 'Missing device id.']);
            }
            $esp32->delete($deviceId);
            api_json(200, ['success' => true, 'message' => 'Device deleted.']);

        case 'toggle-active':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_json(405, ['success' => false, 'message' => 'Use POST for this operation.']);
            }
            $deviceId = (int)($_GET['id'] ?? $parameters['device_id'] ?? 0);
            if ($deviceId <= 0) {
                api_json(400, ['success' => false, 'message' => 'Missing device id.']);
            }
            $result = $esp32->toggleActive($deviceId);
            api_json(200, ['success' => true, 'data' => $result]);

        case 'regenerate-key':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_json(405, ['success' => false, 'message' => 'Use POST for this operation.']);
            }
            $deviceId = (int)($parameters['device_id'] ?? $_GET['id'] ?? 0);
            if ($deviceId <= 0) {
                api_json(400, ['success' => false, 'message' => 'Missing device id.']);
            }
            $result = $esp32->regenerateApiKey($deviceId);
            api_json(200, ['success' => true, 'data' => $result]);

        default:
            api_json(400, ['success' => false, 'message' => 'Unsupported ESP32 device operation.']);
    }
} catch (Throwable $error) {
    error_log('[api/esp32-devices.php] ' . $error->getMessage());
    api_json(500, ['success' => false, 'message' => 'Could not process the ESP32 device request.']);
}
