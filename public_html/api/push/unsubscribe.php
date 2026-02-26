<?php
// public_html/api/push/unsubscribe.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$payload = read_json_body();
$endpoint = trim($payload['endpoint'] ?? '');

if ($endpoint === '') {
    json_response(['ok' => false, 'error' => 'Missing endpoint'], 400);
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
$stmt->execute([$userId, $endpoint]);

json_response(['ok' => true]);
