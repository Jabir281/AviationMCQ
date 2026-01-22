<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();

$pdo = db();
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

$stmt = $pdo->query(
    'SELECT id, username, created_at
     FROM admin_users
     ORDER BY created_at DESC
     LIMIT ' . (int)$limit
);

$rows = $stmt->fetchAll();
$admins = array_map(function ($r) {
    return [
        'id' => (int)$r['id'],
        'username' => (string)$r['username'],
        'createdAt' => (string)$r['created_at'],
    ];
}, $rows);

json_response(['ok' => true, 'admins' => $admins]);
