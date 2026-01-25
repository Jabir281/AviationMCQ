<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/crypto.php';

require_admin_feature('users');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$userId = isset($_GET['userId']) ? (int)$_GET['userId'] : 0;
if ($userId <= 0) {
    json_error('Missing userId', 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, access_code_enc FROM users WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    json_error('User not found', 404);
}

$plain = decrypt_access_code($row['access_code_enc'] ?? null);

json_response([
    'ok' => true,
    'userId' => (int)$row['id'],
    'password' => $plain, // null if not available (older users)
]);
