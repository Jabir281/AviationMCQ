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
    'SELECT id, display_name, created_at, last_seen_at, active_session_id
     FROM users
     ORDER BY created_at DESC
     LIMIT ' . (int)$limit
);

$rows = $stmt->fetchAll();
$users = array_map(function ($r) {
    $active = $r['active_session_id'] ?? null;
    $name = $r['display_name'] ?? null;
    return [
        'id' => (int)$r['id'],
        'name' => ($name === null) ? null : (string)$name,
        'createdAt' => (string)$r['created_at'],
        'lastSeenAt' => $r['last_seen_at'] === null ? null : (string)$r['last_seen_at'],
        'locked' => is_string($active) && $active !== '',
    ];
}, $rows);

json_response(['ok' => true, 'users' => $users]);
