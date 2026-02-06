<?php
/**
 * GET  ?subject=CODE  → list chunks for subject
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('questions');

$pdo = db();

$code = strtoupper(trim((string)($_GET['subject'] ?? '')));
if ($code === '') {
    json_error('Missing subject', 400);
}

$subjStmt = $pdo->prepare('SELECT id, code, name FROM subjects WHERE code = ?');
$subjStmt->execute([$code]);
$subject = $subjStmt->fetch();
if (!$subject) {
    json_error('Subject not found', 404);
}

// Count total questions (answered only)
$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM questions WHERE subject_id = ? AND correct_index IS NOT NULL');
$countStmt->execute([$subject['id']]);
$totalQuestions = (int)$countStmt->fetch()['c'];

// Count ALL questions including unanswered
$countAllStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM questions WHERE subject_id = ?');
$countAllStmt->execute([$subject['id']]);
$totalAllQuestions = (int)$countAllStmt->fetch()['c'];

// Get chunks
$chunkStmt = $pdo->prepare(
    'SELECT id, name, start_index, end_index, sort_order
     FROM subject_chunks
     WHERE subject_id = ?
     ORDER BY sort_order, start_index'
);
$chunkStmt->execute([$subject['id']]);
$chunks = array_map(function ($r) {
    return [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'startIndex' => (int)$r['start_index'],
        'endIndex' => (int)$r['end_index'],
        'sortOrder' => (int)$r['sort_order'],
        'questionCount' => (int)$r['end_index'] - (int)$r['start_index'] + 1,
    ];
}, $chunkStmt->fetchAll());

json_response([
    'ok' => true,
    'subject' => $subject['code'],
    'totalQuestions' => $totalAllQuestions,
    'chunks' => $chunks
]);
