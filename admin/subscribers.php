<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = db();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'deactivate' && $id) {
            $pdo->prepare('UPDATE newsletter_subscribers SET is_active = 0 WHERE id = ?')->execute([$id]);
            $message = 'Subscriber removed from mailing list.';
        }
        if ($action === 'reactivate' && $id) {
            $pdo->prepare('UPDATE newsletter_subscribers SET is_active = 1 WHERE id = ?')->execute([$id]);
            $message = 'Subscriber reactivated.';
        }
        if ($action === 'delete' && $id) {
            $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id = ?')->execute([$id]);
            $message = 'Subscriber deleted permanently.';
        }
        if ($action === 'add') {
            $email = trim($_POST['email'] ?? '');
            if (!validate_email($email)) {
                throw new RuntimeException('Please enter a valid email address.');
            }
            $pdo->prepare(
                'INSERT INTO newsletter_subscribers (email, is_active) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE is_active = 1'
            )->execute([$email]);
            $message = 'Subscriber added.';
        }
    } catch (Throwable $e) {
        $error = 'Action failed. The newsletter table may not exist yet — run install or sql/migrate-newsletter.sql.';
    }
}

$subscribers = [];
$tableMissing = false;

try {
    $subscribers = $pdo->query(
        'SELECT id, email, is_active, created_at FROM newsletter_subscribers ORDER BY created_at DESC'
    )->fetchAll();
} catch (Throwable $e) {
    $tableMissing = true;
    $error = $error ?: 'Newsletter table not found. Run install.php or import sql/migrate-newsletter.sql.';
}

$pageTitle = 'Mailing List';
$activeNav = 'subscribers';
require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2>Subscribers (<?= count(array_filter($subscribers, fn($s) => (int) $s['is_active'] === 1)) ?> active)</h2>
  </div>

  <form method="post" class="stack-form mb-4" style="max-width:420px;padding:0 1.25rem 1rem">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
    <input type="hidden" name="action" value="add" />
    <label>
      <span>Add subscriber manually</span>
      <div class="form-actions-row">
        <input type="email" name="email" required placeholder="you@example.com" style="flex:1" />
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </label>
  </form>

  <?php if ($tableMissing): ?>
    <p class="empty-state">The mailing list table has not been created yet.</p>
  <?php elseif (!$subscribers): ?>
    <p class="empty-state">No subscribers yet. They will appear when visitors sign up from the website footer.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Email</th>
            <th>Status</th>
            <th>Subscribed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subscribers as $row): ?>
            <tr>
              <td><?= sanitize($row['email']) ?></td>
              <td><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
              <td><?= format_datetime($row['created_at']) ?></td>
              <td class="detail-actions">
                <?php if ((int) $row['is_active'] === 1): ?>
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
                    <input type="hidden" name="action" value="deactivate" />
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                    <button type="submit" class="btn btn-soft btn-xs">Unsubscribe</button>
                  </form>
                <?php else: ?>
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
                    <input type="hidden" name="action" value="reactivate" />
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                    <button type="submit" class="btn btn-soft btn-xs">Reactivate</button>
                  </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this subscriber permanently?');">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                  <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
