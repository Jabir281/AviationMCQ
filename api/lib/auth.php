<?php

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

const ADMIN_FEATURES = [
    'questions',
    'subjects',
    'users',
    'admins',
    'settings',
];

function ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Cookies must be sent before output.
        session_start();
    }
}

function require_admin(): array {
    ensure_session();
    if (!isset($_SESSION['admin_id'])) {
        json_error('Unauthorized', 401);
    }

    $adminId = (int)$_SESSION['admin_id'];
    $username = (string)($_SESSION['admin_username'] ?? '');
    if ($adminId <= 0 || $username === '') {
        logout_admin();
        json_error('Unauthorized', 401);
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, username, permissions_json FROM admin_users WHERE id = ?');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();
        if (!$row) {
            logout_admin();
            json_error('Unauthorized', 401);
        }

        $dbUsername = (string)($row['username'] ?? '');
        if ($dbUsername !== '' && $dbUsername !== $username) {
            // Keep session consistent with DB.
            $username = $dbUsername;
            $_SESSION['admin_username'] = $dbUsername;
        }

        $isSuper = admin_is_super($username);
        $features = $isSuper ? ADMIN_FEATURES : admin_parse_features($row['permissions_json'] ?? null);

        return [
            'id' => $adminId,
            'username' => $username,
            'isSuper' => $isSuper,
            'features' => $features,
        ];
    } catch (Throwable $e) {
        // Fail closed if we cannot validate admin.
        logout_admin();
        json_error('Unauthorized', 401);
    }

    // Unreachable (json_error exits), but keeps static analyzers happy.
    throw new RuntimeException('Unreachable');
}

function admin_is_super(string $username): bool {
    $u = mb_strtolower(trim($username));
    if ($u === '') return false;

    try {
        $cfg = api_config();
        $supers = $cfg['superAdmins'] ?? null;
        if (is_array($supers) && count($supers) > 0) {
            foreach ($supers as $s) {
                if (mb_strtolower(trim((string)$s)) === $u) return true;
            }
            return false;
        }
    } catch (Throwable $e) {
        // ignore
    }

    // Fallback (if superAdmins not configured): treat the first two created admins as super.
    try {
        $pdo = db();
        $rows = $pdo->query('SELECT username FROM admin_users ORDER BY created_at ASC, id ASC LIMIT 2')->fetchAll();
        foreach ($rows as $r) {
            if (mb_strtolower(trim((string)($r['username'] ?? ''))) === $u) return true;
        }
    } catch (Throwable $e) {
        // ignore
    }
    return false;
}

function admin_parse_features($permissionsJson): array {
    if ($permissionsJson === null) {
        // Backward compatibility: if no permissions were stored, allow everything.
        return ADMIN_FEATURES;
    }

    $raw = trim((string)$permissionsJson);
    if ($raw === '') {
        // Empty means no access.
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        // If malformed, fail safe (no access).
        return [];
    }

    $allowed = array_flip(ADMIN_FEATURES);
    $out = [];
    foreach ($decoded as $f) {
        $key = (string)$f;
        if (isset($allowed[$key])) $out[] = $key;
    }
    // Ensure deterministic order
    return array_values(array_intersect(ADMIN_FEATURES, $out));
}

function admin_has_feature(array $admin, string $feature): bool {
    if (($admin['isSuper'] ?? false) === true) return true;
    $f = (string)$feature;
    $features = $admin['features'] ?? [];
    return is_array($features) && in_array($f, $features, true);
}

function require_admin_feature(string $feature): array {
    $admin = require_admin();
    if (!admin_has_feature($admin, $feature)) {
        json_error('Forbidden', 403);
    }
    return $admin;
}

function require_super_admin(): array {
    $admin = require_admin();
    if (($admin['isSuper'] ?? false) !== true) {
        json_error('Forbidden', 403);
    }
    return $admin;
}

function require_user(): array {
    ensure_session();
    if (!isset($_SESSION['user_id'])) {
        json_error('Unauthorized', 401);
    }

    $userId = (int)$_SESSION['user_id'];
    if ($userId <= 0) {
        logout_user();
        json_error('Unauthorized', 401);
    }

    // If an admin deleted this user, immediately invalidate the session.
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, active_session_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            logout_user();
            json_error('Unauthorized', 401);
        }

        // Optional: enforce one device (one PHP session) per user.
        $cfg = api_config();
        if (($cfg['singleDevicePerUser'] ?? false) === true) {
            $active = $row['active_session_id'] ?? null;
            $sid = session_id();
            if (!$active || !is_string($active) || $active === '') {
                // If there's no active session recorded yet (e.g., old data), bind it to this session.
                $pdo->prepare('UPDATE users SET active_session_id = ? WHERE id = ?')->execute([$sid, $userId]);
            } elseif (!hash_equals((string)$active, (string)$sid)) {
                logout_user();
                json_error('Unauthorized', 401);
            }
        }
    } catch (Throwable $e) {
        // On DB errors, fail closed.
        logout_user();
        json_error('Unauthorized', 401);
    }

    return [
        'id' => $userId,
    ];
}

function login_user(int $userId): void {
    ensure_session();
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void {
    ensure_session();
    unset($_SESSION['user_id']);
}

function logout_admin(): void {
    ensure_session();
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
}
