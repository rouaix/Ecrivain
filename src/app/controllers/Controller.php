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
        // Debug logging removed after fix

        $this->checkAutoLogin($f3);

        // Check CSRF on POST
        if ($f3->get('VERB') === 'POST') {
            $this->requireCsrf();
        }
    }
    protected function checkAutoLogin(Base $f3)
    {
        $token = $f3->get('GET.token');
        if (!$token) {
            return;
        }

        $userId = null;
        $tokenId = null;

        // Get JWT secret from environment
        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;

        // Try to decode as JWT (new secure format)
        if ($jwtSecret && strpos($token, '.') !== false && substr_count($token, '.') === 2) {
            try {
                $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($jwtSecret, 'HS256'));

                // Validate token type
                if (isset($decoded->type) && $decoded->type === 'auth_token') {
                    $userId = (int)$decoded->sub;
                    $tokenId = $decoded->jti ?? null;

                    // Verify token hasn't been revoked
                    if ($tokenId && $userId) {
                        // Need to find user's email to locate token file
                        $userModel = new User();
                        $userModel->load(['id=?', $userId]);
                        if (!$userModel->dry()) {
                            $email = $userModel->email;
                            $checkFile = $this->getUserDataDir($email) . '/tokens.json';

                            if (file_exists($checkFile)) {
                                try {
                                    $encryptedContent = file_get_contents($checkFile);
                                    $decryptedContent = $this->decryptData($encryptedContent);
                                    $data = json_decode($decryptedContent, true);
                                } catch (Exception $e) {
                                    // Legacy unencrypted format
                                    $content = file_get_contents($checkFile);
                                    $data = json_decode($content, true);
                                }

                                // Check if token still exists (not revoked)
                                if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tokens'][$tokenId])) {
                                    $userId = null; // Token revoked
                                }
                            } else {
                                $userId = null; // No token file = revoked
                            }
                        } else {
                            $userId = null; // User not found
                        }
                    }
                }
            } catch (\Firebase\JWT\ExpiredException $e) {
                // Token expired
                error_log('JWT expired: ' . $e->getMessage());
            } catch (\Firebase\JWT\SignatureInvalidException $e) {
                // Invalid signature - potential tampering
                error_log('JWT signature invalid: ' . $e->getMessage());
            } catch (Exception $e) {
                // Other JWT errors
                error_log('JWT decode error: ' . $e->getMessage());
            }
        }

        // BACKWARD COMPATIBILITY: Check for old format base64(email).hex
        if (!$userId && strpos($token, '.') !== false && substr_count($token, '.') === 1) {
            list($b64Email,) = explode('.', $token, 2);
            $email = base64_decode($b64Email, true);

            if ($email) {
                $checkFile = $this->getUserDataDir($email) . '/tokens.json';
                if (file_exists($checkFile)) {
                    try {
                        $encryptedContent = file_get_contents($checkFile);
                        $decryptedContent = $this->decryptData($encryptedContent);
                        $data = json_decode($decryptedContent, true);
                    } catch (Exception $e) {
                        $content = file_get_contents($checkFile);
                        $data = json_decode($content, true);
                    }
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'][$token])) {
                        $tokenData = $data['tokens'][$token];
                        $userId = $tokenData['user_id'];
                    }
                }
            }
        }

        // BACKWARD COMPATIBILITY: Legacy global file
        if (!$userId) {
            $jsonFile = 'data/auth_tokens.json';
            if (file_exists($jsonFile)) {
                $content = file_get_contents($jsonFile);
                $data = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'][$token])) {
                    $tokenData = $data['tokens'][$token];
                    $userId = $tokenData['user_id'];
                }
            }
        }

        if (!$userId) {
            return;
        }

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        // Login
        $_SESSION['user_id'] = $userId;

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
     * Decrypt data with AES-256-GCM
     */
    protected function decryptData(string $encryptedData): string
    {
        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;
        if (!$jwtSecret) {
            throw new Exception('JWT_SECRET not configured');
        }

        $key = hash('sha256', $jwtSecret . 'encryption', true);
        $cipher = 'aes-256-gcm';
        $ivLen = openssl_cipher_iv_length($cipher);
        $tagLen = 16;

        $data = base64_decode($encryptedData);
        if ($data === false || strlen($data) < $ivLen + $tagLen) {
            throw new Exception('Invalid encrypted data');
        }

        $iv = substr($data, 0, $ivLen);
        $tag = substr($data, $ivLen, $tagLen);
        $ciphertext = substr($data, $ivLen + $tagLen);

        $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }

        return $decrypted;
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
     * Encrypt data with AES-256-GCM (vulnerability #4 fix)
     */
    protected function encryptData(string $data): string
    {
        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;
        if (!$jwtSecret) {
            throw new Exception('JWT_SECRET not configured');
        }

        // Derive encryption key from JWT_SECRET
        $key = hash('sha256', $jwtSecret . 'encryption', true);
        $cipher = 'aes-256-gcm';
        $ivLen = openssl_cipher_iv_length($cipher);
        $iv = random_bytes($ivLen);
        $tag = '';

        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }

        // Return: base64(iv + tag + ciphertext)
        return base64_encode($iv . $tag . $encrypted);
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
    /**
     * Validate image upload with multi-level security checks.
     * Fixes vulnerability #14 - Upload validated only client-side
     *
     * @param array $file The $_FILES array element
     * @param int $maxSizeMB Maximum file size in MB (default 5)
     * @return array ['success' => bool, 'error' => string|null, 'extension' => string|null]
     */
    protected function validateImageUpload(array $file, int $maxSizeMB = 5): array
    {
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Erreur lors de l\'upload.', 'extension' => null];
        }

        // 1. Validate file extension (whitelist)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            return ['success' => false, 'error' => 'Extension non autorisée. Formats acceptés : JPG, PNG, WEBP, GIF.', 'extension' => null];
        }

        // 2. Verify actual MIME type (not client-provided $_FILES['type'])
        // This prevents bypass by renaming .php to .jpg
        $imageInfo = @getimagesize($file['tmp_name']);

        $actualMimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $actualMimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }

        if (!$actualMimeType && function_exists('mime_content_type')) {
            $actualMimeType = mime_content_type($file['tmp_name']);
        }

        if (!$actualMimeType && $imageInfo !== false) {
            $actualMimeType = $imageInfo['mime'] ?? null;
        }

        if (!$actualMimeType) {
            return ['success' => false, 'error' => 'Impossible de détecter le type MIME du fichier.', 'extension' => null];
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($actualMimeType, $allowedMimeTypes)) {
            return ['success' => false, 'error' => 'Type de fichier invalide (détecté : ' . $actualMimeType . ').', 'extension' => null];
        }

        // 3. Verify it's actually a valid image (magic bytes check)
        // This prevents uploading renamed executables
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'Le fichier n\'est pas une image valide.', 'extension' => null];
        }

        // 4. Check file size limit
        $maxSize = $maxSizeMB * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Fichier trop volumineux (max ' . $maxSizeMB . ' Mo).', 'extension' => null];
        }

        // All checks passed
        return ['success' => true, 'error' => null, 'extension' => $ext];
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
        $rows = $this->db->exec(
            'SELECT COUNT(*) AS cnt
             FROM collaboration_requests cr
             JOIN projects p ON p.id = cr.project_id
             WHERE p.user_id = ? AND cr.status = "pending"',
            [$user['id']]
        );
        return (int)($rows[0]['cnt'] ?? 0);
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
