<?php
require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

bootstrap_public_post('register', 5);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$name = post_string('name', 150);
$email = post_string('email', 180);
$address = post_string('address', 500);
$dob = post_string('dob', 20);
$countryBirth = post_string('country_birth', 120);
$countryResident = post_string('country_resident', 120);
$education = post_string('education', 255);
$jobCategory = post_string('job_category', 80);
$jobInterest = post_string('job_interest', 120);
$religion = post_string('religion', 120);
$bornAgain = post_string('born_again', 10);
$baptized = post_string('baptized', 10);
$baptismYear = post_string('baptism_year', 20);
$baptismChurch = post_string('baptism_church', 255);
$baptizer = post_string('baptizer', 150);

$required = [$name, $email, $address, $countryBirth, $countryResident, $education, $jobCategory, $jobInterest, $religion, $bornAgain, $baptized];
foreach ($required as $field) {
    if (!$field) {
        json_response(['success' => false, 'message' => 'Please complete all required fields.'], 422);
    }
}

if (!validate_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}

$documents = save_uploaded_files('documents', 'documents');

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO registration_submissions (
            name, email, home_address, date_of_birth, country_of_birth, country_of_resident,
            education, job_category, job_interest, religion, born_again, baptized,
            baptism_year, baptism_church, baptizer, documents
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $name,
        $email,
        $address,
        $dob ?: null,
        $countryBirth,
        $countryResident,
        $education,
        $jobCategory,
        $jobInterest,
        $religion,
        $bornAgain,
        $baptized,
        $baptismYear,
        $baptismChurch,
        $baptizer,
        $documents ? json_encode($documents) : null,
    ]);

    send_form_emails('register', 'Ministry Registration', [
        'Name' => $name,
        'Email' => $email,
        'Home Address' => $address,
        'Date of Birth' => $dob ?: '—',
        'Country of Birth' => $countryBirth,
        'Country of Resident' => $countryResident,
        'Education' => $education,
        'Job Category' => register_job_category_label($jobCategory),
        'Area of Interest' => register_job_interest_label($jobInterest),
        'Religion' => $religion,
        'Born Again' => yes_no_label($bornAgain),
        'Baptised' => yes_no_label($baptized),
        'Baptism Year' => $baptismYear ?: '—',
        'Baptism Church' => $baptismChurch ?: '—',
        'Baptizer' => $baptizer ?: '—',
        'Documents Uploaded' => $documents ? count($documents) . ' file(s)' : 'None',
    ], $name, $email);

    json_response(['success' => true, 'message' => 'Thank you. Your registration has been submitted successfully.']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
}
