<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_admin();

$pdo = db();
$message = null;
$error = null;
$settings = email_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        try {
            save_email_settings([
                'admin_email' => post_string('admin_email', 180) ?? $settings['admin_email'],
                'smtp_host' => post_string('smtp_host', 180) ?? $settings['smtp_host'],
                'smtp_port' => post_string('smtp_port', 10) ?? $settings['smtp_port'],
                'smtp_user' => post_string('smtp_user', 180) ?? $settings['smtp_user'],
                'smtp_pass' => $_POST['smtp_pass'] ?? '',
                'smtp_from_email' => post_string('smtp_from_email', 180) ?? $settings['smtp_from_email'],
                'smtp_from_name' => post_string('smtp_from_name', 180) ?? $settings['smtp_from_name'],
                'notify_admin' => isset($_POST['notify_admin']) ? '1' : '0',
                'notify_user' => isset($_POST['notify_user']) ? '1' : '0',
            ]);
            $settings = email_settings();
            $message = 'Email settings saved successfully.';
        } catch (Throwable $e) {
            $error = 'Could not save settings: ' . $e->getMessage();
        }
    }

    if ($action === 'test') {
        $testTo = post_string('test_email', 180) ?: $settings['admin_email'];
        if (!validate_email($testTo)) {
            $error = 'Please enter a valid test email address.';
        } else {
            $body = build_email_html(
                'SMTP Test Successful',
                '<p>This is a test email from your Hour of Grace website admin panel.</p>'
                . '<p>If you received this message, SMTP is configured correctly.</p>'
            );
            if (send_site_email($testTo, 'Hour of Grace — SMTP Test Email', $body)) {
                $message = 'Test email sent to ' . sanitize($testTo) . '.';
            } else {
                $error = 'Test email failed. Check the email log below for details.';
            }
        }
    }

    if ($action === 'test_connection') {
        $result = test_smtp_connection();
        if ($result['ok']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$emailLog = [];
try {
    $emailLog = $pdo->query(
        'SELECT recipient, subject, status, error_message, created_at FROM email_log ORDER BY created_at DESC LIMIT 20'
    )->fetchAll();
} catch (Throwable $e) {
    $emailLog = [];
}

$pageTitle = 'Email Settings';
$activeNav = 'email';
require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

<div class="panel-grid">
  <section class="panel">
    <div class="panel-head"><h2>SMTP Configuration</h2></div>
    <form method="post" class="stack-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="save" />

      <label>
        <span>Admin Notification Email</span>
        <input type="email" name="admin_email" value="<?= sanitize($settings['admin_email']) ?>" required placeholder="info@hourofgraceministries.org" />
      </label>

      <div class="form-grid-2">
        <label>
          <span>SMTP Host</span>
          <input type="text" name="smtp_host" value="<?= sanitize($settings['smtp_host']) ?>" required placeholder="mail.hourofgraceministries.org" />
        </label>
        <label>
          <span>SMTP Port</span>
          <input type="number" name="smtp_port" value="<?= sanitize($settings['smtp_port']) ?>" required placeholder="465" />
        </label>
      </div>

      <label>
        <span>SMTP Username</span>
        <input type="text" name="smtp_user" value="<?= sanitize($settings['smtp_user']) ?>" required placeholder="smpt@hourofgraceministries.org" />
      </label>

      <label>
        <span>SMTP Password</span>
        <input type="password" name="smtp_pass" placeholder="Leave blank to keep current password" autocomplete="new-password" />
      </label>

      <div class="form-grid-2">
        <label>
          <span>From Email</span>
          <input type="email" name="smtp_from_email" value="<?= sanitize($settings['smtp_from_email']) ?>" required placeholder="smpt@hourofgraceministries.org" />
        </label>
        <label>
          <span>From Name</span>
          <input type="text" name="smtp_from_name" value="<?= sanitize($settings['smtp_from_name']) ?>" required placeholder="Hour of Grace Ministries" />
        </label>
      </div>

      <div class="checkbox-stack">
        <label class="checkbox-line">
          <input type="checkbox" name="notify_admin" value="1" <?= $settings['notify_admin'] !== '0' ? 'checked' : '' ?> />
          <span>Send admin notification when a form is submitted</span>
        </label>
        <label class="checkbox-line">
          <input type="checkbox" name="notify_user" value="1" <?= $settings['notify_user'] !== '0' ? 'checked' : '' ?> />
          <span>Send thank-you confirmation email to the person who submitted</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary">Save Email Settings</button>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Send Test Email</h2></div>
    <form method="post" class="stack-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="test" />
      <label>
        <span>Send test to</span>
        <input type="email" name="test_email" value="<?= sanitize($settings['admin_email']) ?>" required placeholder="you@example.com" />
      </label>
      <p class="help-text">Uses SSL on port 465 via mail.hourofgraceministries.org.</p>
      <div class="form-actions-row">
        <button type="submit" class="btn btn-soft">Send test email</button>
      </div>
    </form>

    <form method="post" class="stack-form mt-4">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="test_connection" />
      <p class="help-text">Verify SMTP host, port, and credentials without sending mail.</p>
      <button type="submit" class="btn btn-soft">Test SMTP connection</button>
    </form>

    <div class="info-box">
      <strong>Form emails enabled for:</strong>
      <ul>
        <li>Contact messages</li>
        <li>Prayer requests</li>
        <li>Ministry registrations</li>
        <li>School registrations</li>
        <li>Newsletter sign-ups</li>
      </ul>
    </div>
  </section>
</div>

<section class="panel">
  <div class="panel-head"><h2>Recent Email Log</h2></div>
  <?php if (!$emailLog): ?>
    <p class="empty-state">No emails logged yet. Submit a form or send a test email.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Recipient</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Date</th>
            <th>Error</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($emailLog as $row): ?>
            <tr>
              <td><?= sanitize($row['recipient']) ?></td>
              <td class="cell-truncate"><?= sanitize($row['subject']) ?></td>
              <td><span class="status-pill status-<?= sanitize($row['status']) ?>"><?= ucfirst(sanitize($row['status'])) ?></span></td>
              <td><?= format_datetime($row['created_at']) ?></td>
              <td class="cell-truncate"><?= sanitize($row['error_message'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
