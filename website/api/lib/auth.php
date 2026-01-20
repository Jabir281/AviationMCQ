<?php

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

function ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Cookies must be sent before output.
        session_start();
    }
}

function require_admin(): array {
    ensure_session();
    if (!isset($_SESSION['admin_id'])) {
        json_error('Unauthorized', 401);
    }
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? null,
    ];
}
