<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_super_admin();

$pdo = db();
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

// Try with permissions_json; fall back if column doesn't exist yet.
try {
    $stmt = $pdo->query(
        'SELECT id, username, created_at, permissions_json
         FROM admin_users
         ORDER BY created_at DESC
         LIMIT ' . (int)$limit
    );
} catch (Throwable $e) {
    $stmt = $pdo->query(
        'SELECT id, username, created_at
         FROM admin_users
         ORDER BY created_at DESC
         LIMIT ' . (int)$limit
    );
}

$rows = $stmt->fetchAll();
$admins = array_map(function ($r) {
    $username = (string)$r['username'];
    $isSuper = admin_is_super($username);
    $features = $isSuper ? ADMIN_FEATURES : admin_parse_features($r['permissions_json'] ?? null);
    return [
        'id' => (int)$r['id'],
        'username' => $username,
        'createdAt' => (string)$r['created_at'],
        'isSuper' => $isSuper,
        'features' => $features,
    ];
}, $rows);

json_response(['ok' => true, 'admins' => $admins]);
