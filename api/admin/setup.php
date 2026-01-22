<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';

$cfg = api_config();
$token = (string)($cfg['setupToken'] ?? '');
$given = (string)($_GET['token'] ?? '');

if ($token === '' || $given !== $token) {
    json_error('Forbidden', 403);
}

$pdo = db();

// Create tables
$schema = file_get_contents(__DIR__ . '/../schema.sql');
if ($schema === false) {
    json_error('Missing schema.sql', 500);
}

try {
    foreach (array_filter(array_map('trim', explode(";", $schema))) as $stmt) {
        $pdo->exec($stmt);
    }
} catch (Throwable $e) {
    json_error('Failed creating schema', 500);
}

// Lightweight migrations (for existing installs)
try {
    $pdo->exec('ALTER TABLE subjects ADD COLUMN icon VARCHAR(16) NULL');
} catch (Throwable $e) {
    // ignore (likely column already exists)
}

try {
    $pdo->exec("ALTER TABLE subjects ADD COLUMN section VARCHAR(10) NOT NULL DEFAULT 'seen'");
} catch (Throwable $e) {
    // ignore (likely column already exists)
}

// Backfill section for older installs
try {
    $pdo->exec("UPDATE subjects SET section = 'seen' WHERE section IS NULL OR section = ''");
} catch (Throwable $e) {
    // ignore (older installs without the column)
}

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            access_code_hash VARCHAR(255) NOT NULL UNIQUE,
            active_session_id VARCHAR(128) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (Throwable $e) {
    json_error('Failed creating users table', 500);
}

// Add active_session_id for existing installs
try {
    $pdo->exec('ALTER TABLE users ADD COLUMN active_session_id VARCHAR(128) NULL');
} catch (Throwable $e) {
    // ignore (likely column already exists)
}

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS exam_attempts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subject_code VARCHAR(10) NOT NULL,
            mode VARCHAR(16) NOT NULL,
            score_percent INT NULL,
            correct_count INT NULL,
            wrong_count INT NULL,
            skipped_count INT NULL,
            total_count INT NULL,
            started_at TIMESTAMP NULL,
            finished_at TIMESTAMP NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_created (user_id, created_at),
            CONSTRAINT fk_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (Throwable $e) {
    json_error('Failed creating exam_attempts table', 500);
}

// Seed admins only if none exist
$count = (int)$pdo->query('SELECT COUNT(*) AS c FROM admin_users')->fetch()['c'];
if ($count === 0) {
    $admins = $cfg['initialAdmins'] ?? [];
    if (!is_array($admins) || count($admins) === 0) {
        json_error('No initialAdmins configured', 500);
    }

    $ins = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
    foreach ($admins as $a) {
        $u = (string)($a['username'] ?? '');
        $p = (string)($a['password'] ?? '');
        if ($u === '' || $p === '') continue;
        $ins->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
    }
}

json_response(['ok' => true, 'message' => 'Setup complete']);
