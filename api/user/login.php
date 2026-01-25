<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);

$code = (string)($body['password'] ?? $body['code'] ?? $_POST['password'] ?? $_POST['code'] ?? '');
$code = trim($code);

if ($code === '') {
    json_error('Missing password', 400);
}

$pdo = db();

// Find user by access code hash.
$stmt = $pdo->query('SELECT id, access_code_hash FROM users');
$foundId = null;
foreach ($stmt->fetchAll() as $row) {
    if (password_verify($code, (string)$row['access_code_hash'])) {
        $foundId = (int)$row['id'];
        break;
    }
}

if ($foundId === null) {
    json_error('Invalid password', 401);
}

// Prevent session fixation and support single-device enforcement.
ensure_user_session();
@session_regenerate_id(true);

$sid = session_id();

// Optional: enforce one device (one PHP session) per user.
// When enabled, the first device that logs in owns the password until logout
// (or an admin resets the lock).
try {
    $cfg = api_config();
    if (($cfg['singleDevicePerUser'] ?? false) === true) {
        $stmt = $pdo->prepare('SELECT active_session_id FROM users WHERE id = ?');
        $stmt->execute([$foundId]);
        $row = $stmt->fetch();
        $active = $row ? ($row['active_session_id'] ?? null) : null;

        if ($active && is_string($active) && $active !== '' && !hash_equals((string)$active, (string)$sid)) {
            json_error('This password is already in use on another device.', 403);
        }

        if (!$active || !is_string($active) || $active === '') {
            $set = $pdo->prepare('UPDATE users SET active_session_id = ? WHERE id = ?');
            $set->execute([$sid, $foundId]);
        }
    }
} catch (Throwable $e) {
    // ignore
}

login_user($foundId);

try {
    $up = $pdo->prepare('UPDATE users SET last_seen_at = CURRENT_TIMESTAMP WHERE id = ?');
    $up->execute([$foundId]);
} catch (Throwable $e) {
    // ignore
}

json_response(['ok' => true]);
