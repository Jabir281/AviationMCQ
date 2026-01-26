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

$subjectStmt = $pdo->prepare('SELECT id, code, name FROM subjects WHERE code = ?');
$subjectStmt->execute([$subject]);
$subjectRow = $subjectStmt->fetch();
if (!$subjectRow) {
    json_error('Unknown subject', 404);
}

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
