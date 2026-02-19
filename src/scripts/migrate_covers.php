<?php
/**
 * Migration: Move project and section cover images to data/{email}/projects/{id}/
 *
 * - Project covers  : public/uploads/covers/project_*.jpg  → data/{email}/projects/{pid}/
 * - Section images  : public/uploads/covers/cover_*.* OR public/uploads/section_*.* → data/{email}/projects/{pid}/sections/{type}.*
 *
 * Run from the src/ directory:
 *   php scripts/migrate_covers.php
 *
 * Or from the project root:
 *   php src/scripts/migrate_covers.php
 */

// Resolve src/ as working directory
$srcDir = dirname(__DIR__);
if (basename($srcDir) !== 'src') {
    // Called from project root - adjust
    $srcDir = __DIR__ . '/../..';
    $srcDir = realpath($srcDir . '/src') ?: ($srcDir . '/src');
}
chdir($srcDir);

// Load environment variables
$isLocal = strpos($srcDir, 'Projets') !== false;
$envFile = $isLocal ? $srcDir . '/.env.local' : $srcDir . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database connection
$dbHost = $isLocal ? 'localhost' : ($_ENV['DB_HOST'] ?? 'localhost');
$dbName = $isLocal ? 'ecrivain'  : ($_ENV['DB_NAME'] ?? 'ecrivain');
$dbUser = $isLocal ? 'root'      : ($_ENV['DB_USER'] ?? 'root');
$dbPass = $isLocal ? ''          : ($_ENV['DB_PASS'] ?? '');
$dbPort = $isLocal ? 3306        : (int)($_ENV['DB_PORT'] ?? 3306);

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage() . "\n");
}

$oldBase = 'public/uploads/covers/';
$moved   = 0;
$skipped = 0;
$errors  = 0;

// Fetch all projects with a cover image, joined with user email
$stmt = $pdo->query("
    SELECT p.id, p.cover_image, u.email
    FROM projects p
    JOIN users u ON p.user_id = u.id
    WHERE p.cover_image IS NOT NULL AND p.cover_image != ''
    ORDER BY p.id
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Migration des couvertures de projets ===\n\n";
echo count($projects) . " projet(s) avec couverture trouvé(s).\n\n";

foreach ($projects as $project) {
    $pid      = $project['id'];
    $filename = $project['cover_image'];
    $email    = $project['email'];

    $newDir  = 'data/' . $email . '/projects/' . $pid . '/';
    $oldPath = $oldBase . $filename;
    $newPath = $newDir . $filename;

    echo "Projet #$pid ($email) : $filename\n";

    // Already at the new location
    if (file_exists($newPath)) {
        echo "  -> Déjà en place : $newPath\n";
        $skipped++;
        continue;
    }

    // File missing from old location
    if (!file_exists($oldPath)) {
        echo "  -> ATTENTION : fichier introuvable (ni ancien ni nouveau chemin)\n";
        $errors++;
        continue;
    }

    // Create target directory if needed
    if (!is_dir($newDir)) {
        if (!mkdir($newDir, 0755, true)) {
            echo "  -> ERREUR : impossible de créer le répertoire $newDir\n";
            $errors++;
            continue;
        }
        echo "  -> Répertoire créé : $newDir\n";
    }

    // Move the file
    if (rename($oldPath, $newPath)) {
        echo "  -> Déplacé vers : $newPath\n";
        $moved++;
    } else {
        echo "  -> ERREUR : échec du déplacement\n";
        $errors++;
    }
}

// ─── Section images ───────────────────────────────────────────────────────────
echo "\n=== Migration des images de sections ===\n\n";

// image_path stores either:
//   /public/uploads/covers/cover_xxxx.ext   (new code)
//   /public/uploads/section_xxxx.ext        (old code)
// After migration it will be: /project/{pid}/section/{type}/image

$stmt = $pdo->query("
    SELECT s.id, s.project_id, s.type, s.image_path, u.email
    FROM sections s
    JOIN projects p ON s.project_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE s.image_path IS NOT NULL AND s.image_path != ''
      AND s.image_path NOT LIKE '/project/%'
    ORDER BY s.project_id, s.id
");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo count($sections) . " section(s) avec image à migrer.\n\n";

$smoved   = 0;
$sskipped = 0;
$serrors  = 0;

$updateStmt = $pdo->prepare("UPDATE sections SET image_path = ? WHERE id = ?");

foreach ($sections as $sec) {
    $sid      = $sec['id'];
    $pid      = $sec['project_id'];
    $type     = $sec['type'];
    $oldPath  = $sec['image_path'];   // e.g. /public/uploads/covers/cover_xxx.jpg
    $email    = $sec['email'];

    // Build filesystem path from stored URL (strip leading slash, from src/)
    $oldFsPath = ltrim($oldPath, '/');

    $ext     = strtolower(pathinfo($oldFsPath, PATHINFO_EXTENSION));
    $newDir  = 'data/' . $email . '/projects/' . $pid . '/sections/';
    $newFile = $type . '.' . $ext;
    $newPath = $newDir . $newFile;
    $newUrl  = '/project/' . $pid . '/section/' . $type . '/image';

    echo "Section #$sid ($type) projet #$pid ($email) : $oldPath\n";

    // Already migrated (file exists at new location)
    if (file_exists($newPath)) {
        echo "  -> Fichier déjà en place : $newPath\n";
        $updateStmt->execute([$newUrl, $sid]);
        echo "  -> DB mis à jour : $newUrl\n";
        $sskipped++;
        continue;
    }

    // Old file missing
    if (!file_exists($oldFsPath)) {
        echo "  -> ATTENTION : fichier introuvable ($oldFsPath)\n";
        $serrors++;
        continue;
    }

    // Create target directory
    if (!is_dir($newDir)) {
        if (!mkdir($newDir, 0755, true)) {
            echo "  -> ERREUR : impossible de créer $newDir\n";
            $serrors++;
            continue;
        }
        echo "  -> Répertoire créé : $newDir\n";
    }

    // Move file
    if (rename($oldFsPath, $newPath)) {
        echo "  -> Déplacé vers : $newPath\n";
        $updateStmt->execute([$newUrl, $sid]);
        echo "  -> DB mis à jour : $newUrl\n";
        $smoved++;
    } else {
        echo "  -> ERREUR : échec du déplacement\n";
        $serrors++;
    }
}

// ─── Orphan cleanup ───────────────────────────────────────────────────────────
echo "\n=== Nettoyage des fichiers orphelins ===\n";
$deleted = 0;

// Orphan project covers
$oldFiles = glob($oldBase . 'project_*') ?: [];
foreach ($oldFiles as $oldFile) {
    $basename = basename($oldFile);
    $check = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE cover_image = ?");
    $check->execute([$basename]);
    if ($check->fetchColumn() == 0) {
        unlink($oldFile) ? ($deleted++ && print("Supprimé (orphelin projet) : $oldFile\n")) : print("ERREUR suppression : $oldFile\n");
    }
}

// Orphan section covers (covers/ and direct uploads/)
foreach (array_merge(glob($oldBase . 'cover_*') ?: [], glob('public/uploads/section_*') ?: []) as $oldFile) {
    $fsPath = $oldFile;
    $urlPath = '/' . $fsPath;
    $check = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE image_path = ?");
    $check->execute([$urlPath]);
    if ($check->fetchColumn() == 0) {
        unlink($fsPath) ? ($deleted++ && print("Supprimé (orphelin section) : $fsPath\n")) : print("ERREUR suppression : $fsPath\n");
    }
}

if ($deleted === 0) {
    echo "Aucun fichier orphelin trouvé.\n";
}

// ─── Summary ──────────────────────────────────────────────────────────────────
echo "\n=== Résumé ===\n";
echo "Projets  — déplacés : $moved, déjà OK : $skipped, erreurs : $errors\n";
echo "Sections — déplacés : $smoved, déjà OK : $sskipped, erreurs : $serrors\n";
echo "Orphelins supprimés : $deleted\n";
