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
    $upd = $pdo->prepare('UPDATE users SET active_session_id = NULL WHERE id = ?');
    $upd->execute([$userId]);
    json_response(['ok' => true, 'updated' => $upd->rowCount() > 0]);
} catch (Throwable $e) {
    json_error('Failed to reset user session.', 500);
}
