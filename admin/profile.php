<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = db();
$user = admin_user();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !validate_email($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $pdo->prepare('UPDATE admins SET email = ? WHERE id = ?')->execute([
                $email ?: null,
                (int) $_SESSION['admin_id'],
            ]);
            $message = 'Profile updated.';
            $user = null;
            admin_user();
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $password = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($password) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([
                password_hash($password, PASSWORD_DEFAULT),
                (int) $_SESSION['admin_id'],
            ]);
            $message = 'Password changed successfully.';
        }
    }
}

$user = admin_user();
$pageTitle = 'Account Settings';
$activeNav = 'profile';
require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

<div class="panel-grid">
  <section class="panel">
    <div class="panel-head"><h2>Profile</h2></div>
    <form method="post" class="stack-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="update_profile" />
      <label>
        <span>Username</span>
        <input type="text" value="<?= sanitize($user['username'] ?? '') ?>" disabled />
      </label>
      <label>
        <span>Email (for password reset)</span>
        <input type="email" name="email" value="<?= sanitize($user['email'] ?? '') ?>" placeholder="info@hourofgraceministries.org" />
      </label>
      <p class="help-text">Set this email to use “Forgot password” on the login page.</p>
      <button type="submit" class="btn btn-primary">Save profile</button>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Change password</h2></div>
    <form method="post" class="stack-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="change_password" />
      <label>
        <span>Current password</span>
        <input type="password" name="current_password" required autocomplete="current-password" />
      </label>
      <label>
        <span>New password</span>
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password" />
      </label>
      <label>
        <span>Confirm new password</span>
        <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password" />
      </label>
      <button type="submit" class="btn btn-primary">Update password</button>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
