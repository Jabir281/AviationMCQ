<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

// Return 200 when logged in; 401 otherwise.
require_user();
json_response(['ok' => true]);
