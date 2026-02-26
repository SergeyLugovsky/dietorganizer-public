<?php
// public_html/api/push/subscribe.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

// Логируем запросы подписки для диагностики.
$logEnabled = (bool)($config['logging']['push_debug'] ?? true);
$debugLog = APP_PUBLIC . '/storage/logs/push_debug.log';
if ($logEnabled && !is_dir(dirname($debugLog))) {
    @mkdir(dirname($debugLog), 0775, true);
}
$logLine = function (string $message) use ($debugLog, $logEnabled): void {
    if (!$logEnabled) {
        return;
    }
    $timestamp = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message . PHP_EOL;
    $result = @file_put_contents($debugLog, $line, FILE_APPEND);
    if ($result === false) {
        error_log('Push debug log write failed: ' . $message);
    }
};

$logLine('Request start. Method=' . ($_SERVER['REQUEST_METHOD'] ?? ''));

$payload = read_json_body();
$subscription = $payload['subscription'] ?? $payload;

$endpoint = trim($subscription['endpoint'] ?? '');
$keys = $subscription['keys'] ?? [];
$p256dh = trim($keys['p256dh'] ?? '');
$auth = trim($keys['auth'] ?? '');

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    $logLine('Invalid payload: missing endpoint or keys.');
    json_response(['ok' => false, 'error' => 'Invalid subscription payload'], 400);
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    $logLine('Unauthorized: missing user id.');
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
if ($userAgent !== null && strlen($userAgent) > 255) {
    $userAgent = substr($userAgent, 0, 255);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, last_seen_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), last_seen_at = NOW()'
    );
    $stmt->execute([$userId, $endpoint, $p256dh, $auth, $userAgent]);
} catch (Throwable $e) {
    $logLine('DB error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error'], 500);
}

$logLine('Subscription saved for user ' . $userId . '.');

json_response(['ok' => true]);
