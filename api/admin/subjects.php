<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin();

$pdo = db();

$stmt = $pdo->query(
    "SELECT s.code, s.name, s.icon, s.section, COUNT(q.id) AS question_count
     FROM subjects s
     LEFT JOIN questions q ON q.subject_id = s.id
     GROUP BY s.id
     ORDER BY s.code"
);

$rows = $stmt->fetchAll();
$subjects = array_map(function ($r) {
    return [
        'code' => strtoupper((string)$r['code']),
        'name' => (string)$r['name'],
        'icon' => $r['icon'] === null ? null : (string)$r['icon'],
        'section' => ($r['section'] === null || $r['section'] === '') ? 'seen' : (string)$r['section'],
        'questionCount' => (int)$r['question_count'],
    ];
}, $rows);

json_response(['ok' => true, 'subjects' => $subjects]);
