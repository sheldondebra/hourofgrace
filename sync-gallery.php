<?php

/**
 * One-time gallery media sync. Upload to server, visit once, then delete.
 * Copies images from the old WordPress uploads folder into uploads/gallery/.
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$targetDir = $root . '/uploads/gallery';
$wpUploads = $root . '/wp-content/uploads';
$galleryJson = $root . '/data/gallery.json';

ensure_upload_dir($targetDir);

$copied = 0;
$skipped = 0;
$missing = [];

if (is_dir($wpUploads)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($wpUploads, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            continue;
        }

        $basename = $file->getFilename();
        $dest = $targetDir . '/' . $basename;
        if (is_file($dest)) {
            $skipped++;
            continue;
        }

        if (copy($file->getPathname(), $dest)) {
            $copied++;
        }
    }

    echo "WordPress uploads folder found.\n";
    echo "Copied: {$copied}\n";
    echo "Already present: {$skipped}\n\n";
} else {
    echo "No wp-content/uploads folder found at {$wpUploads}\n";
    echo "Upload your old WordPress media there first, or copy images into uploads/gallery/ manually.\n\n";
}

$paths = [];
if (is_file($galleryJson)) {
    $raw = json_decode((string) file_get_contents($galleryJson), true);
    if (is_array($raw)) {
        foreach ($raw as $entry) {
            $local = localize_media_path((string) $entry);
            $paths[] = $local;
            $file = $root . '/' . $local;
            if (!is_file($file)) {
                $missing[] = $local;
            }
        }

        file_put_contents(
            $galleryJson,
            json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
        echo "Updated data/gallery.json with " . count($paths) . " local paths.\n";
    }
}

if (is_file(config_path())) {
    try {
        require_once __DIR__ . '/includes/db.php';
        $pdo = db();

        $rows = $pdo->query('SELECT id, image_path FROM gallery_images')->fetchAll();
        $update = $pdo->prepare('UPDATE gallery_images SET image_path = ? WHERE id = ?');
        $updated = 0;

        foreach ($rows as $row) {
            $local = localize_media_path((string) $row['image_path']);
            if ($local !== $row['image_path']) {
                $update->execute([$local, $row['id']]);
                $updated++;
            }
        }

        $heroRows = $pdo->query('SELECT id, image_path FROM hero_slides')->fetchAll();
        $heroUpdate = $pdo->prepare('UPDATE hero_slides SET image_path = ? WHERE id = ?');
        $heroUpdated = 0;

        foreach ($heroRows as $row) {
            $local = localize_media_path((string) $row['image_path']);
            if ($local !== $row['image_path']) {
                $heroUpdate->execute([$local, $row['id']]);
                $heroUpdated++;
            }
        }

        echo "Database gallery paths updated: {$updated}\n";
        echo "Database hero paths updated: {$heroUpdated}\n";
    } catch (Throwable $e) {
        echo 'Database update skipped: ' . $e->getMessage() . "\n";
    }
}

if ($missing) {
    echo "\nMissing files (" . count($missing) . "):\n";
    foreach (array_slice($missing, 0, 10) as $path) {
        echo " - {$path}\n";
    }
    if (count($missing) > 10) {
        echo ' - ... and ' . (count($missing) - 10) . " more\n";
    }
} else {
    echo "\nAll gallery files are present in uploads/gallery/.\n";
}

echo "\nDelete sync-gallery.php after running.\n";
