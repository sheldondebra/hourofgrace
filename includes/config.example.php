<?php
/**
 * Copy this file to config.php and update values for your cPanel hosting.
 */
return [
    'db_host' => 'localhost',
    'db_name' => 'hourofgr_gracedch',
    'db_user' => 'hourofgr_churchdb',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',

    'site_name' => 'Hour of Grace Family Chapel International',
    'site_email' => 'info@hourofgraceministries.org',
    'site_url' => 'https://hourofgraceministries.org',

    'smtp' => [
        'host' => 'mail.hourofgraceministries.org',
        'port' => 465,
        'user' => 'smtp@hourofgraceministries.org',
        'pass' => 'YOUR_SMTP_PASSWORD',
        'from_email' => 'smtp@hourofgraceministries.org',
        'from_name' => 'Hour of Grace Ministry International',
    ],

    'app_secret' => 'change-this-to-a-random-string',

    'environment' => 'production',
    'allowed_origins' => [
        'https://hourofgraceministries.org',
        'https://www.hourofgraceministries.org',
    ],

    'upload_max_mb' => 5,
    'timezone' => 'Europe/London',
];
