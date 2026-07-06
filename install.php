<?php
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:32rem;margin:3rem auto;padding:0 1rem">';
    echo '<h1>PHP upgrade required</h1>';
    echo '<p>This site needs <strong>PHP 8.0 or newer</strong>. Your server is running PHP ' . htmlspecialchars(PHP_VERSION) . '.</p>';
    echo '<p>In cPanel go to <strong>MultiPHP Manager</strong> and set this domain to PHP 8.1 or 8.2, then reload this page.</p>';
    echo '</body></html>';
    exit;
}

require_once __DIR__ . '/includes/requirements.php';
hog_require_extensions();

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:36rem;margin:3rem auto;padding:0 1rem">';
    echo '<h1>Setup error</h1>';
    echo '<p><strong>' . htmlspecialchars($error['message']) . '</strong></p>';
    echo '<p>File: ' . htmlspecialchars($error['file'] ?? '') . ' (line ' . (int) ($error['line'] ?? 0) . ')</p>';
    echo '<p>Common fixes: enable <strong>PDO</strong>, <strong>pdo_mysql</strong>, and <strong>mbstring</strong> in cPanel → Select PHP Version → Extensions.</p>';
    echo '<p>Upload <code>health.php</code> for a full server check, then delete it.</p>';
    echo '</body></html>';
});

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_site_installed() && ($_GET['action'] ?? '') !== 'status') {
    header('Location: admin/login.php');
    exit;
}

$config = load_config_array();
$dbTest = test_database_connection($config);
$error = null;
$success = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'install';

    if ($action === 'save_config') {
        $existing = load_config_array();
        $config = [
            'db_host' => post_string('db_host', 120) ?? 'localhost',
            'db_name' => post_string('db_name', 120) ?? '',
            'db_user' => post_string('db_user', 120) ?? '',
            'db_pass' => trim((string) ($_POST['db_pass'] ?? '')) !== ''
                ? (string) $_POST['db_pass']
                : ($existing['db_pass'] ?? ''),
            'site_name' => post_string('site_name', 180) ?? $existing['site_name'],
            'site_email' => post_string('site_email', 180) ?? $existing['site_email'],
            'site_url' => rtrim(post_string('site_url', 255) ?? $existing['site_url'], '/'),
            'smtp' => [
                'host' => post_string('smtp_host', 180) ?? ($existing['smtp']['host'] ?? ''),
                'port' => (int) (post_string('smtp_port', 10) ?? ($existing['smtp']['port'] ?? 465)),
                'user' => post_string('smtp_user', 180) ?? ($existing['smtp']['user'] ?? ''),
                'pass' => trim((string) ($_POST['smtp_pass'] ?? '')) !== ''
                    ? (string) $_POST['smtp_pass']
                    : ($existing['smtp']['pass'] ?? ''),
                'from_email' => post_string('smtp_from_email', 180) ?? ($existing['smtp']['from_email'] ?? ''),
                'from_name' => post_string('smtp_from_name', 180) ?? ($existing['smtp']['from_name'] ?? ''),
            ],
            'app_secret' => $existing['app_secret'] ?? bin2hex(random_bytes(32)),
            'environment' => $existing['environment'] ?? 'production',
            'allowed_origins' => $existing['allowed_origins'] ?? default_app_config()['allowed_origins'],
            'upload_max_mb' => (int) ($existing['upload_max_mb'] ?? 5),
            'timezone' => $existing['timezone'] ?? 'Europe/London',
        ];

        if (!$config['db_name'] || !$config['db_user']) {
            $error = 'Database name and username are required.';
        } else {
            $dbTest = test_database_connection($config);
            if (!$dbTest['ok']) {
                $error = 'Database connection failed: ' . $dbTest['message'];
            } else {
                try {
                    write_app_config($config);
                    $notice = 'Configuration saved and database connection verified.';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }

    if ($action === 'install' && !$error) {
        $username = post_string('username', 80) ?? 'admin';
        $adminEmail = post_string('admin_email', 180);
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Admin password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Admin passwords do not match.';
        } else {
            $config = load_config_array();
            $dbTest = test_database_connection($config);

            if (!$dbTest['ok']) {
                $error = 'Database connection failed. Save and test your database settings first.';
            } else {
                try {
                    require_once __DIR__ . '/includes/gallery-data.php';
                    require_once __DIR__ . '/includes/migrations.php';

                    $pdo = db();
                    run_sql_schema($pdo, __DIR__ . '/sql/schema.sql');
                    run_app_migrations($pdo);

                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->exec('DELETE FROM admins');
                    $email = $adminEmail ?: ($config['site_email'] ?? null);
                    $stmt = $pdo->prepare('INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)');
                    $stmt->execute([$username, $email, $hash]);

                    $existingImages = (int) $pdo->query('SELECT COUNT(*) FROM gallery_images')->fetchColumn();
                    if ($existingImages === 0) {
                        $seedImages = default_gallery_images();
                        $insert = $pdo->prepare('INSERT INTO gallery_images (image_path, sort_order) VALUES (?, ?)');
                        foreach ($seedImages as $i => $url) {
                            $insert->execute([$url, $i + 1]);
                        }
                    }

                    ensure_upload_dir(__DIR__ . '/uploads/gallery');
                    ensure_upload_dir(__DIR__ . '/uploads/hero');
                    ensure_upload_dir(__DIR__ . '/uploads/documents');
                    ensure_upload_dir(__DIR__ . '/storage/rate-limits');

                    seed_email_settings($pdo);
                    seed_hero_from_gallery($pdo);
                    mark_site_installed();

                    $success = 'Installation complete. Delete install.php from your server after logging in.';
                } catch (Throwable $e) {
                    $error = 'Installation failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$config = load_config_array();
$dbTest = test_database_connection($config);
$configExists = is_file(config_path());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Install — Hour of Grace</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{brand:{purple:'#3F3D8B'}}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen py-10 px-4">
  <div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm">
      <img src="assets/logo.png" alt="Hour of Grace" class="h-12 mx-auto mb-6" />
      <h1 class="text-xl font-semibold text-center text-slate-900 mb-2">Website Setup</h1>
      <p class="text-slate-600 text-sm text-center mb-6">Connect your database, confirm email settings, then create the admin account.</p>

      <?php if ($error): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm"><?= sanitize($error) ?></div>
      <?php endif; ?>
      <?php if ($notice): ?>
        <div class="mb-4 p-3 rounded-lg bg-sky-50 text-sky-800 text-sm"><?= sanitize($notice) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm"><?= sanitize($success) ?></div>
        <a href="admin/login.php" class="block w-full text-center py-3 rounded-xl bg-brand-purple text-white font-medium">Go to Admin Login</a>
      <?php else: ?>

      <div class="mb-6 p-4 rounded-xl border <?= $dbTest['ok'] ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' ?>">
        <p class="text-sm font-medium <?= $dbTest['ok'] ? 'text-emerald-800' : 'text-red-800' ?>">
          Database status: <?= $dbTest['ok'] ? 'Connected' : 'Not connected' ?>
        </p>
        <?php if (!$dbTest['ok']): ?>
          <p class="text-xs mt-1 text-red-700"><?= sanitize($dbTest['message']) ?></p>
        <?php endif; ?>
        <?php if ($configExists): ?>
          <p class="text-xs mt-2 text-slate-600">Config file found at <code>includes/config.php</code></p>
        <?php else: ?>
          <p class="text-xs mt-2 text-slate-600">No config file yet. Save the form below to create one.</p>
        <?php endif; ?>
      </div>

      <form method="post" class="space-y-4 mb-8 pb-8 border-b border-slate-100">
        <input type="hidden" name="action" value="save_config" />
        <h2 class="font-semibold text-slate-900">1. Database &amp; email</h2>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">DB Host</label>
            <input type="text" name="db_host" value="<?= sanitize($config['db_host']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="localhost" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Database Name</label>
            <input type="text" name="db_name" value="<?= sanitize($config['db_name']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="hourofgr_gracedch" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Database User</label>
            <input type="text" name="db_user" value="<?= sanitize($config['db_user']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="hourofgr_churchdb" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Database Password</label>
            <input type="password" name="db_pass" class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="<?= $config['db_pass'] ? 'Saved — leave blank to keep' : 'Enter password' ?>" autocomplete="new-password" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Site URL</label>
            <input type="url" name="site_url" value="<?= sanitize($config['site_url'] ?? '') ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="https://hourofgraceministries.org" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Admin notification email</label>
            <input type="email" name="site_email" value="<?= sanitize($config['site_email']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="info@hourofgraceministries.org" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Host</label>
            <input type="text" name="smtp_host" value="<?= sanitize($config['smtp']['host'] ?? '') ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="mail.hourofgraceministries.org" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Port</label>
            <input type="number" name="smtp_port" value="<?= sanitize((string) ($config['smtp']['port'] ?? 465)) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="465" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Username</label>
            <input type="text" name="smtp_user" value="<?= sanitize($config['smtp']['user'] ?? '') ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="smpt@hourofgraceministries.org" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Password</label>
            <input type="password" name="smtp_pass" class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="<?= !empty($config['smtp']['pass']) ? 'Saved — leave blank to keep' : 'Enter SMTP password' ?>" autocomplete="new-password" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">From Email</label>
            <input type="email" name="smtp_from_email" value="<?= sanitize($config['smtp']['from_email'] ?? '') ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">From Name</label>
            <input type="text" name="smtp_from_name" value="<?= sanitize($config['smtp']['from_name'] ?? $config['site_name']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" />
          </div>
        </div>

        <button type="submit" class="w-full py-3 rounded-xl border border-brand-purple text-brand-purple font-medium hover:bg-brand-purple hover:text-white transition-colors">
          Save &amp; test connection
        </button>
      </form>

      <form method="post" class="space-y-4">
        <input type="hidden" name="action" value="install" />
        <h2 class="font-semibold text-slate-900">2. Admin account</h2>
        <p class="text-sm text-slate-600">Creates database tables, seeds the gallery, and saves email settings.</p>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Admin Username</label>
          <input type="text" name="username" value="admin" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="admin" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Admin Email (for password reset)</label>
          <input type="email" name="admin_email" value="<?= sanitize($config['site_email'] ?? 'info@hourofgraceministries.org') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="info@hourofgraceministries.org" />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <input type="password" name="password" required minlength="8" class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="Minimum 8 characters" autocomplete="new-password" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
            <input type="password" name="confirm_password" required minlength="8" class="w-full px-4 py-2.5 rounded-xl border border-slate-200" placeholder="Re-enter password" autocomplete="new-password" />
          </div>
        </div>

        <button type="submit" class="w-full py-3 rounded-xl bg-brand-purple text-white font-medium" <?= $dbTest['ok'] ? '' : 'disabled' ?>>
          Complete installation
        </button>
        <?php if (!$dbTest['ok']): ?>
          <p class="text-xs text-slate-500 text-center">Connect the database in step 1 before installing.</p>
        <?php endif; ?>
      </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
