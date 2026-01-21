<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$user = require_user();
$body = read_json_body();

$subject = strtoupper(trim((string)($body['subject'] ?? '')));
$mode = strtolower(trim((string)($body['mode'] ?? '')));

if ($subject === '' || !preg_match('/^[A-Z0-9]{2,10}$/', $subject)) {
    json_error('Invalid subject', 400);
}

if ($mode !== 'mock' && $mode !== 'practice') {
    json_error('Invalid mode', 400);
}

$score = isset($body['score']) ? (int)$body['score'] : null;
$correct = isset($body['correct']) ? (int)$body['correct'] : null;
$wrong = isset($body['wrong']) ? (int)$body['wrong'] : null;
$skipped = isset($body['skipped']) ? (int)$body['skipped'] : null;
$total = isset($body['total']) ? (int)$body['total'] : null;

$startedAt = isset($body['startedAt']) ? (string)$body['startedAt'] : null;
$finishedAt = isset($body['finishedAt']) ? (string)$body['finishedAt'] : null;

$payload = $body['payload'] ?? null;
$payloadJson = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);

$pdo = db();

$stmt = $pdo->prepare(
    'INSERT INTO exam_attempts
        (user_id, subject_code, mode, score_percent, correct_count, wrong_count, skipped_count, total_count, started_at, finished_at, payload_json)
     VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

try {
    $stmt->execute([
        (int)$user['id'],
        $subject,
        $mode,
        $score,
        $correct,
        $wrong,
        $skipped,
        $total,
        $startedAt,
        $finishedAt,
        $payloadJson,
    ]);
} catch (Throwable $e) {
    json_error('Failed saving attempt', 500);
}

json_response(['ok' => true]);
