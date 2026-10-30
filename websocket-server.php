<?php

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/websocket-config.php';

use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\Socket\ConnectionInterface as SocketConnectionInterface;
use React\Socket\SocketServer;

/*
|--------------------------------------------------------------------------
| WebSocket Notification Server
|--------------------------------------------------------------------------
| WebSocket address: ws://0.0.0.0:<WEBSOCKET_PORT>
| Rate limiter:      127.0.0.1:8081
|
| This version logs each client's IP address, and lists which clients
| received every esp32.* event, so you can see if the ESP32 is connected.
|--------------------------------------------------------------------------
*/

class BillingNotifier implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    protected SplObjectStorage $patientPortalClients;

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
        $this->patientPortalClients = new SplObjectStorage();

        echo "[INIT] BillingNotifier initialized\n";
    }

    private function ipOf(ConnectionInterface $conn): string
    {
        try {
            return (string) ($conn->remoteAddress ?? 'unknown');
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        if (isset($conn->httpRequest)) {
            parse_str($conn->httpRequest->getUri()->getQuery(), $query);
            if (($query['channel'] ?? '') === 'patient-portal') {
                $this->patientPortalClients->attach($conn, [
                    'patient_id' => 0,
                    'admission_ids' => [],
                    'statement_ids' => [],
                ]);
            }
        }

        echo sprintf(
            "[%s] [WS OPEN] ID=%s | IP=%s | Total clients=%d\n",
            date('Y-m-d H:i:s'),
            $conn->resourceId,
            $this->ipOf($conn),
            $this->clients->count()
        );
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        echo sprintf(
            "[%s] [WS MESSAGE] ID=%s | IP=%s | Bytes=%d\n",
            date('Y-m-d H:i:s'),
            $from->resourceId,
            $this->ipOf($from),
            strlen((string) $msg)
        );

        $data = json_decode((string) $msg, true);

        if (!is_array($data)) {
            echo sprintf(
                "[WS WARNING] Invalid JSON from ID=%s\n",
                $from->resourceId
            );
            return;
        }

        $action = $data['action'] ?? '';

        if ($this->patientPortalClients->contains($from)) {
            if ($action === 'subscribe_patient_portal') {
                $this->patientPortalClients[$from] = [
                    'patient_id' => max(0, (int)($data['patient_id'] ?? 0)),
                    'admission_ids' => $this->normalizeSubscriptionIds($data['admission_ids'] ?? []),
                    'statement_ids' => $this->normalizeSubscriptionIds($data['statement_ids'] ?? []),
                ];
            }
            return;
        }

        if ($action !== 'broadcast') {
            echo sprintf(
                "[WS INFO] Ignored message from ID=%s | Action=%s\n",
                $from->resourceId,
                (string) $action
            );
            return;
        }

        $eventName = (string) ($data['event'] ?? 'update');

        $frame = json_encode([
            'type'       => 'event',
            'event'      => $eventName,
            'payload'    => $data['payload'] ?? [],
            'roles'      => $data['roles'] ?? [],
            'user_ids'   => $data['user_ids'] ?? [],
            'department' => $data['department_id'] ?? null,
            'timestamp'  => time(),
        ]);

        if ($frame === false) {
            echo "[WS ERROR] Failed to encode broadcast JSON\n";
            return;
        }

        $sent = 0;
        $failed = 0;
        $recipients = [];

        foreach ($this->clients as $client) {
            try {
                if ($this->patientPortalClients->contains($client)) {
                    if ($this->patientPortalSubscriptionMatches(
                        $this->patientPortalClients[$client],
                        is_array($data['payload'] ?? null) ? $data['payload'] : []
                    )) {
                        $client->send('{"type":"portal.refresh"}');
                        $sent++;
                    }
                    continue;
                }

                $client->send($frame);
                $sent++;
                $recipients[] = sprintf(
                    'ID=%s IP=%s',
                    $client->resourceId,
                    $this->ipOf($client)
                );
            } catch (\Throwable $e) {
                $failed++;

                echo sprintf(
                    "[WS SEND ERROR] ID=%s | %s\n",
                    $client->resourceId,
                    $e->getMessage()
                );
            }
        }

        echo sprintf(
            "[WS BROADCAST] Event=%s | Sent=%d | Failed=%d\n",
            $eventName,
            $sent,
            $failed
        );

        // For ESP32 notifications, show exactly who received it
        if (str_starts_with($eventName, 'esp32.')) {
            foreach ($recipients as $recipient) {
                echo "    -> " . $recipient . "\n";
            }
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
        if ($this->patientPortalClients->contains($conn)) {
            $this->patientPortalClients->detach($conn);
        }

        echo sprintf(
            "[%s] [WS CLOSE] ID=%s | IP=%s | Remaining clients=%d\n",
            date('Y-m-d H:i:s'),
            $conn->resourceId,
            $this->ipOf($conn),
            $this->clients->count()
        );
    }

    public function onError(
        ConnectionInterface $conn,
        \Exception $e
    ): void {
        echo sprintf(
            "[%s] [WS ERROR] ID=%s | %s\n",
            date('Y-m-d H:i:s'),
            $conn->resourceId,
            $e->getMessage()
        );

        $conn->close();
    }

    private function normalizeSubscriptionIds($ids): array
    {
        if (!is_array($ids)) return [];

        $normalized = [];
        foreach (array_slice($ids, 0, 50) as $id) {
            $id = (int)$id;
            if ($id > 0) $normalized[$id] = $id;
        }
        return array_values($normalized);
    }

    private function patientPortalSubscriptionMatches(array $subscription, array $payload): bool
    {
        $patientId = (int)($payload['patient_id'] ?? 0);
        if ($patientId > 0 && $patientId === (int)$subscription['patient_id']) {
            return true;
        }

        $admissionId = (int)($payload['admission_id'] ?? 0);
        if ($admissionId > 0 && in_array($admissionId, $subscription['admission_ids'], true)) {
            return true;
        }

        $statementId = (int)($payload['statement_id'] ?? 0);
        return $statementId > 0 && in_array($statementId, $subscription['statement_ids'], true);
    }
}

/*
|--------------------------------------------------------------------------
| WebSocket Server
|--------------------------------------------------------------------------
*/

$webSocketApp = new WsServer(
    new BillingNotifier()
);

$webSocketHttp = new HttpServer(
    $webSocketApp
);

$server = IoServer::factory(
    $webSocketHttp,
    WEBSOCKET_PORT,
    '0.0.0.0'
);

/*
|--------------------------------------------------------------------------
| In-memory Rate Limiter
|--------------------------------------------------------------------------
| This preserves your existing rate limiter.
| It is separate from the WebSocket connection handler.
|--------------------------------------------------------------------------
*/

$loginAttempts = [];

$rateLimiter = new SocketServer('127.0.0.1:8081');

$rateLimiter->on(
    'connection',
    static function (
        SocketConnectionInterface $connection
    ) use (&$loginAttempts): void {

        $buffer = '';

        $connection->on(
            'data',
            static function (string $chunk) use (
                $connection,
                &$buffer,
                &$loginAttempts
            ): void {

                $buffer .= $chunk;

                if (strlen($buffer) > 2048) {
                    $connection->end(
                        "{\"allowed\":false,\"retry_after\":60}\n"
                    );
                    return;
                }

                $lineEnd = strpos($buffer, "\n");

                if ($lineEnd === false) {
                    return;
                }

                $request = json_decode(
                    substr($buffer, 0, $lineEnd),
                    true
                );

                $ipAddress = is_array($request)
                    ? (string) ($request['ip'] ?? '')
                    : '';

                if (
                    filter_var(
                        $ipAddress,
                        FILTER_VALIDATE_IP
                    ) === false
                ) {
                    $connection->end(
                        "{\"allowed\":false,\"retry_after\":60}\n"
                    );
                    return;
                }

                $now = time();

                foreach ($loginAttempts as $ip => $attempts) {
                    if (
                        $now - $attempts['started_at'] >= 900
                    ) {
                        unset($loginAttempts[$ip]);
                    }
                }

                if (!isset($loginAttempts[$ipAddress])) {
                    if (count($loginAttempts) >= 10000) {
                        $connection->end(
                            "{\"allowed\":false,\"retry_after\":900}\n"
                        );
                        return;
                    }

                    $loginAttempts[$ipAddress] = [
                        'started_at' => $now,
                        'count' => 0,
                    ];
                }

                $attempts = &$loginAttempts[$ipAddress];

                if ($attempts['count'] >= 10) {
                    $retryAfter = max(
                        1,
                        900 - ($now - $attempts['started_at'])
                    );

                    $connection->end(
                        json_encode([
                            'allowed' => false,
                            'retry_after' => $retryAfter,
                        ]) . "\n"
                    );

                    unset($attempts);
                    return;
                }

                $attempts['count']++;

                $connection->end(
                    "{\"allowed\":true,\"retry_after\":0}\n"
                );

                unset($attempts);
            }
        );
    }
);

$rateLimiter->on(
    'error',
    static function (\Throwable $error): void {
        error_log(
            '[patient_portal_rate_limit] ' .
            $error->getMessage()
        );
    }
);

/*
|--------------------------------------------------------------------------
| Start Servers
|--------------------------------------------------------------------------
*/

echo "============================================\n";
echo " Billing Notification WebSocket Server\n";
echo "============================================\n";
echo "WebSocket:  ws://0.0.0.0:" . WEBSOCKET_PORT . "\n";
echo "Rate limit: 127.0.0.1:8081\n";
echo "Started:    " . date('Y-m-d H:i:s') . "\n";
echo "Waiting for connections...\n";
echo "============================================\n";

$server->run();