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

$text = (string)($body['question'] ?? '');
$options = $body['options'] ?? null;
$correct = $body['correct'] ?? null;

if ($subject === '' || $externalId === '') {
    json_error('Missing subject or id', 400);
}

if (!is_array($options) || count($options) !== 4) {
    json_error('Options must be an array of 4', 400);
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
    'UPDATE questions
     SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_index = ?
     WHERE subject_id = ? AND external_id = ?'
);

$upd->execute([
    $text,
    (string)$options[0],
    (string)$options[1],
    (string)$options[2],
    (string)$options[3],
    $correctIndex,
    (int)$sub['id'],
    $externalId,
]);

json_response(['ok' => true]);
