<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin_feature('subjects');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$code = strtoupper(trim((string)($body['code'] ?? $body['subject'] ?? '')));

if ($code === '' || !preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
    json_error('Invalid subject code.', 400);
}

$pdo = db();

$del = $pdo->prepare('DELETE FROM subjects WHERE code = ?');
$del->execute([$code]);

json_response(['ok' => true, 'deleted' => $del->rowCount() > 0]);
