<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin_feature('questions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$subject = strtoupper(trim((string)($body['subject'] ?? '')));
$externalId = trim((string)($body['id'] ?? ''));
$correct = $body['correct'] ?? null;

if ($subject === '' || $externalId === '') {
    json_error('Missing subject or id', 400);
}

$correctIndex = ($correct === null || $correct === '' || $correct === false) ? null : (int)$correct;
if ($correctIndex !== null && ($correctIndex < 0 || $correctIndex > 3)) {
    json_error('Invalid correct index', 400);
}

$pdo = db();

$subStmt = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$subStmt->execute([$subject]);
$sub = $subStmt->fetch();
if (!$sub) json_error('Unknown subject', 404);

$upd = $pdo->prepare(
    'UPDATE questions SET correct_index = ? WHERE subject_id = ? AND external_id = ?'
);
$upd->execute([$correctIndex, (int)$sub['id'], $externalId]);

json_response(['ok' => true]);
