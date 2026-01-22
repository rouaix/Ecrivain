<?php
$docRoot = __DIR__ . '/src';
require_once $docRoot . '/vendor/autoload.php';

$f3 = Base::instance();
$f3->config($docRoot . '/app/config.ini');

$dbHost = $f3->get('db_host') ?: 'localhost';
$dbName = $f3->get('db_name') ?: 'ecrivain';
$dbUser = $f3->get('db_user') ?: 'root';
$dbPass = $f3->get('db_pass') ?: '';
$dbPort = $f3->get('db_port') ?: 3306;

try {
    $db = new DB\SQL("mysql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);
    $schema = $db->exec('SHOW CREATE TABLE sections');
    echo $schema[0]['Create Table'];
} catch (PDOException $e) {
    echo 'Database connection failed: ' . $e->getMessage();
}
