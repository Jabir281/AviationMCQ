<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$subject = strtoupper(trim((string)($body['subject'] ?? '')));
$externalId = trim((string)($body['id'] ?? ''));

if ($subject === '' || $externalId === '') {
    json_error('Missing subject or id', 400);
}

$pdo = db();

$subStmt = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$subStmt->execute([$subject]);
$sub = $subStmt->fetch();
if (!$sub) json_error('Unknown subject', 404);

$del = $pdo->prepare('DELETE FROM questions WHERE subject_id = ? AND external_id = ?');
$del->execute([(int)$sub['id'], $externalId]);

json_response(['ok' => true, 'deleted' => $del->rowCount() > 0]);
