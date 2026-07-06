<?php

function default_gallery_images(): array
{
    $path = dirname(__DIR__) . '/data/gallery.json';
    if (!is_file($path)) {
        return [];
    }

    $images = json_decode(file_get_contents($path), true);
    if (!is_array($images)) {
        return [];
    }

    return array_values(array_map('localize_media_path', $images));
}

function seed_gallery_if_empty(PDO $pdo): int
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM gallery_images')->fetchColumn();
    if ($count > 0) {
        return 0;
    }

    $images = default_gallery_images();
    $insert = $pdo->prepare('INSERT INTO gallery_images (image_path, sort_order) VALUES (?, ?)');
    foreach ($images as $i => $url) {
        $insert->execute([$url, $i + 1]);
    }

    return count($images);
}

function merge_default_gallery_images(PDO $pdo): int
{
    $existing = $pdo->query('SELECT image_path FROM gallery_images')->fetchAll(PDO::FETCH_COLUMN);
    $known = array_flip(array_map('strtolower', $existing));
    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM gallery_images')->fetchColumn();
    $insert = $pdo->prepare('INSERT INTO gallery_images (image_path, sort_order) VALUES (?, ?)');
    $added = 0;

    foreach (default_gallery_images() as $url) {
        if (isset($known[strtolower($url)])) {
            continue;
        }
        $sort++;
        $insert->execute([$url, $sort]);
        $known[strtolower($url)] = true;
        $added++;
    }

    return $added;
}
