<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function email_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $config = app_config();
    $defaults = [
        'admin_email' => $config['site_email'] ?? 'info@hourofgraceministries.org',
        'smtp_host' => $config['smtp']['host'] ?? 'mail.hourofgraceministries.org',
        'smtp_port' => (string) ($config['smtp']['port'] ?? 465),
        'smtp_user' => $config['smtp']['user'] ?? '',
        'smtp_pass' => $config['smtp']['pass'] ?? '',
        'smtp_from_email' => $config['smtp']['from_email'] ?? $config['site_email'],
        'smtp_from_name' => $config['smtp']['from_name'] ?? $config['site_name'],
        'notify_admin' => '1',
        'notify_user' => '1',
    ];

    try {
        $pdo = db();
        $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
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

function save_email_settings(array $data): void
{
    $allowed = [
        'admin_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
        'smtp_from_email', 'smtp_from_name', 'notify_admin', 'notify_user',
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
        $value = trim((string) $data[$key]);
        if ($key === 'smtp_pass' && $value === '') {
            continue;
        }
        $stmt->execute([$key, $value]);
    }
}

function log_email(string $recipient, string $subject, bool $sent, ?string $error = null): void
{
    try {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO email_log (recipient, subject, status, error_message) VALUES (?, ?, ?, ?)'
        )->execute([$recipient, $subject, $sent ? 'sent' : 'failed', $error]);
    } catch (Throwable $e) {
        // Ignore logging failures.
    }
}

function build_email_html(string $title, string $bodyHtml): string
{
    $site = htmlspecialchars(app_config()['site_name'], ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f4f5f8;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f8;padding:32px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
        <tr><td style="background:#3F3D8B;padding:24px 28px;">
          <p style="margin:0;color:#87CEEB;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;">{$site}</p>
          <h1 style="margin:8px 0 0;color:#ffffff;font-size:22px;font-weight:600;">{$title}</h1>
        </td></tr>
        <tr><td style="padding:28px;color:#334155;font-size:15px;line-height:1.6;">{$bodyHtml}</td></tr>
        <tr><td style="padding:20px 28px;background:#fafaf8;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;">
          Hour of Grace Ministry International · 403a York Road, Leeds, LS9 6TD
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

function send_site_email(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $replyName = null): bool
{
    $settings = email_settings();

    if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
        log_email($to, $subject, false, 'SMTP not configured');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        configure_phpmailer($mail, $settings);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        if ($replyTo && validate_email($replyTo)) {
            $mail->addReplyTo($replyTo, $replyName ?: $replyTo);
        }

        $mail->send();
        log_email($to, $subject, true);
        return true;
    } catch (Exception $e) {
        log_email($to, $subject, false, $mail->ErrorInfo ?: $e->getMessage());
        return false;
    }
}

function configure_phpmailer(PHPMailer $mail, array $settings): void
{
    $mail->isSMTP();
    $mail->Host = $settings['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $settings['smtp_user'];
    $mail->Password = $settings['smtp_pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int) $settings['smtp_port'];
    $mail->setFrom(
        $settings['smtp_from_email'] ?: $settings['smtp_user'],
        $settings['smtp_from_name'] ?: 'Hour of Grace'
    );
}

function test_smtp_connection(): array
{
    $settings = email_settings();

    if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
        return ['ok' => false, 'message' => 'SMTP host and username are required. Save settings first.'];
    }

    $mail = new PHPMailer(true);

    try {
        configure_phpmailer($mail, $settings);
        $mail->SMTPDebug = 0;
        if (!$mail->smtpConnect()) {
            return ['ok' => false, 'message' => 'Could not connect to SMTP server. Check host, port, and credentials.'];
        }
        $mail->smtpClose();
        return ['ok' => true, 'message' => 'SMTP connection successful to ' . $settings['smtp_host'] . ':' . $settings['smtp_port'] . '.'];
    } catch (Exception $e) {
        return ['ok' => false, 'message' => 'SMTP connection failed: ' . ($mail->ErrorInfo ?: $e->getMessage())];
    }
}

function send_form_emails(string $formType, string $formLabel, array $adminFields, string $userName, string $userEmail): void
{
    $settings = email_settings();
    $siteName = app_config()['site_name'];

    $adminRows = '';
    foreach ($adminFields as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $adminRows .= '<p style="margin:0 0 10px;"><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</strong><br>'
            . nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    if (!empty($settings['notify_admin']) && $settings['notify_admin'] !== '0') {
        $adminBody = build_email_html(
            'New ' . $formLabel,
            '<p>A new <strong>' . htmlspecialchars($formLabel, ENT_QUOTES, 'UTF-8') . '</strong> was submitted on the website.</p>' . $adminRows
        );
        send_site_email(
            $settings['admin_email'],
            '[' . $siteName . '] New ' . $formLabel,
            $adminBody,
            $userEmail,
            $userName
        );
    }

    if (!empty($settings['notify_user']) && $settings['notify_user'] !== '0' && validate_email($userEmail)) {
        $firstName = trim(explode(' ', $userName)[0] ?: $userName);
        $userBody = build_email_html(
            'Thank You',
            '<p>Dear ' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Thank you for contacting <strong>Hour of Grace Ministry International</strong>.</p>'
            . '<p>We have received your <strong>' . htmlspecialchars(strtolower($formLabel), ENT_QUOTES, 'UTF-8') . '</strong> and our team will review it shortly.</p>'
            . '<p>If your enquiry is urgent, you may call us or email '
            . '<a href="mailto:' . htmlspecialchars($settings['admin_email'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($settings['admin_email'], ENT_QUOTES, 'UTF-8') . '</a>.</p>'
            . '<p>God bless you,<br><strong>Hour of Grace Ministry International</strong></p>'
        );
        send_site_email(
            $userEmail,
            'Thank you — ' . $formLabel . ' received',
            $userBody
        );
    }
}

function programme_label(string $value): string
{
    return match ($value) {
        'leeds-college' => 'Bible College (UK) — Leeds',
        'london-school' => 'Bible School — London',
        default => $value,
    };
}

function role_label(string $value): string
{
    return ucwords(str_replace('-', ' ', $value));
}
