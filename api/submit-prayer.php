<?php
require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

bootstrap_public_post('prayer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$name = post_string('name', 150);
$email = post_string('email', 180);
$phone = post_string('phone', 50);
$request = post_string('request', 5000);
$isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1' ? 1 : 0;

if (!$name || !$request) {
    json_response(['success' => false, 'message' => 'Please fill in your name and prayer request.'], 422);
}

if (!validate_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO prayer_requests (name, email, phone, request, is_private) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $phone, $request, $isPrivate]);

    send_form_emails('prayer', 'Prayer Request', [
        'Name' => $name,
        'Email' => $email,
        'Phone' => $phone ?: '—',
        'Private Request' => $isPrivate ? 'Yes' : 'No',
        'Prayer Request' => $request,
    ], $name, $email);

    json_response(['success' => true, 'message' => 'Your prayer request has been received. Our team will be praying with you.']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
}
