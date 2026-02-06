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
$lock = isset($body['lock']) ? (bool)$body['lock'] : null;

if ($userId <= 0) {
    json_error('Missing userId', 400);
}

$pdo = db();

try {
    // Check if is_locked column exists, add it if not
    try {
        $pdo->query('SELECT is_locked FROM users LIMIT 1');
    } catch (Throwable $e) {
        // Column doesn't exist, add it
        $pdo->exec('ALTER TABLE users ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0');
    }

    if ($lock === null) {
        // Toggle: get current state and flip it
        $stmt = $pdo->prepare('SELECT is_locked FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            json_error('User not found', 404);
        }
        $lock = !((bool)$row['is_locked']);
    }

    $upd = $pdo->prepare('UPDATE users SET is_locked = ? WHERE id = ?');
    $upd->execute([$lock ? 1 : 0, $userId]);

    json_response([
        'ok' => true,
        'updated' => $upd->rowCount() > 0,
        'locked' => $lock
    ]);
} catch (Throwable $e) {
    json_error('Failed to toggle user lock: ' . $e->getMessage(), 500);
}
