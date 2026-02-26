<?php
// scripts/generate_vapid_keys.php

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$key = openssl_pkey_new([
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
]);

if (!$key) {
    echo "Cannot generate VAPID keys.\n";
    exit(1);
}

$details = openssl_pkey_get_details($key);
if (!$details || empty($details['ec'])) {
    echo "Cannot read VAPID key details.\n";
    exit(1);
}

$publicKey = "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$privateKey = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

echo "VAPID public key: " . base64url_encode($publicKey) . PHP_EOL;
echo "VAPID private key: " . base64url_encode($privateKey) . PHP_EOL;
