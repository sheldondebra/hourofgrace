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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= sanitize($pageTitle) ?> — Hour of Grace Admin</title>
  <link rel="stylesheet" href="assets/admin.css" />
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="sidebar-brand">
        <img src="../assets/logo.png" alt="Hour of Grace" />
        <div>
          <strong>Hour of Grace</strong>
          <span>Admin Panel</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
          <span>Dashboard</span>
          <?php if ($totalUnread): ?><em class="nav-badge"><?= $totalUnread ?></em><?php endif; ?>
        </a>
        <a href="submissions.php?filter=all" class="<?= $activeNav === 'submissions' ? 'active' : '' ?>">
          <span>Submissions</span>
          <?php if ($totalUnread): ?><em class="nav-badge"><?= $totalUnread ?></em><?php endif; ?>
        </a>
        <a href="subscribers.php" class="<?= $activeNav === 'subscribers' ? 'active' : '' ?>">
          <span>Mailing List</span>
        </a>
        <a href="gallery.php" class="<?= $activeNav === 'gallery' ? 'active' : '' ?>">
          <span>Gallery</span>
        </a>
        <a href="hero.php" class="<?= $activeNav === 'hero' ? 'active' : '' ?>">
          <span>Hero Slider</span>
        </a>
        <a href="giving.php" class="<?= $activeNav === 'giving' ? 'active' : '' ?>">
          <span>Online Giving</span>
        </a>
        <a href="email.php" class="<?= $activeNav === 'email' ? 'active' : '' ?>">
          <span>Email Settings</span>
        </a>
      </nav>

      <div class="sidebar-foot">
        <p>Signed in as <strong><?= sanitize($user['username'] ?? 'Admin') ?></strong></p>
        <a href="profile.php">Account settings</a>
        <a href="/" target="_blank" rel="noopener">View Website</a>
        <a href="logout.php">Sign Out</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <h1><?= sanitize($pageTitle) ?></h1>
      </header>
      <main class="admin-content">
