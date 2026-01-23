<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$userId = (int)($body['userId'] ?? 0);
$name = trim((string)($body['name'] ?? $body['displayName'] ?? $body['userName'] ?? ''));

if ($userId <= 0) {
    json_error('Missing userId', 400);
}

if ($name !== '' && strlen($name) > 120) {
    json_error('Name must be 0-120 characters.', 400);
}

$pdo = db();

try {
    $stmt = $pdo->prepare('UPDATE users SET display_name = ? WHERE id = ?');
    $stmt->execute([($name === '' ? null : $name), $userId]);
} catch (Throwable $e) {
    json_error('Failed updating name (database not migrated yet). Run api/admin/setup.php?token=... then try again.', 500);
}

json_response(['ok' => true, 'userId' => $userId, 'name' => ($name === '' ? null : $name)]);
