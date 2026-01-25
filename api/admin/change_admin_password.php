<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$adminId = (int)($body['adminId'] ?? 0);
$password = trim((string)($body['password'] ?? ''));

if ($adminId <= 0) {
    json_error('Invalid adminId.', 400);
}

if ($password === '' || mb_strlen($password) < 6 || mb_strlen($password) > 64) {
    json_error('Password must be 6-64 characters.', 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo = db();

$stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
$stmt->execute([$hash, $adminId]);

if ($stmt->rowCount() === 0) {
    json_error('Admin not found.', 404);
}

json_response(['ok' => true]);
