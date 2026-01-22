<?php
require 'vendor/autoload.php';
$f3 = \Base::instance();
$f3->config('app/config.ini');

$db = new \DB\SQL(
    $f3->get('db_dns') . $f3->get('db_name'),
    $f3->get('db_user'),
    $f3->get('db_pass')
);

try {
    $db->exec("ALTER TABLE projects ADD COLUMN lines_per_page INT DEFAULT 38");
    echo "Column added successfully.";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'CHECK constraint') !== false) {
        echo "Column likely already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
