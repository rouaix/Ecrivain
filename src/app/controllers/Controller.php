<?php

abstract class Controller
{
    /** @var Base */
    protected $f3;

    /** @var DB\SQL */
    protected $db;

    public function __construct()
    {
        $this->f3 = Base::instance();
        $this->db = $this->f3->get('DB');
    }

    /**
     * Middleware executed before routing to the action.
     */
    public function beforeRoute(Base $f3)
    {
        // DEBUG SESSION
        $dir = __DIR__;
        while (!file_exists($dir . '/vendor') && dirname($dir) !== $dir) {
            $dir = dirname($dir);
        }
        $logDir = $dir . '/data';
        if (!is_dir($logDir) && is_writable($dir)) {
            mkdir($logDir, 0777, true);
        }
        $file = $logDir . '/auth_debug.log';
        if (!is_writable($logDir)) {
            $file = sys_get_temp_dir() . '/auth_debug.log';
        }

        $msg = date('[Y-m-d H:i:s] ') . "Controller::beforeRoute URI: " . $f3->get('URI') . " | SessionID: " . session_id() . " | UserID: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
        file_put_contents($file, $msg, FILE_APPEND);

        $this->checkAutoLogin($f3);

        // Global pre-route logic (e.g. check maintenance mode)
        // Check CSRF on POST
        if ($f3->get('VERB') === 'POST') {
            $this->requireCsrf();
        }

        // --- LAZY MIGRATIONS (ENSURE SCHEMA IS CORRECT ON DEPLOY) ---
        // This runs on every request to guarantee the DB schema matches the code expectations.
        // It uses try/catch to be resilient (idempotent-ish).
        try {
            // ACTS: Rename description -> content
            $this->db->exec("ALTER TABLE acts CHANGE description content TEXT");
        } catch (\Exception $e) {
        }
        try {
            // ACTS: Add resume column
            $this->db->exec("ALTER TABLE acts ADD COLUMN resume TEXT");
        } catch (\Exception $e) {
        }

        try {
            // CHAPTERS: Rename description -> resume
            $this->db->exec("ALTER TABLE chapters CHANGE description resume TEXT");
        } catch (\Exception $e) {
            // If rename failed (e.g. description doesn't exist), try adding resume
            try {
                $this->db->exec("ALTER TABLE chapters ADD COLUMN resume TEXT");
            } catch (\Exception $e2) {
            }
        }

        // COMMENT: Add comment column to core tables
        try {
            $this->db->exec("ALTER TABLE acts ADD COLUMN `comment` TEXT");
        } catch (\Exception $e) {
        }
        try {
            $this->db->exec("ALTER TABLE chapters ADD COLUMN `comment` TEXT");
        } catch (\Exception $e) {
        }
        try {
            $this->db->exec("ALTER TABLE characters ADD COLUMN `comment` TEXT");
        } catch (\Exception $e) {
        }
        try {
            $this->db->exec("ALTER TABLE notes ADD COLUMN `comment` TEXT");
        } catch (\Exception $e) {
        }
        try {
            $this->db->exec("ALTER TABLE projects ADD COLUMN `comment` TEXT");
        } catch (\Exception $e) {
        }
        try {
            $this->db->exec("ALTER TABLE sections ADD COLUMN `comment` TEXT");
        } catch (\Exception $e) {
        }
    }

    protected function checkAutoLogin(Base $f3)
    {
        $token = $f3->get('GET.token');
        if (!$token) {
            return;
        }

        // DEBUG
        $logFile = 'data/login_debug.log';
        $log = function ($msg) use ($logFile) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
        };
        $log("checkAutoLogin called with token: " . substr($token, 0, 10) . "...");

        $userId = null;
        $fileToUpdate = null;
        $loadedTokens = [];

        // Check for new format: base64(email).hex
        if (strpos($token, '.') !== false) {
            list($b64Email, ) = explode('.', $token, 2);
            $email = base64_decode($b64Email, true);

            $log("Decoded email: " . ($email ? $email : 'FALSE'));

            if ($email) {
                $checkFile = 'data/auth_' . $email . '_tokens.json';
                $log("Checking file: " . $checkFile);
                if (file_exists($checkFile)) {
                    $content = file_get_contents($checkFile);
                    $data = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'][$token])) {
                        $tokenData = $data['tokens'][$token];
                        $userId = $tokenData['user_id'];
                        $fileToUpdate = $checkFile; // If we wanted to update last_used, etc.
                        $log("Token found. UserID: " . $userId);
                    } else {
                        $log("Token not found in file or JSON error.");
                    }
                } else {
                    $log("File not found.");
                }
            }
        } else {
            $log("Old token format detected.");
        }

        // Fallback: Legacy global file
        if (!$userId) {
            $jsonFile = 'data/auth_tokens.json';
            if (file_exists($jsonFile)) {
                $content = file_get_contents($jsonFile);
                $data = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'][$token])) {
                    $tokenData = $data['tokens'][$token];
                    $userId = $tokenData['user_id'];
                    $log("Found in legacy file.");
                }
            }
        }

        if (!$userId) {
            $log("Authentication failed.");
            return;
        }

        // Login
        // Ensure session is started (F3 handles this usually, but index.php called session_start)
        // session_regenerate_id(true); // DISABLED CAUSE OF PROD ISSUE
        $_SESSION['user_id'] = $userId;
        $log("Authenticated UserID: " . $userId);

        // Explicitly close session to ensure write before redirect
        session_write_close();

        // Redirect to remove token from URL
        $pattern = $f3->get('PATTERN');
        // If on home or login page, go to dashboard
        if ($pattern === '/' || $pattern === '/login') {
            $f3->reroute('/dashboard');
        } else {
            $f3->reroute($pattern);
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

        $this->f3->set('content', \Template::instance()->render($view));
        echo \Template::instance()->render('layouts/main.html');
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
     * Verify CSRF token.
     */
    protected function requireCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            $this->f3->error(403, 'Token CSRF invalide.');
        }
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
     * Log AI usage.
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

        $configFile = 'data/' . $user['email'] . '/ai_config.json';
        if (!file_exists($configFile)) {
            return $defaultPrompt;
        }

        $config = json_decode(file_get_contents($configFile), true);
        if (!is_array($config)) {
            return $defaultPrompt;
        }

        $system = trim($config['system'] ?? '');
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
            $configFile = 'data/' . $user['email'] . '/ai_config.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                if (is_array($config)) {
                    foreach (array_keys($defaults) as $key) {
                        $value = trim($config[$key] ?? '');
                        if ($value !== '') {
                            $prompts[$key] = $value;
                        }
                    }
                    if (isset($config['custom_prompts']) && is_array($config['custom_prompts'])) {
                        foreach ($config['custom_prompts'] as $item) {
                            if (!is_array($item)) {
                                continue;
                            }
                            $label = trim((string) ($item['label'] ?? ''));
                            $prompt = trim((string) ($item['prompt'] ?? ''));
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
}
