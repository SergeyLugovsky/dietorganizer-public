<?php
// public_html/api/push/mobile_register.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$payload = read_json_body();
$provider = strtolower(trim((string) ($payload['provider'] ?? 'fcm')));
$platform = strtolower(trim((string) ($payload['platform'] ?? 'android')));
$token = trim((string) ($payload['token'] ?? ''));
$deviceId = trim((string) ($payload['device_id'] ?? ''));
$appVersion = trim((string) ($payload['app_version'] ?? ''));

if ($provider !== 'fcm') {
    json_response(['ok' => false, 'error' => 'Unsupported provider'], 400);
}
if (!in_array($platform, ['android', 'ios'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid platform'], 400);
}
if ($token === '' || strlen($token) < 20 || strlen($token) > 4096) {
    json_response(['ok' => false, 'error' => 'Invalid token'], 400);
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$deviceId = $deviceId !== '' ? substr($deviceId, 0, 191) : null;
$appVersion = $appVersion !== '' ? substr($appVersion, 0, 64) : null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
if ($userAgent !== null && strlen($userAgent) > 255) {
    $userAgent = substr($userAgent, 0, 255);
}

$tokenHash = hash('sha256', $token);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO mobile_push_tokens (user_id, provider, platform, device_id, token, token_hash, app_version, user_agent, active, last_seen_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            platform = VALUES(platform),
            device_id = VALUES(device_id),
            token = VALUES(token),
            app_version = VALUES(app_version),
            user_agent = VALUES(user_agent),
            active = 1,
            last_seen_at = NOW()'
    );
    $stmt->execute([$userId, $provider, $platform, $deviceId, $token, $tokenHash, $appVersion, $userAgent]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Database error'], 500);
}

json_response(['ok' => true, 'provider' => $provider, 'platform' => $platform]);
