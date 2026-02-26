<?php
// public_html/pages/google_login.php
require APP_PUBLIC . '/includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require APP_PUBLIC . '/includes/i18n.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => t('Метод не дозволено.')]);
    exit;
}

$token = trim($_POST['credential'] ?? '');
if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => t('Відсутні облікові дані.')]);
    exit;
}

if (!isset($config) || !is_array($config)) {
    $privateRoot = defined('APP_PRIVATE') ? APP_PRIVATE : dirname(__DIR__, 2) . '/private';
    $configFile = $privateRoot . '/.env.php';
    $exampleFile = $privateRoot . '/.env.php.example';
    $config = file_exists($configFile) ? require $configFile : require $exampleFile;
}

$clientId = $config['google_client_id'] ?? '';
if ($clientId === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => t('Google client ID не налаштований.')]);
    exit;
}

if (!function_exists('log_in_user')) {
    require APP_PUBLIC . '/includes/auth.php';
}

function fetch_google_token_info(string $token): ?array
{
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            return null;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

$tokenInfo = fetch_google_token_info($token);
if (!$tokenInfo) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => t('Недійсний токен.')]);
    exit;
}

if (($tokenInfo['aud'] ?? '') !== $clientId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => t('Невірна аудиторія.')]);
    exit;
}

$issuer = $tokenInfo['iss'] ?? '';
if ($issuer !== '' && $issuer !== 'https://accounts.google.com' && $issuer !== 'accounts.google.com') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => t('Невірний видавець.')]);
    exit;
}

if (isset($tokenInfo['exp']) && time() > (int)$tokenInfo['exp']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => t('Токен прострочено.')]);
    exit;
}

$sub = trim($tokenInfo['sub'] ?? '');
$email = trim($tokenInfo['email'] ?? '');
$emailVerified = $tokenInfo['email_verified'] ?? '';
if ($sub === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => t('Недійсні дані токена.')]);
    exit;
}
if ($emailVerified !== true && $emailVerified !== 'true' && $emailVerified !== '1') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => t('Email не підтверджено.')]);
    exit;
}

$name = trim($tokenInfo['name'] ?? '');
if ($name === '') {
    $name = trim($tokenInfo['given_name'] ?? '');
}
if ($name === '' && $email !== '') {
    $name = strstr($email, '@', true) ?: $email;
}
if ($name === '') {
    $name = t('Користувач');
}
if (function_exists('mb_strlen')) {
    if (mb_strlen($name) > 100) {
        $name = mb_substr($name, 0, 100);
    }
} else {
    if (strlen($name) > 100) {
        $name = substr($name, 0, 100);
    }
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id, name, email, google_sub FROM users WHERE google_sub = ? LIMIT 1');
    $stmt->execute([$sub]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare('SELECT id, name, email, google_sub FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if (!empty($user['google_sub']) && $user['google_sub'] !== $sub) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => t('Email прив’язаний до іншого Google-акаунта.')]);
                exit;
            }
            if (empty($user['google_sub'])) {
                $update = $pdo->prepare('UPDATE users SET google_sub = ? WHERE id = ?');
                $update->execute([$sub, $user['id']]);
            }
        } else {
            $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, google_sub, password_hash) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$name, $email, $sub, $passwordHash]);
            $user = [
                'id' => (int)$pdo->lastInsertId(),
                'name' => $name,
                'email' => $email,
            ];
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

log_in_user([
    'id' => (int)$user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
]);

echo json_encode(['success' => true, 'redirect' => '/dashboard']);
