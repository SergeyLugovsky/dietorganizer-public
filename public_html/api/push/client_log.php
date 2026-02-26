<?php
// public_html/api/push/client_log.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$logEnabled = (bool)($config['logging']['push_client'] ?? true);
if (!$logEnabled) {
    json_response(['ok' => true]);
}

$payload = read_json_body();
$message = trim((string)($payload['message'] ?? ''));
$context = $payload['context'] ?? null;

if ($message === '') {
    json_response(['ok' => false, 'error' => 'Message is required'], 400);
}

$logDir = APP_PUBLIC . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/push_client.log';

$entry = [
    'time' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
    'user_id' => (int)(current_user()['id'] ?? 0),
    'message' => $message,
    'context' => $context,
];

@file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

json_response(['ok' => true]);
