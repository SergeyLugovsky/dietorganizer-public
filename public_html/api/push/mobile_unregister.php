<?php
// public_html/api/push/mobile_unregister.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$payload = read_json_body();
$provider = strtolower(trim((string) ($payload['provider'] ?? 'fcm')));
$platform = strtolower(trim((string) ($payload['platform'] ?? '')));
$token = trim((string) ($payload['token'] ?? ''));
$deviceId = trim((string) ($payload['device_id'] ?? ''));

if ($provider !== 'fcm') {
    json_response(['ok' => false, 'error' => 'Unsupported provider'], 400);
}
if ($token === '' && $deviceId === '') {
    json_response(['ok' => false, 'error' => 'Token or device_id is required'], 400);
}
if ($platform !== '' && !in_array($platform, ['android', 'ios'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid platform'], 400);
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

try {
    if ($token !== '') {
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare('DELETE FROM mobile_push_tokens WHERE user_id = ? AND provider = ? AND token_hash = ?');
        $stmt->execute([$userId, $provider, $tokenHash]);
    } else {
        $deviceId = substr($deviceId, 0, 191);
        if ($platform !== '') {
            $stmt = $pdo->prepare('DELETE FROM mobile_push_tokens WHERE user_id = ? AND provider = ? AND device_id = ? AND platform = ?');
            $stmt->execute([$userId, $provider, $deviceId, $platform]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM mobile_push_tokens WHERE user_id = ? AND provider = ? AND device_id = ?');
            $stmt->execute([$userId, $provider, $deviceId]);
        }
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Database error'], 500);
}

json_response(['ok' => true, 'deleted' => (int) $stmt->rowCount()]);
