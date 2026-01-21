<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$code = strtoupper(trim((string)($body['code'] ?? '')));
$section = strtolower(trim((string)($body['section'] ?? '')));

if ($code === '' || !preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
    json_error('Invalid subject code.', 400);
}

if ($section !== 'seen' && $section !== 'unseen') {
    json_error('Invalid section. Use seen or unseen.', 400);
}

$pdo = db();

$st = $pdo->prepare('UPDATE subjects SET section = ? WHERE code = ?');
$st->execute([$section, $code]);

if ($st->rowCount() < 1) {
    // Might already be set or subject not found. Check existence.
    $chk = $pdo->prepare('SELECT id, section FROM subjects WHERE code = ?');
    $chk->execute([$code]);
    $row = $chk->fetch();
    if (!$row) {
        json_error('Subject not found.', 404);
    }
}

json_response(['ok' => true, 'code' => $code, 'section' => $section]);
