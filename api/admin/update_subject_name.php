<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('subjects');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$code = strtoupper(trim((string)($body['code'] ?? '')));
$name = trim((string)($body['name'] ?? ''));

if ($code === '' || !preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
    json_error('Invalid subject code.', 400);
}

if ($name === '' || mb_strlen($name) > 255) {
    json_error('Invalid name. Must be 1–255 characters.', 400);
}

$pdo = db();

$st = $pdo->prepare('UPDATE subjects SET name = ? WHERE code = ?');
$st->execute([$name, $code]);

if ($st->rowCount() < 1) {
    // Might be unchanged or not found. Check existence.
    $chk = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
    $chk->execute([$code]);
    $row = $chk->fetch();
    if (!$row) {
        json_error('Subject not found.', 404);
    }
}

json_response(['ok' => true, 'code' => $code, 'name' => $name]);
