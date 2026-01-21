<?php

require_once __DIR__ . '/../lib/auth.php';

$admin = require_admin();
json_response(['ok' => true, 'admin' => $admin]);
