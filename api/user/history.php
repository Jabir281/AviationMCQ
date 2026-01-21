<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

$user = require_user();
$pdo = db();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit <= 0) $limit = 50;
if ($limit > 200) $limit = 200;

$stmt = $pdo->prepare(
    'SELECT id, subject_code, mode, score_percent, correct_count, wrong_count, skipped_count, total_count, created_at
     FROM exam_attempts
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT ' . (int)$limit
);

$stmt->execute([(int)$user['id']]);
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    return [
        'id' => (string)$r['id'],
        'subject' => (string)$r['subject_code'],
        'mode' => (string)$r['mode'],
        'score' => $r['score_percent'] === null ? null : (int)$r['score_percent'],
        'correct' => $r['correct_count'] === null ? null : (int)$r['correct_count'],
        'wrong' => $r['wrong_count'] === null ? null : (int)$r['wrong_count'],
        'skipped' => $r['skipped_count'] === null ? null : (int)$r['skipped_count'],
        'total' => $r['total_count'] === null ? null : (int)$r['total_count'],
        'date' => (string)$r['created_at'],
    ];
}, $rows);

json_response(['ok' => true, 'history' => $items]);
