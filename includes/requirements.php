<?php

function hog_install_polyfills(): void
{
    if (!function_exists('mb_substr')) {
        function mb_substr($string, $start, $length = null, $encoding = null): string
        {
            $string = (string) $string;

            if ($length === null) {
                return substr($string, $start);
            }

            return substr($string, $start, $length);
        }
    }
}

function hog_missing_extensions(): array
{
    $required = [
        'pdo' => 'PDO',
        'pdo_mysql' => 'pdo_mysql (MySQL driver for PDO)',
    ];

    $missing = [];
    foreach ($required as $extension => $label) {
        if (!extension_loaded($extension)) {
            $missing[] = $label;
        }
    }

    return $missing;
}

function hog_recommended_extensions(): array
{
    $recommended = [
        'mbstring' => 'mbstring (better text handling for forms)',
    ];

    $missing = [];
    foreach ($recommended as $extension => $label) {
        if (!extension_loaded($extension)) {
            $missing[] = $label;
        }
    }

    return $missing;
}

function hog_require_extensions(): void
{
    hog_install_polyfills();

    $missing = hog_missing_extensions();
    if (!$missing) {
        return;
    }

    if (headers_sent()) {
        exit('Required PHP extensions are missing: ' . implode(', ', $missing));
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:36rem;margin:3rem auto;padding:0 1rem;line-height:1.5">';
    echo '<h1>PHP extension required</h1>';
    echo '<p>This site needs the following PHP extensions, which are not enabled on your server:</p><ul>';
    foreach ($missing as $label) {
        echo '<li><strong>' . htmlspecialchars($label) . '</strong></li>';
    }
    echo '</ul>';
    echo '<p>In cPanel:</p><ol>';
    echo '<li>Open <strong>Select PHP Version</strong> (or <strong>MultiPHP INI Editor</strong>)</li>';
    echo '<li>Choose PHP <strong>8.1</strong> or <strong>8.2</strong> for this domain</li>';
    echo '<li>Enable <strong>PDO</strong> and <strong>pdo_mysql</strong></li>';
    echo '<li>Also enable <strong>mbstring</strong> (recommended)</li>';
    echo '<li>Save and reload this page</li>';
    echo '</ol>';
    echo '<p>PHP version detected: ' . htmlspecialchars(PHP_VERSION) . '</p>';
    echo '</body></html>';
    exit;
}
