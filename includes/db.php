<?php

require_once __DIR__ . '/requirements.php';
hog_require_extensions();

require_once __DIR__ . '/functions.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = load_config_array();
    date_default_timezone_set($config['timezone'] ?? 'Europe/London');

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = load_config_array();
    }
    return $config;
}
