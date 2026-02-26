<?php
// public_html/includes/mobile_push.php

function mobile_push_is_enabled(array $config): bool
{
    $mobile = $config['mobile_push'] ?? [];
    return (bool) ($mobile['enabled'] ?? false);
}

function mobile_push_get_provider(array $config): string
{
    $mobile = $config['mobile_push'] ?? [];
    return strtolower(trim((string) ($mobile['provider'] ?? 'fcm')));
}

function mobile_push_get_fcm_config(array $config): array
{
    $mobile = $config['mobile_push'] ?? [];
    $serviceAccount = $mobile['service_account'] ?? null;
    $serviceAccountPath = trim((string) ($mobile['service_account_json'] ?? ''));

    if (!is_array($serviceAccount) && $serviceAccountPath !== '' && is_file($serviceAccountPath)) {
        $raw = @file_get_contents($serviceAccountPath);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $serviceAccount = $decoded;
        }
    }

    if (!is_array($serviceAccount)) {
        return [];
    }

    $projectId = trim((string) ($mobile['project_id'] ?? ($serviceAccount['project_id'] ?? '')));
    $clientEmail = trim((string) ($serviceAccount['client_email'] ?? ''));
    $privateKey = (string) ($serviceAccount['private_key'] ?? '');
    if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
        return [];
    }

    return [
        'project_id' => $projectId,
        'client_email' => $clientEmail,
        'private_key' => $privateKey,
    ];
}

function mobile_push_base64url_encode(string $input): string
{
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function mobile_push_json_encode(array $payload): string
{
    return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function mobile_push_http_post(string $url, array $headers, string $body): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'status' => $status,
            'body' => is_string($response) ? $response : '',
            'error' => $error ?: null,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);

    $responseHeaders = [];
    if (function_exists('http_get_last_response_headers')) {
        $responseHeaders = http_get_last_response_headers();
        if (!is_array($responseHeaders)) {
            $responseHeaders = [];
        }
    }

    $status = 0;
    if ($responseHeaders) {
        $firstLine = (string) ($responseHeaders[0] ?? '');
        if (preg_match('/HTTP\/\S+\s+(\d+)/', $firstLine, $m)) {
            $status = (int) $m[1];
        }
    }

    $error = null;
    if ($response === false && $status === 0) {
        $lastError = error_get_last();
        $error = (string) ($lastError['message'] ?? 'HTTP request failed');
    }

    return [
        'status' => $status,
        'body' => is_string($response) ? $response : '',
        'error' => $error,
    ];
}

function mobile_push_get_access_token(array $fcmConfig): array
{
    static $tokenCache = [];
    if (!function_exists('openssl_pkey_get_private') || !function_exists('openssl_sign')) {
        return ['ok' => false, 'error' => 'OpenSSL extension is required for FCM auth'];
    }

    $cacheKey = sha1($fcmConfig['project_id'] . '|' . $fcmConfig['client_email']);
    $now = time();
    if (isset($tokenCache[$cacheKey]) && ($tokenCache[$cacheKey]['expires_at'] ?? 0) > ($now + 60)) {
        return ['ok' => true, 'access_token' => $tokenCache[$cacheKey]['token']];
    }

    $header = mobile_push_base64url_encode(mobile_push_json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT',
    ]));
    $claims = mobile_push_base64url_encode(mobile_push_json_encode([
        'iss' => $fcmConfig['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $unsignedJwt = $header . '.' . $claims;

    $privateKey = openssl_pkey_get_private($fcmConfig['private_key']);
    if ($privateKey === false) {
        return ['ok' => false, 'error' => 'Invalid FCM private key'];
    }
    $signature = '';
    $signed = openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$signed) {
        return ['ok' => false, 'error' => 'FCM JWT signing failed'];
    }

    $jwt = $unsignedJwt . '.' . mobile_push_base64url_encode($signature);
    $postBody = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);

    $response = mobile_push_http_post(
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        $postBody
    );

    $decoded = json_decode((string) $response['body'], true);
    $accessToken = is_array($decoded) ? trim((string) ($decoded['access_token'] ?? '')) : '';
    $expiresIn = is_array($decoded) ? (int) ($decoded['expires_in'] ?? 3600) : 3600;
    if ($accessToken === '') {
        $error = (string) ($decoded['error_description'] ?? ($decoded['error'] ?? ($response['error'] ?? 'FCM auth failed')));
        return ['ok' => false, 'error' => $error];
    }

    $tokenCache[$cacheKey] = [
        'token' => $accessToken,
        'expires_at' => $now + max(60, $expiresIn),
    ];

    return ['ok' => true, 'access_token' => $accessToken];
}

function mobile_push_extract_fcm_error(array $decodedBody): array
{
    $errorBlock = $decodedBody['error'] ?? [];
    $errorStatus = strtoupper((string) ($errorBlock['status'] ?? ''));
    $errorMessage = (string) ($errorBlock['message'] ?? 'FCM request failed');
    $removeToken = false;

    $details = $errorBlock['details'] ?? [];
    if (is_array($details)) {
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }
            $errorCode = strtoupper((string) ($detail['errorCode'] ?? ''));
            if ($errorCode === 'UNREGISTERED') {
                $removeToken = true;
            }
        }
    }

    if ($errorStatus === 'NOT_FOUND' || stripos($errorMessage, 'UNREGISTERED') !== false) {
        $removeToken = true;
    }

    return [
        'error' => $errorMessage,
        'remove_token' => $removeToken,
    ];
}

function send_mobile_push(array $device, array $payload, array $config): array
{
    if (!mobile_push_is_enabled($config)) {
        return ['ok' => false, 'status' => 0, 'error' => 'Mobile push disabled', 'remove_token' => false];
    }
    if (mobile_push_get_provider($config) !== 'fcm') {
        return ['ok' => false, 'status' => 0, 'error' => 'Unsupported mobile push provider', 'remove_token' => false];
    }

    $fcmConfig = mobile_push_get_fcm_config($config);
    if (!$fcmConfig) {
        return ['ok' => false, 'status' => 0, 'error' => 'Missing FCM config', 'remove_token' => false];
    }

    $token = trim((string) ($device['token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'Missing device token', 'remove_token' => true];
    }

    $accessTokenResult = mobile_push_get_access_token($fcmConfig);
    if (!($accessTokenResult['ok'] ?? false)) {
        return [
            'ok' => false,
            'status' => 0,
            'error' => (string) ($accessTokenResult['error'] ?? 'FCM auth failed'),
            'remove_token' => false,
        ];
    }

    $data = $payload['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }
    if (isset($payload['url'])) {
        $data['url'] = (string) $payload['url'];
    }

    $stringData = [];
    foreach ($data as $key => $value) {
        if (!is_scalar($value) && $value !== null) {
            continue;
        }
        $stringData[(string) $key] = (string) $value;
    }

    $requestPayload = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => (string) ($payload['title'] ?? ''),
                'body' => (string) ($payload['body'] ?? ''),
            ],
            'data' => $stringData,
            'android' => [
                'priority' => 'high',
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ],
        ],
    ];

    $response = mobile_push_http_post(
        'https://fcm.googleapis.com/v1/projects/' . rawurlencode($fcmConfig['project_id']) . '/messages:send',
        [
            'Authorization: Bearer ' . $accessTokenResult['access_token'],
            'Content-Type: application/json; charset=utf-8',
        ],
        mobile_push_json_encode($requestPayload)
    );

    $status = (int) ($response['status'] ?? 0);
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'status' => $status, 'error' => null, 'remove_token' => false];
    }

    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    $parsed = is_array($decoded)
        ? mobile_push_extract_fcm_error($decoded)
        : [
            'error' => (string) ($response['error'] ?? 'FCM request failed'),
            'remove_token' => $status === 404,
        ];

    return [
        'ok' => false,
        'status' => $status,
        'error' => $parsed['error'],
        'remove_token' => (bool) $parsed['remove_token'],
    ];
}
