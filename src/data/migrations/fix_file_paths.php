<?php
/**
 * Migration to fix absolute file paths in project_files table
 * Converts absolute paths to relative paths for portability between environments
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Initialize F3
$f3 = Base::instance();
$f3->config(__DIR__ . '/../../app/config.ini');

// Get database connection
$db = $f3->get('DB');

echo "Starting migration: fix_file_paths\n";
echo "================================\n\n";

// Get all files
$files = $db->exec("SELECT id, filepath FROM project_files");

echo "Found " . count($files) . " files to process\n\n";

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($files as $file) {
    $oldPath = $file['filepath'];

    // Check if path is absolute
    if (strpos($oldPath, '/home/') === 0 || strpos($oldPath, 'P:\\') === 0 || strpos($oldPath, 'C:\\') === 0) {
        echo "Processing file ID {$file['id']}\n";
        echo "  Old path: $oldPath\n";

        // Extract relative path
        // Pattern: .../data/{email}/files/{filename}
        if (preg_match('#/data/([^/]+)/files/(.+)$#', $oldPath, $matches)) {
            $email = $matches[1];
            $filename = $matches[2];
            $newPath = "data/$email/files/$filename";

            echo "  New path: $newPath\n";

            try {
                $db->exec(
                    "UPDATE project_files SET filepath = ? WHERE id = ?",
                    [$newPath, $file['id']]
                );
                $updated++;
                echo "  ✓ Updated\n\n";
            } catch (Exception $e) {
                echo "  ✗ Error: " . $e->getMessage() . "\n\n";
                $errors++;
            }
        } else {
            echo "  ! Could not extract relative path - skipping\n\n";
            $skipped++;
        }
    } else {
        // Already relative
        $skipped++;
    }
}

echo "\n================================\n";
echo "Migration completed!\n";
echo "Updated: $updated\n";
echo "Skipped: $skipped\n";
echo "Errors: $errors\n";
