<?php
// public_html/api/push/mobile_status.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS cnt, MAX(last_seen_at) AS last_seen
     FROM mobile_push_tokens
     WHERE user_id = ? AND active = 1'
);
$stmt->execute([$userId]);
$summary = $stmt->fetch() ?: [];

$platformStmt = $pdo->prepare(
    'SELECT platform, COUNT(*) AS cnt
     FROM mobile_push_tokens
     WHERE user_id = ? AND active = 1
     GROUP BY platform'
);
$platformStmt->execute([$userId]);
$platformRows = $platformStmt->fetchAll() ?: [];

$byPlatform = [
    'android' => 0,
    'ios' => 0,
];
foreach ($platformRows as $row) {
    $platform = strtolower((string) ($row['platform'] ?? ''));
    if (!isset($byPlatform[$platform])) {
        continue;
    }
    $byPlatform[$platform] = (int) ($row['cnt'] ?? 0);
}

json_response([
    'ok' => true,
    'count' => (int) ($summary['cnt'] ?? 0),
    'last_seen' => $summary['last_seen'] ?? null,
    'by_platform' => $byPlatform,
]);
