<?php
require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/giving.php';

bootstrap_public_post('giving-paypal', 15);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$orderId = post_string('order_id', 255);
if (!$orderId) {
    json_response(['success' => false, 'message' => 'Missing PayPal order ID.'], 422);
}

try {
    $donationId = capture_paypal_order($orderId);
    json_response(['success' => true, 'donationId' => $donationId]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'PayPal payment could not be completed.'], 500);
}
