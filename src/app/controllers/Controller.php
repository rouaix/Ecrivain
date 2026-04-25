<?php

abstract class Controller
{
    /** @var Base */
    protected $f3;

    /** @var DB\SQL */
    protected $db;

    private ?TokenService $_tokenService = null;

    public function __construct()
    {
        $this->f3 = Base::instance();
        $this->db = $this->f3->get('DB');
    }

    /** Lazy-initialised TokenService (one instance per request). */
    protected function tokenService(): TokenService
    {
        if ($this->_tokenService === null) {
            $secret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? '';
            $this->_tokenService = new TokenService($secret);
        }
        return $this->_tokenService;
    }
    /**
     * Middleware executed before routing to the action.
     */
    public function beforeRoute(Base $f3)
    {
        // Debug logging removed after fix

        $this->checkAutoLogin($f3);

        // Check CSRF on POST
        if ($f3->get('VERB') === 'POST') {
            $this->requireCsrf();
        }
    }
    protected function checkAutoLogin(Base $f3): void
    {
        $token = $f3->get('GET.token');
        if (!$token) {
            return;
        }

        $svc    = $this->tokenService();
        $userId = null;

        // 1. New JWT format (3 dot-separated parts)
        if (substr_count($token, '.') === 2) {
            $decoded = $svc->decodeJwt($token);
            if ($decoded && ($decoded->type ?? '') === 'auth_token') {
                $uid = (int) ($decoded->sub ?? 0);
                $jti = $decoded->jti ?? null;
                if ($uid && $jti) {
                    $userModel = new User();
                    $userModel->load(['id=?', $uid]);
                    if (!$userModel->dry()) {
                        $tokenFile = $this->getUserDataDir($userModel->email) . '/tokens.json';
                        if ($svc->isTokenInFile($tokenFile, $jti)) {
                            $userId = $uid;
                        }
                    }
                }
            }
        }

        // 2. BACKWARD COMPAT: legacy base64(email).hex format (1 dot separator)
        if (!$userId && substr_count($token, '.') === 1) {
            [$b64Email] = explode('.', $token, 2);
            $email = base64_decode($b64Email, true);
            if ($email) {
                $filePath = $this->getUserDataDir($email) . '/tokens.json';
                $data     = $svc->readTokenFile($filePath);
                if ($data && isset($data['tokens'][$token])) {
                    $userId = (int) $data['tokens'][$token]['user_id'];
                }
            }
        }

        // 3. BACKWARD COMPAT: global unencrypted auth_tokens.json
        if (!$userId) {
            $userId = $svc->validateGlobalFallback($token, 'data/auth_tokens.json');
        }

        if (!$userId) {
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        session_write_close();

        $pattern = $f3->get('PATTERN');
        $f3->reroute(($pattern === '/' || $pattern === '/login') ? '/dashboard' : $pattern);
    }
    /**
     * Get safe user data directory path.
     */
    protected function getUserDataDir(?string $email): string
    {
        if (!$email) {
            return 'data/anonymous';
        }
        $safeEmail = $this->sanitizeEmailForPath($email);
        return 'data/' . $safeEmail;
    }
    /**
     * Sanitize email for use in filesystem paths.
     * Prevents path traversal attacks.
     */
    protected function sanitizeEmailForPath(string $email): string
    {
        // Remove any path traversal attempts
        $email = str_replace(['..', '/', '\\', "\0"], '', $email);
        // Only allow safe characters
        $email = preg_replace('/[^a-zA-Z0-9@._-]/', '', $email);
        // Validate it looks like an email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid_user';
        }
        return $email;
    }
    /**
     * Clean Quill HTML to prevent accumulation of empty paragraphs.
     * Removes consecutive empty <p> tags that Quill adds on each save.
     */
    protected function cleanQuillHtml(string $html): string
    {
        if (empty(trim($html))) {
            return $html;
        }
        $cleaned = $html;
        $cleaned = preg_replace('/(<p><br><\/p>\s*){2,}/i', '<p><br></p>', $cleaned);
        $cleaned = preg_replace('/(<p>\s*<\/p>\s*){2,}/i', '', $cleaned);
        $cleaned = preg_replace('/^(<p><br><\/p>\s*)+/i', '', $cleaned);
        $cleaned = preg_replace('/(<p><br><\/p>\s*)+$/i', '<p><br></p>', $cleaned);
        return $cleaned;
    }
    /**
     * Decrypt data with AES-256-GCM.
     * Delegates to TokenService — kept here for backward compatibility with callers.
     */
    protected function decryptData(string $encryptedData): string
    {
        return $this->tokenService()->decrypt($encryptedData);
    }
    /**
     * Validate a Bearer token (or ?token= fallback) for API routes.
     * Returns the user ID on success, null on failure.
     * Does NOT start a session or redirect.
     */
    protected function authenticateApiRequest(): ?int
    {
        $token = $this->extractBearerToken();
        if (!$token || substr_count($token, '.') !== 2) {
            return null;
        }

        $svc     = $this->tokenService();
        $decoded = $svc->decodeJwt($token);

        if (!$decoded || ($decoded->type ?? '') !== 'auth_token') {
            Logger::warn('auth', 'API token rejected', ['reason' => 'wrong_type', 'uri' => $_SERVER['REQUEST_URI'] ?? '-']);
            return null;
        }

        $uid = (int) ($decoded->sub ?? 0);
        $jti = $decoded->jti ?? null;
        if (!$uid || !$jti) {
            return null;
        }

        $userModel = new User();
        $userModel->load(['id=?', $uid]);
        if ($userModel->dry()) {
            Logger::warn('auth', 'API token: user not found', ['uid' => $uid]);
            return null;
        }

        $tokenFile = $this->getUserDataDir($userModel->email) . '/tokens.json';
        if (!$svc->isTokenInFile($tokenFile, $jti)) {
            Logger::warn('auth', 'API token: not in file or revoked', ['jti' => $jti, 'file' => $tokenFile]);
            return null;
        }

        return $uid;
    }

    /** Extract the Bearer token from the Authorization header, or fall back to ?token= query param. */
    private function extractBearerToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) && function_exists('apache_request_headers')) {
            $headers    = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
            return trim($m[1]);
        }
        return $this->f3->get('GET.token') ?: null;
    }

    /**
     * Verify CSRF token.
     */
    protected function requireCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            $_SESSION['error'] = 'Votre session a expiré. Veuillez vous reconnecter.';
            $this->f3->reroute('/login');
        }
    }
    /**
     * Middleware executed after routing to the action.
     */
    public function afterRoute(Base $f3)
    {
        // Post-route logic
    }
    /**
     * Render a view within the main layout.
     */
    protected function render(string $view, array $data = []): void
    {
        $this->f3->mset($data);
        $this->f3->set('csrfToken', $this->csrfToken());
        $currentUser = $this->currentUser();
        $this->f3->set('currentUser', $currentUser);
        $this->f3->set('base', $this->f3->get('BASE'));
        $this->f3->set('aiSystemPrompt', $this->resolveAiSystemPrompt($currentUser));
        $this->f3->set('aiUserPrompts', $this->resolveAiUserPrompts($currentUser));
        // $view is now expected to be an F3 template path, usually.
        // But legacy calls might pass 'project/show' (without extension).
        // If we are strictly refactoring to .html, we should ensure the view has .html or add it.
        // However, this task assumes all view files are now .html.
        // Let's check extension.
        if (!fnmatch('*.html', $view) && !fnmatch('*.php', $view)) {
            $view .= '.html';
        }

        // Notification counts for the header badge
        $this->f3->set('pendingCollabCount', $this->pendingCollabCount());

        // Sidebar modules — injected when a project is in context (Pro layout)
        $project = $this->f3->get('project');
        if ($project && isset($project['id'])) {
            $this->f3->set('sidebarModules', $this->loadSidebarModules($project));
        }

        // Flash messages (read + clear)
        if (!empty($_SESSION['success'])) {
            $this->f3->set('flash_success', $_SESSION['success']);
            unset($_SESSION['success']);
        }
        if (!empty($_SESSION['error'])) {
            $this->f3->set('flash_error', $_SESSION['error']);
            unset($_SESSION['error']);
        }

        $this->f3->set('content', \Template::instance()->render($view));
        $layout = 'layouts/main-pro.html';
        echo \Template::instance()->render($layout);
    }
    /**
     * Get or create CSRF token.
     */
    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    /**
     * Get current user.
     */
    protected function currentUser(): ?array
    {
        if (isset($_SESSION['user_id'])) {
            $userModel = new User();
            $user = $userModel->load(['id=?', $_SESSION['user_id']]);
            return $user ? $user->cast() : null;
        }
        return null;
    }
    /**
     * Resolve the current user's AI system prompt from config, with a safe default.
     */
    protected function resolveAiSystemPrompt(?array $user): string
    {
        $defaultPrompt = "Tu es un assistant d'écriture créative expert.";
        if (!$user || empty($user['email'])) {
            return $defaultPrompt;
        }

        $configFile = $this->getUserDataDir($user['email']) . '/ai_config.json';
        if (!file_exists($configFile)) {
            return $defaultPrompt;
        }

        $config = json_decode(file_get_contents($configFile), true);
        if (!is_array($config)) {
            return $defaultPrompt;
        }

        // New structure: prompts.system
        // Fallback: root system (legacy or migration transient)
        $system = trim($config['prompts']['system'] ?? $config['system'] ?? '');
        return $system !== '' ? $system : $defaultPrompt;
    }
    /**
     * Resolve the current user's AI user prompts (excluding system).
     */
    protected function resolveAiUserPrompts(?array $user): array
    {
        $defaults = [
            'continue' => "Continue le texte suivant de manière cohérente, dans le même style. N'ajoute pas de guillemets autour de tout le texte généré sauf si nécessaire.",
            'rephrase' => "Reformule le texte suivant pour améliorer le style, la clarté et l'élégance, sans changer le sens.",
            'summarize_chapter' => "Fais un résumé d'une dizaine de lignes du contenu suivant qui est une agrégation de sous-chapitres. Le résumé doit être captivant et bien écrit.",
            'summarize_act' => "Fais un résumé d'une dizaine de lignes pour cet Acte, basé sur les résumés de ses chapitres ci-dessous. Le résumé doit donner une bonne vue d'ensemble de l'arc narratif de l'acte."
        ];

        $prompts = $defaults;
        $custom = [];
        if ($user && !empty($user['email'])) {
            $configFile = $this->getUserDataDir($user['email']) . '/ai_config.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                if (is_array($config)) {
                    foreach (array_keys($defaults) as $key) {
                        // Check prompts[key] first, then root[key]
                        $val = $config['prompts'][$key] ?? $config[$key] ?? '';
                        $value = trim((string)$val);
                        if ($value !== '') {
                            $prompts[$key] = $value;
                        }
                    }

                    // Custom prompts might be in prompts['custom'] or root 'custom_prompts'
                    $customSource = $config['prompts']['custom'] ?? $config['custom_prompts'] ?? [];
                    if (is_array($customSource)) {
                        foreach ($customSource as $item) {
                            if (!is_array($item)) {
                                continue;
                            }
                            $label = trim((string)($item['label'] ?? ''));
                            $prompt = trim((string)($item['prompt'] ?? ''));
                            if ($label !== '' && $prompt !== '') {
                                $custom[] = [
                                    'key' => 'custom',
                                    'label' => $label,
                                    'prompt' => $prompt
                                ];
                            }
                        }
                    }
                }
            }
        }

        $builtins = [
            ['key' => 'continue', 'label' => 'Continuer', 'prompt' => $prompts['continue']],
            ['key' => 'rephrase', 'label' => 'Reformuler', 'prompt' => $prompts['rephrase']],
            ['key' => 'summarize_chapter', 'label' => 'Résumé chapitre', 'prompt' => $prompts['summarize_chapter']],
            ['key' => 'summarize_act', 'label' => 'Résumé acte', 'prompt' => $prompts['summarize_act']]
        ];
        return array_merge($builtins, $custom);
    }
    /**
     * Encrypt data with AES-256-GCM.
     * Delegates to TokenService — kept here for backward compatibility with callers.
     */
    protected function encryptData(string $data): string
    {
        return $this->tokenService()->encrypt($data);
    }
    /**
     * Log AI usage and check usage-alert threshold.
     */
    protected function logAiUsage(string $model, int $promptTokens, int $completionTokens, string $feature): void
    {
        $user = $this->currentUser();
        if (!$user)
            return;

        $usage = new AiUsage();
        $usage->user_id = $user['id'];
        $usage->model_name = $model;
        $usage->prompt_tokens = $promptTokens;
        $usage->completion_tokens = $completionTokens;
        $usage->total_tokens = $promptTokens + $completionTokens;
        $usage->feature_name = $feature;
        $usage->save();

        $this->checkAiUsageAlert($user);
    }

    /**
     * Send a usage-threshold alert email once per day if enabled.
     */
    private function checkAiUsageAlert(array $user): void
    {
        if (empty($user['email'])) return;

        $configFile = $this->getUserDataDir($user['email']) . '/ai_config.json';
        if (!file_exists($configFile)) return;

        $config = json_decode(file_get_contents($configFile), true);
        if (!is_array($config)) return;

        $notifs = $config['notifications'] ?? [];
        if (empty($notifs['usage_alert_enabled'])) return;

        $threshold = (int) ($notifs['usage_alert_threshold'] ?? 0);
        if ($threshold <= 0) return;

        // Only send once per day
        $today = date('Y-m-d');
        if (($notifs['usage_alert_sent_date'] ?? '') === $today) return;

        $usageModel = new AiUsage();
        $todayTotal = $usageModel->getTodayTotalByUser($user['id']);

        if ($todayTotal >= $threshold) {
            $notif = new NotificationService();
            if ($notif->sendUsageAlertEmail($user['email'], $todayTotal, $threshold)) {
                $config['notifications']['usage_alert_sent_date'] = $today;
                file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }
    protected function validateImageUpload(array $file, int $maxSizeMB = 5): array
    {
        return (new ImageUploadService())->validate($file, $maxSizeMB);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Permission guards — abort with 403/redirect instead of duplicating the
    // if (!$this->isOwner()) { $f3->error(403); return; } pattern everywhere.
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Abort with 403 unless the current user owns the project.
     * Call at the top of any owner-only action.
     */
    protected function requireOwner(int $projectId): void
    {
        if (!$this->isOwner($projectId)) {
            $this->f3->error(403);
        }
    }

    /**
     * Abort with 403 unless the current user has read access (owner or accepted collaborator).
     */
    protected function requireProjectAccess(int $projectId): void
    {
        if (!$this->hasProjectAccess($projectId)) {
            $this->f3->error(403);
        }
    }

    /**
     * Redirect to /login unless a user is authenticated.
     */
    protected function requireAuth(): void
    {
        if (!$this->currentUser()) {
            $this->f3->reroute('/login');
        }
    }

    /**
     * Returns true if the current user owns the given project.
     */
    protected function isOwner(int $projectId): bool
    {
        $user = $this->currentUser();
        if (!$user) return false;
        $rows = $this->db->exec(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$projectId, $user['id']]
        );
        return !empty($rows);
    }

    /**
     * Returns true if the current user is an accepted collaborator on the given project.
     */
    protected function isCollaborator(int $projectId): bool
    {
        $user = $this->currentUser();
        if (!$user) return false;
        $rows = $this->db->exec(
            'SELECT id FROM project_collaborators WHERE project_id = ? AND user_id = ? AND status = "accepted"',
            [$projectId, $user['id']]
        );
        return !empty($rows);
    }

    /**
     * Returns true if the current user is owner OR accepted collaborator on the given project.
     */
    protected function hasProjectAccess(int $projectId): bool
    {
        return $this->isOwner($projectId) || $this->isCollaborator($projectId);
    }

    /**
     * Get the email of the project's owner (creator).
     * Used to build the correct data path for images/files,
     * especially in shared/collaborative mode where the current user
     * is not the owner.
     */
    protected function getProjectOwnerEmail(int $projectId): ?string
    {
        $rows = $this->db->exec(
            'SELECT u.email FROM projects p JOIN users u ON u.id = p.user_id WHERE p.id = ?',
            [$projectId]
        );
        return !empty($rows) ? $rows[0]['email'] : null;
    }

    /**
     * Count pending collaboration requests for all projects owned by the current user.
     * Used to display the notification badge.
     */
    protected function pendingCollabCount(): int
    {
        $user = $this->currentUser();
        if (!$user) return 0;

        // Demandes de modification en attente sur les projets dont l'utilisateur est propriétaire
        $requests = $this->db->exec(
            'SELECT COUNT(*) AS cnt
             FROM collaboration_requests cr
             JOIN projects p ON p.id = cr.project_id
             WHERE p.user_id = ? AND cr.status = "pending"',
            [$user['id']]
        );

        // Invitations reçues en attente
        $invitations = $this->db->exec(
            'SELECT COUNT(*) AS cnt
             FROM project_collaborators
             WHERE user_id = ? AND status = "pending"',
            [$user['id']]
        );

        return (int)($requests[0]['cnt'] ?? 0) + (int)($invitations[0]['cnt'] ?? 0);
    }

    /**
     * Write one entry to project_activity_logs (non-blocking).
     *
     * @param int    $projectId
     * @param string $action      'create' | 'update' | 'delete'
     * @param string $entityType  'chapter' | 'act' | 'note' | 'character' | 'element' | 'glossary' | 'section'
     * @param int|null $entityId
     * @param string $entityLabel Human-readable name (title, …)
     */
    protected function logActivity(
        int $projectId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        string $entityLabel = ''
    ): void {
        $user = $this->currentUser();
        if (!$user) return;
        try {
            $this->db->exec(
                'INSERT INTO project_activity_logs (project_id, user_id, action, entity_type, entity_id, entity_label)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$projectId, $user['id'], $action, $entityType, $entityId, mb_substr($entityLabel, 0, 255)]
            );
        } catch (\Exception $e) {
            // Table may not exist yet on first run — silently ignore
        }
    }

    /**
     * Build the ordered list of content modules for the Pro sidebar.
     * Driven by the project's template; falls back to a full default set.
     * Each entry: [label, path, icon, count]
     */
    private function loadSidebarModules(array $project): array
    {
        $pid = (int) $project['id'];
        $hasNoteModule = false;

        // ── Counts (one query per table to isolate failures) ────────────────
        $cnt = [];
        $countQueries = [
            'chapter'   => 'SELECT COUNT(*) AS n FROM chapters WHERE project_id = ? AND parent_id IS NULL',
            'act'       => 'SELECT COUNT(*) AS n FROM acts             WHERE project_id = ?',
            'character' => 'SELECT COUNT(*) AS n FROM characters       WHERE project_id = ?',
            'note'      => 'SELECT COUNT(*) AS n FROM notes            WHERE project_id = ?',
            'glossary'  => 'SELECT COUNT(*) AS n FROM glossary_entries WHERE project_id = ?',
            'file'      => 'SELECT COUNT(*) AS n FROM project_files    WHERE project_id = ?',
            'scenario'  => 'SELECT COUNT(*) AS n FROM scenarios        WHERE project_id = ?',
        ];
        foreach ($countQueries as $key => $sql) {
            try {
                $r = $this->db->exec($sql, [$pid]);
                $cnt[$key] = (int)($r[0]['n'] ?? 0);
            } catch (\Exception $e) {
                $cnt[$key] = 0;
            }
        }

        // Section counts by subtype (for section template elements)
        try {
            $sRows       = $this->db->exec(
                'SELECT type, COUNT(*) AS cnt FROM sections WHERE project_id = ? GROUP BY type',
                [$pid]
            );
            $sectionCounts = [];
            foreach ($sRows as $sr) {
                $sectionCounts[$sr['type']] = (int) $sr['cnt'];
            }
        } catch (\Exception $e) {
            $sectionCounts = [];
        }

        $defaultModules = [
            ['label' => 'Structure',   'path' => '/project/'.$pid.'#chapters',    'icon' => 'list-ol',    'nb' => (int)($cnt['chapter']   ?? 0)],
            ['label' => 'Personnages', 'path' => '/project/'.$pid.'/characters',  'icon' => 'users',      'nb' => (int)($cnt['character'] ?? 0)],
            ['label' => 'Notes',       'path' => '/project/'.$pid.'/notes',       'icon' => 'sticky-note','nb' => (int)($cnt['note']      ?? 0)],
            ['label' => 'Glossaire',   'path' => '/project/'.$pid.'/glossary',    'icon' => 'book-open',  'nb' => (int)($cnt['glossary']  ?? 0)],
            ['label' => 'Fichiers',    'path' => '/project/'.$pid.'/files',       'icon' => 'paperclip',  'nb' => (int)($cnt['file']      ?? 0)],
        ];

        // ── Resolve template ────────────────────────────────────────────────
        $templateId = isset($project['template_id']) && $project['template_id']
            ? (int) $project['template_id']
            : null;

        if (!$templateId) {
            try {
                // Same fallback order as ProjectController: is_default first, then first available
                $tRows = $this->db->exec('SELECT id FROM templates WHERE is_default = 1 LIMIT 1');
                if (!$tRows) {
                    $tRows = $this->db->exec('SELECT id FROM templates ORDER BY id ASC LIMIT 1');
                }
                $templateId = $tRows ? (int) $tRows[0]['id'] : null;
            } catch (\Exception $e) {
                $templateId = null;
            }
        }

        if (!$templateId) {
            return $defaultModules;
        }

        // ── Template elements ───────────────────────────────────────────────
        try {
            $teRows = $this->db->exec(
                'SELECT id, element_type, element_subtype, section_placement, config_json
                 FROM template_elements
                 WHERE template_id = ? AND is_enabled = 1
                 ORDER BY display_order ASC',
                [$templateId]
            );
        } catch (\Exception $e) {
            return $defaultModules;
        }

        if (empty($teRows)) {
            return $defaultModules;
        }

        $iconMap = [
            'chapter'   => 'list-ol',
            'act'       => 'layer-group',
            'section'   => 'bookmark',
            'character' => 'users',
            'note'      => 'sticky-note',
            'file'      => 'paperclip',
            'scenario'  => 'film',
            'synopsis'  => 'file-alt',
            'element'   => 'puzzle-piece',
            'glossary'  => 'book-open',
        ];

        $modules = [];

        foreach ($teRows as $te) {
            $type = $te['element_type'];
            $cfg  = json_decode($te['config_json'] ?? '{}', true);

            if ($type === 'chapter') {
                $modules[] = [
                    'label' => $cfg['label_plural'] ?? 'Chapitres',
                    'path'  => '/project/'.$pid.'/chapters',
                    'icon'  => 'list-ol',
                    'nb'    => (int)($cnt['chapter'] ?? 0),
                ];
                continue;
            }

            if ($type === 'act') {
                $modules[] = [
                    'label' => $cfg['label_plural'] ?? 'Actes',
                    'path'  => '/project/'.$pid.'/acts',
                    'icon'  => 'layer-group',
                    'nb'    => (int)($cnt['act'] ?? 0),
                ];
                continue;
            }

            if ($type === 'section') {
                $subtype = $te['element_subtype'] ?? '';
                $label   = $cfg['label'] ?? $cfg['label_plural'] ?? 'Sections';
                $nb      = $subtype ? ($sectionCounts[$subtype] ?? 0) : array_sum($sectionCounts);
                $path    = $subtype
                    ? '/project/'.$pid.'/section/'.$subtype
                    : '/project/'.$pid;
                $modules[] = [
                    'label' => $label,
                    'path'  => $path,
                    'icon'  => 'bookmark',
                    'nb'    => $nb,
                ];
                continue;
            }

            if ($type === 'element') {
                try {
                    $eRows  = $this->db->exec(
                        'SELECT COUNT(*) AS cnt FROM elements WHERE project_id = ? AND template_element_id = ? AND parent_id IS NULL',
                        [$pid, (int) $te['id']]
                    );
                    $eCount = (int)($eRows[0]['cnt'] ?? 0);
                } catch (\Exception $e) {
                    $eCount = 0;
                }
                $modules[] = [
                    'label' => $cfg['label_plural'] ?? 'Éléments',
                    'path'  => '/project/'.$pid.'/elements/'.(int)$te['id'],
                    'icon'  => 'puzzle-piece',
                    'nb'    => $eCount,
                ];
                continue;
            }

            $pathMap = [
                'character' => '/project/'.$pid.'/characters',
                'note'      => '/project/'.$pid.'/notes',
                'file'      => '/project/'.$pid.'/files',
                'scenario'  => '/project/'.$pid.'/scenarios',
                'synopsis'  => '/project/'.$pid.'/synopsis',
                'glossary'  => '/project/'.$pid.'/glossary',
            ];

            $defaultLabels = [
                'character' => 'Personnages',
                'note'      => 'Notes',
                'file'      => 'Fichiers',
                'scenario'  => 'Scénario',
                'synopsis'  => 'Synopsis',
                'glossary'  => 'Glossaire',
            ];

            $countMap = [
                'character' => (int)($cnt['character'] ?? 0),
                'note'      => (int)($cnt['note']      ?? 0),
                'file'      => (int)($cnt['file']      ?? 0),
                'scenario'  => (int)($cnt['scenario']  ?? 0),
                'glossary'  => (int)($cnt['glossary']  ?? 0),
                'synopsis'  => 0,
            ];

            if (!isset($pathMap[$type])) continue;

            if ($type === 'note') {
                $hasNoteModule = true;
            }

            $modules[] = [
                'label' => $cfg['label_plural'] ?? ($defaultLabels[$type] ?? ucfirst($type)),
                'path'  => $pathMap[$type],
                'icon'  => $iconMap[$type] ?? 'circle',
                'nb'    => $countMap[$type] ?? 0,
            ];
        }

        // Fallback: if notes exist but template has no built-in 'note' module, always show them
        if (!$hasNoteModule && (int)($cnt['note'] ?? 0) > 0) {
            $modules[] = [
                'label' => 'Notes',
                'path'  => '/project/'.$pid.'/notes',
                'icon'  => 'sticky-note',
                'nb'    => (int)($cnt['note'] ?? 0),
            ];
        }

        $result = $modules ?: $defaultModules;
        foreach ($result as &$m) {
            $m['active_path'] = strtok($m['path'], '#');
        }
        unset($m);
        return $result;
    }

    /**
     * Sliding-window rate limiter stored in the user session.
     *
     * @param string $key         Unique bucket name (e.g. 'ai_gen')
     * @param int    $maxRequests Maximum number of requests allowed in the window
     * @param int    $windowSecs  Rolling window duration in seconds
     * @return bool  true = request allowed, false = rate limit exceeded
     */
    protected function checkRateLimit(string $key, int $maxRequests, int $windowSecs): bool
    {
        $now = time();
        $sessionKey = '_rl_' . $key;

        // Retrieve timestamps recorded in the current window
        $timestamps = $_SESSION[$sessionKey] ?? [];

        // Discard entries that have fallen outside the rolling window
        $timestamps = array_values(
            array_filter($timestamps, fn(int $t) => ($now - $t) < $windowSecs)
        );

        if (count($timestamps) >= $maxRequests) {
            return false;
        }

        $timestamps[] = $now;
        $_SESSION[$sessionKey] = $timestamps;

        return true;
    }
}
