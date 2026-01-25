<?php

require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/auth.php';

ensure_admin_session();
$_SESSION = [];
session_destroy();
json_response(['ok' => true]);
