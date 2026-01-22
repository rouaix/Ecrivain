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

    // 1. Create notes table
    $db->exec("CREATE TABLE IF NOT EXISTS `notes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `project_id` int(11) NOT NULL,
      `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `content` longtext COLLATE utf8mb4_unicode_ci,
      `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `order_index` int(11) DEFAULT '0',
      `is_exported` tinyint(1) DEFAULT '1',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_project_note` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Migrate data
    $db->exec("INSERT INTO `notes` (project_id, title, content, image_path, order_index, is_exported, created_at, updated_at)
               SELECT project_id, title, content, image_path, order_index, is_exported, created_at, updated_at
               FROM `sections`
               WHERE `type` = 'notes'");

    // 3. Remove from sections
    $db->exec("DELETE FROM `sections` WHERE `type` = 'notes'");

    echo "Migration successful!";
} catch (PDOException $e) {
    echo 'Migration failed: ' . $e->getMessage();
}
