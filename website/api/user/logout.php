<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';

logout_user();
json_response(['ok' => true]);
