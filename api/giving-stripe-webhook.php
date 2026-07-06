<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/giving.php';
require_once __DIR__ . '/../vendor/autoload.php';

$payload = @file_get_contents('php://input');
$settings = giving_settings();
$secret = $settings['stripe_webhook_secret'] ?? '';

if (!$secret) {
    http_response_code(400);
    exit;
}

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '',
        $secret
    );
} catch (Throwable $e) {
    http_response_code(400);
    exit;
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;
    $donationId = (int) ($session->metadata->donation_id ?? 0);
    if ($donationId) {
        complete_giving_donation($donationId);
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
