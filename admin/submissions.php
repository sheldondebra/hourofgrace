<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_admin();

$pdo = db();
$filter = $_GET['filter'] ?? ($_GET['type'] ?? 'all');
$detailType = $_GET['type'] ?? null;
$allowedTypes = ['all', 'contact', 'prayer', 'register', 'school'];
if (!in_array($filter, $allowedTypes, true)) {
    $filter = 'all';
}
if ($detailType && !in_array($detailType, ['contact', 'prayer', 'register', 'school'], true)) {
    $detailType = null;
}

$viewId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $itemType = $_POST['item_type'] ?? '';
    $itemId = (int) ($_POST['item_id'] ?? 0);

    $tables = [
        'contact' => 'contact_submissions',
        'prayer' => 'prayer_requests',
        'register' => 'registration_submissions',
        'school' => 'school_registrations',
    ];

    if (isset($tables[$itemType]) && $itemId) {
        $table = $tables[$itemType];

        if ($action === 'mark_read') {
            $pdo->prepare("UPDATE {$table} SET is_read = 1 WHERE id = ?")->execute([$itemId]);
            $message = 'Marked as read.';
        }
        if ($action === 'mark_unread') {
            $pdo->prepare("UPDATE {$table} SET is_read = 0 WHERE id = ?")->execute([$itemId]);
            $message = 'Marked as unread.';
        }
        if ($action === 'delete') {
            if ($itemType === 'register') {
                $stmt = $pdo->prepare('SELECT documents FROM registration_submissions WHERE id = ?');
                $stmt->execute([$itemId]);
                $docs = json_decode($stmt->fetchColumn() ?: '[]', true) ?: [];
                foreach ($docs as $doc) {
                    $file = dirname(__DIR__) . '/' . $doc;
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$itemId]);
            $message = 'Submission deleted.';
            $viewId = null;
        }

        if ($action === 'reply') {
            $to = trim($_POST['reply_to'] ?? '');
            $subject = post_string('reply_subject', 255) ?? 'Reply from Hour of Grace';
            $bodyText = post_string('reply_message', 5000) ?? '';
            if (!validate_email($to) || $bodyText === '') {
                $message = 'Reply failed: valid email and message are required.';
            } else {
                $html = build_email_html(
                    $subject,
                    '<p>' . nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8')) . '</p>'
                );
                if (send_site_email($to, $subject, $html)) {
                    $message = 'Reply sent to ' . sanitize($to) . '.';
                } else {
                    $message = 'Reply failed. Check Email Settings and the email log.';
                }
            }
        }
    }
}

function fetch_submissions(PDO $pdo, string $type): array
{
    $items = [];

    if ($type === 'all' || $type === 'contact') {
        foreach ($pdo->query('SELECT *, "contact" AS item_type FROM contact_submissions ORDER BY created_at DESC')->fetchAll() as $row) {
            $items[] = $row;
        }
    }
    if ($type === 'all' || $type === 'prayer') {
        foreach ($pdo->query('SELECT *, "prayer" AS item_type FROM prayer_requests ORDER BY created_at DESC')->fetchAll() as $row) {
            $items[] = $row;
        }
    }
    if ($type === 'all' || $type === 'register') {
        foreach ($pdo->query('SELECT *, "register" AS item_type FROM registration_submissions ORDER BY created_at DESC')->fetchAll() as $row) {
            $items[] = $row;
        }
    }
    if ($type === 'all' || $type === 'school') {
        foreach ($pdo->query('SELECT *, "school" AS item_type FROM school_registrations ORDER BY created_at DESC')->fetchAll() as $row) {
            $items[] = $row;
        }
    }

    usort($items, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $items;
}

$listType = ($detailType && $viewId) ? $detailType : $filter;
$submissions = fetch_submissions($pdo, $listType);
$selected = null;

if ($viewId && $detailType) {
    foreach ($submissions as $item) {
        if ((int) $item['id'] === $viewId && $item['item_type'] === $detailType) {
            $selected = $item;
            if (!$item['is_read']) {
                $table = match ($item['item_type']) {
                    'contact' => 'contact_submissions',
                    'prayer' => 'prayer_requests',
                    'register' => 'registration_submissions',
                    'school' => 'school_registrations',
                    default => null,
                };
                if ($table) {
                    $pdo->prepare("UPDATE {$table} SET is_read = 1 WHERE id = ?")->execute([$viewId]);
                    $selected['is_read'] = 1;
                }
            }
            break;
        }
    }
}

$pageTitle = 'Form Submissions';
$activeNav = 'submissions';
require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>

<div class="filter-tabs">
  <a href="submissions.php?filter=all" class="<?= $filter === 'all' && !$detailType ? 'active' : '' ?>">All</a>
  <a href="submissions.php?filter=contact" class="<?= ($filter === 'contact' || $detailType === 'contact') ? 'active' : '' ?>">Contact</a>
  <a href="submissions.php?filter=prayer" class="<?= ($filter === 'prayer' || $detailType === 'prayer') ? 'active' : '' ?>">Prayer</a>
  <a href="submissions.php?filter=register" class="<?= ($filter === 'register' || $detailType === 'register') ? 'active' : '' ?>">Ministry</a>
  <a href="submissions.php?filter=school" class="<?= ($filter === 'school' || $detailType === 'school') ? 'active' : '' ?>">School</a>
</div>

<div class="submissions-layout">
  <section class="panel submissions-list">
    <div class="panel-head"><h2>Submissions (<?= count($submissions) ?>)</h2></div>

    <?php if (!$submissions): ?>
      <p class="empty-state">No submissions in this category yet.</p>
    <?php else: ?>
      <div class="submission-items">
        <?php foreach ($submissions as $item): ?>
          <?php
            $summary = match ($item['item_type']) {
                'contact' => $item['subject'] ?? '',
                'prayer' => mb_substr($item['request'] ?? '', 0, 60),
                'register' => register_job_interest_label($item['job_interest'] ?? '') ?: 'Ministry registration',
                'school' => ($item['programme'] ?? '') === 'leeds-college' ? 'Bible College — Leeds' : 'Bible School — London',
                default => '',
            };
            $isActive = $selected && (int) $selected['id'] === (int) $item['id'] && $selected['item_type'] === $item['item_type'];
          ?>
          <a
            href="submissions.php?filter=<?= urlencode($filter) ?>&type=<?= urlencode($item['item_type']) ?>&id=<?= (int) $item['id'] ?>#detail"
            class="submission-item <?= $item['is_read'] ? '' : 'is-unread' ?> <?= $isActive ? 'is-active' : '' ?>"
          >
            <div class="submission-item-top">
              <span class="type-pill type-<?= sanitize($item['item_type']) ?>"><?= ucfirst($item['item_type']) ?></span>
              <time><?= format_datetime($item['created_at']) ?></time>
            </div>
            <strong><?= sanitize($item['name']) ?></strong>
            <p><?= sanitize($summary) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel submission-detail" id="detail">
    <?php if (!$selected): ?>
      <div class="empty-state detail-empty">
        <h3>Select a submission</h3>
        <p>Choose an item from the list to view full details.</p>
      </div>
    <?php else: ?>
      <div class="panel-head">
        <div>
          <span class="type-pill type-<?= sanitize($selected['item_type']) ?>"><?= ucfirst($selected['item_type']) ?></span>
          <h2><?= sanitize($selected['name']) ?></h2>
          <p class="detail-meta"><?= format_datetime($selected['created_at']) ?> · <?= sanitize($selected['email']) ?></p>
        </div>
        <div class="detail-actions">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
            <input type="hidden" name="item_type" value="<?= sanitize($selected['item_type']) ?>" />
            <input type="hidden" name="item_id" value="<?= (int) $selected['id'] ?>" />
            <input type="hidden" name="action" value="<?= $selected['is_read'] ? 'mark_unread' : 'mark_read' ?>" />
            <button type="submit" class="btn btn-soft"><?= $selected['is_read'] ? 'Mark Unread' : 'Mark Read' ?></button>
          </form>
          <form method="post" onsubmit="return confirm('Delete this submission permanently?');">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
            <input type="hidden" name="item_type" value="<?= sanitize($selected['item_type']) ?>" />
            <input type="hidden" name="item_id" value="<?= (int) $selected['id'] ?>" />
            <input type="hidden" name="action" value="delete" />
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </div>
      </div>

      <dl class="detail-grid">
        <?php if ($selected['item_type'] === 'contact'): ?>
          <div><dt>Subject</dt><dd><?= sanitize($selected['subject']) ?></dd></div>
          <div class="detail-full"><dt>Message</dt><dd><?= nl2br(sanitize($selected['message'])) ?></dd></div>
        <?php elseif ($selected['item_type'] === 'prayer'): ?>
          <?php if (!empty($selected['phone'])): ?><div><dt>Phone</dt><dd><?= sanitize($selected['phone']) ?></dd></div><?php endif; ?>
          <div><dt>Private Request</dt><dd><?= !empty($selected['is_private']) ? 'Yes — keep confidential' : 'No' ?></dd></div>
          <div class="detail-full"><dt>Prayer Request</dt><dd><?= nl2br(sanitize($selected['request'])) ?></dd></div>
        <?php elseif ($selected['item_type'] === 'school'): ?>
          <div><dt>Phone</dt><dd><?= sanitize($selected['phone']) ?></dd></div>
          <div><dt>Programme</dt><dd><?= sanitize(programme_label($selected['programme'])) ?></dd></div>
          <div><dt>Role</dt><dd><?= sanitize(role_label($selected['role'])) ?></dd></div>
          <div><dt>Education</dt><dd><?= sanitize($selected['education']) ?></dd></div>
          <?php if (!empty($selected['church_name'])): ?><div><dt>Church</dt><dd><?= sanitize($selected['church_name']) ?></dd></div><?php endif; ?>
          <div class="detail-full"><dt>Home Address</dt><dd><?= nl2br(sanitize($selected['home_address'])) ?></dd></div>
          <?php if (!empty($selected['message'])): ?><div class="detail-full"><dt>Additional Information</dt><dd><?= nl2br(sanitize($selected['message'])) ?></dd></div><?php endif; ?>
        <?php else: ?>
          <div><dt>Home Address</dt><dd><?= nl2br(sanitize($selected['home_address'])) ?></dd></div>
          <div><dt>Date of Birth</dt><dd><?= sanitize($selected['date_of_birth'] ?: '—') ?></dd></div>
          <div><dt>Country of Birth</dt><dd><?= sanitize($selected['country_of_birth']) ?></dd></div>
          <div><dt>Country of Resident</dt><dd><?= sanitize($selected['country_of_resident']) ?></dd></div>
          <div><dt>Education</dt><dd><?= sanitize($selected['education']) ?></dd></div>
          <div><dt>Job Category</dt><dd><?= sanitize(register_job_category_label($selected['job_category'])) ?></dd></div>
          <div><dt>Area of Interest</dt><dd><?= sanitize(register_job_interest_label($selected['job_interest'])) ?></dd></div>
          <div><dt>Religion</dt><dd><?= sanitize($selected['religion']) ?></dd></div>
          <div><dt>Born Again</dt><dd><?= sanitize(yes_no_label($selected['born_again'])) ?></dd></div>
          <div><dt>Baptised</dt><dd><?= sanitize(yes_no_label($selected['baptized'])) ?></dd></div>
          <div><dt>Baptism Year</dt><dd><?= sanitize($selected['baptism_year'] ?: '—') ?></dd></div>
          <div><dt>Baptism Church</dt><dd><?= sanitize($selected['baptism_church'] ?: '—') ?></dd></div>
          <div><dt>Baptizer</dt><dd><?= sanitize($selected['baptizer'] ?: '—') ?></dd></div>
          <?php
            $docs = json_decode($selected['documents'] ?? '[]', true) ?: [];
            if ($docs):
          ?>
            <div class="detail-full">
              <dt>Uploaded Documents</dt>
              <dd class="doc-links">
                <?php foreach ($docs as $doc): ?>
                  <a href="../<?= sanitize($doc) ?>" target="_blank" rel="noopener"><?= sanitize(basename($doc)) ?></a>
                <?php endforeach; ?>
              </dd>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </dl>

      <?php if (validate_email($selected['email'] ?? '')): ?>
        <section class="reply-panel">
          <h3>Reply by email</h3>
          <form method="post" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
            <input type="hidden" name="action" value="reply" />
            <input type="hidden" name="item_type" value="<?= sanitize($selected['item_type']) ?>" />
            <input type="hidden" name="item_id" value="<?= (int) $selected['id'] ?>" />
            <input type="hidden" name="reply_to" value="<?= sanitize($selected['email']) ?>" />
            <label>
              <span>To</span>
              <input type="email" value="<?= sanitize($selected['email']) ?>" disabled />
            </label>
            <label>
              <span>Subject</span>
              <input type="text" name="reply_subject" value="Re: Your message to Hour of Grace" required />
            </label>
            <label>
              <span>Message</span>
              <textarea name="reply_message" rows="5" required placeholder="Write your reply…"></textarea>
            </label>
            <button type="submit" class="btn btn-primary">Send reply</button>
          </form>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
