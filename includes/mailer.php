<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const HOG_ADMIN_EMAIL = 'info@hourofgraceministries.org';

function email_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $config = app_config();
    $smtp = $config['smtp'] ?? [];
    $defaults = [
        'admin_email' => HOG_ADMIN_EMAIL,
        'smtp_host' => $smtp['host'] ?? 'mail.hourofgraceministries.org',
        'smtp_port' => (string) ($smtp['port'] ?? 465),
        'smtp_user' => $smtp['user'] ?? 'smtp@hourofgraceministries.org',
        'smtp_pass' => $smtp['pass'] ?? '',
        'smtp_from_email' => $smtp['from_email'] ?? 'smtp@hourofgraceministries.org',
        'smtp_from_name' => $smtp['from_name'] ?? ($config['site_name'] ?? 'Hour of Grace Ministry International'),
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

    if (empty($settings['admin_email'])) {
        $settings['admin_email'] = HOG_ADMIN_EMAIL;
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

function email_form_meta(string $formType): array
{
    return match ($formType) {
        'contact' => ['label' => 'Contact Message', 'accent' => '#2563eb', 'icon' => '✉️'],
        'prayer' => ['label' => 'Prayer Request', 'accent' => '#7c3aed', 'icon' => '🙏'],
        'register' => ['label' => 'Ministry Registration', 'accent' => '#059669', 'icon' => '📋'],
        'school' => ['label' => 'Bible School Registration', 'accent' => '#d97706', 'icon' => '📚'],
        'newsletter' => ['label' => 'Newsletter Subscription', 'accent' => '#3F3D8B', 'icon' => '📬'],
        default => ['label' => 'Website Submission', 'accent' => '#3F3D8B', 'icon' => '📝'],
    };
}

function email_escape(?string $value): string
{
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function email_field_table(array $fields): string
{
    $rows = '';
    foreach ($fields as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $rows .= '<tr>'
            . '<td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;background:#f8fafc;width:34%;font-size:13px;font-weight:600;color:#475569;vertical-align:top;">'
            . email_escape($label) . '</td>'
            . '<td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;line-height:1.55;vertical-align:top;">'
            . nl2br(email_escape((string) $value)) . '</td>'
            . '</tr>';
    }

    if ($rows === '') {
        return '';
    }

    return '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin:20px 0 8px;">'
        . $rows
        . '</table>';
}

function build_email_html(string $title, string $bodyHtml, ?string $accent = null): string
{
    $site = email_escape(app_config()['site_name'] ?? 'Hour of Grace Family Chapel International');
    $brand = $accent ?: '#3F3D8B';
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#eef1f6;font-family:'Segoe UI',Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6;padding:36px 16px;">
    <tr><td align="center">
      <table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dbe1ea;box-shadow:0 10px 30px rgba(15,23,42,0.06);">
        <tr><td style="background:{$brand};padding:28px 32px;">
          <p style="margin:0 0 6px;color:rgba(255,255,255,0.82);font-size:11px;letter-spacing:0.14em;text-transform:uppercase;">{$site}</p>
          <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:600;line-height:1.3;">{$title}</h1>
        </td></tr>
        <tr><td style="padding:32px;color:#334155;font-size:15px;line-height:1.65;">{$bodyHtml}</td></tr>
        <tr><td style="padding:22px 32px;background:#fafaf8;border-top:1px solid #e2e8f0;">
          <p style="margin:0 0 6px;color:#64748b;font-size:12px;line-height:1.6;">
            <strong style="color:#475569;">Hour of Grace Ministry International</strong><br />
            403a York Road, Leeds, LS9 6TD · United Kingdom<br />
            Phone: 07482 673887 · Email: <a href="mailto:info@hourofgraceministries.org" style="color:#3F3D8B;text-decoration:none;">info@hourofgraceministries.org</a>
          </p>
          <p style="margin:10px 0 0;color:#94a3b8;font-size:11px;">© {$year} Hour of Grace Ministry International. All rights reserved.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

function build_admin_submission_email(string $formType, string $formLabel, array $fields, string $userName, string $userEmail): string
{
    $meta = email_form_meta($formType);
    $submittedAt = date('l, j F Y \a\t g:i A T');
    $summary = email_field_table($fields);

    $body = '<p style="margin:0 0 16px;font-size:15px;color:#334155;">A new submission has been received through the website.</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">'
        . '<tr><td style="padding:14px 16px;font-size:13px;color:#475569;"><strong style="color:#0f172a;">Form:</strong> '
        . email_escape($meta['icon'] . ' ' . $formLabel) . '<br />'
        . '<strong style="color:#0f172a;">Submitted:</strong> ' . email_escape($submittedAt) . '<br />'
        . '<strong style="color:#0f172a;">From:</strong> ' . email_escape($userName)
        . ' &lt;' . email_escape($userEmail) . '&gt;</td></tr></table>'
        . $summary
        . '<p style="margin:18px 0 0;font-size:13px;color:#64748b;">You can review this submission in the admin dashboard.</p>';

    return build_email_html('New ' . $formLabel, $body, $meta['accent']);
}

function build_user_confirmation_email(string $formType, string $formLabel, string $userName): string
{
    $meta = email_form_meta($formType);
    $firstName = email_escape(trim(explode(' ', $userName)[0] ?: $userName));

    $messages = match ($formType) {
        'prayer' => 'We have received your prayer request and our team will be praying with you.',
        'register' => 'We have received your ministry registration application and will review your details shortly.',
        'school' => 'We have received your Bible School registration and our admissions team will be in touch.',
        'newsletter' => 'You have been added to our mailing list. We look forward to staying connected with you.',
        default => 'We have received your message and a member of our team will respond as soon as possible.',
    };

    $body = '<p style="margin:0 0 14px;font-size:16px;color:#0f172a;">Dear ' . $firstName . ',</p>'
        . '<p style="margin:0 0 14px;">Thank you for contacting <strong>Hour of Grace Ministry International</strong>.</p>'
        . '<p style="margin:0 0 18px;">' . email_escape($messages) . '</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-left:4px solid ' . $meta['accent'] . ';border-radius:8px;margin:0 0 18px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;color:#475569;">'
        . '<strong style="color:#0f172a;">Submission type:</strong> ' . email_escape($formLabel) . '<br />'
        . '<strong style="color:#0f172a;">Reference time:</strong> ' . email_escape(date('j F Y, g:i A')) . '</td></tr></table>'
        . '<p style="margin:0 0 14px;">If your enquiry is urgent, please call us on <strong>07482 673887</strong> or email '
        . '<a href="mailto:info@hourofgraceministries.org" style="color:#3F3D8B;text-decoration:none;">info@hourofgraceministries.org</a>.</p>'
        . '<p style="margin:0;">God bless you,<br /><strong>Hour of Grace Ministry International</strong></p>';

    return build_email_html('Thank You — ' . $formLabel, $body, $meta['accent']);
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
        $mail->AltBody = html_to_plain_text($htmlBody);

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

function html_to_plain_text(string $html): string
{
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $text = preg_replace('/<\/p>/i', "\n\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

function configure_phpmailer(PHPMailer $mail, array $settings): void
{
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->isSMTP();
    $mail->Host = $settings['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $settings['smtp_user'];
    $mail->Password = $settings['smtp_pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int) $settings['smtp_port'];
    $mail->setFrom(
        $settings['smtp_from_email'] ?: $settings['smtp_user'],
        $settings['smtp_from_name'] ?: 'Hour of Grace Ministry International'
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
    $siteName = app_config()['site_name'] ?? 'Hour of Grace Family Chapel International';
    $adminTo = $settings['admin_email'] ?: HOG_ADMIN_EMAIL;

    if (!empty($settings['notify_admin']) && $settings['notify_admin'] !== '0') {
        send_site_email(
            $adminTo,
            '[' . $siteName . '] New ' . $formLabel,
            build_admin_submission_email($formType, $formLabel, $adminFields, $userName, $userEmail),
            validate_email($userEmail) ? $userEmail : null,
            $userName
        );
    }

    if (!empty($settings['notify_user']) && $settings['notify_user'] !== '0' && validate_email($userEmail)) {
        send_site_email(
            $userEmail,
            'Thank you — ' . $formLabel . ' received',
            build_user_confirmation_email($formType, $formLabel, $userName)
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
