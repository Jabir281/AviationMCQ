<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$password = (string)($body['password'] ?? $_POST['password'] ?? '');
$password = trim($password);

if ($password === '') {
    json_error('Missing password', 400);
}

$pdo = db();

$stmt = $pdo->query('SELECT id, username, password_hash FROM admin_users');
$admins = $stmt->fetchAll();

foreach ($admins as $a) {
    if (password_verify($password, $a['password_hash'])) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['admin_id'] = (int)$a['id'];
        $_SESSION['admin_username'] = $a['username'];
        json_response(['ok' => true, 'username' => $a['username']]);
    }
}

json_error('Invalid password', 401);
