<?php

require_once __DIR__ . '/response.php';

function api_config_base(): array {
    $configPath = __DIR__ . '/../config.php';
    if (!file_exists($configPath)) {
        json_error('Server not configured (missing api/config.php).', 500);
    }

    $cfg = require $configPath;
    if (!is_array($cfg) || !isset($cfg['db'])) {
        json_error('Server config invalid.', 500);
    }
    return $cfg;
}

function api_config(): array {
    static $fullConfig = null;
    if ($fullConfig !== null) {
        return $fullConfig;
    }
    
    $cfg = api_config_base();
    
    // Override with database settings if available
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        if ($stmt) {
            foreach ($stmt->fetchAll() as $row) {
                if ($row['setting_key'] === 'singleDevicePerUser') {
                    $cfg['singleDevicePerUser'] = ($row['setting_value'] === '1');
                }
            }
        }
    } catch (Throwable $e) {
        // Table might not exist yet or other error - use file config
    }
    
    $fullConfig = $cfg;
    return $fullConfig;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = api_config_base();
    $db = $cfg['db'];

    $host = $db['host'] ?? 'localhost';
    $name = $db['name'] ?? '';
    $user = $db['user'] ?? '';
    $pass = $db['pass'] ?? '';
    $charset = $db['charset'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        json_error('Database connection failed.', 500);
    }

    // Unreachable (json_error exits), but keeps static analyzers happy.
    throw new RuntimeException('Unreachable');
}
