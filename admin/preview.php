<?php
/**
 * Standalone dashboard PREVIEW harness.
 * No database or login required — renders the admin dashboard with sample
 * data so the UI can be reviewed locally. Delete before deploying.
 */

function admin_icon(string $name): string
{
    $paths = [
        'dashboard'   => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'submissions' => '<path d="M4 4h16v12H5.5L4 17.5V4Z"/><path d="M8 9h8M8 12h5"/>',
        'subscribers' => '<path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/>',
        'gallery'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m4 18 5-5 4 4 3-3 4 4"/>',
        'hero'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l4-4 3 3 4-5 7 7"/>',
        'giving'      => '<path d="M12 21s-7-4.35-9.5-8.5C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.3.8-1.1 2-2.3 4-2.3 3.5 0 5 3.5 3.5 7C19 16.65 12 21 12 21Z"/>',
        'email'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'prayer'      => '<path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>',
        'profile'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
        'site'        => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/>',
        'logout'      => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

function admin_nav_link(string $href, string $icon, string $label, string $key, string $active, int $badge = 0): string
{
    $isActive = $active === $key ? ' active' : '';
    $badgeHtml = $badge > 0 ? '<em class="nav-badge">' . $badge . '</em>' : '';
    return '<a href="' . $href . '" class="nav-link' . $isActive . '">'
        . '<span class="nav-ico">' . admin_icon($icon) . '</span>'
        . '<span class="nav-label">' . htmlspecialchars($label, ENT_QUOTES) . '</span>'
        . $badgeHtml . '</a>';
}

// ---- Sample data --------------------------------------------------------
$adminUsername = 'Pastor Sheldon';
$adminInitial  = 'P';
$dbOnline = true;
$mailLibraryReady = true;

$unreadContact = 3;
$unreadPrayer = 2;
$unreadRegister = 1;
$unreadSchool = 0;
$totalUnread = $unreadContact + $unreadPrayer + $unreadRegister + $unreadSchool;

$stats = [
    'contact' => 42, 'prayer' => 27, 'register' => 15, 'school' => 9,
    'gallery' => 68, 'hero' => 5, 'subscribers' => 213, 'giving' => 34, 'giving_total' => 4820.50,
];

$recent = [
    ['id' => 1, 'type' => 'contact', 'name' => 'Grace Mensah', 'email' => 'grace@example.com', 'detail' => 'Question about Sunday service times and childcare', 'is_read' => 0, 'created_at' => '2026-07-18 14:22:00'],
    ['id' => 2, 'type' => 'prayer', 'name' => 'Daniel Osei', 'email' => 'daniel@example.com', 'detail' => 'Please pray for my mother who is unwell in hospital', 'is_read' => 0, 'created_at' => '2026-07-18 11:05:00'],
    ['id' => 3, 'type' => 'register', 'name' => 'Ama Boateng', 'email' => 'ama@example.com', 'detail' => 'Singer', 'is_read' => 1, 'created_at' => '2026-07-17 19:40:00'],
    ['id' => 4, 'type' => 'school', 'name' => 'Kwame Adjei', 'email' => 'kwame@example.com', 'detail' => 'Diploma in Theology', 'is_read' => 1, 'created_at' => '2026-07-17 09:12:00'],
    ['id' => 5, 'type' => 'contact', 'name' => 'Sarah Nkrumah', 'email' => 'sarah@example.com', 'detail' => 'Requesting information about baptism classes', 'is_read' => 1, 'created_at' => '2026-07-16 16:30:00'],
];

function format_datetime($s) { return date('j M Y, g:i a', strtotime($s)); }
function sanitize($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= sanitize($pageTitle) ?> — Hour of Grace Admin (Preview)</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css" />
</head>
<body class="admin-body">
  <div class="sidebar-scrim" data-close-sidebar aria-hidden="true"></div>

  <div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-brand">
        <img src="../assets/logo.png" alt="Hour of Grace" />
        <div>
          <strong>Hour of Grace</strong>
          <span>Admin Panel</span>
        </div>
        <button type="button" class="sidebar-close" data-close-sidebar aria-label="Close menu">&times;</button>
      </div>

      <nav class="sidebar-nav">
        <p class="nav-section">Overview</p>
        <?= admin_nav_link('index.php', 'dashboard', 'Dashboard', 'dashboard', $activeNav, $totalUnread) ?>

        <p class="nav-section">Inbox</p>
        <?= admin_nav_link('submissions.php?filter=all', 'submissions', 'Submissions', 'submissions', $activeNav, $totalUnread) ?>
        <?= admin_nav_link('subscribers.php', 'subscribers', 'Mailing List', 'subscribers', $activeNav) ?>

        <p class="nav-section">Website</p>
        <?= admin_nav_link('gallery.php', 'gallery', 'Gallery', 'gallery', $activeNav) ?>
        <?= admin_nav_link('hero.php', 'hero', 'Hero Slider', 'hero', $activeNav) ?>
        <?= admin_nav_link('giving.php', 'giving', 'Online Giving', 'giving', $activeNav) ?>

        <p class="nav-section">System</p>
        <?= admin_nav_link('email.php', 'email', 'Email Settings', 'email', $activeNav) ?>
        <?= admin_nav_link('profile.php', 'profile', 'Account', 'profile', $activeNav) ?>
      </nav>

      <div class="sidebar-foot">
        <div class="sidebar-status">
          <span class="dot dot-ok"></span> Database connected
        </div>
        <div class="sidebar-status">
          <span class="dot dot-ok"></span> Mail library installed
        </div>
        <a class="foot-link" href="/" target="_blank" rel="noopener"><?= admin_icon('site') ?><span>View Website</span></a>
        <a class="foot-link" href="logout.php"><?= admin_icon('logout') ?><span>Sign Out</span></a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div class="topbar-left">
          <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open menu"><span></span><span></span><span></span></button>
          <div>
            <p class="topbar-crumb">Hour of Grace · Admin</p>
            <h1><?= sanitize($pageTitle) ?></h1>
          </div>
        </div>
        <div class="topbar-right">
          <span class="status-chip is-ok" title="Database status"><span class="dot dot-ok"></span>Online</span>
          <a href="submissions.php?filter=all" class="topbar-bell" title="<?= $totalUnread ?> unread"><?= admin_icon('submissions') ?><em><?= $totalUnread ?></em></a>
          <div class="user-chip">
            <span class="avatar"><?= sanitize($adminInitial) ?></span>
            <span class="user-name"><?= sanitize($adminUsername) ?></span>
          </div>
        </div>
      </header>
      <main class="admin-content">

<div class="dash-hero">
  <div class="dash-hero-copy">
    <h2>Welcome back, <?= sanitize($adminUsername) ?></h2>
    <p>
      <?= date('l, j F Y') ?> ·
      <?php if ($totalUnread): ?>
        <?= $totalUnread ?> new submission<?= $totalUnread === 1 ? '' : 's' ?> waiting.
      <?php else: ?>
        You're all caught up — no unread submissions.
      <?php endif; ?>
    </p>
  </div>
  <div class="dash-hero-actions">
    <a href="submissions.php?filter=all" class="btn btn-ghost">Submissions</a>
    <a href="/" target="_blank" rel="noopener" class="btn btn-ghost">Website</a>
  </div>
</div>

<section class="dash-section">
  <h2 class="dash-section-title">Inbox</h2>
  <div class="stat-grid stat-grid--inbox">
    <a href="submissions.php?filter=contact" class="stat-card accent-blue">
      <span class="stat-ico"><?= admin_icon('email') ?></span>
      <span class="stat-label">Contact Messages</span>
      <strong class="stat-value"><?= $stats['contact'] ?></strong>
      <?php if ($unreadContact): ?><em><?= $unreadContact ?> unread</em><?php endif; ?>
    </a>
    <a href="submissions.php?filter=prayer" class="stat-card accent-purple">
      <span class="stat-ico"><?= admin_icon('prayer') ?></span>
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
  </div>
</section>

<section class="dash-section">
  <h2 class="dash-section-title">Website</h2>
  <div class="stat-grid stat-grid--site">
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
</section>

<section class="panel">
  <div class="panel-head"><h2>System Status</h2></div>
  <div class="status-grid">
    <div class="status-item ok">
      <span class="s-badge">✓</span>
      <div><strong>Database</strong><span>Connected and responding</span></div>
    </div>
    <div class="status-item ok">
      <span class="s-badge">✓</span>
      <div><strong>Mail Library</strong><span>PHPMailer installed</span></div>
    </div>
    <a href="email.php" class="status-item ok" style="text-decoration:none;color:inherit;">
      <span class="s-badge">✓</span>
      <div><strong>Email &amp; SMTP</strong><span>Open to test connection</span></div>
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
  <div class="recent-feed">
    <?php foreach ($recent as $row): ?>
      <a href="#" class="recent-feed-item<?= $row['is_read'] ? '' : ' is-unread' ?>">
        <div class="recent-feed-top">
          <span class="type-pill type-<?= sanitize($row['type']) ?>"><?= ucfirst($row['type']) ?></span>
          <time><?= format_datetime($row['created_at']) ?></time>
        </div>
        <strong><?= sanitize($row['name']) ?></strong>
        <p><?= sanitize($row['detail']) ?></p>
        <span class="recent-feed-meta"><?= sanitize($row['email']) ?> · <?= $row['is_read'] ? 'Read' : 'New' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

      </main>
    </div>
  </div>
  <script src="assets/admin.js"></script>
</body>
</html>
