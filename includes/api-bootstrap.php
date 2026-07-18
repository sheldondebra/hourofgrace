<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

// Safety net: never let a fatal error return an empty body to the browser.
// A blank response makes the frontend fail with "Unexpected end of JSON input".
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if (!($error['type'] & $fatalTypes)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'message' => 'A server error occurred. Please try again later.',
    ]);
});

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
