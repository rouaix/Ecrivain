<?php
/**
 * Migration script to move cover images from data/ to public/uploads/covers/
 * Run this script once after deploying the new code
 *
 * Usage: php migrate_cover_images.php
 */

$docRoot = __DIR__;
chdir($docRoot);
require_once $docRoot . '/vendor/autoload.php';

$f3 = Base::instance();

// Load configuration
$f3->config($docRoot . '/app/config.ini');

// Initialize Database (same logic as index.php)
if (strpos($docRoot, 'Projets') !== false) {
    // Local environment
    $f3->set('db_host', 'localhost');
    $f3->set('db_name', 'ecrivain');
    $f3->set('db_user', 'root');
    $f3->set('db_pass', '');
    $f3->set('db_port', 3306);
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
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "Starting cover image migration...\n\n";

// Get all projects with cover images
$projects = $db->exec('SELECT id, cover_image FROM projects WHERE cover_image IS NOT NULL AND cover_image != ""');

if (empty($projects)) {
    echo "No projects with cover images found.\n";
    exit(0);
}

$uploadDir = 'public/uploads/covers/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die("Failed to create upload directory: {$uploadDir}\n");
    }
    echo "Created upload directory: {$uploadDir}\n";
}

$migrated = 0;
$failed = 0;
$skipped = 0;

foreach ($projects as $project) {
    $pid = $project['id'];
    $oldFilename = $project['cover_image'];

    echo "Processing project #{$pid} - {$oldFilename}... ";

    // Try to find the old file
    $oldPaths = [
        // Try to find in data directory structure
        glob("data/*/projects/{$pid}/{$oldFilename}"),
        // Also check if already in new location
        [$uploadDir . $oldFilename]
    ];

    $oldFilePath = null;
    foreach ($oldPaths as $paths) {
        if (!empty($paths)) {
            foreach ($paths as $path) {
                if (file_exists($path) && !is_dir($path)) {
                    $oldFilePath = $path;
                    break 2;
                }
            }
        }
    }

    if (!$oldFilePath) {
        echo "SKIPPED (file not found)\n";
        $skipped++;
        continue;
    }

    // If already in new location, just update DB
    if (strpos($oldFilePath, $uploadDir) === 0) {
        echo "ALREADY MIGRATED\n";
        $skipped++;
        continue;
    }

    // Generate new filename
    $extension = pathinfo($oldFilename, PATHINFO_EXTENSION);
    $newFilename = "project_{$pid}_couverture_" . time() . ".{$extension}";
    $newFilePath = $uploadDir . $newFilename;

    // Copy file to new location
    if (copy($oldFilePath, $newFilePath)) {
        // Update database
        $result = $db->exec(
            'UPDATE projects SET cover_image = ? WHERE id = ?',
            [$newFilename, $pid]
        );

        if ($result) {
            echo "MIGRATED ({$newFilename})\n";
            $migrated++;

            // Optionally delete old file
            // unlink($oldFilePath);
        } else {
            echo "FAILED (database update)\n";
            unlink($newFilePath); // Clean up
            $failed++;
        }
    } else {
        echo "FAILED (copy error)\n";
        $failed++;
    }
}

echo "\n";
echo "Migration complete!\n";
echo "Migrated: {$migrated}\n";
echo "Failed: {$failed}\n";
echo "Skipped: {$skipped}\n";
echo "\n";
echo "Note: Old images in data/ directory were NOT deleted for safety.\n";
echo "You can manually delete them after verifying the migration.\n";
