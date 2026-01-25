<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

require_admin_feature('subjects');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$code = strtoupper(trim((string)($_POST['code'] ?? '')));
$name = trim((string)($_POST['name'] ?? ''));
$icon = trim((string)($_POST['icon'] ?? ''));
$section = strtolower(trim((string)($_POST['section'] ?? 'seen')));

if ($code === '' || !preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
    json_error('Invalid subject code. Use 2-10 letters/numbers (e.g. MET, NAV1).', 400);
}

if ($name === '' || mb_strlen($name) > 255) {
    json_error('Invalid subject name.', 400);
}

if ($icon !== '' && mb_strlen($icon) > 16) {
    json_error('Invalid icon.', 400);
}

if ($section !== 'seen' && $section !== 'unseen') {
    json_error('Invalid section. Use seen or unseen.', 400);
}

if (!isset($_FILES['jsonFile'])) {
    json_error('Missing JSON file (jsonFile).', 400);
}

$f = $_FILES['jsonFile'];
if (!is_array($f) || !isset($f['error'])) {
    json_error('Invalid file upload.', 400);
}

if ((int)$f['error'] !== UPLOAD_ERR_OK) {
    json_error('Upload failed (error ' . (int)$f['error'] . ').', 400);
}

// Safety: prevent huge uploads from exhausting memory. Hostinger may enforce its own limits.
$maxBytes = 25 * 1024 * 1024; // 25MB
if (isset($f['size']) && (int)$f['size'] > $maxBytes) {
    json_error('File too large. Please upload a smaller JSON file.', 413);
}

$tmp = (string)($f['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    json_error('Upload temp file missing.', 400);
}

$raw = file_get_contents($tmp);
$decoded = json_decode($raw ?: '[]', true);

if (is_array($decoded) && array_key_exists('questions', $decoded) && is_array($decoded['questions'])) {
    $items = $decoded['questions'];
} else {
    $items = $decoded;
}

if (!is_array($items)) {
    json_error('Invalid JSON format. Expected an array of questions.', 400);
}

$pdo = db();

$pdo->beginTransaction();
try {
    // Upsert subject
    $pdo->prepare(
        'INSERT INTO subjects (code, name, icon, section) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), section = VALUES(section)'
    )->execute([$code, $name, ($icon === '' ? null : $icon), $section]);

    $subRow = $pdo->prepare('SELECT id FROM subjects WHERE code = ?');
    $subRow->execute([$code]);
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
        if (!is_array($q)) continue;

        $text = (string)($q['question'] ?? $q['question_text'] ?? '');
        $text = trim($text);

        $opts = $q['options'] ?? null;
        if (!is_array($opts) || count($opts) < 4) continue;

        $external = (string)($q['id'] ?? $q['external_id'] ?? '');
        $external = trim($external);
        if ($external === '') {
            $external = sha1($code . '|' . $idx . '|' . $text);
        }

        $correct = $q['correct'] ?? $q['correct_index'] ?? null;
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

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error('Import failed.', 500);
}

json_response(['ok' => true, 'subject' => $code, 'name' => $name, 'icon' => ($icon === '' ? null : $icon), 'section' => $section, 'imported' => $imported]);
