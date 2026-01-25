<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

$me = require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$adminId = (int)($body['adminId'] ?? 0);
$featuresIn = $body['features'] ?? $body['permissions'] ?? null;

if ($adminId <= 0) {
    json_error('Invalid adminId.', 400);
}

if (!is_array($featuresIn)) {
    json_error('features must be an array.', 400);
}

$pdo = db();

// Ensure admin exists
$stmt = $pdo->prepare('SELECT id, username FROM admin_users WHERE id = ?');
$stmt->execute([$adminId]);
$target = $stmt->fetch();
if (!$target) {
    json_error('Admin not found.', 404);
}

$targetUsername = (string)($target['username'] ?? '');
if ($targetUsername !== '' && admin_is_super($targetUsername)) {
    json_error('Cannot edit permissions for a super admin.', 400);
}

$allowed = array_flip(ADMIN_FEATURES);
$out = [];
foreach ($featuresIn as $f) {
    $key = (string)$f;
    if (isset($allowed[$key])) $out[] = $key;
}
$features = array_values(array_intersect(ADMIN_FEATURES, $out));

$permissionsJson = json_encode($features, JSON_UNESCAPED_SLASHES);
if (!is_string($permissionsJson)) {
    json_error('Failed to encode permissions.', 500);
}

try {
    $upd = $pdo->prepare('UPDATE admin_users SET permissions_json = ? WHERE id = ?');
    $upd->execute([$permissionsJson, $adminId]);
} catch (Throwable $e) {
    json_error('Failed to update permissions.', 500);
}

json_response([
    'ok' => true,
    'adminId' => $adminId,
    'features' => $features,
]);
