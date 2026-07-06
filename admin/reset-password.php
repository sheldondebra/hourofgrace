<?php
require_once __DIR__ . '/includes/auth.php';

if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$message = null;
$valid = false;
$adminId = null;

if ($token) {
    try {
        run_app_migrations(db());
        $hash = hash('sha256', $token);
        $stmt = db()->prepare(
            'SELECT t.admin_id, t.expires_at, t.used_at FROM password_reset_tokens t WHERE t.token_hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if ($row && empty($row['used_at']) && strtotime($row['expires_at']) >= time()) {
            $valid = true;
            $adminId = (int) $row['admin_id'];
        } else {
            $error = 'This reset link is invalid or has expired.';
        }
    } catch (Throwable $e) {
        $error = 'Unable to validate reset link.';
    }
} else {
    $error = 'Missing reset token.';
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
        $valid = true;
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
        $valid = true;
    } else {
        try {
            $hash = hash('sha256', $token);
            $pdo = db();
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $adminId,
            ]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?')->execute([$hash]);
            $message = 'Your password has been updated. You can sign in now.';
            $valid = false;
        } catch (Throwable $e) {
            $error = 'Could not update password. Please try again.';
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
  <title>Reset Password — Hour of Grace Admin</title>
  <link rel="stylesheet" href="assets/admin.css" />
</head>
<body class="admin-auth">
  <div class="auth-card">
    <img src="../assets/logo.png" alt="Hour of Grace" class="auth-logo" />
    <h1>Reset password</h1>

    <?php if ($message): ?>
      <div class="alert alert-success"><?= sanitize($message) ?></div>
      <p class="auth-foot"><a href="login.php">Sign in →</a></p>
    <?php elseif ($valid): ?>
      <p class="auth-sub">Choose a new password for your admin account.</p>
      <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="token" value="<?= sanitize($token) ?>" />
        <label>
          <span>New password</span>
          <input type="password" name="password" required minlength="8" autocomplete="new-password" />
        </label>
        <label>
          <span>Confirm password</span>
          <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password" />
        </label>
        <button type="submit" class="btn btn-primary btn-block">Update password</button>
      </form>
    <?php else: ?>
      <div class="alert alert-error"><?= sanitize($error ?: 'Invalid reset link.') ?></div>
      <p class="auth-foot"><a href="forgot-password.php">Request a new link</a> · <a href="login.php">Sign in</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
