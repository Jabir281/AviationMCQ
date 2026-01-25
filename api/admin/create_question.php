<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin_feature('questions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$subject = strtoupper(trim((string)($body['subject'] ?? '')));
$text = trim((string)($body['question'] ?? ''));
$options = $body['options'] ?? null;
$correct = $body['correct'] ?? null;

if ($subject === '') {
    json_error('Missing subject', 400);
}

if ($text === '') {
    json_error('Question text is required.', 400);
}

if (!is_array($options) || count($options) !== 4) {
    json_error('Options must be an array of 4.', 400);
}

$opt0 = trim((string)$options[0]);
$opt1 = trim((string)$options[1]);
$opt2 = trim((string)$options[2]);
$opt3 = trim((string)$options[3]);

if ($opt0 === '' || $opt1 === '' || $opt2 === '' || $opt3 === '') {
    json_error('All 4 options are required.', 400);
}

$correctIndex = ($correct === null || $correct === '' || $correct === false) ? null : (int)$correct;
if ($correctIndex !== null && ($correctIndex < 0 || $correctIndex > 3)) {
    json_error('Invalid correct index', 400);
}

$pdo = db();

$subStmt = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
$subStmt->execute([$subject]);
$sub = $subStmt->fetch();
if (!$sub) json_error('Unknown subject', 404);

function make_external_id(): string {
    // Prefix to avoid collisions with imported external IDs.
    // 5 + 24 = 29 chars.
    return 'ADM_' . bin2hex(random_bytes(12));
}

$subjectId = (int)$sub['id'];

for ($i = 0; $i < 5; $i++) {
    $externalId = make_external_id();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO questions (subject_id, external_id, question_text, option_a, option_b, option_c, option_d, correct_index)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)' 
        );

        $ins->execute([
            $subjectId,
            $externalId,
            $text,
            $opt0,
            $opt1,
            $opt2,
            $opt3,
            $correctIndex,
        ]);

        json_response([
            'ok' => true,
            'subject' => $subject,
            'id' => $externalId,
        ]);
    } catch (Throwable $e) {
        // Retry if external_id collided (extremely unlikely).
        continue;
    }
}

json_error('Failed to create question. Please try again.', 500);
