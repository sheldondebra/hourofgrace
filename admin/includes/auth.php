<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';

harden_admin_session();
session_start();

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/migrations.php';

const ADMIN_SESSION_TIMEOUT = 7200;

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function enforce_admin_session_timeout(): void
{
    $now = time();
    if (!empty($_SESSION['admin_last_activity']) && ($now - (int) $_SESSION['admin_last_activity']) > ADMIN_SESSION_TIMEOUT) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['admin_last_activity'] = $now;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }

    try {
        run_app_migrations(db());
    } catch (Throwable $e) {
        // Continue — individual pages handle DB errors.
    }

    enforce_admin_session_timeout();
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request.');
    }
}

function admin_user(): ?array
{
    if (!admin_logged_in()) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, username, email FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function count_unread(string $table): int
{
    $allowed = ['contact_submissions', 'prayer_requests', 'registration_submissions', 'school_registrations'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    try {
        return (int) db()->query("SELECT COUNT(*) FROM {$table} WHERE is_read = 0")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function admin_login_rate_limit(): void
{
    $dir = dirname(__DIR__, 2) . '/storage/rate-limits';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = hash('sha256', 'admin-login|' . $ip);
    $file = $dir . '/' . $key . '.json';
    $now = time();
    $window = 900;
    $maxAttempts = 10;
    $data = ['count' => 0, 'reset' => $now + $window];

    if (is_file($file)) {
        $stored = json_decode((string) file_get_contents($file), true);
        if (is_array($stored)) {
            $data = $stored;
        }
    }

    if ($now > (int) ($data['reset'] ?? 0)) {
        $data = ['count' => 0, 'reset' => $now + $window];
    }

    if ((int) ($data['count'] ?? 0) >= $maxAttempts) {
        http_response_code(429);
        exit('Too many login attempts. Please wait 15 minutes and try again.');
    }

    $data['count'] = (int) ($data['count'] ?? 0) + 1;
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function clear_admin_login_rate_limit(): void
{
    $dir = dirname(__DIR__, 2) . '/storage/rate-limits';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = hash('sha256', 'admin-login|' . $ip);
    $file = $dir . '/' . $key . '.json';
    if (is_file($file)) {
        unlink($file);
    }
}
