<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

// If single-device is enabled, clear active_session_id on logout.
try {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
	$cfg = api_config();
	if ($uid > 0 && (($cfg['singleDevicePerUser'] ?? false) === true)) {
		$pdo = db();
		$pdo->prepare('UPDATE users SET active_session_id = NULL WHERE id = ?')->execute([$uid]);
	}
} catch (Throwable $e) {
	// ignore
}

logout_user();
json_response(['ok' => true]);
