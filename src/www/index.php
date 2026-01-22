<?php
// ERROR LOGGING DIRECT
file_put_contents(__DIR__ . '/entry_log.txt', date('[Y-m-d H:i:s] ') . "Index reached inside " . __DIR__ . "\n", FILE_APPEND);

// ERROR LOGGING DIRECT
file_put_contents(__DIR__ . '/entry_log.txt', date('[Y-m-d H:i:s] ') . "Index reached inside " . __DIR__ . "\n", FILE_APPEND);


// Autoload framework and dependencies
$docRoot = dirname(__DIR__);
if (file_exists(__DIR__ . '/src/vendor/autoload.php')) {
    $docRoot = __DIR__ . '/src';
}

chdir($docRoot);
require_once $docRoot . '/vendor/autoload.php';

$f3 = Base::instance();

// Load configuration
$f3->config($docRoot . '/app/config.ini');

// Initialize Database
// Environment check
if (strpos($docRoot, 'Projets') !== false) {
    $f3->set('db_host', 'localhost');
    $f3->set('db_name', 'ecrivain');
    $f3->set('db_user', 'root');
    $f3->set('db_pass', '');
    $f3->set('db_port', 3306);
    $f3->set('SESSION_DOMAIN', ''); // Fix for local login persistence
}

$dbHost = $f3->get('db_host');
$dbName = $f3->get('db_name');
$dbUser = $f3->get('db_user');
$dbPass = $f3->get('db_pass');
$dbPort = $f3->get('db_port');

try {
    $db = new DB\SQL("mysql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);
    $f3->set('DB', $db);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Session configuration
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Force SESSION_DOMAIN to empty for better cross-environment persistence
// unless specific multi-subdomain support is needed.
$f3->set('SESSION_DOMAIN', '');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false, // FORCE FALSE FOR DEBUG
    'httponly' => true,
    'samesite' => 'Lax',
]);

file_put_contents(__DIR__ . '/entry_log.txt', date('[Y-m-d H:i:s] ') . "Session Params: " . json_encode(session_get_cookie_params()) . " | HTTPS Detected: " . ($isHttps ? 'Yes' : 'No') . "\n", FILE_APPEND);

session_start();

$f3->run();
