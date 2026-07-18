<?php
require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

bootstrap_public_post('school-register', 5);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$name = post_string('name', 150);
$email = post_string('email', 180);
$phone = post_string('phone', 50);
$address = post_string('address', 500);
$programme = post_string('programme', 80);
$role = post_string('role', 80);
$education = post_string('education', 255);
$churchName = post_string('church_name', 255);
$message = post_string('message', 2000);

$allowedProgrammes = ['leeds-college', 'london-school'];
$allowedRoles = ['pastor', 'church-leader', 'church-worker', 'other'];

if (!$name || !$phone || !$address || !$education) {
    json_response(['success' => false, 'message' => 'Please fill in all required fields.'], 422);
}

if (!validate_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}

if (!in_array($programme, $allowedProgrammes, true) || !in_array($role, $allowedRoles, true)) {
    json_response(['success' => false, 'message' => 'Please select a valid programme and role.'], 422);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO school_registrations (name, email, phone, home_address, programme, role, education, church_name, message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $phone, $address, $programme, $role, $education, $churchName, $message]);

    send_form_emails('school', 'Bible School Registration', [
        'Name' => $name,
        'Email' => $email,
        'Phone' => $phone,
        'Home Address' => $address,
        'Programme' => programme_label($programme),
        'Role' => role_label($role),
        'Education' => $education,
        'Church Name' => $churchName ?: '—',
        'Additional Information' => $message ?: '—',
    ], $name, $email);

    json_response(['success' => true, 'message' => 'Thank you. Your school registration has been submitted. We will contact you shortly.']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
}
