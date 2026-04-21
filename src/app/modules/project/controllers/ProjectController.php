<?php

class ProjectController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        $pattern = $f3->get('PATTERN');
        Logger::debug('project', 'beforeRoute', ['pattern' => $pattern, 'verb' => $f3->get('VERB')]);

        // Skip login check AND CSRF check for setTheme to allow theme switching on login/register pages
        if ($pattern === '/theme') {
            $this->checkAutoLogin($f3);
            return;
        }

        parent::beforeRoute($f3);

        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function setTheme()
    {
        $theme = $this->f3->get('POST.theme');
        Logger::debug('project', 'setTheme called', ['theme' => $theme]);

        if (in_array($theme, ['default', 'sepia', 'dark', 'modern', 'paper', 'midnight', 'deep', 'studio', 'writer', 'rouge', 'blue', 'forest', 'moderne'])) {

            if (headers_sent($file, $line)) {
                Logger::warn('project', 'setTheme: headers already sent', ['file' => $file, 'line' => $line]);
                return;
            }

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            $domain = $this->f3->get('SESSION_DOMAIN');
            Logger::debug('project', 'setTheme: setting cookie', ['domain' => $domain, 'https' => $isHttps]);

            setcookie('theme', '', time() - 3600, '/', '', $isHttps, false);
            setcookie('theme', '', time() - 3600, '/', $this->f3->get('HOST'), $isHttps, false);

            $res = setcookie(
                'theme',
                $theme,
                [
                    'expires'  => time() + (3600 * 24 * 30),
                    'path'     => '/',
                    'domain'   => $domain,
                    'secure'   => $isHttps,
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]
            );

            Logger::debug('project', 'setTheme: cookie result', ['success' => $res]);

            $_COOKIE['theme'] = $theme;
            $this->f3->sync('COOKIE');
        } else {
            Logger::warn('project', 'setTheme: invalid theme', ['theme' => $theme]);
        }

        // Prevent open redirect - only allow internal redirects
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $base    = $this->f3->get('BASE');
        $host    = $this->f3->get('HOST');

        if ($referer && parse_url($referer, PHP_URL_HOST) === $host) {
            $this->f3->reroute($referer);
        } else {
            $this->f3->reroute($base . '/dashboard');
        }
    }

    public function dashboard()
    {
        $user = $this->currentUser();
        $svc  = new ProjectService($this->db);

        $projects = $svc->getOwnedProjects($user['id']);

        // Attach tags to each owned project
        $projectIds = array_map('intval', array_column($projects, 'id'));
        $tagsMap    = $svc->getTagsForProjects($projectIds);
        foreach ($projects as &$proj) {
            $proj['tags'] = $tagsMap[(int) $proj['id']] ?? [];
        }
        unset($proj);

        // Daily goal widget
        $profileFile = $this->getUserDataDir($user['email']) . 'profile.json';
        $profileData = file_exists($profileFile)
            ? (json_decode(file_get_contents($profileFile), true) ?: [])
            : [];
        $dailyGoal   = max(0, (int) ($profileData['daily_goal'] ?? 0));
        $daily       = $svc->getDailyProgress($user['id'], $dailyGoal);

        $this->render('project/dashboard', [
            'title'              => 'Tableau de bord',
            'projects'           => $projects,
            'user'               => $user,
            'sharedProjects'     => $svc->getSharedProjects($user['id']),
            'pendingInvitations' => $svc->getPendingInvitations($user['id']),
            'allTags'            => $svc->getAllTags($user['id']),
            'dailyGoal'          => $dailyGoal,
            'wordsToday'         => $daily['wordsToday'],
            'dailyPct'           => $daily['dailyPct'],
        ]);
    }

    public function create()
    {
        $templates       = [];
        $defaultTemplate = null;
        $db              = $this->f3->get('DB');
        if ($db->exists('templates')) {
            $templateModel   = new ProjectTemplate();
            $user            = $this->currentUser();
            $templates       = $templateModel->getAllAvailable($user['id']);
            $defaultTemplate = $templateModel->getDefault();
        }

        $this->render('project/create', [
            'title'     => 'Nouveau projet',
            'templates' => $templates,
            'old'       => [
                'title'          => '',
                'description'    => '',
                'words_per_page' => 350,
                'target_pages'   => 0,
                'target_words'   => 0,
                'tags'           => '',
                'template_id'    => $defaultTemplate['id'] ?? ''
            ]
        ]);
    }

    public function store()
    {
        $f3          = Base::instance();
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $target      = intval($_POST['target_words'] ?? 0);
        $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
        $targetPages = intval($_POST['target_pages'] ?? 0);
        $templateId  = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
        $tagsRaw     = trim($_POST['tags'] ?? '');

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre du projet est obligatoire.';
        }

        if (empty($errors)) {
            if (!$templateId && $this->f3->get('DB')->exists('templates')) {
                $templateModel = new ProjectTemplate();
                $defaultTemplate = $templateModel->getDefault();
                $templateId = $defaultTemplate['id'] ?? null;
            }

            $projectModel              = new Project();
            $projectModel->user_id     = $this->currentUser()['id'];
            $projectModel->title       = $title;
            $projectModel->description = $description;
            $projectModel->target_words = $target;
            $projectModel->words_per_page = $wordsPerPage;
            $projectModel->target_pages = $targetPages;
            $projectModel->template_id = $templateId;

            try {
                if ($projectModel->save()) {
                    $this->saveProjectTags((int)$projectModel->id, $this->currentUser()['id'], $tagsRaw);
                    $this->f3->reroute('/project/' . $projectModel->id);
                } else {
                    $errors[] = 'Impossible de créer le projet.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }

        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user          = $this->currentUser();
            $templates     = $templateModel->getAllAvailable($user['id']);
        }

        $this->render('project/create', [
            'title'     => 'Nouveau projet',
            'errors'    => $errors,
            'templates' => $templates,
            'old'       => [
                'title'          => htmlspecialchars($title),
                'description'    => htmlspecialchars($description),
                'target_words'   => $target,
                'words_per_page' => $wordsPerPage,
                'target_pages'   => $targetPages,
                'template_id'    => $templateId
            ],
        ]);
    }

    public function show()
    {
        $pid = (int) $this->f3->get('PARAMS.id');

        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }

        $isOwner      = $this->isOwner($pid);
        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=?', $pid]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }

        $data = (new ProjectShowService($this->f3, $this->currentUser()))
            ->load($pid, $project[0], $isOwner);

        $this->render('project/show.html', array_merge(
            ['title' => 'Projet: ' . htmlspecialchars($project[0]['title'])],
            $data
        ));
    }


    public function edit()
    {
        $pid          = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404);
            return;
        }

        $projectData                    = $project[0];
        $projectData['lines_per_page']  = $projectData['lines_per_page'] ?? 38;

        $settings                = json_decode($projectData['settings'] ?? '{}', true);
        $projectData['author']   = $settings['author'] ?? '';

        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user          = $this->currentUser();
            $templates     = $templateModel->getAllAvailable($user['id']);
        }

        // Load existing tags for this project
        $existingTags = $this->getProjectTags($pid);
        $projectData['tags_string'] = implode(', ', array_column($existingTags, 'name'));

        $fileModel   = new ProjectFile();
        $rawImgFiles = $fileModel->find(['project_id=? AND filetype LIKE ?', $pid, 'image/%']);
        $imageFiles  = [];
        if ($rawImgFiles) {
            foreach ($rawImgFiles as $f) {
                $imageFiles[] = $f->cast();
            }
        }

        $this->render('project/edit.html', [
            'title'      => 'Modifier le projet',
            'project'    => $projectData,
            'templates'  => $templates,
            'imageFiles' => $imageFiles,
            'errors'     => []
        ]);
    }

    public function update()
    {
        $pid          = (int) $this->f3->get('PARAMS.id');
        $user         = $this->currentUser();
        $projectModel = new Project();
        $projectModel->load(['id=? AND user_id=?', $pid, $user['id']]);

        if ($projectModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $author       = trim($_POST['author'] ?? '');
        $comment      = $_POST['comment'] ?? '';
        $target       = intval($_POST['target_words'] ?? 0);
        $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
        $linesPerPage = intval($_POST['lines_per_page'] ?? 38);
        $targetPages  = intval($_POST['target_pages'] ?? 0);
        $templateId   = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
        $tagsRaw      = trim($_POST['tags'] ?? '');

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (empty($errors)) {
            // Lazy Migration: Ensure columns exist
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN lines_per_page INT DEFAULT 38"); } catch (\Exception $e) {}
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN settings TEXT"); } catch (\Exception $e) {}
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN cover_image TEXT"); } catch (\Exception $e) {}

            $coverFromFile   = intval($_POST['cover_from_file'] ?? 0);
            $newFileUploaded = isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE;

            if ($newFileUploaded) {
                $validation = $this->validateImageUpload($_FILES['cover_image']);

                if (!$validation['success']) {
                    $errors[] = $validation['error'];
                    error_log("Cover upload validation failed: " . $validation['error']);
                } else {
                    $uploadDir = 'data/' . $user['email'] . '/projects/' . $pid . '/';
                    $imgSvc    = new ImageUploadService();
                    $dest      = $imgSvc->move($_FILES['cover_image'], $validation['extension'], $uploadDir, 'cover');

                    if ($dest) {
                        $newFilename = basename($dest);
                        if (!empty($projectModel->cover_image) && $projectModel->cover_image !== $newFilename) {
                            $imgSvc->deleteOld($uploadDir, 'cover.*', $newFilename);
                        }
                        $projectModel->cover_image = $newFilename;
                    } else {
                        $errors[] = 'Erreur lors de l\'upload de l\'image.';
                    }
                }
            } elseif ($coverFromFile > 0) {
                $srcFileModel = new ProjectFile();
                $srcFileModel->load(['id=? AND project_id=?', $coverFromFile, $pid]);

                if (!$srcFileModel->dry()) {
                    $absoluteSource = $this->f3->get('ROOT') . '/' . $srcFileModel->filepath;

                    if (file_exists($absoluteSource)) {
                        $ext       = strtolower(pathinfo($srcFileModel->filepath, PATHINFO_EXTENSION));
                        $uploadDir = 'data/' . $user['email'] . '/projects/' . $pid . '/';

                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $filename   = 'cover.' . $ext;
                        $targetPath = $uploadDir . $filename;

                        if (copy($absoluteSource, $targetPath)) {
                            if (!empty($projectModel->cover_image) && $projectModel->cover_image !== $filename) {
                                $oldFile = $uploadDir . $projectModel->cover_image;
                                if (file_exists($oldFile)) {
                                    unlink($oldFile);
                                }
                            }
                            $projectModel->cover_image = $filename;
                        } else {
                            $errors[] = 'Erreur lors de la copie de l\'image existante.';
                        }
                    }
                }
            }

            $oldTemplateId = (int)($projectModel->template_id ?? 0);

            $projectModel->title          = $title;
            $projectModel->description    = $description;
            $projectModel->comment        = $comment;
            $projectModel->target_words   = $target;
            $projectModel->words_per_page = $wordsPerPage;
            $projectModel->lines_per_page = $linesPerPage;
            $projectModel->target_pages   = $targetPages;
            if ($templateId) {
                $projectModel->template_id = $templateId;
            }

            $currentSettings           = json_decode($projectModel->settings ?? '{}', true) ?: [];
            $currentSettings['author'] = $author;
            $projectModel->settings    = json_encode($currentSettings);

            error_log("Saving Author: " . $author);
            error_log("Settings JSON: " . $projectModel->settings);

            $projectModel->save();
            $this->saveProjectTags($pid, $user['id'], $tagsRaw);

            // Migrate elements to new template if template changed
            if ($templateId && $templateId !== $oldTemplateId && $oldTemplateId > 0) {
                $this->migrateElementsOnTemplateChange($pid, $oldTemplateId, $templateId);
            }

            $this->f3->reroute('/project/' . $pid);
        }

        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user          = $this->currentUser();
            $templates     = $templateModel->getAllAvailable($user['id']);
        }

        $rawImgFilesErr = (new ProjectFile())->find(['project_id=? AND filetype LIKE ?', $pid, 'image/%']);
        $imageFilesErr  = [];
        if ($rawImgFilesErr) {
            foreach ($rawImgFilesErr as $f) {
                $imageFilesErr[] = $f->cast();
            }
        }

        $this->render('project/edit.html', [
            'title'      => 'Modifier le projet',
            'project'    => $projectModel->cast(),
            'templates'  => $templates,
            'imageFiles' => $imageFilesErr,
            'errors'     => $errors,
        ]);
    }

    public function delete()
    {
        $pid          = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $projectModel->load(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);
        if (!$projectModel->dry()) {
            $projectModel->erase();
        }
        $this->f3->reroute('/dashboard');
    }

    public function cover()
    {
        $pid = (int) $this->f3->get('PARAMS.id');

        if (!$this->canAccessProject($pid)) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=?', $pid]);

        if (!$project || empty($project[0]['cover_image'])) {
            error_log("Cover not found - Project: {$pid}, Cover: " . ($project[0]['cover_image'] ?? 'none'));
            $this->f3->error(404);
            return;
        }

        $projectData    = $project[0];
        $coverImage     = $projectData['cover_image'];
        $projectDataDir = $this->getProjectDataDir($pid);
        $filePath       = $projectDataDir . '/projects/' . $pid . '/' . $coverImage;

        if (!file_exists($filePath)) {
            error_log("Cover file not found at path: {$filePath}");
            $this->f3->error(404);
            return;
        }

        $ext       = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000');
        readfile($filePath);
        exit;
    }

    // ─── Kanban status ────────────────────────────────────────────────────────

    public function updateStatus()
    {
        $pid    = (int) $this->f3->get('PARAMS.pid');
        $user   = $this->currentUser();
        $json   = json_decode($this->f3->get('BODY'), true);
        $status = $json['status'] ?? '';

        $allowed = ['active', 'review', 'done'];
        if (!in_array($status, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Statut invalide']);
            return;
        }

        $rows = $this->db->exec(
            'UPDATE projects SET status=? WHERE id=? AND user_id=?',
            [$status, $pid, $user['id']]
        );

        echo json_encode(['success' => true]);
    }

    // ─── Tag helpers ──────────────────────────────────────────────────────────

    private function saveProjectTags(int $projectId, int $userId, string $rawTags): void
    {
        // Parse comma-separated tag names, normalize
        $names = array_unique(array_filter(array_map(
            fn($t) => mb_substr(trim($t), 0, 64),
            explode(',', $rawTags)
        )));

        // Remove existing links for this project
        $this->db->exec('DELETE FROM project_tag_links WHERE project_id = ?', [$projectId]);

        foreach ($names as $name) {
            if ($name === '') continue;

            // Upsert tag for this user
            $this->db->exec(
                'INSERT INTO project_tags (user_id, name) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                [$userId, $name]
            );
            $tagId = (int) $this->db->exec('SELECT LAST_INSERT_ID() AS id')[0]['id'];

            if ($tagId > 0) {
                $this->db->exec(
                    'INSERT IGNORE INTO project_tag_links (project_id, tag_id) VALUES (?, ?)',
                    [$projectId, $tagId]
                );
            }
        }
    }

    private function getProjectTags(int $projectId): array
    {
        return $this->db->exec(
            'SELECT pt.name FROM project_tags pt
             JOIN project_tag_links ptl ON ptl.tag_id = pt.id
             WHERE ptl.project_id = ?
             ORDER BY pt.name ASC',
            [$projectId]
        ) ?: [];
    }

}
