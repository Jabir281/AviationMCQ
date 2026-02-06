<?php
/**
 * GET ?subject=CODE → returns chunks for a subject (public, for users)
 * Returns empty array if subject has no chunks.
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/response.php';

$cfg = api_config();
if (($cfg['requireUserAuth'] ?? false) === true) {
    require_user();
}

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

// Get chunks
try {
    $chunkStmt = $pdo->prepare(
        'SELECT id, name, start_index, end_index
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
            'questionCount' => (int)$r['end_index'] - (int)$r['start_index'] + 1,
        ];
    }, $chunkStmt->fetchAll());
} catch (Throwable $e) {
    // Table may not exist yet
    $chunks = [];
}

json_response([
    'ok' => true,
    'subject' => $subject['code'],
    'chunks' => $chunks
]);
