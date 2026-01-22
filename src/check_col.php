<?php
require 'vendor/autoload.php';
$f3 = \Base::instance();
$f3->config('app/config.ini');

// Instantiate DB explicitly
$db = new \DB\SQL(
    'mysql:host=' . $f3->get('db_host') . ';port=' . $f3->get('db_port') . ';dbname=' . $f3->get('db_name'),
    $f3->get('db_user'),
    $f3->get('db_pass')
);

$schema = $db->schema('projects');
if (isset($schema['lines_per_page'])) {
    echo "EXISTS";
} else {
    echo "MISSING";
    // Try adding it
    try {
        $db->exec("ALTER TABLE projects ADD COLUMN lines_per_page INT DEFAULT 38");
        echo " - ADDED";
    } catch (\Exception $e) {
        echo " - ERROR: " . $e->getMessage();
    }
}
