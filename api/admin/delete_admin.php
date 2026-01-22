<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$adminId = (int)($body['adminId'] ?? 0);

if ($adminId <= 0) {
    json_error('Invalid adminId.', 400);
}

if ((int)$me['id'] === $adminId) {
    json_error('You cannot revoke your own admin account.', 400);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM admin_users')->fetch()['c'];
    if ($count <= 1) {
        $pdo->rollBack();
        json_error('Cannot revoke the last admin.', 400);
    }

    $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = ?');
    $stmt->execute([$adminId]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        json_error('Admin not found.', 404);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('Failed to revoke admin.', 500);
}

json_response(['ok' => true]);
