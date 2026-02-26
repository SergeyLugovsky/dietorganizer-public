<?php
// public_html/includes/web_push.php

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder !== 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function hkdf(string $salt, string $ikm, string $info, int $length): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    return substr(hash_hmac('sha256', $info . chr(1), $prk, true), 0, $length);
}

function create_ec_private_key_pem(string $rawPrivateKey): string
{
    // Формируем EC PRIVATE KEY (SEC1) для prime256v1.
    $der = "\x30\x31\x02\x01\x01\x04\x20" . $rawPrivateKey . "\xA0\x0A\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";
    $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    return $pem;
}

function create_ec_public_key_pem(string $rawPublicKey): string
{
    $prefix = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200');
    $der = $prefix . $rawPublicKey;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    return $pem;
}

function ecdsa_der_to_raw(string $der, int $partLength = 32): string
{
    $offset = 0;
    if (ord($der[$offset]) !== 0x30) {
        return '';
    }
    $offset++;
    $length = ord($der[$offset]);
    if ($length & 0x80) {
        $bytes = $length & 0x7f;
        $length = 0;
        $offset++;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($der[$offset + $i]);
        }
        $offset += $bytes - 1;
    }
    $offset++;
    if (ord($der[$offset]) !== 0x02) {
        return '';
    }
    $offset++;
    $rLength = ord($der[$offset]);
    $offset++;
    $r = substr($der, $offset, $rLength);
    $offset += $rLength;
    if (ord($der[$offset]) !== 0x02) {
        return '';
    }
    $offset++;
    $sLength = ord($der[$offset]);
    $offset++;
    $s = substr($der, $offset, $sLength);

    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, $partLength, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, $partLength, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

function create_vapid_jwt(string $audience, string $subject, string $publicKeyB64, string $privateKeyB64): string
{
    $header = ['typ' => 'JWT', 'alg' => 'ES256'];
    $payload = [
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => $subject,
    ];

    $headerEncoded = base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadEncoded = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $data = $headerEncoded . '.' . $payloadEncoded;

    $privateKeyRaw = base64url_decode($privateKeyB64);
    $privateKeyPem = create_ec_private_key_pem($privateKeyRaw);
    $signatureDer = '';
    $ok = openssl_sign($data, $signatureDer, $privateKeyPem, OPENSSL_ALGO_SHA256);
    if (!$ok) {
        return '';
    }
    $signature = ecdsa_der_to_raw($signatureDer, 32);
    if ($signature === '') {
        return '';
    }

    return $data . '.' . base64url_encode($signature);
}

function webpush_encrypt_aes128gcm(string $payload, string $userPublicKeyB64, string $userAuthB64): array
{
    $userPublicKey = base64url_decode($userPublicKeyB64);
    $userAuthToken = base64url_decode($userAuthB64);

    $localKey = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$localKey) {
        throw new RuntimeException('Cannot create local ECDH key.');
    }

    $details = openssl_pkey_get_details($localKey);
    if (!$details || empty($details['ec'])) {
        throw new RuntimeException('Cannot read local ECDH key details.');
    }

    openssl_pkey_export($localKey, $localPrivatePem);
    $localPublicKey = "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    $userPublicPem = create_ec_public_key_pem($userPublicKey);
    $publicKeyResource = openssl_pkey_get_public($userPublicPem);
    $privateKeyResource = openssl_pkey_get_private($localPrivatePem);
    if (!$publicKeyResource || !$privateKeyResource) {
        throw new RuntimeException('Cannot load ECDH keys.');
    }
    $sharedSecret = openssl_pkey_derive($publicKeyResource, $privateKeyResource, 32);
    if ($sharedSecret === false) {
        throw new RuntimeException('Cannot derive ECDH secret.');
    }
    $sharedSecret = str_pad($sharedSecret, 32, "\x00", STR_PAD_LEFT);

    $salt = random_bytes(16);
    $ikm = hkdf($userAuthToken, $sharedSecret, "WebPush: info\0" . $userPublicKey . $localPublicKey, 32);
    $cek = hkdf($salt, $ikm, "Content-Encoding: aes128gcm\0", 16);
    $nonce = hkdf($salt, $ikm, "Content-Encoding: nonce\0", 12);

    $plaintext = $payload . chr(2);
    $tag = '';
    $cipherText = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipherText === false) {
        throw new RuntimeException('Cannot encrypt payload.');
    }

    $contentCodingHeader = $salt . pack('N', 4096) . pack('C', strlen($localPublicKey)) . $localPublicKey;

    return [
        'cipherText' => $cipherText . $tag,
        'salt' => $salt,
        'localPublicKey' => $localPublicKey,
        'contentCodingHeader' => $contentCodingHeader,
    ];
}

function send_web_push(array $subscription, array $payload, array $vapid): array
{
    $endpoint = $subscription['endpoint'] ?? '';
    if ($endpoint === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'Missing endpoint.'];
    }
    if (empty($subscription['p256dh']) || empty($subscription['auth'])) {
        return ['ok' => false, 'status' => 0, 'error' => 'Missing subscription keys.'];
    }

    $publicKey = $vapid['public_key'] ?? '';
    $privateKey = $vapid['private_key'] ?? '';
    $subject = $vapid['subject'] ?? '';

    if ($publicKey === '' || $privateKey === '' || $subject === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'Missing VAPID config.'];
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Payload encode failed.'];
    }

    try {
        $encrypted = webpush_encrypt_aes128gcm($payloadJson, $subscription['p256dh'] ?? '', $subscription['auth'] ?? '');
    } catch (Throwable $e) {
        return ['ok' => false, 'status' => 0, 'error' => $e->getMessage()];
    }

    $content = $encrypted['contentCodingHeader'] . $encrypted['cipherText'];
    $scheme = parse_url($endpoint, PHP_URL_SCHEME);
    $host = parse_url($endpoint, PHP_URL_HOST);
    if (!$scheme || !$host) {
        return ['ok' => false, 'status' => 0, 'error' => 'Invalid endpoint origin.'];
    }
    $audience = $scheme . '://' . $host;
    $jwt = create_vapid_jwt($audience, $subject, $publicKey, $privateKey);
    if ($jwt === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'VAPID JWT build failed.'];
    }

    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 300',
        'Authorization: vapid t=' . $jwt . ', k=' . base64url_encode(base64url_decode($publicKey)),
        'Content-Length: ' . strlen($content),
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => $status >= 200 && $status < 300 ? null : ($error ?: 'Push failed'),
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'timeout' => 15,
        ],
    ]);

    $result = @file_get_contents($endpoint, false, $context);
    $status = 0;
    $responseHeaders = [];
    if (function_exists('http_get_last_response_headers')) {
        $responseHeaders = http_get_last_response_headers();
        if (!is_array($responseHeaders)) {
            $responseHeaders = [];
        }
    } elseif (isset($http_response_header) && is_array($http_response_header)) {
        $responseHeaders = $http_response_header;
    }
    if ($responseHeaders) {
        foreach ($responseHeaders as $line) {
            if (preg_match('/^HTTP\/[0-9\.]+\s+(\d+)/', $line, $matches)) {
                $status = (int)$matches[1];
                break;
            }
        }
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'error' => $status >= 200 && $status < 300 ? null : 'Push failed',
    ];
}
