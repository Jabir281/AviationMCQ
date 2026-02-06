<?php
/**
 * POST { subject, name, startIndex, endIndex }
 * Creates a new chunk for a subject.
 * Indices are 1-based (question 1 = index 1).
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('questions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$code = strtoupper(trim((string)($body['subject'] ?? '')));
$name = trim((string)($body['name'] ?? ''));
$startIndex = (int)($body['startIndex'] ?? 0);
$endIndex = (int)($body['endIndex'] ?? 0);

if ($code === '') json_error('Missing subject', 400);
if ($name === '') json_error('Missing chunk name', 400);
if ($startIndex < 1) json_error('Start index must be >= 1', 400);
if ($endIndex < $startIndex) json_error('End index must be >= start index', 400);

$pdo = db();

// Validate subject
$subjStmt = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$subjStmt->execute([$code]);
$subject = $subjStmt->fetch();
if (!$subject) {
    json_error('Subject not found', 404);
}
$subjectId = (int)$subject['id'];

// Count total questions
$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM questions WHERE subject_id = ?');
$countStmt->execute([$subjectId]);
$total = (int)$countStmt->fetch()['c'];

if ($endIndex > $total) {
    json_error("End index ($endIndex) exceeds total questions ($total)", 400);
}

// Get max sort_order
$maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) AS m FROM subject_chunks WHERE subject_id = ?');
$maxStmt->execute([$subjectId]);
$sortOrder = (int)$maxStmt->fetch()['m'] + 1;

$ins = $pdo->prepare(
    'INSERT INTO subject_chunks (subject_id, name, start_index, end_index, sort_order) VALUES (?, ?, ?, ?, ?)'
);
$ins->execute([$subjectId, $name, $startIndex, $endIndex, $sortOrder]);

json_response([
    'ok' => true,
    'id' => (int)$pdo->lastInsertId(),
    'name' => $name,
    'startIndex' => $startIndex,
    'endIndex' => $endIndex,
]);
