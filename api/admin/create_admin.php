<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$username = trim((string)($body['username'] ?? ''));
$password = trim((string)($body['password'] ?? ''));

if ($username === '' || mb_strlen($username) > 50) {
    json_error('Username must be 1-50 characters.', 400);
}

// Keep username simple to avoid weird whitespace/encoding issues in logins.
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    json_error('Username must be 3-50 chars using letters, numbers, underscore, dot, dash.', 400);
}

if ($password === '' || mb_strlen($password) < 6 || mb_strlen($password) > 64) {
    json_error('Password must be 6-64 characters.', 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo = db();

try {
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $hash]);
    $id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    json_error('Failed creating admin (username may already exist).', 400);
}

json_response(['ok' => true, 'id' => $id, 'username' => $username]);
