<?php
require_once __DIR__ . '/auth.php';
require_admin();

$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? 'dashboard';
$user = admin_user();
$csrf = admin_csrf_token();

$unreadContact = count_unread('contact_submissions');
$unreadPrayer = count_unread('prayer_requests');
$unreadRegister = count_unread('registration_submissions');
$unreadSchool = count_unread('school_registrations');
$totalUnread = $unreadContact + $unreadPrayer + $unreadRegister + $unreadSchool;

// Lightweight status checks for the topbar (no heavy includes / no side effects).
$dbOnline = true;
try {
    db()->query('SELECT 1');
} catch (Throwable $e) {
    $dbOnline = false;
}
$mailLibraryReady = is_file(dirname(__DIR__, 2) . '/vendor/autoload.php');

$adminUsername = $user['username'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr($adminUsername, 0, 1));

/**
 * Inline SVG icon set for the sidebar (kept tiny and dependency-free).
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
    ];
    $inner = $paths[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/**
 * Renders a sidebar link with icon + optional badge.
 */
function admin_nav_link(string $href, string $icon, string $label, string $key, string $active, int $badge = 0): string
{
    $isActive = $active === $key ? ' active' : '';
    $badgeHtml = $badge > 0 ? '<em class="nav-badge">' . $badge . '</em>' : '';
    return '<a href="' . $href . '" class="nav-link' . $isActive . '">'
        . '<span class="nav-ico">' . admin_icon($icon) . '</span>'
        . '<span class="nav-label">' . htmlspecialchars($label, ENT_QUOTES) . '</span>'
        . $badgeHtml . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= sanitize($pageTitle) ?> — Hour of Grace Admin</title>
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
          <span class="dot <?= $dbOnline ? 'dot-ok' : 'dot-bad' ?>"></span>
          Database <?= $dbOnline ? 'connected' : 'offline' ?>
        </div>
        <div class="sidebar-status">
          <span class="dot <?= $mailLibraryReady ? 'dot-ok' : 'dot-bad' ?>"></span>
          Mail library <?= $mailLibraryReady ? 'installed' : 'missing' ?>
        </div>
        <a class="foot-link" href="/" target="_blank" rel="noopener"><?= admin_icon('site') ?><span>View Website</span></a>
        <a class="foot-link" href="logout.php"><?= admin_icon('logout') ?><span>Sign Out</span></a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div class="topbar-left">
          <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-controls="adminSidebar" aria-expanded="false">
            <span></span><span></span><span></span>
          </button>
          <div>
            <p class="topbar-crumb">Hour of Grace · Admin</p>
            <h1><?= sanitize($pageTitle) ?></h1>
          </div>
        </div>
        <div class="topbar-right">
          <span class="status-chip <?= $dbOnline ? 'is-ok' : 'is-bad' ?>" title="Database status">
            <span class="dot <?= $dbOnline ? 'dot-ok' : 'dot-bad' ?>"></span><?= $dbOnline ? 'Online' : 'DB error' ?>
          </span>
          <?php if ($totalUnread): ?>
            <a href="submissions.php?filter=all" class="topbar-bell" title="<?= $totalUnread ?> unread">
              <?= admin_icon('submissions') ?><em><?= $totalUnread ?></em>
            </a>
          <?php endif; ?>
          <div class="user-chip">
            <span class="avatar"><?= sanitize($adminInitial) ?></span>
            <span class="user-name"><?= sanitize($adminUsername) ?></span>
          </div>
        </div>
      </header>
      <main class="admin-content">
