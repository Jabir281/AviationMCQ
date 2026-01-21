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
    'SELECT id, created_at, last_seen_at
     FROM users
     ORDER BY created_at DESC
     LIMIT ' . (int)$limit
);

$rows = $stmt->fetchAll();
$users = array_map(function ($r) {
    return [
        'id' => (int)$r['id'],
        'createdAt' => (string)$r['created_at'],
        'lastSeenAt' => $r['last_seen_at'] === null ? null : (string)$r['last_seen_at'],
    ];
}, $rows);

json_response(['ok' => true, 'users' => $users]);
