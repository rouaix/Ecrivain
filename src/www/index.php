<?php

/**

 * Entry point - Assistant Écrivain

 * Security hardened 2026-01-31

 */

// Security Headers

header('X-Content-Type-Options: nosniff');

header('X-Frame-Options: SAMEORIGIN');

header('X-XSS-Protection: 1; mode=block');

header('Referrer-Policy: strict-origin-when-cross-origin');

header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Autoload framework and dependencies

$docRoot = dirname(__DIR__);

if (file_exists(__DIR__ . '/src/vendor/autoload.php')) {

    $docRoot = __DIR__ . '/src';

}

// Generate nonce for inline scripts

$nonce = base64_encode(random_bytes(16));

// Content Security Policy

// script-src: 'nonce-...' allows inline scripts with matching nonce. 'strict-dynamic' often used but here we stick to nonce + specific domains.

// style-src: 'unsafe-inline' kept for now as many libs/styles might need it, user only asked about script-src.

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdn.quilljs.com https://cdn.tiny.cloud https://cdnjs.cloudflare.com https://kit.fontawesome.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.quilljs.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://ka-f.fontawesome.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self' https://api.openai.com https://generativelanguage.googleapis.com https://api.anthropic.com https://api.mistral.ai;");

chdir($docRoot);

require_once $docRoot . '/vendor/autoload.php';

$f3 = Base::instance();

$f3->set('nonce', $nonce);

// Debug logging removed after fix

// Load environment variables from .env file

$isLocal = strpos($docRoot, 'Projets') !== false;

if ($isLocal) {

    $envFile = $docRoot . '/.env.local';

} else {

    $envFile = $docRoot . '/.env';

}

if (file_exists($envFile)) {

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {

        // Skip comments

        if (strpos(trim($line), '#') === 0) {

            continue;

        }

        // Parse KEY=VALUE

        if (strpos($line, '=') !== false) {

            list($key, $value) = explode('=', $line, 2);

            $key = trim($key);

            $value = trim($value);

            // Set as environment variable and F3 variable

            putenv("$key=$value");

            $_ENV[$key] = $value;

        }

    }

}

// Setup logging outside webroot

$logDir = $docRoot . '/logs';

if (!is_dir($logDir)) {

    mkdir($logDir, 0755, true);

}

$logFile = $logDir . '/app.log';

// Log entry

file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Application started\n", FILE_APPEND);

// Load configuration

$f3->config($docRoot . '/app/config.ini');

// Initialize Database from environment variables

// Local development detection

$isLocal = strpos($docRoot, 'Projets') !== false;

if ($isLocal) {

    // Local development overrides

    $dbHost = 'localhost';

    $dbName = 'ecrivain';

    $dbUser = 'root';

    $dbPass = '';

    $dbPort = 3306;

    $sessionDomain = '';

    $f3->set('DEBUG', 3);

} else {

    // Production: Use environment variables

    $dbHost = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';

    $dbName = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'ecrivain';

    $dbUser = getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'root';

    $dbPass = getenv('DB_PASS') ?: $_ENV['DB_PASS'] ?? '';

    $dbPort = getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? 3306;

    $sessionDomain = getenv('SESSION_DOMAIN') ?: $_ENV['SESSION_DOMAIN'] ?? '';

    $debugLevel = getenv('DEBUG') ?: $_ENV['DEBUG'] ?? 0;

    $f3->set('DEBUG', (int) $debugLevel);

    // Guard: JWT_SECRET is mandatory in production
    $jwtCheck = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;
    if (!$jwtCheck || strlen($jwtCheck) < 32) {
        http_response_code(500);
        error_log('FATAL: JWT_SECRET is missing or too short (min 32 chars). Set it in .env.');
        die('Configuration error: JWT_SECRET must be set in .env (minimum 32 characters).');
    }

}

$f3->set('ROOT', $docRoot);

if ($sessionDomain && $sessionDomain[0] !== '.') {

    $sessionDomain = '.' . $sessionDomain;

}

$f3->set('SESSION_DOMAIN', $sessionDomain ?? '');

// Debug request flow

$logMsg = sprintf(
    "[%s] %s %s | PATTERN: %s | DOMAIN: %s | COOKIE_THEME: %s\n",

    date('Y-m-d H:i:s'),

    $_SERVER['REQUEST_METHOD'],

    $_SERVER['REQUEST_URI'],

    $f3->get('PATTERN') ?: 'N/A',

    $f3->get('SESSION_DOMAIN'),

    $_COOKIE['theme'] ?? 'not set'

);

file_put_contents($docRoot . '/logs/theme_debug.log', $logMsg, FILE_APPEND);

// Override config.ini database settings with secure values

$f3->set('db_host', $dbHost);

$f3->set('db_name', $dbName);

$f3->set('db_user', $dbUser);

$f3->set('db_pass', $dbPass);

$f3->set('db_port', $dbPort);

try {

    $db = new DB\SQL("mysql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);

    $f3->set('DB', $db);

} catch (PDOException $e) {

    die('Database connection failed: ' . $e->getMessage());

}

// Run database migrations automatically

try {

    require_once $docRoot . '/app/core/Migrations.php';

    $migrations = new \App\Core\Migrations($f3);

    $migrations->run();

} catch (Exception $e) {

    // Log migration errors but don't stop the app

    error_log('Migration error: ' . $e->getMessage());

}

// Session configuration with security

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')

    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Secure session parameters

// IMPORTANT: Empty domain allows cookie to work with current hostname (e.g., www.rouaix.com)

// Setting a specific domain like 'rouaix.com' would block www.rouaix.com

session_set_cookie_params([

    'lifetime' => 0,

    'path' => '/',

    'domain' => $sessionDomain, // Use configured domain (e.g. .rouaix.com)

    'secure' => $isLocal ? false : $isHttps, // Secure in production HTTPS

    'httponly' => true,

    'samesite' => 'Lax', // Changed from Strict to Lax to allow normal navigation

]);

// Log session info in development only

if ($isLocal && isset($logFile)) {

    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Session Params: " . json_encode(session_get_cookie_params()) . " | HTTPS: " . ($isHttps ? 'Yes' : 'No') . "\n", FILE_APPEND);

}

session_start();

// Session Debugging removed

$f3->run();

