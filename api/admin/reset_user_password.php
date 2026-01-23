<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/crypto.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$userId = (int)($body['userId'] ?? 0);
$requested = trim((string)($body['password'] ?? $body['code'] ?? ''));

if ($userId <= 0) {
    json_error('Missing userId', 400);
}

function random_code(int $len = 10): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

$code = $requested !== '' ? $requested : random_code(10);

if (strlen($code) < 6 || strlen($code) > 64) {
    json_error('Password must be 6-64 characters.', 400);
}

$pdo = db();

// Ensure user exists
$check = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$check->execute([$userId]);
if (!$check->fetch()) {
    json_error('User not found', 404);
}

$hash = password_hash($code, PASSWORD_DEFAULT);
$enc = encrypt_access_code($code);

try {
    $stmt = $pdo->prepare('UPDATE users SET access_code_hash = ?, access_code_enc = ?, active_session_id = NULL WHERE id = ?');
    $stmt->execute([$hash, $enc, $userId]);
} catch (Throwable $e) {
    json_error('Failed resetting password', 500);
}

json_response(['ok' => true, 'userId' => $userId, 'password' => $code]);
