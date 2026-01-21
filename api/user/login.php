<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);

$code = (string)($body['password'] ?? $body['code'] ?? $_POST['password'] ?? $_POST['code'] ?? '');
$code = trim($code);

if ($code === '') {
    json_error('Missing password', 400);
}

$pdo = db();

// Find user by access code hash.
$stmt = $pdo->query('SELECT id, access_code_hash FROM users');
$foundId = null;
foreach ($stmt->fetchAll() as $row) {
    if (password_verify($code, (string)$row['access_code_hash'])) {
        $foundId = (int)$row['id'];
        break;
    }
}

if ($foundId === null) {
    json_error('Invalid password', 401);
}

login_user($foundId);

try {
    $up = $pdo->prepare('UPDATE users SET last_seen_at = CURRENT_TIMESTAMP WHERE id = ?');
    $up->execute([$foundId]);
} catch (Throwable $e) {
    // ignore
}

json_response(['ok' => true]);
