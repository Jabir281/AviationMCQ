<?php

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/response.php';

$pdo = db();

// Returns array: [{ id: 'COMS', name: 'Communications', questionCount: 192 }, ...]
$stmt = $pdo->query(
    "SELECT s.code, s.name, s.icon, COUNT(q.id) AS question_count
     FROM subjects s
     LEFT JOIN questions q ON q.subject_id = s.id
     GROUP BY s.id
     ORDER BY s.code"
);

$rows = $stmt->fetchAll();
$subjects = array_map(function ($r) {
    return [
        'id' => strtoupper($r['code']),
        'name' => $r['name'],
        'icon' => $r['icon'] === null ? null : (string)$r['icon'],
        'questionCount' => (int)$r['question_count'],
    ];
}, $rows);

json_response(['ok' => true, 'subjects' => $subjects]);
