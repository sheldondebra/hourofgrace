<?php

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function post_string(string $key, int $max = 500): ?string
{
    if (!isset($_POST[$key])) {
        return null;
    }
    $value = trim((string) $_POST[$key]);
    if ($value === '') {
        return null;
    }
    return mb_substr($value, 0, $max);
}

function validate_email(?string $email): bool
{
    return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function ensure_upload_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function save_uploaded_files(string $field, string $subdir): array
{
    $config = app_config();
    $maxBytes = ($config['upload_max_mb'] ?? 5) * 1024 * 1024;
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $saved = [];

    if (empty($_FILES[$field])) {
        return $saved;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/' . $subdir;
    ensure_upload_dir($uploadDir);

    $files = $_FILES[$field];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ($files['size'][$i] > $maxBytes) {
            continue;
        }

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }

        $filename = uniqid('doc_', true) . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;

        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $saved[] = 'uploads/' . $subdir . '/' . $filename;
        }
    }

    return $saved;
}

function gallery_public_url(string $path): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    $rootRelative = '/' . ltrim($path, '/');
    $base = site_base_url();

    return $base !== '' ? $base . $rootRelative : $rootRelative;
}

function site_base_url(): string
{
    try {
        $config = load_config_array();
        $url = rtrim((string) ($config['site_url'] ?? ''), '/');
        if ($url !== '') {
            return $url;
        }
    } catch (Throwable $e) {
        // Fall back to request-based URL below.
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return '';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    return ($https ? 'https' : 'http') . '://' . $host;
}

function localize_media_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return $path;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        $basename = basename(parse_url($path, PHP_URL_PATH) ?: '');
        return $basename !== '' ? 'assets/gallery/' . $basename : $path;
    }

    if (str_contains($path, 'wp-content/uploads/')) {
        $basename = basename($path);
        return $basename !== '' ? 'assets/gallery/' . $basename : $path;
    }

    return ltrim($path, '/');
}

function format_datetime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d M Y, H:i', $ts) : $dt;
}

function submission_badge(bool $read): string
{
    return $read
        ? '<span class="badge badge-read">Read</span>'
        : '<span class="badge badge-new">New</span>';
}

function config_path(): string
{
    return dirname(__DIR__) . '/includes/config.php';
}

function install_lock_path(): string
{
    return dirname(__DIR__) . '/.installed';
}

function is_site_installed(): bool
{
    if (!is_file(config_path())) {
        return false;
    }

    if (is_file(install_lock_path())) {
        return true;
    }

    try {
        $pdo = db();
        $pdo->query('SELECT 1 FROM admins LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function default_app_config(): array
{
    return [
        'db_host' => 'localhost',
        'db_name' => 'hourofgr_gracedch',
        'db_user' => 'hourofgr_churchdb',
        'db_pass' => '',

        'site_name' => 'Hour of Grace Family Chapel International',
        'site_email' => 'info@hourofgraceministries.org',
        'site_url' => 'https://hourofgraceministries.org',

        'smtp' => [
            'host' => 'mail.hourofgraceministries.org',
            'port' => 465,
            'user' => 'smtp@hourofgraceministries.org',
            'pass' => '',
            'from_email' => 'smtp@hourofgraceministries.org',
            'from_name' => 'Hour of Grace Ministry International',
        ],

        'app_secret' => bin2hex(random_bytes(32)),
        'environment' => 'production',
        'allowed_origins' => [
            'https://hourofgraceministries.org',
            'https://www.hourofgraceministries.org',
            'http://127.0.0.1:5500',
            'http://localhost:5500',
        ],

        'upload_max_mb' => 5,
        'timezone' => 'Europe/London',
    ];
}

function load_config_array(): array
{
    if (is_file(config_path())) {
        $config = require config_path();
        return is_array($config) ? $config : default_app_config();
    }

    return default_app_config();
}

function write_app_config(array $config): void
{
    $export = var_export($config, true);
    $contents = "<?php\nreturn {$export};\n";
    $path = config_path();

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write config.php. Check folder permissions.');
    }
}

function friendly_db_error(string $message): string
{
    if (stripos($message, 'using password: NO') !== false) {
        return 'No database password was saved. Enter the password from cPanel → MySQL Databases, then click Save & test connection.';
    }

    return $message;
}

function test_database_connection(array $config): array
{
    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_name']
        );

        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->query('SELECT 1');

        return ['ok' => true, 'message' => 'Database connection successful.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function run_sql_schema(PDO $pdo, string $schemaFile): void
{
    $sql = file_get_contents($schemaFile);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

function mark_site_installed(): void
{
    file_put_contents(install_lock_path(), date('c') . PHP_EOL, LOCK_EX);
}

function register_job_category_label(string $value): string
{
    return match ($value) {
        'minister' => 'Minister of Religion',
        'worker' => 'Religious Worker',
        default => ucwords(str_replace('-', ' ', $value)),
    };
}

function register_job_interest_label(string $value): string
{
    $labels = [
        'singer' => 'Singer',
        'organist' => 'Organist',
        'guitarist' => 'Guitarist',
        'drummer' => 'Drummer',
        'trumpeter' => 'Trumpeter',
        'bible-teacher' => 'Bible Teacher',
        'sound-engineer' => 'Sound Engineer',
        'children-bible-teacher' => "Children's Bible Teacher",
        'pastor' => 'Pastor',
        'evangelism-leader' => 'Leader of Evangelism',
        'youth-leader' => 'Leader of Youth Ministry',
        'procurement' => 'Procurement Officers',
        'women-fellowship' => "Women's Fellowship Leaders",
        'men-fellowship' => "Men's Fellowship Leaders",
    ];

    return $labels[$value] ?? ucwords(str_replace('-', ' ', $value));
}

function yes_no_label(string $value): string
{
    return match (strtolower($value)) {
        'yes' => 'Yes',
        'no' => 'No',
        default => $value,
    };
}

function seed_email_settings(PDO $pdo): void
{
    $config = app_config();
    $smtp = $config['smtp'] ?? [];
    $emailDefaults = [
        'admin_email' => $config['site_email'] ?? 'info@hourofgraceministries.org',
        'smtp_host' => $smtp['host'] ?? 'mail.hourofgraceministries.org',
        'smtp_port' => (string) ($smtp['port'] ?? 465),
        'smtp_user' => $smtp['user'] ?? '',
        'smtp_pass' => $smtp['pass'] ?? '',
        'smtp_from_email' => $smtp['from_email'] ?? ($smtp['user'] ?? ''),
        'smtp_from_name' => $smtp['from_name'] ?? $config['site_name'],
        'notify_admin' => '1',
        'notify_user' => '1',
    ];

    $settingStmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($emailDefaults as $key => $value) {
        $settingStmt->execute([$key, $value]);
    }
}
