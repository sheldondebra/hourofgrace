<?php
require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/giving.php';

bootstrap_public_post('giving', 15);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$config = public_giving_config();
if (!$config['enabled']) {
    json_response(['success' => false, 'message' => 'Online giving is not available at the moment.'], 503);
}

$name = post_string('name', 150) ?? 'Anonymous';
$email = post_string('email', 180);
$giftType = post_string('gift_type', 40);
$message = post_string('message', 500);
$provider = post_string('provider', 20);
$amount = normalize_gift_amount($_POST['amount'] ?? null);

if (!$amount) {
    json_response(['success' => false, 'message' => 'Please enter a valid amount (minimum £1).'], 422);
}

if (!$giftType || !in_array($giftType, allowed_gift_types(), true)) {
    json_response(['success' => false, 'message' => 'Please select a gift type.'], 422);
}

if ($email && !validate_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}

if ($provider === 'stripe' && !$config['stripe']['enabled']) {
    json_response(['success' => false, 'message' => 'Card payments are not enabled.'], 422);
}

if ($provider === 'paypal' && !$config['paypal']['enabled']) {
    json_response(['success' => false, 'message' => 'PayPal is not enabled.'], 422);
}

if (!in_array($provider, ['stripe', 'paypal'], true)) {
    json_response(['success' => false, 'message' => 'Please choose a payment method.'], 422);
}

try {
    $donationId = create_giving_donation([
        'name' => $name,
        'email' => $email,
        'gift_type' => $giftType,
        'amount' => $amount,
        'currency' => $config['currency'],
        'provider' => $provider,
        'status' => 'pending',
        'message' => $message,
    ]);

    $donation = [
        'id' => $donationId,
        'gift_type' => $giftType,
        'amount' => $amount,
        'currency' => $config['currency'],
        'email' => $email,
    ];

    if ($provider === 'stripe') {
        $url = create_stripe_checkout_session($donation);
        json_response(['success' => true, 'redirectUrl' => $url]);
    }

    $url = create_paypal_order($donation);
    json_response(['success' => true, 'redirectUrl' => $url]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to start payment. Please try again or contact the church.'], 500);
}
