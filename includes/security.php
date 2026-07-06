<?php

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    $config = app_config();
    if (($config['environment'] ?? 'production') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function allowed_origins(): array
{
    $config = app_config();
    $origins = $config['allowed_origins'] ?? [];

    if (!$origins) {
        $siteUrl = rtrim($config['site_url'] ?? '', '/');
        if ($siteUrl) {
            $origins[] = $siteUrl;
            $origins[] = str_replace('://www.', '://', $siteUrl);
            $origins[] = str_replace('://', '://www.', $siteUrl);
        }
    }

    return array_values(array_unique(array_filter($origins)));
}

function set_api_cors_headers(string $method = 'POST'): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = allowed_origins();

    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } elseif (!$origin && ($method === 'GET')) {
        header('Access-Control-Allow-Origin: ' . ($allowed[0] ?? '*'));
    }

    header('Access-Control-Allow-Methods: ' . $method . ', OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

function validate_api_origin(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        return;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!$origin) {
        return;
    }

    if (!in_array($origin, allowed_origins(), true)) {
        json_response(['success' => false, 'message' => 'Forbidden.'], 403);
    }
}

function is_honeypot_triggered(): bool
{
    $trap = trim((string) ($_POST['website'] ?? ''));
    return $trap !== '';
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function enforce_rate_limit(string $bucket, int $maxAttempts = 8, int $windowSeconds = 3600): void
{
    $dir = dirname(__DIR__) . '/storage/rate-limits';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $key = hash('sha256', $bucket . '|' . client_ip());
    $file = $dir . '/' . $key . '.json';
    $now = time();
    $data = ['count' => 0, 'reset' => $now + $windowSeconds];

    if (is_file($file)) {
        $stored = json_decode((string) file_get_contents($file), true);
        if (is_array($stored)) {
            $data = $stored;
        }
    }

    if ($now > (int) ($data['reset'] ?? 0)) {
        $data = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    $data['count'] = (int) ($data['count'] ?? 0) + 1;
    file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $maxAttempts) {
        json_response(['success' => false, 'message' => 'Too many requests. Please try again later.'], 429);
    }
}

function harden_admin_session(): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');

    if ($secure) {
        ini_set('session.cookie_secure', '1');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
