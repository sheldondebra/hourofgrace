<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/giving.php';
require_admin();

$pdo = db();
$message = null;
$error = null;
$settings = giving_settings();
$baseUrl = site_base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        try {
            save_giving_settings([
                'giving_enabled' => isset($_POST['giving_enabled']) ? '1' : '0',
                'giving_currency' => strtolower(post_string('giving_currency', 10) ?? 'gbp'),
                'giving_intro' => post_string('giving_intro', 500) ?? '',
                'stripe_enabled' => isset($_POST['stripe_enabled']) ? '1' : '0',
                'stripe_public_key' => post_string('stripe_public_key', 255) ?? '',
                'stripe_secret_key' => $_POST['stripe_secret_key'] ?? '',
                'stripe_webhook_secret' => $_POST['stripe_webhook_secret'] ?? '',
                'paypal_enabled' => isset($_POST['paypal_enabled']) ? '1' : '0',
                'paypal_client_id' => post_string('paypal_client_id', 255) ?? '',
                'paypal_client_secret' => $_POST['paypal_client_secret'] ?? '',
                'paypal_mode' => post_string('paypal_mode', 20) ?? 'live',
            ]);
            $settings = giving_settings();
            $message = 'Giving settings saved successfully.';
        } catch (Throwable $e) {
            $error = 'Could not save settings: ' . $e->getMessage();
        }
    }

    if ($action === 'test_stripe') {
        try {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            $settings = giving_settings();
            if (empty($settings['stripe_secret_key'])) {
                throw new RuntimeException('Stripe secret key is not configured.');
            }
            \Stripe\Stripe::setApiKey($settings['stripe_secret_key']);
            \Stripe\Balance::retrieve();
            $message = 'Stripe connection successful.';
        } catch (Throwable $e) {
            $error = 'Stripe test failed: ' . $e->getMessage();
        }
    }

    if ($action === 'test_paypal') {
        try {
            paypal_access_token();
            $message = 'PayPal connection successful.';
        } catch (Throwable $e) {
            $error = 'PayPal test failed: ' . $e->getMessage();
        }
    }

    if ($action === 'delete_donation') {
        $id = (int) ($_POST['donation_id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM giving_donations WHERE id = ?')->execute([$id]);
            $message = 'Donation record deleted.';
        }
    }
}

$donations = [];
$tableMissing = false;

try {
    $donations = $pdo->query(
        'SELECT * FROM giving_donations ORDER BY created_at DESC LIMIT 50'
    )->fetchAll();
} catch (Throwable $e) {
    $tableMissing = true;
}

$pageTitle = 'Online Giving';
$activeNav = 'giving';
require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

<div class="panel-grid">
  <section class="panel">
    <div class="panel-head"><h2>General</h2></div>
    <form method="post" class="stack-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
      <input type="hidden" name="action" value="save" />

      <label class="checkbox-line">
        <input type="checkbox" name="giving_enabled" value="1" <?= ($settings['giving_enabled'] ?? '1') !== '0' ? 'checked' : '' ?> />
        <span>Enable online giving page</span>
      </label>

      <label>
        <span>Currency</span>
        <select name="giving_currency">
          <?php foreach (['gbp' => 'GBP (£)', 'usd' => 'USD ($)', 'eur' => 'EUR (€)'] as $code => $label): ?>
            <option value="<?= $code ?>" <?= ($settings['giving_currency'] ?? 'gbp') === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        <span>Intro text on Give page</span>
        <textarea name="giving_intro" rows="3" placeholder="Short message for the giving page"><?= sanitize($settings['giving_intro'] ?? '') ?></textarea>
      </label>

      <hr class="giving-admin-divider" />

      <h3 class="giving-admin-subhead">Stripe</h3>
      <label class="checkbox-line">
        <input type="checkbox" name="stripe_enabled" value="1" <?= ($settings['stripe_enabled'] ?? '0') === '1' ? 'checked' : '' ?> />
        <span>Enable card payments via Stripe</span>
      </label>
      <label>
        <span>Publishable key</span>
        <input type="text" name="stripe_public_key" value="<?= sanitize($settings['stripe_public_key'] ?? '') ?>" placeholder="pk_live_..." />
      </label>
      <label>
        <span>Secret key</span>
        <input type="password" name="stripe_secret_key" placeholder="<?= !empty($settings['stripe_secret_key']) ? 'Saved — leave blank to keep' : 'sk_live_...' ?>" autocomplete="new-password" />
      </label>
      <label>
        <span>Webhook signing secret</span>
        <input type="password" name="stripe_webhook_secret" placeholder="<?= !empty($settings['stripe_webhook_secret']) ? 'Saved — leave blank to keep' : 'whsec_...' ?>" autocomplete="new-password" />
      </label>
      <p class="help-text">Stripe webhook URL:<br><code><?= sanitize($baseUrl) ?>/api/giving-stripe-webhook.php</code><br>Event: <strong>checkout.session.completed</strong></p>

      <hr class="giving-admin-divider" />

      <h3 class="giving-admin-subhead">PayPal</h3>
      <label class="checkbox-line">
        <input type="checkbox" name="paypal_enabled" value="1" <?= ($settings['paypal_enabled'] ?? '0') === '1' ? 'checked' : '' ?> />
        <span>Enable PayPal</span>
      </label>
      <label>
        <span>Client ID</span>
        <input type="text" name="paypal_client_id" value="<?= sanitize($settings['paypal_client_id'] ?? '') ?>" placeholder="PayPal REST app Client ID" />
      </label>
      <label>
        <span>Client secret</span>
        <input type="password" name="paypal_client_secret" placeholder="<?= !empty($settings['paypal_client_secret']) ? 'Saved — leave blank to keep' : 'PayPal REST app secret' ?>" autocomplete="new-password" />
      </label>
      <label>
        <span>Environment</span>
        <select name="paypal_mode">
          <option value="live" <?= ($settings['paypal_mode'] ?? 'live') === 'live' ? 'selected' : '' ?>>Live</option>
          <option value="sandbox" <?= ($settings['paypal_mode'] ?? 'live') === 'sandbox' ? 'selected' : '' ?>>Sandbox (testing)</option>
        </select>
      </label>

      <button type="submit" class="btn btn-primary">Save Giving Settings</button>
    </form>

    <div class="form-actions-row mt-4">
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
        <input type="hidden" name="action" value="test_stripe" />
        <button type="submit" class="btn btn-soft">Test Stripe</button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
        <input type="hidden" name="action" value="test_paypal" />
        <button type="submit" class="btn btn-soft">Test PayPal</button>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Setup guide</h2></div>
    <div class="info-box">
      <p><strong>Stripe</strong></p>
      <ol>
        <li>Create a account at stripe.com</li>
        <li>Copy your publishable and secret keys</li>
        <li>Add the webhook URL above in Stripe Dashboard → Developers → Webhooks</li>
      </ol>
      <p class="mt-4"><strong>PayPal</strong></p>
      <ol>
        <li>Go to developer.paypal.com and create a REST app</li>
        <li>Copy Client ID and Secret</li>
        <li>Use Sandbox mode for testing first</li>
      </ol>
      <p class="mt-4"><a href="/give/" target="_blank" rel="noopener">Preview Give page →</a></p>
    </div>
  </section>
</div>

<section class="panel">
  <div class="panel-head"><h2>Recent gifts (<?= count($donations) ?>)</h2></div>

  <?php if ($tableMissing): ?>
    <p class="empty-state">The giving table will be created automatically on next admin page load. Refresh this page.</p>
  <?php elseif (!$donations): ?>
    <p class="empty-state">No online gifts recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Donor</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Provider</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($donations as $row): ?>
            <tr>
              <td><?= format_datetime($row['created_at']) ?></td>
              <td><?= sanitize($row['name']) ?><br><span class="text-muted"><?= sanitize($row['email'] ?? '—') ?></span></td>
              <td><?= sanitize(gift_type_label($row['gift_type'])) ?></td>
              <td><?= sanitize(strtoupper($row['currency'])) ?> <?= number_format((float) $row['amount'], 2) ?></td>
              <td><?= ucfirst(sanitize($row['payment_provider'])) ?></td>
              <td><span class="status-pill status-<?= $row['payment_status'] === 'completed' ? 'sent' : 'failed' ?>"><?= ucfirst(sanitize($row['payment_status'])) ?></span></td>
              <td>
                <form method="post" onsubmit="return confirm('Delete this donation record?');">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>" />
                  <input type="hidden" name="action" value="delete_donation" />
                  <input type="hidden" name="donation_id" value="<?= (int) $row['id'] ?>" />
                  <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
