<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('settings');

$pdo = db();

// Ensure settings table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    // Table might already exist
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all settings
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Default values if not set
        if (!isset($settings['singleDevicePerUser'])) {
            $settings['singleDevicePerUser'] = '1'; // enabled by default
        }
        
        json_response(['ok' => true, 'settings' => $settings]);
    } catch (Throwable $e) {
        json_error('Failed to fetch settings', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body();
    $key = $body['key'] ?? '';
    $value = $body['value'] ?? '';
    
    $allowedKeys = ['singleDevicePerUser'];
    
    if (!in_array($key, $allowedKeys, true)) {
        json_error('Invalid setting key', 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$key, $value]);
        
        json_response(['ok' => true]);
    } catch (Throwable $e) {
        json_error('Failed to update setting', 500);
    }
}

json_error('Method not allowed', 405);
