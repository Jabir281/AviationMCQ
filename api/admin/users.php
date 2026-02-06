<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('users');

$pdo = db();
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

$warning = null;

try {
    $stmt = $pdo->query(
        'SELECT id, display_name, created_at, last_seen_at, active_session_id, is_locked
         FROM users
         ORDER BY created_at DESC
         LIMIT ' . (int)$limit
    );

    $rows = $stmt->fetchAll();
    $users = array_map(function ($r) {
        $active = $r['active_session_id'] ?? null;
        $name = $r['display_name'] ?? null;
        $isLocked = isset($r['is_locked']) ? (bool)$r['is_locked'] : false;
        return [
            'id' => (int)$r['id'],
            'name' => ($name === null) ? null : (string)$name,
            'createdAt' => (string)$r['created_at'],
            'lastSeenAt' => $r['last_seen_at'] === null ? null : (string)$r['last_seen_at'],
            'hasSession' => is_string($active) && $active !== '',
            'locked' => $isLocked,
        ];
    }, $rows);
} catch (Throwable $e) {
    // Backward compatibility: older databases don't have display_name or is_locked yet.
    $warning = 'Database not migrated yet (missing columns). Run api/admin/setup.php?token=... to enable all features.';
    try {
        $stmt = $pdo->query(
            'SELECT id, created_at, last_seen_at, active_session_id
             FROM users
             ORDER BY created_at DESC
             LIMIT ' . (int)$limit
        );
        $rows = $stmt->fetchAll();
        $users = array_map(function ($r) {
            $active = $r['active_session_id'] ?? null;
            return [
                'id' => (int)$r['id'],
                'name' => null,
                'createdAt' => (string)$r['created_at'],
                'lastSeenAt' => $r['last_seen_at'] === null ? null : (string)$r['last_seen_at'],
                'hasSession' => is_string($active) && $active !== '',
                'locked' => false,
            ];
        }, $rows);
    } catch (Throwable $e2) {
        json_error('Failed loading users. Please run admin setup/migration.', 500);
    }
}

json_response(['ok' => true, 'users' => $users, 'warning' => $warning]);
