<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gallery-data.php';
require_once __DIR__ . '/../includes/security.php';

send_security_headers();
set_api_cors_headers('GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = db();
    seed_gallery_if_empty($pdo);

    $stmt = $pdo->query(
        'SELECT id, image_path, caption FROM gallery_images WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    );
    $images = [];

    while ($row = $stmt->fetch()) {
        $images[] = [
            'id' => (int) $row['id'],
            'url' => gallery_public_url($row['image_path']),
            'caption' => $row['caption'],
        ];
    }

    if (!$images) {
        foreach (default_gallery_images() as $i => $url) {
            $images[] = [
                'id' => $i + 1,
                'url' => $url,
                'caption' => null,
            ];
        }
    }

    json_response(['success' => true, 'images' => $images]);
} catch (Throwable $e) {
    $fallback = [];
    foreach (default_gallery_images() as $i => $url) {
        $fallback[] = ['id' => $i + 1, 'url' => $url, 'caption' => null];
    }
    json_response(['success' => true, 'images' => $fallback, 'source' => 'fallback']);
}
