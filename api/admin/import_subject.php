<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin();
$cfg = api_config();
$files = $cfg['importFiles'] ?? [];

$subject = isset($_GET['subject']) ? strtoupper(trim((string)$_GET['subject'])) : '';
if ($subject === '') {
    json_error('Missing subject', 400);
}

if (!isset($files[$subject])) {
    json_error('Import not allowed for this subject', 403);
}

$filePath = $files[$subject];
if (!file_exists($filePath)) {
    json_error('JSON file not found on server', 404);
}

$raw = file_get_contents($filePath);
$items = json_decode($raw ?: '[]', true);
if (!is_array($items)) {
    json_error('Invalid JSON file', 400);
}

$pdo = db();

// Ensure subject row
$pdo->prepare('INSERT IGNORE INTO subjects (code, name) VALUES (?, ?)')
    ->execute([$subject, $subject]);

$subRow = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$subRow->execute([$subject]);
$subjectId = (int)$subRow->fetch()['id'];

$up = $pdo->prepare(
    'INSERT INTO questions (subject_id, external_id, question_text, option_a, option_b, option_c, option_d, correct_index)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       question_text = VALUES(question_text),
       option_a = VALUES(option_a),
       option_b = VALUES(option_b),
       option_c = VALUES(option_c),
       option_d = VALUES(option_d),
       correct_index = VALUES(correct_index)'
);

$imported = 0;
foreach ($items as $idx => $q) {
    $text = (string)($q['question'] ?? '');
    $opts = $q['options'] ?? [];
    if (!is_array($opts) || count($opts) < 4) continue;

    $external = (string)($q['id'] ?? '');
    $external = trim($external);
    if ($external === '') {
        $external = sha1($subject . '|' . $idx . '|' . $text);
    }

    $correct = $q['correct'];
    $correctIndex = ($correct === null || $correct === '' || $correct === false) ? null : (int)$correct;

    $up->execute([
        $subjectId,
        $external,
        $text,
        (string)$opts[0],
        (string)$opts[1],
        (string)$opts[2],
        (string)$opts[3],
        $correctIndex,
    ]);
    $imported++;
}

json_response(['ok' => true, 'subject' => $subject, 'imported' => $imported]);
