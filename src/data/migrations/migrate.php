<?php

/**
 * migrate.php — Migrations dry-run / status CLI
 *
 * Usage:
 *   php src/data/migrations/migrate.php           # show status
 *   php src/data/migrations/migrate.php --run     # run pending migrations
 *   php src/data/migrations/migrate.php --dry-run # alias for status
 *
 * Reads DB credentials from src/.env.local (dev) or src/.env (production).
 * Must be run from the project root: php src/data/migrations/migrate.php
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

$projectRoot = dirname(__DIR__, 2); // src/
$migrationsDir = __DIR__ . '/';

// Detect env file
$isLocal = str_contains($projectRoot, 'Projets');
$envFile = $isLocal ? $projectRoot . '/.env.local' : $projectRoot . '/.env';

$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

$dbHost = $env['DB_HOST'] ?? ($isLocal ? 'localhost' : 'localhost');
$dbName = $env['DB_NAME'] ?? 'ecrivain';
$dbUser = $env['DB_USER'] ?? ($isLocal ? 'root' : 'root');
$dbPass = $env['DB_PASS'] ?? '';
$dbPort = (int) ($env['DB_PORT'] ?? 3306);

// ── Connect ───────────────────────────────────────────────────────────────────

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// ── Ensure migrations table exists ───────────────────────────────────────────

$pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
    `id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Load state ───────────────────────────────────────────────────────────────

$executed = $pdo->query("SELECT name, executed_at FROM migrations ORDER BY id")
    ->fetchAll(PDO::FETCH_KEY_PAIR);  // name => executed_at

$files = glob($migrationsDir . '*.sql') ?: [];
sort($files);

$allMigrations = array_map(fn($f) => [
    'name' => basename($f, '.sql'),
    'path' => $f,
], $files);

$pending  = array_filter($allMigrations, fn($m) => !isset($executed[$m['name']]));
$applied  = array_filter($allMigrations, fn($m) =>  isset($executed[$m['name']]));

// ── Parse args ───────────────────────────────────────────────────────────────

$doRun = in_array('--run', $argv ?? [], true);

// ── Report ────────────────────────────────────────────────────────────────────

echo "\n=== Migration status (" . date('Y-m-d H:i:s') . ") ===\n\n";
echo "DB: $dbName @ $dbHost:$dbPort\n\n";

echo "Applied (" . count($applied) . "):\n";
foreach ($applied as $m) {
    echo "  [OK] {$m['name']}  ({$executed[$m['name']]})\n";
}

echo "\nPending (" . count($pending) . "):\n";
if (empty($pending)) {
    echo "  (none — database is up to date)\n";
} else {
    foreach ($pending as $m) {
        echo "  [ ] {$m['name']}\n";
    }
}

if (!$doRun) {
    if (!empty($pending)) {
        echo "\nRun with --run to apply pending migrations.\n";
    }
    echo "\n";
    exit(0);
}

// ── Run pending ───────────────────────────────────────────────────────────────

if (empty($pending)) {
    echo "\nNothing to run.\n\n";
    exit(0);
}

echo "\nRunning pending migrations...\n";
$failed = 0;

foreach ($pending as $m) {
    echo "  Applying {$m['name']}... ";
    $sql = file_get_contents($m['path']);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    try {
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
        $pdo->prepare("INSERT INTO migrations (name) VALUES (?)")->execute([$m['name']]);
        echo "OK\n";
    } catch (PDOException $e) {
        echo "FAILED\n";
        fwrite(STDERR, "  Error: " . $e->getMessage() . "\n");
        $failed++;
    }
}

echo "\nDone. " . (count($pending) - $failed) . "/" . count($pending) . " applied.\n\n";
exit($failed > 0 ? 1 : 0);
