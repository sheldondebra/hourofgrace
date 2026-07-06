<?php
require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

bootstrap_public_post('newsletter', 10);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$email = post_string('email', 180);

if (!validate_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}

try {
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(180) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_newsletter_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $existing = $pdo->prepare('SELECT id, is_active FROM newsletter_subscribers WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    $row = $existing->fetch();

    if ($row) {
        if ((int) $row['is_active'] === 0) {
            $pdo->prepare('UPDATE newsletter_subscribers SET is_active = 1 WHERE id = ?')->execute([(int) $row['id']]);
            json_response(['success' => true, 'message' => 'Welcome back! You are subscribed again.']);
        }

        json_response(['success' => true, 'message' => 'You are already subscribed to our updates.']);
    }

    $stmt = $pdo->prepare('INSERT INTO newsletter_subscribers (email) VALUES (?)');
    $stmt->execute([$email]);

    send_form_emails('newsletter', 'Newsletter Subscription', [
        'Email' => $email,
    ], 'Subscriber', $email);

    json_response(['success' => true, 'message' => 'Thank you for subscribing!']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Subscription failed. Please try again later.'], 500);
}
