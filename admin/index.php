<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$dbError = null;
$stats = [
    'contact' => 0,
    'prayer' => 0,
    'register' => 0,
    'school' => 0,
    'gallery' => 0,
    'subscribers' => 0,
];
$recent = [];

try {
    $pdo = db();

    $stats = [
        'contact' => (int) $pdo->query('SELECT COUNT(*) FROM contact_submissions')->fetchColumn(),
        'prayer' => (int) $pdo->query('SELECT COUNT(*) FROM prayer_requests')->fetchColumn(),
        'register' => (int) $pdo->query('SELECT COUNT(*) FROM registration_submissions')->fetchColumn(),
        'school' => (int) $pdo->query('SELECT COUNT(*) FROM school_registrations')->fetchColumn(),
        'gallery' => (int) $pdo->query('SELECT COUNT(*) FROM gallery_images WHERE is_active = 1')->fetchColumn(),
        'hero' => 0,
        'subscribers' => 0,
        'giving' => 0,
        'giving_total' => 0,
    ];

    try {
        $stats['hero'] = (int) $pdo->query('SELECT COUNT(*) FROM hero_slides WHERE is_active = 1')->fetchColumn();
    } catch (Throwable $e) {
        $stats['hero'] = 0;
    }

    try {
        $stats['subscribers'] = (int) $pdo->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1')->fetchColumn();
    } catch (Throwable $e) {
        $stats['subscribers'] = 0;
    }

    try {
        $stats['giving'] = (int) $pdo->query("SELECT COUNT(*) FROM giving_donations WHERE payment_status = 'completed'")->fetchColumn();
        $stats['giving_total'] = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM giving_donations WHERE payment_status = 'completed'")->fetchColumn();
    } catch (Throwable $e) {
        $stats['giving'] = 0;
        $stats['giving_total'] = 0;
    }

    $recent = $pdo->query(
        "(SELECT id, 'contact' AS type, name, email, subject AS detail, is_read, created_at FROM contact_submissions)
         UNION ALL
         (SELECT id, 'prayer' AS type, name, email, LEFT(request, 80) AS detail, is_read, created_at FROM prayer_requests)
         UNION ALL
         (SELECT id, 'register' AS type, name, email, job_interest AS detail, is_read, created_at FROM registration_submissions)
         UNION ALL
         (SELECT id, 'school' AS type, name, email, programme AS detail, is_read, created_at FROM school_registrations)
         ORDER BY created_at DESC LIMIT 8"
    )->fetchAll();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<?php if ($dbError): ?>
  <div class="alert alert-error">
    Database error: <?= sanitize($dbError) ?>.
    <?php if (!is_site_installed()): ?>
      Please run <a href="../install.php">install.php</a> first.
    <?php endif; ?>
  </div>
<?php else: ?>

<div class="dash-hero">
  <div>
    <h2>Welcome back, <?= sanitize($adminUsername) ?> 👋</h2>
    <p>
      <?= date('l, j F Y') ?> ·
      <?php if ($totalUnread): ?>
        <?= $totalUnread ?> new submission<?= $totalUnread === 1 ? '' : 's' ?> waiting for you.
      <?php else: ?>
        You're all caught up — no unread submissions.
      <?php endif; ?>
    </p>
  </div>
  <div class="dash-hero-actions">
    <a href="submissions.php?filter=all" class="btn btn-ghost">View Submissions</a>
    <a href="/" target="_blank" rel="noopener" class="btn btn-ghost">Visit Website</a>
  </div>
</div>

<div class="stat-grid">
  <a href="submissions.php?filter=contact" class="stat-card accent-blue">
    <span class="stat-ico"><?= admin_icon('email') ?></span>
    <span class="stat-label">Contact Messages</span>
    <strong class="stat-value"><?= $stats['contact'] ?></strong>
    <?php if ($unreadContact): ?><em><?= $unreadContact ?> unread</em><?php endif; ?>
  </a>
  <a href="submissions.php?filter=prayer" class="stat-card accent-purple">
    <span class="stat-ico"><?= admin_icon('giving') ?></span>
    <span class="stat-label">Prayer Requests</span>
    <strong class="stat-value"><?= $stats['prayer'] ?></strong>
    <?php if ($unreadPrayer): ?><em><?= $unreadPrayer ?> unread</em><?php endif; ?>
  </a>
  <a href="submissions.php?filter=register" class="stat-card accent-green">
    <span class="stat-ico"><?= admin_icon('profile') ?></span>
    <span class="stat-label">Ministry Registrations</span>
    <strong class="stat-value"><?= $stats['register'] ?></strong>
    <?php if ($unreadRegister): ?><em><?= $unreadRegister ?> unread</em><?php endif; ?>
  </a>
  <a href="submissions.php?filter=school" class="stat-card accent-amber">
    <span class="stat-ico"><?= admin_icon('submissions') ?></span>
    <span class="stat-label">School Registrations</span>
    <strong class="stat-value"><?= $stats['school'] ?></strong>
    <?php if ($unreadSchool): ?><em><?= $unreadSchool ?> unread</em><?php endif; ?>
  </a>
  <a href="gallery.php" class="stat-card accent-sky">
    <span class="stat-ico"><?= admin_icon('gallery') ?></span>
    <span class="stat-label">Gallery Photos</span>
    <strong class="stat-value"><?= $stats['gallery'] ?></strong>
  </a>
  <a href="hero.php" class="stat-card accent-purple">
    <span class="stat-ico"><?= admin_icon('hero') ?></span>
    <span class="stat-label">Hero Slides</span>
    <strong class="stat-value"><?= $stats['hero'] ?></strong>
  </a>
  <a href="subscribers.php" class="stat-card accent-blue">
    <span class="stat-ico"><?= admin_icon('subscribers') ?></span>
    <span class="stat-label">Mailing List</span>
    <strong class="stat-value"><?= $stats['subscribers'] ?></strong>
  </a>
  <a href="giving.php" class="stat-card accent-rose">
    <span class="stat-ico"><?= admin_icon('giving') ?></span>
    <span class="stat-label">Online Gifts</span>
    <strong class="stat-value"><?= $stats['giving'] ?></strong>
    <?php if ($stats['giving_total'] > 0): ?><em>£<?= number_format($stats['giving_total'], 2) ?> total</em><?php endif; ?>
  </a>
</div>

<section class="panel">
  <div class="panel-head"><h2>System Status</h2></div>
  <div class="status-grid">
    <div class="status-item <?= $dbOnline ? 'ok' : 'bad' ?>">
      <span class="s-badge"><?= $dbOnline ? '✓' : '!' ?></span>
      <div>
        <strong>Database</strong>
        <span><?= $dbOnline ? 'Connected and responding' : 'Not reachable — check config' ?></span>
      </div>
    </div>
    <div class="status-item <?= $mailLibraryReady ? 'ok' : 'bad' ?>">
      <span class="s-badge"><?= $mailLibraryReady ? '✓' : '!' ?></span>
      <div>
        <strong>Mail Library</strong>
        <span><?= $mailLibraryReady ? 'PHPMailer installed' : 'Missing — upload /vendor folder' ?></span>
      </div>
    </div>
    <a href="email.php" class="status-item <?= $mailLibraryReady ? 'ok' : 'bad' ?>" style="text-decoration:none;color:inherit;">
      <span class="s-badge"><?= $mailLibraryReady ? '✓' : '!' ?></span>
      <div>
        <strong>Email &amp; SMTP</strong>
        <span><?= $mailLibraryReady ? 'Open to test connection' : 'Configure once library is installed' ?></span>
      </div>
    </a>
  </div>
</section>

<section class="panel">
  <div class="panel-head"><h2>Quick Actions</h2></div>
  <div class="quick-actions">
    <a href="gallery.php" class="quick-action"><span class="qa-ico"><?= admin_icon('gallery') ?></span>Upload Photos</a>
    <a href="hero.php" class="quick-action"><span class="qa-ico"><?= admin_icon('hero') ?></span>Edit Hero Slider</a>
    <a href="subscribers.php" class="quick-action"><span class="qa-ico"><?= admin_icon('subscribers') ?></span>Mailing List</a>
    <a href="email.php" class="quick-action"><span class="qa-ico"><?= admin_icon('email') ?></span>Email Settings</a>
    <a href="giving.php" class="quick-action"><span class="qa-ico"><?= admin_icon('giving') ?></span>Giving Setup</a>
    <a href="profile.php" class="quick-action"><span class="qa-ico"><?= admin_icon('profile') ?></span>Account</a>
  </div>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Recent Submissions</h2>
    <a href="submissions.php?filter=all" class="btn btn-soft">View All</a>
  </div>

  <?php if (!$recent): ?>
    <p class="empty-state">No submissions yet. They will appear here when visitors submit forms.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Name</th>
            <th>Email</th>
            <th>Summary</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $row): ?>
            <tr class="<?= $row['is_read'] ? '' : 'row-unread' ?>">
              <td><span class="type-pill type-<?= sanitize($row['type']) ?>"><?= ucfirst($row['type']) ?></span></td>
              <td><?= sanitize($row['name']) ?></td>
              <td><?= sanitize($row['email']) ?></td>
              <td class="cell-truncate"><?= sanitize($row['detail'] ?? '') ?></td>
              <td><?= $row['is_read'] ? 'Read' : 'New' ?></td>
              <td><?= format_datetime($row['created_at']) ?></td>
              <td><a href="submissions.php?filter=all&type=<?= urlencode($row['type']) ?>&id=<?= (int) $row['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
