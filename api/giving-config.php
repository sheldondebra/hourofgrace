<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/giving.php';
require_once __DIR__ . '/../includes/security.php';

send_security_headers();
set_api_cors_headers('GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

json_response(['success' => true, 'config' => public_giving_config()]);
