<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('users');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$userId = (int)($body['userId'] ?? $body['id'] ?? 0);
if ($userId <= 0) {
    json_error('Missing userId', 400);
}

$pdo = db();

try {
    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $del->execute([$userId]);
    json_response(['ok' => true, 'deleted' => $del->rowCount() > 0]);
} catch (Throwable $e) {
    json_error('Failed to delete user.', 500);
}
