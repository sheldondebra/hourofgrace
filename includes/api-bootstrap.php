<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

send_security_headers();
set_api_cors_headers($_SERVER['REQUEST_METHOD'] === 'GET' ? 'GET, OPTIONS' : 'POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function bootstrap_public_post(string $rateBucket, int $maxAttempts = 8): void
{
    validate_api_origin();
    enforce_rate_limit($rateBucket, $maxAttempts);

    if (is_honeypot_triggered()) {
        json_response(['success' => true, 'message' => 'Thank you. Your submission has been received.']);
    }
}
