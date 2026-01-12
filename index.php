<?php
// Entry point for the Assistant application.

session_start();

// Autoload or require our minimal framework and models
// Load the Fat‑Free base (compatibility wrapper). This will include our minimal
// framework and return its instance. Replace with the actual F3 `base.php` if
// you have the full Fat‑Free library.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Project.php';
require_once __DIR__ . '/app/models/Act.php';
require_once __DIR__ . '/app/models/Chapter.php';
require_once __DIR__ . '/app/models/Character.php';
require_once __DIR__ . '/app/models/Synonyms.php';
require_once __DIR__ . '/app/models/Comment.php';
require_once __DIR__ . '/app/models/Section.php';

// Obtain the framework instance
$f3 = Base::instance();

// Initialize the database connection (MySQLi)
$dbHost = 'localhost';
$dbName = 'ecrivain';
$dbUser = 'root';
$dbPass = '';

// Connect to MySQL server (no database selected yet)
$mysqli = new mysqli($dbHost, $dbUser, $dbPass);
if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

// Create database if it doesn't exist
$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Select the database
if (!$mysqli->select_db($dbName)) {
    die('Could not select database: ' . $mysqli->error);
}

$mysqli->set_charset('utf8mb4');

// Map the db() method to return the mysqli instance
// Store database in F3 hive
$f3->set('DB', $mysqli);

// Ensure the database and tables are initialized
initializeDatabase($f3->get('DB'));

/**
 * Initialize database tables on first run.
 *
 * @param mysqli $db
 * @return void
 */
function initializeDatabase(mysqli $db): void
{
    // Create users table
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(191) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create projects table
    $db->query("CREATE TABLE IF NOT EXISTS projects (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        target_words INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create acts table
    $db->query("CREATE TABLE IF NOT EXISTS acts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        project_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        order_index INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");

    // Create chapters table
    $db->query("CREATE TABLE IF NOT EXISTS chapters (
        id INT PRIMARY KEY AUTO_INCREMENT,
        project_id INT NOT NULL,
        act_id INT,
        parent_id INT,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT,
        order_index INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (act_id) REFERENCES acts(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES chapters(id) ON DELETE CASCADE
    )");

    // Migration for existing databases
    $result = $db->query("SHOW COLUMNS FROM chapters LIKE 'act_id'");
    if ($result->num_rows == 0) {
        $db->query("ALTER TABLE chapters ADD COLUMN act_id INT AFTER project_id");
        $db->query("ALTER TABLE chapters ADD CONSTRAINT fk_chapters_act FOREIGN KEY (act_id) REFERENCES acts(id) ON DELETE CASCADE");
    }

    $result = $db->query("SHOW COLUMNS FROM chapters LIKE 'parent_id'");
    if ($result->num_rows == 0) {
        $db->query("ALTER TABLE chapters ADD COLUMN parent_id INT AFTER act_id");
        $db->query("ALTER TABLE chapters ADD CONSTRAINT fk_chapters_parent FOREIGN KEY (parent_id) REFERENCES chapters(id) ON DELETE CASCADE");
    }

    // Create characters table
    $db->query("CREATE TABLE IF NOT EXISTS characters (
        id INT PRIMARY KEY AUTO_INCREMENT,
        project_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");

    // Create comments table for annotations
    $db->query("CREATE TABLE IF NOT EXISTS comments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        chapter_id INT NOT NULL,
        start_pos INT,
        end_pos INT,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
    )");

    // Create sections table for special book sections (cover, preface, etc.)
    $db->query("CREATE TABLE IF NOT EXISTS sections (
        id INT PRIMARY KEY AUTO_INCREMENT,
        project_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255),
        content LONGTEXT,
        image_path VARCHAR(255),
        order_index INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_project_section (project_id, type)
    )");

    // Migration for projects table
    $result = $db->query("SHOW COLUMNS FROM projects LIKE 'words_per_page'");
    if ($result->num_rows == 0) {
        $db->query("ALTER TABLE projects ADD COLUMN words_per_page INT DEFAULT 350 AFTER target_words");
    }

    $result = $db->query("SHOW COLUMNS FROM projects LIKE 'target_pages'");
    if ($result->num_rows == 0) {
        $db->query("ALTER TABLE projects ADD COLUMN target_pages INT DEFAULT 0 AFTER words_per_page");
    }

    // Migration for sections table: drop unique index and add non-unique index
    $result = $db->query("SHOW INDEX FROM sections WHERE Key_name = 'unique_section_per_project'");
    if ($result->num_rows > 0) {
        $db->query("ALTER TABLE sections DROP INDEX unique_section_per_project");
    }

    $result = $db->query("SHOW INDEX FROM sections WHERE Key_name = 'idx_project_section'");
    if ($result->num_rows == 0) {
        $db->query("CREATE INDEX idx_project_section ON sections (project_id, type)");
    }

    $result = $db->query("SHOW COLUMNS FROM sections LIKE 'order_index'");
    if ($result->num_rows == 0) {
        $db->query("ALTER TABLE sections ADD COLUMN order_index INT DEFAULT 0 AFTER image_path");
    }

    $result = $db->query("SHOW COLUMNS FROM chapters LIKE 'is_exported'");
    if ($result->num_rows == 0) {
        $db->query("ALTER TABLE chapters ADD COLUMN is_exported TINYINT(1) DEFAULT 1 AFTER order_index");
    }

    $result = $db->query("SHOW COLUMNS FROM sections LIKE 'is_exported'");
    if ($result->num_rows == 0) {
        $db->query("ALTER TABLE sections ADD COLUMN is_exported TINYINT(1) DEFAULT 1 AFTER order_index");
    }
}

/**
 * Helper to render a view within the main layout. Starts an output buffer
 * to capture the view contents, then includes the layout which will
 * output the captured content inside a common HTML frame. The $data
 * array becomes variables in the view scope.
 *
 * @param Base  $f3
 * @param string $view Relative view path (e.g. 'auth/login')
 * @param array $data Data passed to the view
 */
function renderView(Base $f3, string $view, array $data = []): void
{
    // Make base path available to views
    $data['base'] = $f3->get('BASE');

    // Extract data variables so they are accessible in the view
    extract($data);

    // Start output buffering to capture view content
    ob_start();
    include __DIR__ . '/app/views/' . $view . '.php';
    $content = ob_get_clean();

    // Default title if not set
    $title = $data['title'] ?? 'Assistant';

    // Render the main layout which uses $content and $title
    include __DIR__ . '/app/views/layouts/main.php';
}

// Convenience function to retrieve the logged in user record
function currentUser(Base $f3): ?array
{
    if (isset($_SESSION['user_id'])) {
        $userModel = new User($f3->get('DB'));
        return $userModel->find((int) $_SESSION['user_id']);
    }
    return null;
}

// Route definitions

// Home page
$f3->route('GET /', function (Base $f3) {
    renderView($f3, 'home', ['title' => 'Accueil']);
});

// Registration form
$f3->route('GET /register', function (Base $f3) {
    if (currentUser($f3)) {
        $f3->reroute('/dashboard');
    }
    renderView($f3, 'auth/register', ['title' => 'Inscription']);
});

// Handle registration
$f3->route('POST /register', function (Base $f3) {
    $userModel = new User($f3->get('DB'));
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $errors = [];
    if ($username === '' || $password === '') {
        $errors[] = 'Merci de remplir tous les champs obligatoires.';
    }
    if ($userModel->findByUsername($username)) {
        $errors[] = 'Ce nom d’utilisateur est déjà utilisé.';
    }
    if (empty($errors)) {
        $userId = $userModel->create($username, $password, $email);
        if ($userId) {
            $_SESSION['user_id'] = $userId;
            $f3->reroute('/dashboard');
        } else {
            $errors[] = 'Une erreur est survenue lors de l’inscription.';
        }
    }
    renderView($f3, 'auth/register', [
        'title' => 'Inscription',
        'errors' => $errors,
        'old' => ['username' => htmlspecialchars($username), 'email' => htmlspecialchars($email)],
    ]);
});

// Login form
$f3->route('GET /login', function (Base $f3) {
    if (currentUser($f3)) {
        $f3->reroute('/dashboard');
    }
    renderView($f3, 'auth/login', ['title' => 'Connexion']);
});

// Handle login
$f3->route('POST /login', function (Base $f3) {
    $userModel = new User($f3->get('DB'));
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];
    $user = $userModel->authenticate($username, $password);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $f3->reroute('/dashboard');
    } else {
        $errors[] = 'Identifiants invalides.';
    }
    renderView($f3, 'auth/login', [
        'title' => 'Connexion',
        'errors' => $errors,
        'old' => ['username' => htmlspecialchars($username)],
    ]);
});

// Logout route
$f3->route('GET /logout', function (Base $f3) {
    session_destroy();
    $f3->reroute('/');
});

// Dashboard: list projects
$f3->route('GET /dashboard', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $projectModel = new Project($f3->get('DB'));
    $projects = $projectModel->getAllByUser($user['id']);
    renderView($f3, 'project/dashboard', [
        'title' => 'Tableau de bord',
        'projects' => $projects,
        'user' => $user,
    ]);
});

// Create project form
$f3->route('GET /project/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    renderView($f3, 'project/create', [
        'title' => 'Nouveau projet',
    ]);
});

// Handle project creation
$f3->route('POST /project/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user || !isset($user['id'])) {
        $f3->reroute('/login');
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $target = intval($_POST['target_words'] ?? 0);
    $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
    $targetPages = intval($_POST['target_pages'] ?? 0);
    $errors = [];
    if ($title === '') {
        $errors[] = 'Le titre du projet est obligatoire.';
    }
    if (empty($errors)) {
        $projectModel = new Project($f3->get('DB'));
        $pid = $projectModel->create($user['id'], $title, $description, $target, $wordsPerPage, $targetPages);
        if ($pid) {
            $f3->reroute('/project/' . $pid);
        } else {
            $errors[] = 'Impossible de créer le projet.';
        }
    }
    renderView($f3, 'project/create', [
        'title' => 'Nouveau projet',
        'errors' => $errors,
        'old' => [
            'title' => htmlspecialchars($title),
            'description' => htmlspecialchars($description),
            'target_words' => $target,
            'words_per_page' => $wordsPerPage,
            'target_pages' => $targetPages
        ],
    ]);
});

// Show project details (chapters list, characters)
$f3->route('GET /project/@id', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    $chapterModel = new Chapter($f3->get('DB'));
    $allChapters = $chapterModel->getAllByProject($pid);
    $characterModel = new Character($f3->get('DB'));
    $characters = $characterModel->getAllByProject($pid);
    $actModel = new Act($f3->get('DB'));
    $acts = $actModel->getAllByProject($pid);
    $sectionModel = new Section($f3->get('DB'));
    $sectionsBeforeChapters = $sectionModel->getBeforeChapters($pid);
    $sectionsAfterChapters = $sectionModel->getAfterChapters($pid);

    // Group chapters by parent and act
    $chaptersByAct = [];
    $chaptersWithoutAct = [];
    $subChaptersByParent = [];

    foreach ($allChapters as $ch) {
        if ($ch['parent_id']) {
            $subChaptersByParent[$ch['parent_id']][] = $ch;
        } else {
            if ($ch['act_id']) {
                $chaptersByAct[$ch['act_id']][] = $ch;
            } else {
                $chaptersWithoutAct[] = $ch;
            }
        }
    }

    renderView($f3, 'project/show', [
        'title' => 'Projet: ' . htmlspecialchars($project['title']),
        'project' => $project,
        'acts' => $acts,
        'chaptersByAct' => $chaptersByAct,
        'chaptersWithoutAct' => $chaptersWithoutAct,
        'subChaptersByParent' => $subChaptersByParent,
        'allChapters' => $allChapters,
        'characters' => $characters,
        'sectionsBeforeChapters' => $sectionsBeforeChapters,
        'sectionsAfterChapters' => $sectionsAfterChapters,
    ]);
});

// Edit project form
$f3->route('GET /project/@id/edit', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    renderView($f3, 'project/edit', [
        'title' => 'Modifier le projet',
        'project' => $project,
    ]);
});

// Handle project update
$f3->route('POST /project/@id/edit', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $target = intval($_POST['target_words'] ?? 0);
    $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
    $targetPages = intval($_POST['target_pages'] ?? 0);
    $errors = [];
    if ($title === '') {
        $errors[] = 'Le titre est obligatoire.';
    }
    if (empty($errors)) {
        $projectModel->update($pid, $title, $description, $target, $wordsPerPage, $targetPages);
        $f3->reroute('/project/' . $pid);
    }
    renderView($f3, 'project/edit', [
        'title' => 'Modifier le projet',
        'project' => array_merge($project, [
            'title' => $title,
            'description' => $description,
            'target_words' => $target,
            'words_per_page' => $wordsPerPage,
            'target_pages' => $targetPages
        ]),
        'errors' => $errors,
    ]);
});

// Delete project
$f3->route('GET /project/@id/delete', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        header('Location: /login');
        exit;
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if ($project) {
        $projectModel->delete($pid);
    }
    $f3->reroute('/dashboard');
});

// Create act form
$f3->route('GET /project/@pid/act/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    renderView($f3, 'project/create_act', [
        'title' => 'Nouvel acte',
        'project' => $project,
    ]);
});

// Handle new act creation
$f3->route('POST /project/@pid/act/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $errors = [];
    if ($title === '') {
        $errors[] = 'Le titre de l’acte est obligatoire.';
    }
    if (empty($errors)) {
        $actModel = new Act($f3->get('DB'));
        $aid = $actModel->create($pid, $title, $description);
        if ($aid) {
            $f3->reroute('/project/' . $pid);
        } else {
            $errors[] = 'Impossible de créer l’acte.';
        }
    }
    renderView($f3, 'project/create_act', [
        'title' => 'Nouvel acte',
        'project' => $project,
        'errors' => $errors,
        'old' => ['title' => htmlspecialchars($title), 'description' => htmlspecialchars($description)],
    ]);
});

// Edit act form
$f3->route('GET /act/@id/edit', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $aid = (int) $f3->get('PARAMS.id');
    $actModel = new Act($f3->get('DB'));
    $act = $actModel->find($aid);
    if (!$act) {
        http_response_code(404);
        echo 'Acte introuvable.';
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($act['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo 'Accès refusé.';
        return;
    }
    renderView($f3, 'project/edit_act', [
        'title' => 'Modifier l’acte',
        'project' => $project,
        'act' => $act,
    ]);
});

// Handle act update
$f3->route('POST /act/@id/edit', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $aid = (int) $f3->get('PARAMS.id');
    $actModel = new Act($f3->get('DB'));
    $act = $actModel->find($aid);
    if (!$act) {
        http_response_code(404);
        echo 'Acte introuvable.';
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($act['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo 'Accès refusé.';
        return;
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $errors = [];
    if ($title === '') {
        $errors[] = 'Le titre est obligatoire.';
    }
    if (empty($errors)) {
        $actModel->update($aid, $title, $description);
        $f3->reroute('/project/' . $project['id']);
    }
    renderView($f3, 'project/edit_act', [
        'title' => 'Modifier l’acte',
        'project' => $project,
        'act' => array_merge($act, ['title' => $title, 'description' => $description]),
        'errors' => $errors,
    ]);
});

// Delete act
$f3->route('GET /act/@id/delete', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $aid = (int) $f3->get('PARAMS.id');
    $actModel = new Act($f3->get('DB'));
    $act = $actModel->find($aid);
    if ($act) {
        $projectModel = new Project($f3->get('DB'));
        $project = $projectModel->find($act['project_id'], $user['id']);
        if ($project) {
            $actModel->delete($aid);
            $f3->reroute('/project/' . $project['id']);
        }
    }
    $f3->reroute('/dashboard');
});

// Reorder acts
$f3->route('POST /project/@id/acts/reorder', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        http_response_code(403);
        return;
    }
    $pid = (int) $f3->get('PARAMS.id');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['order'])) {
        http_response_code(400);
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if ($project) {
        $actModel = new Act($f3->get('DB'));
        $actModel->reorder($pid, $data['order']);
    }
});

// Create chapter form
$f3->route('GET /project/@pid/chapter/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    $actModel = new Act($f3->get('DB'));
    $acts = $actModel->getAllByProject($pid);
    $chapterModel = new Chapter($f3->get('DB'));
    $chapters = $chapterModel->getTopLevelByProject($pid);
    $parentId = !empty($_GET['parent_id']) ? (int) $_GET['parent_id'] : null;
    $actId = !empty($_GET['act_id']) ? (int) $_GET['act_id'] : null;
    renderView($f3, 'project/create_chapter', [
        'title' => 'Nouveau chapitre',
        'project' => $project,
        'acts' => $acts,
        'chapters' => $chapters,
        'old' => ['parent_id' => $parentId, 'act_id' => $actId],
    ]);
});

// Handle new chapter creation
$f3->route('POST /project/@pid/chapter/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    $title = trim($_POST['title'] ?? '');
    $actId = !empty($_POST['act_id']) ? (int) $_POST['act_id'] : null;
    $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
    $errors = [];
    if ($title === '') {
        $errors[] = 'Le titre du chapitre est obligatoire.';
    }
    if (empty($errors)) {
        $chapterModel = new Chapter($f3->get('DB'));
        // Inherit act from parent if not set
        if ($parentId && $actId === null) {
            $parent = $chapterModel->find($parentId);
            if ($parent) {
                $actId = (int) $parent['act_id'] ?: null;
            }
        }
        $cid = $chapterModel->create($pid, $title, $actId, $parentId);
        if ($cid) {
            $f3->set('SESSION.success', 'Chapitre créé avec succès.');
            $f3->reroute('/chapter/' . $cid);
        } else {
            $errors[] = 'Impossible de créer le chapitre.';
        }
    }
    // Get acts and chapters for the form
    $actModel = new Act($f3->get('DB'));
    $acts = $actModel->getAllByProject($pid);
    $chapterModel = new Chapter($f3->get('DB'));
    $chapters = $chapterModel->getTopLevelByProject($pid);
    renderView($f3, 'project/create_chapter', [
        'title' => 'Nouveau chapitre',
        'project' => $project,
        'acts' => $acts,
        'chapters' => $chapters,
        'errors' => $errors,
        'old' => ['title' => htmlspecialchars($title), 'act_id' => $actId, 'parent_id' => $parentId],
    ]);
});

// Edit chapter (writing editor)
$f3->route('GET /chapter/@id', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $cid = (int) $f3->get('PARAMS.id');
    $chapterModel = new Chapter($f3->get('DB'));
    $chapter = $chapterModel->find($cid);
    if (!$chapter) {
        http_response_code(404);
        echo 'Chapitre introuvable.';
        return;
    }
    // Ensure the chapter belongs to a project owned by the user
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($chapter['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo 'Accès refusé.';
        return;
    }
    // Load any annotations for this chapter to display them in the editor
    $commentModel = new Comment($f3->get('DB'));
    $comments = $commentModel->getByChapter($cid);
    $actModel = new Act($f3->get('DB'));
    $acts = $actModel->getAllByProject($chapter['project_id']);
    $topChapters = $chapterModel->getTopLevelByProject($chapter['project_id']);

    // Get parent context if sub-chapter
    $parentChapter = null;
    if ($chapter['parent_id']) {
        $parentChapter = $chapterModel->find($chapter['parent_id']);
    }

    // Get act context
    $currentAct = null;
    if ($chapter['act_id']) {
        $currentAct = $actModel->find($chapter['act_id']);
    }

    $success = $f3->get('SESSION.success');
    $f3->clear('SESSION.success');
    renderView($f3, 'editor/edit', [
        'title' => 'Éditer le chapitre',
        'chapter' => $chapter,
        'project' => $project,
        'acts' => $acts,
        'topChapters' => $topChapters,
        'parentChapter' => $parentChapter,
        'currentAct' => $currentAct,
        'comments' => $comments,
        'success' => $success,
    ]);
});

// Save chapter changes
$f3->route('POST /chapter/@id/save', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $cid = (int) $f3->get('PARAMS.id');
    $chapterModel = new Chapter($f3->get('DB'));
    $chapter = $chapterModel->find($cid);
    if (!$chapter) {
        http_response_code(404);
        echo 'Chapitre introuvable.';
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($chapter['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo 'Accès refusé.';
        return;
    }
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $actId = !empty($_POST['act_id']) ? (int) $_POST['act_id'] : null;
    $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
    $errors = [];
    if ($title === '') {
        $errors[] = 'Le titre est obligatoire.';
    }
    if (empty($errors)) {
        // Inherit act from parent if not set
        if ($parentId && $actId === null) {
            $parent = $chapterModel->find($parentId);
            if ($parent) {
                $actId = (int) $parent['act_id'] ?: null;
            }
        }
        if ($chapterModel->update($cid, $title, $content, $actId, $parentId)) {
            $f3->set('SESSION.success', 'Modifications enregistrées.');
            $f3->reroute('/chapter/' . $cid);
        } else {
            $errors[] = 'Erreur lors de l\'enregistrement en base de données.';
        }
    }
    $actModel = new Act($f3->get('DB'));
    $acts = $actModel->getAllByProject($chapter['project_id']);
    $topChapters = $chapterModel->getTopLevelByProject($chapter['project_id']);

    $parentChapter = $parentId ? $chapterModel->find($parentId) : null;
    $currentAct = $actId ? $actModel->find($actId) : null;

    renderView($f3, 'editor/edit', [
        'title' => 'Éditer le chapitre',
        'chapter' => array_merge($chapter, ['title' => $title, 'content' => $content, 'act_id' => $actId, 'parent_id' => $parentId]),
        'project' => $project,
        'acts' => $acts,
        'topChapters' => $topChapters,
        'parentChapter' => $parentChapter,
        'currentAct' => $currentAct,
        'errors' => $errors,
    ]);
});

// Delete chapter
$f3->route('GET /chapter/@id/delete', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $cid = (int) $f3->get('PARAMS.id');
    $chapterModel = new Chapter($f3->get('DB'));
    $chapter = $chapterModel->find($cid);
    if ($chapter) {
        $projectModel = new Project($f3->get('DB'));
        $project = $projectModel->find($chapter['project_id'], $user['id']);
        if ($project) {
            $chapterModel->delete($cid);
            $f3->reroute('/project/' . $project['id']);
        }
    }
    $f3->reroute('/dashboard');
});

// Create or edit section form
$f3->route('GET /project/@pid/section/@type', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $type = $f3->get('PARAMS.type');

    // Validate section type
    if (!isset(Section::SECTION_TYPES[$type])) {
        http_response_code(404);
        echo 'Type de section invalide.';
        return;
    }

    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }

    $sectionModel = new Section($f3->get('DB'));
    $id = !empty($_GET['id']) ? (int) $_GET['id'] : null;

    if ($id) {
        $section = $sectionModel->find($id, $pid);
    } elseif ($type !== 'notes' && $type !== 'appendices') {
        $section = $sectionModel->findByType($pid, $type);
    } else {
        $section = null;
    }

    renderView($f3, 'section/edit', [
        'title' => ($section ? 'Modifier' : 'Créer') . ' - ' . Section::getTypeName($type),
        'project' => $project,
        'section' => $section,
        'sectionType' => $type,
        'sectionTypeName' => Section::getTypeName($type),
    ]);
});

// Handle section creation/update
$f3->route('POST /project/@pid/section/@type', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $type = $f3->get('PARAMS.type');

    // Validate section type
    if (!isset(Section::SECTION_TYPES[$type])) {
        http_response_code(404);
        echo 'Type de section invalide.';
        return;
    }

    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }

    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $sectionModel = new Section($f3->get('DB'));
    $id = $f3->get('GET.id') ?: ($f3->get('POST.id') ?: null);
    $imagePath = null;

    if ($id) {
        $existing = $sectionModel->find($id, $pid);
        if ($existing) {
            $imagePath = $existing['image_path'];
        }
    }

    // Handle image upload for cover and back_cover
    if (($type === 'cover' || $type === 'back_cover') && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('section_') . '.' . $ext;
        $uploadPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $imagePath = '/public/uploads/' . $filename;
        }
    }

    $sid = $sectionModel->createOrUpdate($pid, $type, $title, $content, $imagePath, $id);

    if ($sid) {
        $f3->reroute('/project/' . $pid);
    } else {
        $errors = ['Impossible de sauvegarder la section.'];
        renderView($f3, 'section/edit', [
            'title' => 'Modifier - ' . Section::getTypeName($type),
            'project' => $project,
            'section' => ['title' => $title, 'content' => $content, 'type' => $type],
            'sectionType' => $type,
            'sectionTypeName' => Section::getTypeName($type),
            'errors' => $errors,
        ]);
    }
});

// Delete section
$f3->route('GET /section/@id/delete', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $sid = (int) $f3->get('PARAMS.id');
    $sectionModel = new Section($f3->get('DB'));
    $section = $sectionModel->find($sid);
    if ($section) {
        $projectModel = new Project($f3->get('DB'));
        $project = $projectModel->find($section['project_id'], $user['id']);
        if ($project) {
            $sectionModel->delete($sid);
            $f3->reroute('/project/' . $project['id']);
        }
    }
    $f3->reroute('/dashboard');
});

// Reorder sections
$f3->route('POST /project/@id/sections/reorder', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        http_response_code(403);
        return;
    }
    $pid = (int) $f3->get('PARAMS.id');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['order'])) {
        http_response_code(400);
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if ($project) {
        $sectionModel = new Section($f3->get('DB'));
        $sectionModel->reorder($pid, $data['order']);
    }
});

// Characters listing for a project
$f3->route('GET /project/@pid/characters', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    $characterModel = new Character($f3->get('DB'));
    $characters = $characterModel->getAllByProject($pid);
    renderView($f3, 'character/list', [
        'title' => 'Personnages',
        'project' => $project,
        'characters' => $characters,
    ]);
});

// Create character form
$f3->route('GET /project/@pid/character/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    renderView($f3, 'character/create', [
        'title' => 'Créer un personnage',
        'project' => $project,
    ]);
});

// Handle new character creation
$f3->route('POST /project/@pid/character/create', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable.';
        return;
    }
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $errors = [];
    if ($name === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    if (empty($errors)) {
        $characterModel = new Character($f3->get('DB'));
        $cid = $characterModel->create($pid, $name, $description);
        if ($cid) {
            $f3->reroute('/project/' . $pid . '/characters');
        } else {
            $errors[] = 'Impossible de créer le personnage.';
        }
    }
    renderView($f3, 'character/create', [
        'title' => 'Créer un personnage',
        'project' => $project,
        'errors' => $errors,
        'old' => ['name' => htmlspecialchars($name), 'description' => htmlspecialchars($description)],
    ]);
});

// Edit character form
$f3->route('GET /character/@id/edit', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $cid = (int) $f3->get('PARAMS.id');
    $characterModel = new Character($f3->get('DB'));
    $character = $characterModel->find($cid);
    if (!$character) {
        http_response_code(404);
        echo 'Personnage introuvable.';
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($character['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo 'Accès refusé.';
        return;
    }
    renderView($f3, 'character/edit', [
        'title' => 'Modifier le personnage',
        'project' => $project,
        'character' => $character,
    ]);
});

// Handle character update
$f3->route('POST /character/@id/edit', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $cid = (int) $f3->get('PARAMS.id');
    $characterModel = new Character($f3->get('DB'));
    $character = $characterModel->find($cid);
    if (!$character) {
        http_response_code(404);
        echo 'Personnage introuvable.';
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($character['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo 'Accès refusé.';
        return;
    }
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $errors = [];
    if ($name === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    if (empty($errors)) {
        $characterModel->update($cid, $name, $description);
        $f3->reroute('/project/' . $project['id'] . '/characters');
    }
    renderView($f3, 'character/edit', [
        'title' => 'Modifier le personnage',
        'project' => $project,
        'character' => array_merge($character, ['name' => $name, 'description' => $description]),
        'errors' => $errors,
    ]);
});

// Delete character
$f3->route('GET /character/@id/delete', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        $f3->reroute('/login');
    }
    $cid = (int) $f3->get('PARAMS.id');
    $characterModel = new Character($f3->get('DB'));
    $character = $characterModel->find($cid);
    if ($character) {
        $projectModel = new Project($f3->get('DB'));
        $project = $projectModel->find($character['project_id'], $user['id']);
        if ($project) {
            $characterModel->delete($cid);
            $f3->reroute('/project/' . $project['id'] . '/characters');
        }
    }
    $f3->reroute('/dashboard');
});

// Add a comment (annotation) to a chapter. Expects JSON payload with
// start_pos, end_pos and content.
$f3->route('POST /chapter/@id/comment', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        http_response_code(403);
        echo 'Connexion requise';
        return;
    }
    $cid = (int) $f3->get('PARAMS.id');
    $chapterModel = new Chapter($f3->get('DB'));
    $chapter = $chapterModel->find($cid);
    if (!$chapter) {
        http_response_code(404);
        echo 'Chapitre introuvable';
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($chapter['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo 'Accès refusé';
        return;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['start']) || !isset($data['end']) || !isset($data['content'])) {
        http_response_code(400);
        echo 'Paramètres manquants';
        return;
    }
    $commentModel = new Comment($f3->get('DB'));
    $id = $commentModel->create($cid, (int) $data['start'], (int) $data['end'], trim($data['content']));
    if ($id) {
        echo json_encode(['id' => $id]);
    } else {
        http_response_code(500);
        echo 'Impossible de créer le commentaire';
    }
});

// Fetch comments for a chapter as JSON with excerpts. This is used by the editor
// to display existing annotations. It requires authentication and that the
// chapter belongs to the current user.
$f3->route('GET /chapter/@id/comments', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        http_response_code(403);
        echo json_encode(['error' => 'Connexion requise']);
        return;
    }
    $cid = (int) $f3->get('PARAMS.id');
    $chapterModel = new Chapter($f3->get('DB'));
    $chapter = $chapterModel->find($cid);
    if (!$chapter) {
        http_response_code(404);
        echo json_encode(['error' => 'Chapitre introuvable']);
        return;
    }
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($chapter['project_id'], $user['id']);
    if (!$project) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        return;
    }
    $commentModel = new Comment($f3->get('DB'));
    $comments = $commentModel->getByChapter($cid);
    $content = $chapter['content'] ?? '';
    $result = [];
    foreach ($comments as $c) {
        $start = (int) $c['start_pos'];
        $end = (int) $c['end_pos'];
        $snippet = '';
        if ($start >= 0 && $end > $start) {
            $length = $end - $start;
            $snippet = mb_substr($content, $start, $length);
        }
        $result[] = [
            'id' => $c['id'],
            'start' => $start,
            'end' => $end,
            'content' => $c['content'],
            'snippet' => $snippet,
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($result);
});

// Export project as an EPUB file. Creates a minimal EPUB package containing
// all chapters concatenated into a single XHTML document. The resulting
// archive adheres to the basic EPUB specification (mimetype file first
// and uncompressed, container.xml, content.opf and content.xhtml). It
// requires the PHP Zip extension.
$f3->route('GET /project/@id/export/epub', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        header('Location: /login');
        exit;
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable';
        return;
    }
    $chapterModel = new Chapter($f3->get('DB'));
    $chapters = array_filter($chapterModel->getAllByProject($pid), fn($c) => ($c['is_exported'] ?? 1));
    $sectionModel = new Section($f3->get('DB'));
    $sectionsBeforeChapters = array_filter($sectionModel->getBeforeChapters($pid), fn($s) => ($s['is_exported'] ?? 1));
    $sectionsAfterChapters = array_filter($sectionModel->getAfterChapters($pid), fn($s) => ($s['is_exported'] ?? 1));
    // Prepare temporary working directory
    $baseDir = sys_get_temp_dir() . '/epub_' . uniqid();
    $oebpsDir = $baseDir . '/OEBPS';
    $metaDir = $baseDir . '/META-INF';
    if (!mkdir($oebpsDir, 0777, true) || !mkdir($metaDir, 0777, true)) {
        http_response_code(500);
        echo 'Erreur lors de la création des répertoires temporaires';
        return;
    }
    // Write mimetype file (must be first and uncompressed)
    file_put_contents($baseDir . '/mimetype', 'application/epub+zip');
    // Write container.xml
    $containerXml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'
        . '<rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles>'
        . '</container>';
    file_put_contents($metaDir . '/container.xml', $containerXml);
    // Build content.xhtml
    $contentXhtml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        . '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'
        . '<html xmlns="http://www.w3.org/1999/xhtml">'
        . '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>'
        . '<title>' . htmlspecialchars($project['title'], ENT_XML1) . '</title></head>'
        . '<body>';
    $contentXhtml .= '<h1>' . htmlspecialchars($project['title'], ENT_XML1) . '</h1>';

    // Add sections before chapters
    foreach ($sectionsBeforeChapters as $sec) {
        $sectionTitle = $sec['title'] ?: Section::getTypeName($sec['type']);
        $contentXhtml .= '<div class="section section-' . htmlspecialchars($sec['type'], ENT_XML1) . '">';
        $contentXhtml .= '<h2>' . htmlspecialchars($sectionTitle, ENT_XML1) . '</h2>';
        if (!empty($sec['content'])) {
            // Content is now HTML from TinyMCE, we embed it directly
            // We wrap it to ensure it's valid XML if possible, or at least consistent
            $contentXhtml .= '<div>' . $sec['content'] . '</div>';
        }
        $contentXhtml .= '</div>';
    }

    // Build hierarchy for export
    $topLevel = [];
    $subsByParent = [];
    foreach ($chapters as $ch) {
        if ($ch['parent_id']) {
            $subsByParent[$ch['parent_id']][] = $ch;
        } else {
            $topLevel[] = $ch;
        }
    }

    // Add chapters
    $lastActId = null;
    foreach ($topLevel as $ch) {
        if ($ch['act_id'] && $ch['act_id'] !== $lastActId) {
            $contentXhtml .= '<h1 style="page-break-before: always; border-bottom: 2px solid #000; padding-bottom: 10px;">' . htmlspecialchars($ch['act_title'], ENT_XML1) . '</h1>';
            $lastActId = $ch['act_id'];
        } elseif (!$ch['act_id'] && $lastActId !== null) {
            $lastActId = null;
        }

        $contentXhtml .= '<h2>' . htmlspecialchars($ch['title'], ENT_XML1) . '</h2>';
        $contentXhtml .= '<div>' . ($ch['content'] ?? '') . '</div>';

        if (isset($subsByParent[$ch['id']])) {
            foreach ($subsByParent[$ch['id']] as $sub) {
                $contentXhtml .= '<h3>' . htmlspecialchars($sub['title'], ENT_XML1) . '</h3>';
                $contentXhtml .= '<div>' . ($sub['content'] ?? '') . '</div>';
            }
        }
    }

    // Add sections after chapters
    foreach ($sectionsAfterChapters as $sec) {
        $sectionTitle = $sec['title'] ?: Section::getTypeName($sec['type']);
        $contentXhtml .= '<div class="section section-' . htmlspecialchars($sec['type'], ENT_XML1) . '">';
        $contentXhtml .= '<h2>' . htmlspecialchars($sectionTitle, ENT_XML1) . '</h2>';
        if (!empty($sec['content'])) {
            $contentXhtml .= '<div>' . $sec['content'] . '</div>';
        }
        $contentXhtml .= '</div>';
    }

    $contentXhtml .= '</body></html>';
    file_put_contents($oebpsDir . '/content.xhtml', $contentXhtml);
    // Build content.opf (manifest and spine)
    $manifest = '<item id="content" href="content.xhtml" media-type="application/xhtml+xml"/>';
    $contentOpf = '<?xml version="1.0" encoding="UTF-8"?>\n'
        . '<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="BookId">'
        . '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title>' . htmlspecialchars($project['title'], ENT_XML1) . '</dc:title>'
        . '<dc:language>fr</dc:language>'
        . '<dc:identifier id="BookId">id-' . $pid . '</dc:identifier>'
        . '</metadata>'
        . '<manifest>' . $manifest . '</manifest>'
        . '<spine toc="ncx"><itemref idref="content"/></spine>'
        . '</package>';
    file_put_contents($oebpsDir . '/content.opf', $contentOpf);
    // Package into EPUB
    $epubFile = sys_get_temp_dir() . '/project_' . $pid . '_' . uniqid() . '.epub';
    $success = false;

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($epubFile, ZipArchive::CREATE) === true) {
            $zip->addFile($baseDir . '/mimetype', 'mimetype');
            $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $localName = substr($file, strlen($baseDir) + 1);
                if ($localName === 'mimetype')
                    continue;
                $zip->addFile($file->getPathname(), $localName);
            }
            $zip->close();
            $success = true;
        }
    } else {
        // Fallback to system 'tar' command (available on modern Windows and Linux)
        $oldCwd = getcwd();
        chdir($baseDir);
        // Create the zip using tar. -a auto-detects format by extension (.epub treated as zip)
        // or we can be explicit. On Windows bsdtar, -a -c -f works well.
        $cmd = 'tar -ac -f ' . escapeshellarg($epubFile) . ' mimetype META-INF OEBPS';
        shell_exec($cmd);
        chdir($oldCwd);
        if (file_exists($epubFile) && filesize($epubFile) > 0) {
            $success = true;
        }
    }

    if (!$success) {
        http_response_code(500);
        echo 'Erreur : Impossible de générer le fichier EPUB. L’extension PHP "zip" est manquante et la commande système "tar" a échoué.';
        return;
    }
    // Output EPUB to user
    header('Content-Type: application/epub+zip');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $project['title']) . '.epub"');
    readfile($epubFile);
    // Clean up temporary files
    // Remove directory recursively
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $fileInfo) {
        $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileInfo->getRealPath());
    }
    rmdir($baseDir);
    unlink($epubFile);
});

// Export project as an ODT file (OpenDocument Text).
// Export project as a single HTML file.
$f3->route('GET /project/@id/export/html', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        header('Location: /login');
        exit;
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable';
        return;
    }
    $chapterModel = new Chapter($f3->get('DB'));
    $chapters = $chapterModel->getAllByProject($pid);
    $sectionModel = new Section($f3->get('DB'));
    $sectionsBeforeChapters = $sectionModel->getBeforeChapters($pid);
    $sectionsAfterChapters = $sectionModel->getAfterChapters($pid);

    $title = htmlspecialchars($project['title']);

    // Helper to embed images as base64
    $embedImage = function ($path) {
        if (!$path)
            return '';
        $fullPath = __DIR__ . $path;
        if (!file_exists($fullPath))
            return '';
        $type = pathinfo($fullPath, PATHINFO_EXTENSION);
        $data = file_get_contents($fullPath);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        return "<div class='export-image'><img src='{$base64}' style='max-width: 100%; height: auto; display: block; margin: 0 auto 2rem; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'></div>";
    };

    // HTML Template with embedded CSS
    $htmlOutput = "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title}</title>
    <style>
        body { font-family: 'Georgia', serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 2rem; background-color: #f9f9f9; }
        .container { background: white; padding: 3rem; box-shadow: 0 0 20px rgba(0,0,0,0.05); border-radius: 8px; }
        h1 { text-align: center; color: #111; margin: 3rem 0 1rem; font-size: 3rem; }
        .subtitle { text-align: center; color: #666; font-size: 1.5rem; margin-bottom: 3rem; font-style: italic; }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 0.5rem; margin-top: 4rem; color: #222; }
        h3 { margin-top: 2.5rem; color: #444; font-style: italic; border-left: 4px solid #eee; padding-left: 1rem; }
        p { margin-bottom: 1.2rem; text-align: justify; }
        .act-separator { text-align: center; margin: 6rem 0 3rem; position: relative; }
        .act-separator::before { content: ''; position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #eee; z-index: 1; }
        .act-title { display: inline-block; background: white; padding: 0 2rem; position: relative; z-index: 2; text-transform: uppercase; letter-spacing: 0.3rem; color: #999; font-size: 1.2rem; }
        .section-content, .chapter-content { margin-bottom: 5rem; }
        .export-image { text-align: center; margin-bottom: 3rem; }
        @media print {
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 0; width: 100%; max-width: none; }
            .chapter-content, .section-content { page-break-after: always; }
            h2, h3 { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>{$title}</h1>";

    // 1. Render Sections BEFORE (excluding cover if we want it very first)
    // Actually, let's find the first cover section specifically
    foreach ($sectionsBeforeChapters as $sec) {
        if ($sec['type'] === 'cover') {
            if (!empty($sec['title'])) {
                $htmlOutput .= "<div class='subtitle'>" . htmlspecialchars($sec['title']) . "</div>";
            }
            $htmlOutput .= $embedImage($sec['image_path']);
            if (!empty($sec['content'])) {
                $htmlOutput .= "<div class='section-content'>" . $sec['content'] . "</div>";
            }
            break;
        }
    }

    // 2. Render other BEFORE sections
    foreach ($sectionsBeforeChapters as $sec) {
        if ($sec['type'] === 'cover')
            continue;
        $secTitle = htmlspecialchars($sec['title'] ?: Section::getTypeName($sec['type']));
        $htmlOutput .= "<div class='section-content'><h2>{$secTitle}</h2>";
        $htmlOutput .= $embedImage($sec['image_path']);
        $htmlOutput .= $sec['content'] . "</div>";
    }

    $topLevel = [];
    $subsByParent = [];
    foreach ($chapters as $ch) {
        if ($ch['parent_id'])
            $subsByParent[$ch['parent_id']][] = $ch;
        else
            $topLevel[] = $ch;
    }

    // 3. Render Chapters grouped by Acts
    $lastActId = null;
    foreach ($topLevel as $ch) {
        if ($ch['act_id'] && $ch['act_id'] !== $lastActId) {
            $actTitle = htmlspecialchars($ch['act_title']);
            $htmlOutput .= "<div class='act-separator'><span class='act-title'>{$actTitle}</span></div>";
            $lastActId = $ch['act_id'];
        }
        $chTitle = htmlspecialchars($ch['title']);
        $htmlOutput .= "<div class='chapter-content'><h2>{$chTitle}</h2>" . $ch['content'];

        if (isset($subsByParent[$ch['id']])) {
            foreach ($subsByParent[$ch['id']] as $sub) {
                $subTitle = htmlspecialchars($sub['title']);
                $htmlOutput .= "<h3>{$subTitle}</h3>" . $sub['content'];
            }
        }
        $htmlOutput .= "</div>";
    }

    // 4. Render Sections AFTER
    foreach ($sectionsAfterChapters as $sec) {
        $secTitle = htmlspecialchars($sec['title'] ?: Section::getTypeName($sec['type']));
        $htmlOutput .= "<div class='section-content'><h2>{$secTitle}</h2>";
        $htmlOutput .= $embedImage($sec['image_path']);
        $htmlOutput .= $sec['content'] . "</div>";
    }

    $htmlOutput .= "</div></body></html>";

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $project['title']) . '.html"');
    echo $htmlOutput;
});

// Display a simple mindmap view. This is a placeholder that lists the
// characters of a project. A full mindmap would require a JS library
// such as D3.js and custom relationships between nodes.
$f3->route('GET /project/@id/mindmap', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        header('Location: /login');
        exit;
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable';
        return;
    }
    $characterModel = new Character($f3->get('DB'));
    $characters = $characterModel->getAllByProject($pid);
    renderView($f3, 'project/mindmap', [
        'title' => 'Carte mentale',
        'project' => $project,
        'characters' => $characters,
    ]);
});

// Export project as a plain text file concatenating chapters. A proper
// implementation could use PHPWord to generate Word/Epub files.
$f3->route('GET /project/@id/export', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        header('Location: /login');
        exit;
    }
    $pid = (int) $f3->get('PARAMS.id');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable';
        return;
    }
    $chapterModel = new Chapter($f3->get('DB'));
    $chapters = array_filter($chapterModel->getAllByProject($pid), fn($c) => ($c['is_exported'] ?? 1));
    $sectionModel = new Section($f3->get('DB'));
    $sectionsBefore = array_filter($sectionModel->getBeforeChapters($pid), fn($s) => ($s['is_exported'] ?? 1));
    $sectionsAfter = array_filter($sectionModel->getAfterChapters($pid), fn($s) => ($s['is_exported'] ?? 1));

    $filename = 'projet_' . $pid . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "================================================\n";
    echo "   " . mb_strtoupper($project['title']) . "\n";
    echo "================================================\n\n";

    // Helper for cleaning HTML to text
    $cleanText = function ($html) {
        if (empty($html))
            return '';
        // Replace block-level tags with newlines to preserve structure
        $text = str_replace(['</p>', '</div>', '</h1>', '</h2>', '<h3>', '</h4>', '</h5>', '</h6>'], "\n", $html);
        $text = str_replace(['<br>', '<br />', '<br/>'], "\n", $text);
        // Strip all remaining tags
        $text = strip_tags($text);
        // Decode HTML entities (e.g., &nbsp;, &eacute;)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Final trim and return
        return trim($text);
    };

    foreach ($sectionsBefore as $sec) {
        $title = $sec['title'] ?: Section::getTypeName($sec['type']);
        echo "### " . mb_strtoupper($title) . " ###\n\n";
        echo $cleanText($sec['content'] ?? '') . "\n\n";
    }

    // Build hierarchy
    $topLevel = [];
    $subsByParent = [];
    foreach ($chapters as $ch) {
        if ($ch['parent_id']) {
            $subsByParent[$ch['parent_id']][] = $ch;
        } else {
            $topLevel[] = $ch;
        }
    }

    $lastActId = null;
    foreach ($topLevel as $ch) {
        if ($ch['act_id'] && $ch['act_id'] !== $lastActId) {
            echo "\n========== " . mb_strtoupper($ch['act_title']) . " ==========\n\n";
            $lastActId = $ch['act_id'];
        } elseif (!$ch['act_id'] && $lastActId !== null) {
            $lastActId = null;
        }
        echo "[ " . $ch['title'] . " ]\n";
        echo str_repeat("-", mb_strlen($ch['title']) + 4) . "\n";

        $content = $ch['content'] ?? '';
        echo $cleanText($content) . "\n\n";

        if (isset($subsByParent[$ch['id']])) {
            foreach ($subsByParent[$ch['id']] as $sub) {
                echo "   > " . $sub['title'] . "\n";
                echo "     " . str_repeat("~", mb_strlen($sub['title'])) . "\n";
                $subContent = $cleanText($sub['content'] ?? '');
                echo "     " . str_replace("\n", "\n     ", $subContent) . "\n\n";
            }
        }
    }

    foreach ($sectionsAfter as $sec) {
        $title = $sec['title'] ?: Section::getTypeName($sec['type']);
        echo "### " . mb_strtoupper($title) . " ###\n\n";
        echo $cleanText($sec['content'] ?? '') . "\n\n";
    }
});

// Reorder chapters via drag‑and‑drop. Accepts JSON payload {order: [id1,id2,...]}
$f3->route('POST /project/@pid/chapters/reorder', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        http_response_code(403);
        echo 'Connexion requise';
        return;
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        echo 'Projet introuvable';
        return;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['order']) || !is_array($data['order'])) {
        http_response_code(400);
        echo 'Format invalide';
        return;
    }
    $chapterModel = new Chapter($f3->get('DB'));
    $success = $chapterModel->reorder($pid, array_map('intval', $data['order']));
    if ($success) {
        echo 'OK';
    } else {
        http_response_code(500);
        echo 'Erreur de mise à jour';
    }
});

// Change theme: store the selected theme in a cookie and redirect back. Themes
// correspond to CSS variable overrides defined in public/theme-*.css. A cookie
// persists the selection for a month.
$f3->route('POST /theme', function (Base $f3) {
    $theme = $_POST['theme'] ?? 'default';
    $allowed = ['default', 'dark', 'modern'];
    if (!in_array($theme, $allowed)) {
        $theme = 'default';
    }
    setcookie('theme', $theme, time() + 30 * 24 * 60 * 60, '/');
    // Redirect back to referring page or home
    $ref = $_SERVER['HTTP_REFERER'] ?? $f3->get('BASE') . '/';
    header('Location: ' . $ref);
    exit;
});

// Endpoint to fetch synonyms for a given word via GET
$f3->route('GET /synonyms/@word', function (Base $f3) {
    $word = $f3->get('PARAMS.word');
    $syns = Synonyms::get($word);
    header('Content-Type: application/json');
    echo json_encode($syns);
});

// Toggle element export status
$f3->route('POST /project/@pid/export-toggle', function (Base $f3) {
    $user = currentUser($f3);
    if (!$user) {
        http_response_code(403);
        return;
    }
    $pid = (int) $f3->get('PARAMS.pid');
    $projectModel = new Project($f3->get('DB'));
    $project = $projectModel->find($pid, $user['id']);
    if (!$project) {
        http_response_code(404);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $type = $data['type'] ?? '';
    $id = (int) ($data['id'] ?? 0);
    $isExported = (int) ($data['is_exported'] ?? 1);

    if ($type === 'chapter') {
        $f3->get('DB')->execute_query(
            "UPDATE chapters SET is_exported = ? WHERE id = ? AND project_id = ?",
            [$isExported, $id, $pid]
        );
    } elseif ($type === 'section') {
        $f3->get('DB')->execute_query(
            "UPDATE sections SET is_exported = ? WHERE id = ? AND project_id = ?",
            [$isExported, $id, $pid]
        );
    }
    echo 'OK';
});

// Kick off routing
$f3->run();