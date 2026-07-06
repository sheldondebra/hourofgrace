<?php
/**
 * One-time gallery recovery for cPanel. Visit once, then delete.
 * Searches the server for old WordPress uploads and copies images into assets/gallery/.
 */
require_once __DIR__ . '/includes/requirements.php';
hog_require_extensions();
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$targetDir = $root . '/assets/gallery';
$galleryJson = $root . '/data/gallery.json';
$user = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? (posix_getpwuid(posix_geteuid())['name'] ?? '')
    : '';

ensure_upload_dir($targetDir);

$wanted = [];
if (is_file($galleryJson)) {
    $raw = json_decode((string) file_get_contents($galleryJson), true);
    if (is_array($raw)) {
        foreach ($raw as $entry) {
            $wanted[basename((string) $entry)] = true;
        }
    }
}

$searchRoots = array_values(array_unique(array_filter([
    $root . '/wp-content/uploads',
    $root . '/../wp-content/uploads',
    $root . '/../../wp-content/uploads',
    $root . '/old/wp-content/uploads',
    $root . '/backup/wp-content/uploads',
    $root . '/public_html_old/wp-content/uploads',
    $user ? "/home1/{$user}/wp-content/uploads" : null,
    $user ? "/home1/{$user}/public_html/wp-content/uploads" : null,
    $user ? "/home1/{$user}/backup" : null,
    $user ? "/home1/{$user}/backups" : null,
    $user ? "/home1/{$user}/old_site" : null,
    $user ? "/home1/{$user}/public_html.backup" : null,
])));

$copied = 0;
$foundByScan = 0;
$indexed = [];

foreach ($searchRoots as $searchRoot) {
    if (!is_dir($searchRoot)) {
        continue;
    }

    echo "Scanning {$searchRoot}\n";
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($searchRoot, FilesystemIterator::SKIP_DOTS)
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
        if ($wanted && !isset($wanted[$basename])) {
            continue;
        }

        if (!isset($indexed[$basename])) {
            $indexed[$basename] = $file->getPathname();
            $foundByScan++;
        }
    }
}

foreach ($indexed as $basename => $source) {
    $dest = $targetDir . '/' . $basename;
    if (is_file($dest) && filesize($dest) > 0) {
        continue;
    }

    if (@copy($source, $dest)) {
        $copied++;
        echo "Copied {$basename}\n";
    }
}

echo "\nSearch roots checked: " . count($searchRoots) . "\n";
echo "Matching files found on server: {$foundByScan}\n";
echo "Copied into assets/gallery/: {$copied}\n\n";

$paths = [];
$missing = [];

if (is_file($galleryJson)) {
    $raw = json_decode((string) file_get_contents($galleryJson), true);
    if (is_array($raw)) {
        foreach ($raw as $entry) {
            $local = localize_media_path((string) $entry);
            $paths[] = $local;
            if (!is_file($root . '/' . $local)) {
                $missing[] = $local;
            }
        }

        file_put_contents(
            $galleryJson,
            json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
        echo 'Updated data/gallery.json (' . count($paths) . " entries)\n";
    }
}

if (is_file(config_path())) {
    try {
        require_once __DIR__ . '/includes/db.php';
        $pdo = db();

        foreach (['gallery_images', 'hero_slides'] as $table) {
            $rows = $pdo->query("SELECT id, image_path FROM {$table}")->fetchAll();
            $update = $pdo->prepare("UPDATE {$table} SET image_path = ? WHERE id = ?");
            $updated = 0;

            foreach ($rows as $row) {
                $local = localize_media_path((string) $row['image_path']);
                if ($local !== $row['image_path']) {
                    $update->execute([$local, $row['id']]);
                    $updated++;
                }
            }

            echo "Updated {$table}: {$updated} rows\n";
        }
    } catch (Throwable $e) {
        echo 'Database update skipped: ' . $e->getMessage() . "\n";
    }
}

if ($missing) {
    echo "\nStill missing " . count($missing) . " files in assets/gallery/.\n";
    echo "Upload them via cPanel File Manager or restore wp-content/uploads from backup,\n";
    echo "then run this script again.\n\n";
    foreach (array_slice($missing, 0, 8) as $path) {
        echo " - {$path}\n";
    }
    if (count($missing) > 8) {
        echo ' - ... and ' . (count($missing) - 8) . " more\n";
    }
} else {
    echo "\nAll gallery files are present.\n";
}

echo "\nDelete sync-gallery.php when finished.\n";
