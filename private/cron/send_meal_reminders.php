<?php
// private/cron/send_meal_reminders.php
require __DIR__ . '/../../public_html/includes/bootstrap.php';
require APP_PUBLIC . '/includes/web_push.php';
require APP_PUBLIC . '/includes/mobile_push.php';

$vapidConfig = $config['vapid'] ?? [];
$vapid = [
    'public_key' => (string) ($vapidConfig['public_key'] ?? ($config['vapid_public_key'] ?? '')),
    'private_key' => (string) ($vapidConfig['private_key'] ?? ($config['vapid_private_key'] ?? '')),
    'subject' => (string) ($vapidConfig['subject'] ?? ($config['vapid_subject'] ?? 'mailto:admin@example.com')),
];

$webPushEnabled = $vapid['public_key'] !== '' && $vapid['private_key'] !== '';
$mobilePushEnabled = mobile_push_is_enabled($config) && mobile_push_get_provider($config) === 'fcm' && mobile_push_get_fcm_config($config);

$logEnabled = (bool) ($config['logging']['cron_reminders'] ?? true);
$logSkips = $logEnabled;
$logPushResults = $logEnabled;

function log_line(string $message): void
{
    global $logEnabled;
    if (!$logEnabled) {
        return;
    }
    $timestamp = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    echo '[' . $timestamp . '] ' . $message . PHP_EOL;
}

if (!$webPushEnabled && !$mobilePushEnabled) {
    log_line('No push channels enabled. Configure VAPID or mobile_push (FCM).');
    exit(1);
}

$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$retryWindowMinutes = 4;

$stmt = $pdo->query(
    'SELECT c.id, c.user_id, c.name, c.reminder_time, c.reminder_days, u.timezone
     FROM meal_categories c
     JOIN users u ON u.id = c.user_id
     WHERE c.reminder_enabled = 1 AND c.reminder_time IS NOT NULL'
);
$categories = $stmt->fetchAll();

$logCheckStmt = $pdo->prepare(
    'SELECT id FROM reminder_logs WHERE user_id = ? AND category_id = ? AND fire_date = ? AND fire_time = ? LIMIT 1'
);
$logInsertStmt = $pdo->prepare(
    'INSERT INTO reminder_logs (user_id, category_id, fire_date, fire_time) VALUES (?, ?, ?, ?)'
);

$webSubsStmt = $pdo->prepare('SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?');
$webSubsUpdateStmt = $pdo->prepare('UPDATE push_subscriptions SET last_seen_at = NOW() WHERE id = ?');
$webSubsDeleteStmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE id = ?');

$mobileStmt = null;
$mobileUpdateStmt = null;
$mobileDeactivateStmt = null;
try {
    $mobileStmt = $pdo->prepare('SELECT id, provider, platform, token FROM mobile_push_tokens WHERE user_id = ? AND active = 1');
    $mobileUpdateStmt = $pdo->prepare('UPDATE mobile_push_tokens SET last_seen_at = NOW(), active = 1 WHERE id = ?');
    $mobileDeactivateStmt = $pdo->prepare('UPDATE mobile_push_tokens SET active = 0 WHERE id = ?');
} catch (Throwable $e) {
    $mobilePushEnabled = false;
    log_line('Mobile push table not available, skipping mobile channel.');
}

log_line('Run start. Categories: ' . count($categories));
log_line('Channels: web=' . ($webPushEnabled ? 'on' : 'off') . ', mobile=' . ($mobilePushEnabled ? 'on' : 'off') . '.');

function reminder_day_matches(?string $days, int $currentDay): bool
{
    if ($days === null || trim($days) === '') {
        return true;
    }
    $parts = array_filter(array_map('trim', explode(',', $days)), 'strlen');
    foreach ($parts as $part) {
        if ((int) $part === $currentDay) {
            return true;
        }
    }
    return false;
}

foreach ($categories as $category) {
    $categoryId = (int) $category['id'];
    $userId = (int) $category['user_id'];
    $categoryName = (string) $category['name'];
    $timezone = $category['timezone'] ?? 'Europe/Kyiv';
    try {
        $tz = new DateTimeZone((string) $timezone);
    } catch (Throwable $e) {
        $tz = new DateTimeZone('Europe/Kyiv');
    }

    $localNow = $nowUtc->setTimezone($tz);
    $localDay = (int) $localNow->format('w');
    $reminderTime = substr((string) $category['reminder_time'], 0, 5);
    $scheduledAt = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        $localNow->format('Y-m-d') . ' ' . $reminderTime,
        $tz
    );
    if (!$scheduledAt) {
        if ($logSkips) {
            log_line("Skip category {$categoryId} ({$categoryName}) user {$userId}: invalid reminder time.");
        }
        continue;
    }
    $windowEnd = $scheduledAt->modify('+' . $retryWindowMinutes . ' minutes');

    if ($localNow < $scheduledAt || $localNow > $windowEnd) {
        if ($logSkips) {
            log_line(
                "Skip category {$categoryId} ({$categoryName}) user {$userId}: outside send window now=" .
                $localNow->format('H:i') . ' scheduled=' . $scheduledAt->format('H:i') . ' tz=' . $tz->getName() . '.'
            );
        }
        continue;
    }

    if (!reminder_day_matches($category['reminder_days'] ?? null, $localDay)) {
        if ($logSkips) {
            $days = $category['reminder_days'] ?? '';
            log_line("Skip category {$categoryId} ({$categoryName}) user {$userId}: day mismatch localDay={$localDay} days={$days}.");
        }
        continue;
    }

    $fireDate = $scheduledAt->format('Y-m-d');
    $fireTime = $reminderTime . ':00';

    $logCheckStmt->execute([$userId, $categoryId, $fireDate, $fireTime]);
    if ($logCheckStmt->fetch()) {
        if ($logSkips) {
            log_line("Skip category {$categoryId} ({$categoryName}) user {$userId}: already sent for {$fireDate} {$fireTime}.");
        }
        continue;
    }

    $webSubscriptions = [];
    if ($webPushEnabled) {
        $webSubsStmt->execute([$userId]);
        $webSubscriptions = $webSubsStmt->fetchAll() ?: [];
    }

    $mobileDevices = [];
    if ($mobilePushEnabled && $mobileStmt) {
        $mobileStmt->execute([$userId]);
        $mobileDevices = $mobileStmt->fetchAll() ?: [];
    }

    if (!$webSubscriptions && !$mobileDevices) {
        if ($logSkips) {
            log_line("Skip category {$categoryId} ({$categoryName}) user {$userId}: no active devices.");
        }
        continue;
    }

    $payload = [
        'title' => 'Meal reminder',
        'body' => 'Time for ' . $category['name'],
        'url' => '/diary?date=' . $fireDate,
        'data' => [
            'category_id' => (string) $categoryId,
            'date' => $fireDate,
        ],
    ];

    $attempted = false;
    $sentAny = false;

    foreach ($webSubscriptions as $subscription) {
        $attempted = true;
        $result = send_web_push($subscription, $payload, $vapid);
        if ($result['ok']) {
            $sentAny = true;
            $webSubsUpdateStmt->execute([$subscription['id']]);
            if ($logPushResults) {
                log_line("Web push ok for category {$categoryId} user {$userId} subscription {$subscription['id']}.");
            }
            continue;
        }

        if ($logPushResults) {
            $status = (int) ($result['status'] ?? 0);
            $error = (string) ($result['error'] ?? 'Unknown error');
            log_line("Web push failed for category {$categoryId} user {$userId} subscription {$subscription['id']} status={$status} error={$error}.");
        }
        if (in_array((int) ($result['status'] ?? 0), [404, 410], true)) {
            $webSubsDeleteStmt->execute([$subscription['id']]);
            if ($logPushResults) {
                log_line("Web subscription {$subscription['id']} removed.");
            }
        }
    }

    foreach ($mobileDevices as $device) {
        $attempted = true;
        $result = send_mobile_push($device, $payload, $config);
        if ($result['ok']) {
            $sentAny = true;
            if ($mobileUpdateStmt) {
                $mobileUpdateStmt->execute([$device['id']]);
            }
            if ($logPushResults) {
                log_line("Mobile push ok for category {$categoryId} user {$userId} device {$device['id']}.");
            }
            continue;
        }

        if ($logPushResults) {
            $status = (int) ($result['status'] ?? 0);
            $error = (string) ($result['error'] ?? 'Unknown error');
            log_line("Mobile push failed for category {$categoryId} user {$userId} device {$device['id']} status={$status} error={$error}.");
        }
        if (($result['remove_token'] ?? false) && $mobileDeactivateStmt) {
            $mobileDeactivateStmt->execute([$device['id']]);
            if ($logPushResults) {
                log_line("Mobile token {$device['id']} deactivated.");
            }
        }
    }

    if ($attempted && $sentAny) {
        $logInsertStmt->execute([$userId, $categoryId, $fireDate, $fireTime]);
        log_line("Reminder logged for category {$categoryId} user {$userId} at {$fireDate} {$fireTime}.");
    } elseif ($attempted && $logPushResults) {
        log_line("All push attempts failed for category {$categoryId} user {$userId}; will retry within window.");
    }
}

log_line('Run done.');
