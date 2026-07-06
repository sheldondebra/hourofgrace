<?php
require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

bootstrap_public_post('contact');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$name = post_string('name', 150);
$email = post_string('email', 180);
$subject = post_string('subject', 255);
$message = post_string('message', 5000);

if (!$name || !$subject || !$message) {
    json_response(['success' => false, 'message' => 'Please fill in all required fields.'], 422);
}

if (!validate_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO contact_submissions (name, email, subject, message) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $subject, $message]);

    send_form_emails('contact', 'Contact Message', [
        'Name' => $name,
        'Email' => $email,
        'Subject' => $subject,
        'Message' => $message,
    ], $name, $email);

    json_response(['success' => true, 'message' => 'Thank you. Your message has been sent successfully.']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
}
