<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin();

$subject = isset($_GET['subject']) ? strtoupper(trim((string)$_GET['subject'])) : '';
if ($subject === '') {
    json_error('Missing subject', 400);
}

$pdo = db();

$subStmt = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$subStmt->execute([$subject]);
$sub = $subStmt->fetch();
if (!$sub) json_error('Unknown subject', 404);

$qStmt = $pdo->prepare(
    'SELECT external_id, question_text, option_a, option_b, option_c, option_d, correct_index
     FROM questions
     WHERE subject_id = ?
     ORDER BY id'
);
$qStmt->execute([(int)$sub['id']]);
$rows = $qStmt->fetchAll();

$questions = array_map(function ($r) {
    return [
        'id' => $r['external_id'],
        'question' => $r['question_text'],
        'options' => [$r['option_a'], $r['option_b'], $r['option_c'], $r['option_d']],
        'correct' => $r['correct_index'] === null ? null : (int)$r['correct_index'],
    ];
}, $rows);

json_response(['ok' => true, 'subject' => $subject, 'questions' => $questions]);
