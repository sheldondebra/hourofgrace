<?php

function run_app_migrations(?PDO $pdo = null): void
{
    static $completed = false;
    if ($completed) {
        return;
    }

    try {
        $pdo = $pdo ?? db();
    } catch (Throwable $e) {
        return;
    }

    $migrationFile = dirname(__DIR__) . '/sql/migrate-admin-v2.sql';
    if (is_file($migrationFile)) {
        run_sql_schema($pdo, $migrationFile);
    }

    ensure_admin_email_column($pdo);
    ensure_giving_donations_table($pdo);
    seed_giving_settings($pdo);

    $completed = true;
}

function ensure_admin_email_column(PDO $pdo): void
{
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM admins LIKE 'email'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE admins ADD COLUMN email VARCHAR(180) DEFAULT NULL AFTER username');
        }
    } catch (Throwable $e) {
        // Ignore if table missing during install.
    }
}

function ensure_giving_donations_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS giving_donations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(180) DEFAULT NULL,
            gift_type VARCHAR(40) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'gbp',
            payment_provider ENUM('stripe', 'paypal') NOT NULL,
            payment_status ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
            external_id VARCHAR(255) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_giving_status (payment_status),
            INDEX idx_giving_created (created_at),
            INDEX idx_giving_external (external_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function seed_giving_settings(PDO $pdo): void
{
    $defaults = [
        'giving_enabled' => '1',
        'giving_currency' => 'gbp',
        'giving_intro' => 'Support the ministry through tithe, offertory, and freewill giving.',
        'stripe_enabled' => '0',
        'paypal_enabled' => '0',
        'paypal_mode' => 'live',
    ];

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)'
    );

    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

function seed_hero_from_gallery(PDO $pdo): int
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM hero_slides')->fetchColumn();
    if ($count > 0) {
        return 0;
    }

    $images = $pdo->query(
        'SELECT image_path, caption FROM gallery_images WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 5'
    )->fetchAll();

    if (!$images) {
        return 0;
    }

    $insert = $pdo->prepare('INSERT INTO hero_slides (image_path, caption, sort_order) VALUES (?, ?, ?)');
    $order = 1;
    foreach ($images as $image) {
        $insert->execute([$image['image_path'], $image['caption'], $order++]);
    }

    return count($images);
}
