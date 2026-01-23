<?php

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

function access_code_key_bytes(): string {
    $cfg = api_config();
    $raw = (string)($cfg['accessCodeKey'] ?? '');
    $raw = trim($raw);

    if ($raw === '') {
        json_error('Server not configured (missing accessCodeKey in api/config.php).', 500);
    }

    // Prefer base64 (recommended).
    $decoded = base64_decode($raw, true);
    if ($decoded !== false && strlen($decoded) >= 32) {
        return substr($decoded, 0, 32);
    }

    // Fallback: treat as raw string.
    if (strlen($raw) < 32) {
        json_error('Server not configured (accessCodeKey must be at least 32 chars or base64 of 32+ bytes).', 500);
    }

    return substr(hash('sha256', $raw, true), 0, 32);
}

function encrypt_access_code(string $plain): string {
    $key = access_code_key_bytes();
    $iv = random_bytes(12); // recommended for GCM
    $tag = '';

    $ciphertext = openssl_encrypt(
        $plain,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($ciphertext === false || $tag === '') {
        json_error('Encryption failed.', 500);
    }

    // Pack as: v1:<b64(iv)>.<b64(tag)>.<b64(ct)>
    return 'v1:' . base64_encode($iv) . '.' . base64_encode($tag) . '.' . base64_encode($ciphertext);
}

function decrypt_access_code(?string $packed): ?string {
    if ($packed === null) return null;
    $packed = trim($packed);
    if ($packed === '') return null;

    if (substr($packed, 0, 3) !== 'v1:') {
        // Unknown format (older installs).
        return null;
    }

    $rest = substr($packed, 3);
    $parts = explode('.', $rest);
    if (count($parts) !== 3) return null;

    $iv = base64_decode($parts[0], true);
    $tag = base64_decode($parts[1], true);
    $ct = base64_decode($parts[2], true);

    if ($iv === false || $tag === false || $ct === false) return null;

    $key = access_code_key_bytes();
    $plain = openssl_decrypt(
        $ct,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plain === false) return null;
    return $plain;
}
