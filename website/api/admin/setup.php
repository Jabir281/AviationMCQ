<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';

$cfg = api_config();
$token = (string)($cfg['setupToken'] ?? '');
$given = (string)($_GET['token'] ?? '');

if ($token === '' || $given !== $token) {
    json_error('Forbidden', 403);
}

$pdo = db();

// Create tables
$schema = file_get_contents(__DIR__ . '/../schema.sql');
if ($schema === false) {
    json_error('Missing schema.sql', 500);
}

try {
    foreach (array_filter(array_map('trim', explode(";", $schema))) as $stmt) {
        $pdo->exec($stmt);
    }
} catch (Throwable $e) {
    json_error('Failed creating schema', 500);
}

// Seed admins only if none exist
$count = (int)$pdo->query('SELECT COUNT(*) AS c FROM admin_users')->fetch()['c'];
if ($count === 0) {
    $admins = $cfg['initialAdmins'] ?? [];
    if (!is_array($admins) || count($admins) === 0) {
        json_error('No initialAdmins configured', 500);
    }

    $ins = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
    foreach ($admins as $a) {
        $u = (string)($a['username'] ?? '');
        $p = (string)($a['password'] ?? '');
        if ($u === '' || $p === '') continue;
        $ins->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
    }
}

json_response(['ok' => true, 'message' => 'Setup complete']);
