<?php

$configuredWebSocketPort = getenv('WEBSOCKET_PORT');
$configuredWebSocketPort = $configuredWebSocketPort === false || $configuredWebSocketPort === ''
    ? '8090'
    : $configuredWebSocketPort;
$validatedWebSocketPort = filter_var(
    $configuredWebSocketPort,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 65535]]
);

if ($validatedWebSocketPort === false) {
    throw new RuntimeException('WEBSOCKET_PORT must be a valid TCP port between 1 and 65535.');
}

if (!defined('WEBSOCKET_PORT')) {
    define('WEBSOCKET_PORT', $validatedWebSocketPort);
}
