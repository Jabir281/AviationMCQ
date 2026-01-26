<?php

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/response.php';

$cfg = api_config();
if (($cfg['requireUserAuth'] ?? false) === true) {
    require_user();
}

$pdo = db();

// Returns array: [{ id: 'COMS', name: 'Communications', questionCount: 192 }, ...]
// Only count questions that have correct answers set
$stmt = $pdo->query(
    "SELECT s.code, s.name, s.icon, s.section, COUNT(q.id) AS question_count
     FROM subjects s
     LEFT JOIN questions q ON q.subject_id = s.id AND q.correct_index IS NOT NULL
    GROUP BY s.id, s.code, s.name, s.icon, s.section
     ORDER BY s.code"
);

$rows = $stmt->fetchAll();
$subjects = array_map(function ($r) {
    return [
        'id' => strtoupper($r['code']),
        'name' => $r['name'],
        'icon' => $r['icon'] === null ? null : (string)$r['icon'],
        'section' => ($r['section'] === null || $r['section'] === '') ? 'seen' : (string)$r['section'],
        'questionCount' => (int)$r['question_count'],
    ];
}, $rows);

json_response(['ok' => true, 'subjects' => $subjects]);
