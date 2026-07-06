<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}

if (!is_file(config_path())) {
    header('Location: ../install.php');
    exit;
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!validate_email($email)) {
        $error = 'Please enter a valid admin email address.';
    } else {
        try {
            run_app_migrations(db());
            $stmt = db()->prepare('SELECT id, username, email FROM admins WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', time() + 3600);

                db()->prepare('DELETE FROM password_reset_tokens WHERE admin_id = ? AND used_at IS NULL')->execute([(int) $admin['id']]);
                db()->prepare(
                    'INSERT INTO password_reset_tokens (admin_id, token_hash, expires_at) VALUES (?, ?, ?)'
                )->execute([(int) $admin['id'], $hash, $expires]);

                $base = rtrim(app_config()['site_url'] ?? '', '/');
                if (!$base) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                }
                $resetUrl = $base . '/admin/reset-password.php?token=' . urlencode($token);

                $body = build_email_html(
                    'Reset Your Password',
                    '<p>Hello <strong>' . htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                    . '<p>We received a request to reset your Hour of Grace admin password.</p>'
                    . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 20px;background:#3F3D8B;color:#fff;text-decoration:none;border-radius:999px;">Reset password</a></p>'
                    . '<p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>'
                );

                send_site_email($email, 'Hour of Grace — Admin Password Reset', $body);
            }

            $message = 'If an account exists for that email, a reset link has been sent.';
        } catch (Throwable $e) {
            $error = 'Unable to process request. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Forgot Password — Hour of Grace Admin</title>
  <link rel="stylesheet" href="assets/admin.css" />
</head>
<body class="admin-auth">
  <div class="auth-card">
    <img src="../assets/logo.png" alt="Hour of Grace" class="auth-logo" />
    <h1>Forgot password</h1>
    <p class="auth-sub">Enter the email address linked to your admin account.</p>

    <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

    <form method="post" class="auth-form">
      <label>
        <span>Admin email</span>
        <input type="email" name="email" required autocomplete="email" placeholder="info@hourofgraceministries.org" />
      </label>
      <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
    </form>

    <p class="auth-foot"><a href="login.php">← Back to sign in</a></p>
  </div>
</body>
</html>
