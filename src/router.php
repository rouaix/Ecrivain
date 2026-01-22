<?php
// router.php for PHP built-in web server
if (php_sapi_name() == 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
        return false; // serve the requested file directly
    }
}
require __DIR__ . '/index.php';
