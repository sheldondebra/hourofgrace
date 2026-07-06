<?php
/**
 * Temporary server diagnostic — delete after fixing install issues.
 */
header('Content-Type: text/plain; charset=utf-8');

echo 'Hour of Grace — server check' . PHP_EOL;
echo 'PHP version: ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.0.0', '>=') ? ' (ok)' : ' (needs 8.0+)') . PHP_EOL;
echo 'PDO MySQL: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'NO — enable in cPanel') . PHP_EOL;
echo 'mbstring: ' . (extension_loaded('mbstring') ? 'yes' : 'NO — enable in cPanel') . PHP_EOL;

$configPath = __DIR__ . '/includes/config.php';
echo 'config.php: ' . (is_file($configPath) ? 'found' : 'missing (installer will create it)') . PHP_EOL;

if (is_file($configPath)) {
    $config = @include $configPath;
    if (!is_array($config)) {
        echo 'config.php: FAILED — file must return an array (check for syntax errors)' . PHP_EOL;
    } else {
        echo 'config.php: loaded ok' . PHP_EOL;
        if (!empty($config['db_name'])) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    $config['db_host'] ?? 'localhost',
                    $config['db_name']
                );
                new PDO($dsn, $config['db_user'] ?? '', $config['db_pass'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                echo 'database: connected' . PHP_EOL;
            } catch (Throwable $e) {
                echo 'database: ' . $e->getMessage() . PHP_EOL;
            }
        }
    }
}

if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    $functionsPath = __DIR__ . '/includes/functions.php';
    if (is_file($functionsPath)) {
        require_once $functionsPath;
        echo 'includes/functions.php: loaded ok' . PHP_EOL;
    } else {
        echo 'includes/functions.php: MISSING — re-upload the includes/ folder' . PHP_EOL;
    }
}

echo PHP_EOL . 'Delete health.php when done.' . PHP_EOL;
