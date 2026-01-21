<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$requested = trim((string)($body['password'] ?? $body['code'] ?? ''));

function random_code(int $len = 10): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

$code = $requested;
if ($code === '') {
    $code = random_code(10);
}

if (strlen($code) < 6 || strlen($code) > 64) {
    json_error('Password must be 6-64 characters.', 400);
}

$hash = password_hash($code, PASSWORD_DEFAULT);
$pdo = db();

try {
    $stmt = $pdo->prepare('INSERT INTO users (access_code_hash) VALUES (?)');
    $stmt->execute([$hash]);
    $id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    json_error('Failed creating user (try again).', 500);
}

json_response(['ok' => true, 'userId' => $id, 'password' => $code]);
