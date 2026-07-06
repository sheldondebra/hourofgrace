<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/gallery-data.php';
require_admin();

$pdo = db();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_url') {
            $url = trim($_POST['image_url'] ?? '');
            $caption = post_string('caption', 255);
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Please enter a valid image URL.');
            }
            $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_images')->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO gallery_images (image_path, caption, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$url, $caption, $sort]);
            $message = 'Image added successfully.';
        }

        if ($action === 'upload') {
            if (empty($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please choose an image file to upload.');
            }

            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                throw new RuntimeException('Only JPG, PNG, WEBP, and GIF files are allowed.');
            }

            ensure_upload_dir(dirname(__DIR__) . '/uploads/gallery');
            $filename = uniqid('gallery_', true) . '.' . $ext;
            $dest = dirname(__DIR__) . '/uploads/gallery/' . $filename;

            if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
                throw new RuntimeException('Upload failed. Check folder permissions.');
            }

            $caption = post_string('caption', 255);
            $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_images')->fetchColumn();
            $path = 'uploads/gallery/' . $filename;
            $stmt = $pdo->prepare('INSERT INTO gallery_images (image_path, caption, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$path, $caption, $sort]);
            $message = 'Image uploaded successfully.';
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE gallery_images SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Image visibility updated.';
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT image_path FROM gallery_images WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if ($row && !str_starts_with($row['image_path'], 'http')) {
                $file = dirname(__DIR__) . '/' . $row['image_path'];
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $del = $pdo->prepare('DELETE FROM gallery_images WHERE id = ?');
            $del->execute([$id]);
            $message = 'Image removed.';
        }

        if ($action === 'import_defaults') {
            $added = merge_default_gallery_images($pdo);
            $message = $added
                ? "Imported {$added} ministry photo(s) from the default gallery set."
                : 'All default ministry photos are already in the gallery.';
        }

        if ($action === 'update_caption') {
            $id = (int) ($_POST['id'] ?? 0);
            $caption = post_string('caption', 255) ?? '';
            $pdo->prepare('UPDATE gallery_images SET caption = ? WHERE id = ?')->execute([$caption, $id]);
            $message = 'Caption updated.';
        }

        if ($action === 'move') {
            $id = (int) ($_POST['id'] ?? 0);
            $direction = $_POST['direction'] ?? '';
            $current = $pdo->prepare('SELECT id, sort_order FROM gallery_images WHERE id = ?');
            $current->execute([$id]);
            $item = $current->fetch();
            if (!$item) {
                throw new RuntimeException('Image not found.');
            }

            if ($direction === 'up') {
                $neighbor = $pdo->prepare('SELECT id, sort_order FROM gallery_images WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1');
                $neighbor->execute([$item['sort_order']]);
            } else {
                $neighbor = $pdo->prepare('SELECT id, sort_order FROM gallery_images WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1');
                $neighbor->execute([$item['sort_order']]);
            }

            $swap = $neighbor->fetch();
            if ($swap) {
                $pdo->prepare('UPDATE gallery_images SET sort_order = ? WHERE id = ?')->execute([$swap['sort_order'], $item['id']]);
                $pdo->prepare('UPDATE gallery_images SET sort_order = ? WHERE id = ?')->execute([$item['sort_order'], $swap['id']]);
                $message = 'Order updated.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$images = $pdo->query('SELECT * FROM gallery_images ORDER BY sort_order ASC, id ASC')->fetchAll();

$pageTitle = 'Gallery Manager';
$activeNav = 'gallery';
require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

<div class="panel-grid">
  <section class="panel">
    <div class="panel-head"><h2>Upload Photo</h2></div>
    <form method="post" enctype="multipart/form-data" class="stack-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="upload" />
      <label>
        <span>Image File</span>
        <input type="file" name="image_file" accept="image/*" required />
      </label>
      <label>
        <span>Caption (optional)</span>
        <input type="text" name="caption" maxlength="255" placeholder="Optional photo caption" />
      </label>
      <button type="submit" class="btn btn-primary">Upload Image</button>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Add by URL</h2></div>
    <form method="post" class="stack-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="add_url" />
      <label>
        <span>Image URL</span>
        <input type="url" name="image_url" placeholder="https://..." required />
      </label>
      <label>
        <span>Caption (optional)</span>
        <input type="text" name="caption" maxlength="255" placeholder="Optional photo caption" />
      </label>
      <button type="submit" class="btn btn-soft">Add URL</button>
    </form>
  </section>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>Gallery Images (<?= count($images) ?>)</h2>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="import_defaults" />
      <button type="submit" class="btn btn-soft">Import ministry photos</button>
    </form>
  </div>

  <?php if (!$images): ?>
    <p class="empty-state">No gallery images yet. Upload your first photo above.</p>
  <?php else: ?>
    <div class="gallery-admin-grid">
      <?php foreach ($images as $img): ?>
        <?php
          $src = str_starts_with($img['image_path'], 'http')
            ? $img['image_path']
            : '../' . $img['image_path'];
        ?>
        <article class="gallery-admin-card <?= $img['is_active'] ? '' : 'is-hidden' ?>">
          <img src="<?= sanitize($src) ?>" alt="" loading="lazy" />
          <div class="gallery-admin-meta">
            <form method="post" class="caption-edit-form">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
              <input type="hidden" name="action" value="update_caption" />
              <input type="hidden" name="id" value="<?= (int) $img['id'] ?>" />
              <input type="text" name="caption" value="<?= sanitize($img['caption'] ?? '') ?>" placeholder="Caption" maxlength="255" />
              <button type="submit" class="btn btn-xs">Save</button>
            </form>
            <span>#<?= (int) $img['sort_order'] ?> · <?= $img['is_active'] ? 'Visible' : 'Hidden' ?></span>
          </div>
          <div class="gallery-admin-actions">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
              <input type="hidden" name="action" value="move" />
              <input type="hidden" name="id" value="<?= (int) $img['id'] ?>" />
              <input type="hidden" name="direction" value="up" />
              <button type="submit" class="btn btn-xs">↑</button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
              <input type="hidden" name="action" value="move" />
              <input type="hidden" name="id" value="<?= (int) $img['id'] ?>" />
              <input type="hidden" name="direction" value="down" />
              <button type="submit" class="btn btn-xs">↓</button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
              <input type="hidden" name="action" value="toggle" />
              <input type="hidden" name="id" value="<?= (int) $img['id'] ?>" />
              <button type="submit" class="btn btn-xs"><?= $img['is_active'] ? 'Hide' : 'Show' ?></button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this image?');">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="id" value="<?= (int) $img['id'] ?>" />
              <button type="submit" class="btn btn-xs btn-danger">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
