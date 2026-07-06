<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/migrations.php';
require_once __DIR__ . '/../includes/security.php';

send_security_headers();
set_api_cors_headers('GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = db();
    run_app_migrations($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM hero_slides')->fetchColumn();
    if ($count === 0) {
        seed_hero_from_gallery($pdo);
    }

    $rows = $pdo->query(
        'SELECT image_path, caption FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();

    $images = array_map(static function (array $row): array {
        $path = $row['image_path'];
        $url = str_starts_with($path, 'http') ? $path : gallery_public_url($path);
        return [
            'url' => $url,
            'caption' => $row['caption'] ?? '',
        ];
    }, $rows);

    json_response(['success' => true, 'images' => $images]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Hero slides unavailable.', 'images' => []], 500);
}
