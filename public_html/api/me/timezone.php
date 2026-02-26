<?php
// public_html/api/me/timezone.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$payload = read_json_body();
$timezone = trim($payload['timezone'] ?? ($_POST['timezone'] ?? ''));

if ($timezone === '') {
    json_response(['ok' => false, 'error' => 'Timezone is required'], 400);
}

if (!in_array($timezone, timezone_identifiers_list(), true)) {
    json_response(['ok' => false, 'error' => 'Invalid timezone'], 400);
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$stmt = $pdo->prepare('UPDATE users SET timezone = ? WHERE id = ?');
$stmt->execute([$timezone, $userId]);

$_SESSION['user']['timezone'] = $timezone;

json_response(['ok' => true]);
