<?php

namespace App\Core;

class Migrations
{
    private $pdo;
    private $f3;

    private $originalErrMode;

    public function __construct($f3)
    {
        $this->f3 = $f3;
        // Use raw PDO to bypass F3's user_error() on SQL errors
        $db = \Base::instance()->get('DB');
        $this->pdo = $db->pdo();
        // Save original error mode to restore after migrations
        $this->originalErrMode = $this->pdo->getAttribute(\PDO::ATTR_ERRMODE);
    }

    /**
     * Run all pending migrations
     */
    public function run()
    {
        // Switch PDO to exception mode for migrations (F3 uses ERRMODE_SILENT + user_error)
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            // Create migrations tracking table if not exists
            $this->createMigrationsTable();

            // Get list of executed migrations
            $executed = $this->getExecutedMigrations();

            // Get all migration files
            $migrations = $this->getMigrationFiles();

            // Execute pending migrations
            foreach ($migrations as $migration) {
                if (!in_array($migration['name'], $executed)) {
                    $this->executeMigration($migration);
                }
            }
        } finally {
            // Always restore original error mode so F3 works normally after
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, $this->originalErrMode);
        }
    }

    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->pdo->exec($sql);
    }

    /**
     * Get list of executed migrations
     */
    private function getExecutedMigrations()
    {
        try {
            $stmt = $this->pdo->query("SELECT name FROM migrations ORDER BY id");
            return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Get all migration files
     */
    private function getMigrationFiles()
    {
        $migrationsDir = $this->f3->get('ROOT') . '/data/migrations/';

        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
            return [];
        }

        $files = glob($migrationsDir . '*.sql');
        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = [
                'name' => basename($file, '.sql'),
                'path' => $file
            ];
        }

        // Sort by name (which should include timestamp/version)
        usort($migrations, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $migrations;
    }

    /**
     * Execute a migration using raw PDO (not F3) to get real exceptions
     */
    private function executeMigration($migration)
    {
        try {
            // Read SQL file
            $sql = file_get_contents($migration['path']);

            if (empty($sql)) {
                return;
            }

            // Strip block comments /* ... */ and single-line SQL comments
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);

            // Split by semicolon to handle multiple statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt);
                }
            );

            // Execute each statement via raw PDO
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    try {
                        $this->pdo->exec($statement);
                    } catch (\PDOException $pe) {
                        // Tolerate truly idempotent errors only:
                        // 1060 = Duplicate column (ALTER TABLE ADD COLUMN already exists)
                        // 1061 = Duplicate key name (ALTER TABLE ADD INDEX already exists)
                        // 1062 = Duplicate entry (INSERT IGNORE equivalent)
                        // NOTE: 1005/1215 (FK errors) and 1050 (table exists) are NOT tolerated —
                        // they indicate the statement actually failed and the table was not created.
                        $code = (int) ($pe->errorInfo[1] ?? 0);
                        if (in_array($code, [1060, 1061, 1062])) {
                            error_log("Migration warning (ignored): " . $pe->getMessage());
                            continue;
                        }
                        throw $pe;
                    }
                }
            }

            // Record migration as executed
            $stmt = $this->pdo->prepare("INSERT INTO migrations (name) VALUES (?)");
            $stmt->execute([$migration['name']]);

            // Log success
            error_log("Migration executed: " . $migration['name']);

        } catch (\Exception $e) {
            error_log("Migration failed: " . $migration['name'] . " - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a table exists
     */
    public function tableExists($tableName)
    {
        try {
            $stmt = $this->pdo->query('SELECT 1 FROM `' . $tableName . '` LIMIT 1');
            return $stmt !== false;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
