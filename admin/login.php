<?php
require_once __DIR__ . '/includes/auth.php';

if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}

if (!is_file(config_path())) {
    header('Location: ../install.php');
    exit;
}

$error = null;
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_login_rate_limit();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            clear_admin_login_rate_limit();
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_last_activity'] = time();
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid username or password.';
    } catch (Throwable $e) {
        $error = 'Unable to connect to the database. Please run install.php first.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Admin Login — Hour of Grace</title>
  <link rel="stylesheet" href="assets/admin.css" />
</head>
<body class="admin-auth">
  <div class="auth-card">
    <img src="../assets/logo.png" alt="Hour of Grace" class="auth-logo" />
    <h1>Admin Dashboard</h1>
    <p class="auth-sub">Sign in to manage gallery, submissions, and email settings.</p>

    <?php if ($timeout): ?>
      <div class="alert alert-error">Your session expired. Please sign in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="post" class="auth-form">
      <label>
        <span>Username</span>
        <input type="text" name="username" required autocomplete="username" placeholder="Enter username" />
      </label>
      <label>
        <span>Password</span>
        <input type="password" name="password" required autocomplete="current-password" placeholder="Enter password" />
      </label>
      <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>
    <p class="auth-foot"><a href="forgot-password.php">Forgot password?</a></p>
  </div>
</body>
</html>
