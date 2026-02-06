<?php

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/response.php';

$cfg = api_config();
if (($cfg['requireUserAuth'] ?? false) === true) {
    require_user();
}

$pdo = db();

$subject = isset($_GET['subject']) ? strtoupper(trim((string)$_GET['subject'])) : '';
if ($subject === '') {
    json_error('Missing subject', 400);
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
$random = isset($_GET['random']) ? (int)$_GET['random'] : 0;
$chunkId = isset($_GET['chunk']) ? (int)$_GET['chunk'] : 0;

$subjectStmt = $pdo->prepare('SELECT id, code, name FROM subjects WHERE code = ?');
$subjectStmt->execute([$subject]);
$subjectRow = $subjectStmt->fetch();
if (!$subjectRow) {
    json_error('Unknown subject', 404);
}

// If a chunk is requested, resolve its start/end to get question IDs
$chunkQuestionIds = null;
if ($chunkId > 0) {
    try {
        $chunkStmt = $pdo->prepare('SELECT start_index, end_index FROM subject_chunks WHERE id = ? AND subject_id = ?');
        $chunkStmt->execute([$chunkId, $subjectRow['id']]);
        $chunkRow = $chunkStmt->fetch();
        if ($chunkRow) {
            $startIdx = (int)$chunkRow['start_index'];
            $endIdx = (int)$chunkRow['end_index'];
            // Get the question IDs in this range (1-based, ordered by id)
            $idStmt = $pdo->prepare(
                'SELECT id FROM questions WHERE subject_id = ? ORDER BY id LIMIT ? OFFSET ?'
            );
            $idStmt->execute([$subjectRow['id'], $endIdx - $startIdx + 1, $startIdx - 1]);
            $chunkQuestionIds = array_column($idStmt->fetchAll(), 'id');
        }
    } catch (Throwable $e) {
        // chunk table may not exist, ignore
    }
}

if ($chunkQuestionIds !== null && count($chunkQuestionIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($chunkQuestionIds), '?'));
    $sql = "SELECT external_id, question_text, option_a, option_b, option_c, option_d, correct_index
            FROM questions
            WHERE id IN ($placeholders) AND correct_index IS NOT NULL";

    if ($random === 1) {
        $sql .= " ORDER BY RAND()";
    } else {
        $sql .= " ORDER BY id";
    }

    $params = $chunkQuestionIds;

    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
} else {
    $sql = "SELECT external_id, question_text, option_a, option_b, option_c, option_d, correct_index
            FROM questions
            WHERE subject_id = ? AND correct_index IS NOT NULL";

    if ($random === 1) {
        $sql .= " ORDER BY RAND()";
    } else {
        $sql .= " ORDER BY id";
    }

    $params = [$subjectRow['id']];
    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
}

$qStmt = $pdo->prepare($sql);
$qStmt->execute($params);
$rows = $qStmt->fetchAll();

$questions = array_map(function ($r) {
    return [
        'id' => $r['external_id'],
        'question' => $r['question_text'],
        'options' => [
            $r['option_a'],
            $r['option_b'],
            $r['option_c'],
            $r['option_d'],
        ],
        'correct' => $r['correct_index'] === null ? null : (int)$r['correct_index'],
    ];
}, $rows);

json_response(['ok' => true, 'subject' => $subjectRow['code'], 'questions' => $questions]);
