<?php

function giving_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $defaults = [
        'giving_enabled' => '1',
        'giving_currency' => 'gbp',
        'giving_intro' => 'Support the ministry through tithe, offertory, and freewill giving.',
        'stripe_enabled' => '0',
        'stripe_public_key' => '',
        'stripe_secret_key' => '',
        'stripe_webhook_secret' => '',
        'paypal_enabled' => '0',
        'paypal_client_id' => '',
        'paypal_client_secret' => '',
        'paypal_mode' => 'live',
    ];

    try {
        $pdo = db();
        $rows = $pdo->query(
            "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'giving_%' OR setting_key LIKE 'stripe_%' OR setting_key LIKE 'paypal_%'"
        )->fetchAll();
        $stored = [];
        foreach ($rows as $row) {
            $stored[$row['setting_key']] = $row['setting_value'];
        }
        $settings = array_merge($defaults, $stored);
    } catch (Throwable $e) {
        $settings = $defaults;
    }

    return $settings;
}

function save_giving_settings(array $data): void
{
    $allowed = [
        'giving_enabled', 'giving_currency', 'giving_intro',
        'stripe_enabled', 'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret',
        'paypal_enabled', 'paypal_client_id', 'paypal_client_secret', 'paypal_mode',
    ];

    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        if (in_array($key, ['stripe_secret_key', 'stripe_webhook_secret', 'paypal_client_secret'], true) && trim((string) $data[$key]) === '') {
            continue;
        }
        $stmt->execute([$key, trim((string) $data[$key])]);
    }
}

function public_giving_config(): array
{
    $s = giving_settings();
    $enabled = ($s['giving_enabled'] ?? '1') !== '0';
    $stripeOn = $enabled && ($s['stripe_enabled'] ?? '0') === '1' && !empty($s['stripe_public_key']) && !empty($s['stripe_secret_key']);
    $paypalOn = $enabled && ($s['paypal_enabled'] ?? '0') === '1' && !empty($s['paypal_client_id']) && !empty($s['paypal_client_secret']);

    return [
        'enabled' => $enabled && ($stripeOn || $paypalOn),
        'currency' => strtolower($s['giving_currency'] ?? 'gbp'),
        'intro' => $s['giving_intro'] ?? '',
        'stripe' => [
            'enabled' => $stripeOn,
            'publicKey' => $stripeOn ? $s['stripe_public_key'] : '',
        ],
        'paypal' => [
            'enabled' => $paypalOn,
            'clientId' => $paypalOn ? $s['paypal_client_id'] : '',
            'mode' => ($s['paypal_mode'] ?? 'live') === 'sandbox' ? 'sandbox' : 'live',
        ],
    ];
}

function gift_type_label(string $type): string
{
    return match ($type) {
        'tithe' => 'Tithe',
        'offertory' => 'Offertory',
        'offering' => 'Offering',
        'building' => 'Building Fund',
        'mission' => 'Missions',
        'other' => 'Other',
        default => ucfirst($type),
    };
}

function allowed_gift_types(): array
{
    return ['tithe', 'offertory', 'offering', 'building', 'mission', 'other'];
}

function normalize_gift_amount($amount): ?float
{
    if (!is_numeric($amount)) {
        return null;
    }
    $value = round((float) $amount, 2);
    if ($value < 1 || $value > 50000) {
        return null;
    }
    return $value;
}

function site_base_url(): string
{
    $config = app_config();
    $url = rtrim($config['site_url'] ?? '', '/');
    if ($url) {
        return $url;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function create_giving_donation(array $data): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO giving_donations (name, email, gift_type, amount, currency, payment_provider, payment_status, external_id, message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['name'] ?: 'Anonymous',
        $data['email'] ?: null,
        $data['gift_type'],
        $data['amount'],
        $data['currency'],
        $data['provider'],
        $data['status'] ?? 'pending',
        $data['external_id'] ?? null,
        $data['message'] ?? null,
    ]);

    return (int) $pdo->lastInsertId();
}

function update_giving_donation(int $id, array $data): void
{
    $fields = [];
    $values = [];

    foreach (['payment_status', 'external_id', 'email'] as $key) {
        if (array_key_exists($key, $data)) {
            $fields[] = "{$key} = ?";
            $values[] = $data[$key];
        }
    }

    if (!$fields) {
        return;
    }

    $values[] = $id;
    db()->prepare('UPDATE giving_donations SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
}

function complete_giving_donation(int $id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM giving_donations WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $donation = $stmt->fetch();
    if (!$donation || $donation['payment_status'] === 'completed') {
        return;
    }

    update_giving_donation($id, ['payment_status' => 'completed']);

    require_once __DIR__ . '/mailer.php';
    $settings = email_settings();
    $label = gift_type_label($donation['gift_type']);
    $amount = number_format((float) $donation['amount'], 2) . ' ' . strtoupper($donation['currency']);

    if (!empty($settings['notify_admin']) && $settings['notify_admin'] !== '0') {
        send_form_emails('giving', 'Online Giving', [
            'Donor' => $donation['name'],
            'Email' => $donation['email'] ?: '—',
            'Gift Type' => $label,
            'Amount' => $amount,
            'Provider' => ucfirst($donation['payment_provider']),
            'Message' => $donation['message'] ?: '—',
        ], $donation['name'], $donation['email'] ?: $settings['admin_email']);
    }
}

function create_stripe_checkout_session(array $donation): string
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $settings = giving_settings();
    \Stripe\Stripe::setApiKey($settings['stripe_secret_key']);

    $currency = strtolower($donation['currency']);
    $label = gift_type_label($donation['gift_type']);
    $base = site_base_url();

    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'success_url' => $base . '/give/?success=1&provider=stripe',
        'cancel_url' => $base . '/give/?cancelled=1',
        'customer_email' => $donation['email'] ?: null,
        'metadata' => [
            'donation_id' => (string) $donation['id'],
            'gift_type' => $donation['gift_type'],
        ],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => (int) round((float) $donation['amount'] * 100),
                'product_data' => [
                    'name' => 'Hour of Grace — ' . $label,
                    'description' => 'Online giving to Hour of Grace Family Chapel International',
                ],
            ],
        ]],
    ]);

    update_giving_donation((int) $donation['id'], ['external_id' => $session->id]);

    return $session->url;
}

function paypal_api_base(): string
{
    $mode = giving_settings()['paypal_mode'] ?? 'live';
    return $mode === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';
}

function paypal_access_token(): string
{
    $settings = giving_settings();
    $ch = curl_init(paypal_api_base() . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $settings['paypal_client_id'] . ':' . $settings['paypal_client_secret'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_GB'],
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 400) {
        throw new RuntimeException('PayPal authentication failed.');
    }

    $data = json_decode((string) $response, true);
    if (empty($data['access_token'])) {
        throw new RuntimeException('PayPal access token missing.');
    }

    return $data['access_token'];
}

function create_paypal_order(array $donation): string
{
    $token = paypal_access_token();
    $currency = strtoupper($donation['currency']);
    $label = gift_type_label($donation['gift_type']);
    $base = site_base_url();

    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string) $donation['id'],
            'description' => 'Hour of Grace — ' . $label,
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format((float) $donation['amount'], 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'brand_name' => 'Hour of Grace Ministries',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
            'return_url' => $base . '/give/?success=1&provider=paypal',
            'cancel_url' => $base . '/give/?cancelled=1',
        ],
    ];

    $ch = curl_init(paypal_api_base() . '/v2/checkout/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    if ($code >= 400 || empty($data['id'])) {
        throw new RuntimeException('Could not create PayPal order.');
    }

    update_giving_donation((int) $donation['id'], ['external_id' => $data['id']]);

    foreach ($data['links'] ?? [] as $link) {
        if (($link['rel'] ?? '') === 'approve') {
            return $link['href'];
        }
    }

    throw new RuntimeException('PayPal approval link not found.');
}

function capture_paypal_order(string $orderId): int
{
    $token = paypal_access_token();
    $ch = curl_init(paypal_api_base() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    if ($code >= 400 || ($data['status'] ?? '') !== 'COMPLETED') {
        throw new RuntimeException('PayPal payment was not completed.');
    }

    $reference = $data['purchase_units'][0]['reference_id'] ?? null;
    $donationId = (int) $reference;

    if ($donationId) {
        complete_giving_donation($donationId);
    }

    return $donationId;
}
