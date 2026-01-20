<?php

require_once __DIR__ . '/../lib/response.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
json_response(['ok' => true]);
