<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('questions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$fromSubject = strtoupper(trim((string)($body['fromSubject'] ?? '')));
$toSubject = strtoupper(trim((string)($body['toSubject'] ?? '')));
$questionIds = $body['questionIds'] ?? [];

if ($fromSubject === '' || $toSubject === '') {
    json_error('Missing fromSubject or toSubject', 400);
}

if ($fromSubject === $toSubject) {
    json_error('Source and destination subjects are the same', 400);
}

if (!is_array($questionIds) || count($questionIds) === 0) {
    json_error('No questions selected', 400);
}

$pdo = db();

// Get source subject ID
$fromStmt = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$fromStmt->execute([$fromSubject]);
$fromRow = $fromStmt->fetch();
if (!$fromRow) {
    json_error('Source subject not found', 404);
}
$fromSubjectId = (int)$fromRow['id'];

// Get destination subject ID
$toStmt = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$toStmt->execute([$toSubject]);
$toRow = $toStmt->fetch();
if (!$toRow) {
    json_error('Destination subject not found', 404);
}
$toSubjectId = (int)$toRow['id'];

// Sanitize question IDs
$safeIds = [];
foreach ($questionIds as $qid) {
    $id = trim((string)$qid);
    if ($id !== '') {
        $safeIds[] = $id;
    }
}

if (count($safeIds) === 0) {
    json_error('No valid question IDs', 400);
}

$pdo->beginTransaction();
try {
    $movedCount = 0;
    $skippedCount = 0;

    foreach ($safeIds as $externalId) {
        // Check if question exists in source subject
        $checkStmt = $pdo->prepare('SELECT id, external_id FROM questions WHERE subject_id = ? AND external_id = ?');
        $checkStmt->execute([$fromSubjectId, $externalId]);
        $qRow = $checkStmt->fetch();

        if (!$qRow) {
            $skippedCount++;
            continue;
        }

        // Check if external_id already exists in destination subject
        $existsStmt = $pdo->prepare('SELECT id FROM questions WHERE subject_id = ? AND external_id = ?');
        $existsStmt->execute([$toSubjectId, $externalId]);
        if ($existsStmt->fetch()) {
            // Generate a new unique external_id for the moved question
            $newExternalId = 'MOV_' . bin2hex(random_bytes(12));
            $updateStmt = $pdo->prepare('UPDATE questions SET subject_id = ?, external_id = ? WHERE id = ?');
            $updateStmt->execute([$toSubjectId, $newExternalId, (int)$qRow['id']]);
        } else {
            // Move question to destination subject (keep external_id)
            $updateStmt = $pdo->prepare('UPDATE questions SET subject_id = ? WHERE id = ?');
            $updateStmt->execute([$toSubjectId, (int)$qRow['id']]);
        }

        $movedCount++;
    }

    $pdo->commit();

    json_response([
        'ok' => true,
        'moved' => $movedCount,
        'skipped' => $skippedCount,
        'fromSubject' => $fromSubject,
        'toSubject' => $toSubject,
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    json_error('Failed to move questions: ' . $e->getMessage(), 500);
}
