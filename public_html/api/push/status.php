<?php
// public_html/api/push/status.php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$webStmt = $pdo->prepare(
    'SELECT COUNT(*) AS cnt, MAX(last_seen_at) AS last_seen
     FROM push_subscriptions
     WHERE user_id = ?'
);
$webStmt->execute([$userId]);
$webRow = $webStmt->fetch() ?: [];

$mobileRow = ['cnt' => 0, 'last_seen' => null];
try {
    $mobileStmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt, MAX(last_seen_at) AS last_seen
         FROM mobile_push_tokens
         WHERE user_id = ? AND active = 1'
    );
    $mobileStmt->execute([$userId]);
    $mobileRow = $mobileStmt->fetch() ?: $mobileRow;
} catch (Throwable $e) {
    // Mobile table may not exist yet before migration.
}

json_response([
    'ok' => true,
    'count' => (int)($webRow['cnt'] ?? 0),
    'last_seen' => $webRow['last_seen'] ?? null,
    'mobile_count' => (int)($mobileRow['cnt'] ?? 0),
    'mobile_last_seen' => $mobileRow['last_seen'] ?? null,
    'total_count' => (int)($webRow['cnt'] ?? 0) + (int)($mobileRow['cnt'] ?? 0),
]);
