<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$username = trim((string)($body['username'] ?? ''));
$password = trim((string)($body['password'] ?? ''));
$featuresIn = $body['features'] ?? $body['permissions'] ?? null;

if ($username === '' || mb_strlen($username) > 50) {
    json_error('Username must be 1-50 characters.', 400);
}

// Keep username simple to avoid weird whitespace/encoding issues in logins.
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    json_error('Username must be 3-50 chars using letters, numbers, underscore, dot, dash.', 400);
}

if ($password === '' || mb_strlen($password) < 6 || mb_strlen($password) > 64) {
    json_error('Password must be 6-64 characters.', 400);
}

$features = null;
if ($featuresIn !== null) {
    if (!is_array($featuresIn)) {
        json_error('features must be an array.', 400);
    }
    $allowed = array_flip(ADMIN_FEATURES);
    $out = [];
    foreach ($featuresIn as $f) {
        $key = (string)$f;
        if (isset($allowed[$key])) $out[] = $key;
    }
    // Ensure deterministic order, and fail safe if empty.
    $features = array_values(array_intersect(ADMIN_FEATURES, $out));
} else {
    // Default: allow all features for newly created admins unless super admin chooses otherwise.
    $features = ADMIN_FEATURES;
}

$permissionsJson = json_encode($features, JSON_UNESCAPED_SLASHES);
if (!is_string($permissionsJson)) {
    json_error('Failed to encode permissions.', 500);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo = db();

try {
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, permissions_json) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hash, $permissionsJson]);
    $id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    json_error('Failed creating admin (username may already exist).', 400);
}

json_response(['ok' => true, 'id' => $id, 'username' => $username, 'features' => $features]);
