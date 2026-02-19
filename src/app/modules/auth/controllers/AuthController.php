<?php

class AuthController extends Controller

{

    private const PASSWORD_RESET_TTL = 3600;

    public function beforeRoute(Base $f3)

    {

        parent::beforeRoute($f3);

        // Redirect if already logged in for login/register pages

        $pattern = $f3->get('PATTERN');

        if ($this->currentUser() && ($pattern === '/login' || $pattern === '/register')) {

            $f3->reroute('/dashboard');

        }

    }

    public function login()

    {

        // Render with F3 Template

        $this->render('auth/login.html', [

            'title' => 'Connexion',

            'errors' => [],

            'success' => '',

            'old' => ['username' => '']

        ]);

    }

    private function log($msg)

    {

        // Debug logging disabled in production for security

        // Uncomment for debugging if needed

        // $f3 = Base::instance();

        // if ($f3->get('DEBUG') < 3) return;

        // error_log('[Auth] ' . $msg);

    }

    public function authenticate()

    {

        // $this->log("Authenticate called.");

        

        $username = trim($_POST['username'] ?? '');

        $password = $_POST['password'] ?? '';

        // $this->log("Username: " . $username);

        $errors = [];

        // Rate Limiting Check

        $ip = $this->f3->get('IP');

        if ($this->isRateLimited($ip)) {

            $this->log("Rate limit exceeded for IP: " . $ip);

            $errors[] = 'Trop de tentatives de connexion. Veuillez réessayer dans 15 minutes.';

            $this->render('auth/login.html', [

                'title' => 'Connexion',

                'errors' => $errors,

                'success' => '',

                'old' => ['username' => htmlspecialchars($username)],

            ]);

            return;

        }

        $userModel = new User();

        $user = $userModel->authenticate($username, $password);

        if ($user) {

            $this->log("User authenticated. Old SessionID: " . session_id());

            if (headers_sent($file, $line)) {

                $this->log("WARNING: Headers already sent at $file:$line");

            }

            // Regenerate session ID to prevent session fixation attacks

            // False = keep old session data temporarily to avoid race conditions/cookie failure

            session_regenerate_id(false);

            $_SESSION['user_id'] = $user['id'];

            $this->log("New SessionID: " . session_id() . " | Set UserID: " . $_SESSION['user_id']);

            // Send weekly stats email if enabled and due (non-blocking)
            try {
                $this->sendWeeklyStatsIfDue(['id' => $user['id'], 'email' => $user['email']]);
            } catch (\Throwable $e) {
                error_log('AuthController: weekly stats notification failed — ' . $e->getMessage());
            }

            // Explicitly close session to ensure write before redirect

            session_write_close();

            $this->f3->reroute('/dashboard');

        } else {

            $this->log("Authentication failed for user: " . $username);

            $this->incrementFailedLogin($ip);

            $errors[] = 'Identifiants invalides.';

        }

        $this->render('auth/login.html', [

            'title' => 'Connexion',

            'errors' => $errors,

            'success' => '',

            'old' => ['username' => htmlspecialchars($username)],

        ]);

    }

    public function forgotPassword()

    {

        $this->render('auth/forgot-password.html', [

            'title' => 'Mot de passe perdu',

            'errors' => [],

            'success' => '',

            'old' => ['identifier' => '']

        ]);

    }

    public function requestPasswordReset()

    {

        $identifier = trim($_POST['username'] ?? '');

        $errors = [];

        $success = '';

        $userModel = new User();

        $user = null;

        if ($identifier === '') {

            $errors[] = 'Merci de saisir votre nom d\'utilisateur ou votre adresse e-mail.';

        } else {

            // If identifier looks like email, validate it

            if (strpos($identifier, '@') !== false) {

                $validatedEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

                if (!$validatedEmail) {

                    $errors[] = 'Adresse e-mail invalide.';

                } else {

                    $identifier = $validatedEmail;

                }

            }

            if (empty($errors)) {

                $user = $userModel->findByUsernameOrEmail($identifier);

                if (!$user || empty($user['email'])) {

                    $errors[] = 'Aucun compte ne correspond à cet identifiant.';

                }

            }

        }

        if (empty($errors) && $user) {

            $token = $this->createPasswordResetToken($user);

            $this->sendPasswordResetEmail($user['email'], $token);

            $success = 'Un e-mail contenant les instructions de réinitialisation a été envoyé.';

        }

        $this->render('auth/forgot-password.html', [

            'title' => 'Mot de passe perdu',

            'errors' => $errors,

            'success' => $success,

            'old' => ['identifier' => htmlspecialchars($identifier)],

        ]);

    }

    public function showPasswordResetForm()

    {

        $token = $this->f3->get('PARAMS.token');

        $reset = $this->loadPasswordResetToken($token);

        $this->render('auth/reset-password.html', [

            'title' => 'Réinitialiser le mot de passe',

            'errors' => $reset ? [] : ['Le lien de réinitialisation est invalide ou a expiré.'],

            'success' => '',

            'token' => $token,

            'invalidToken' => !$reset,

        ]);

    }

    public function performPasswordReset()

    {

        $token = $this->f3->get('PARAMS.token');

        $reset = $this->loadPasswordResetToken($token);

        $password = $_POST['password'] ?? '';

        $confirm = $_POST['password_confirmation'] ?? '';

        $errors = [];

        if (!$reset) {

            $errors[] = 'Le lien de réinitialisation est invalide ou a expiré.';

        }

        if ($password === '' || $confirm === '') {

            $errors[] = 'Merci de saisir votre nouveau mot de passe deux fois.';

        } elseif ($password !== $confirm) {

            $errors[] = 'Les mots de passe ne correspondent pas.';

        } elseif (strlen($password) < 8) {

            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';

        }

        if (empty($errors) && $reset) {

            $userModel = new User();

            if ($userModel->resetPassword((int) $reset['user_id'], $password)) {

                $this->removePasswordResetToken($token);

                $this->render('auth/login.html', [

                    'title' => 'Connexion',

                    'errors' => [],

                    'success' => 'Votre mot de passe a été mis à jour. Connectez-vous.',

                    'old' => ['username' => htmlspecialchars($reset['email'])],

                ]);

                return;

            }

            $errors[] = 'Impossible de mettre à jour le mot de passe pour ce compte.';

        }

        $this->render('auth/reset-password.html', [

            'title' => 'Réinitialiser le mot de passe',

            'errors' => $errors,

            'success' => '',

            'token' => $token,

            'invalidToken' => !$reset,

        ]);

    }

    public function register()

    {

        // Render with F3 Template

        $this->render('auth/register.html', [

            'title' => 'Inscription',

            'errors' => [],

            'old' => ['username' => '', 'email' => '']

        ]);

    }

    public function store()

    {

        $username = trim($_POST['username'] ?? '');

        $password = $_POST['password'] ?? '';

        $email = trim($_POST['email'] ?? '');

        $errors = [];

        $userModel = new User();

        if ($username === '' || $password === '') {

            $errors[] = 'Merci de remplir tous les champs obligatoires.';

        }

        if (strlen($password) < 8) {

            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';

        }

        if ($userModel->count(['username=?', $username])) {

            $errors[] = 'Ce nom d\'utilisateur est déjà utilisé.';

        }

        // Validate email format with filter_var (security hardening)

        if ($email !== '') {

            $validatedEmail = filter_var($email, FILTER_VALIDATE_EMAIL);

            if (!$validatedEmail) {

                $errors[] = 'Adresse e-mail invalide.';

            } else {

                $email = $validatedEmail; // Use filtered value

                if ($userModel->count(['email=?', $email])) {

                    $errors[] = 'Cette adresse e-mail est déjà utilisée.';

                }

            }

        }

        if (empty($errors)) {

            if ($userModel->register($username, $password, $email)) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $userModel->id;

                $this->f3->reroute('/dashboard');

            } else {

                $errors[] = 'Une erreur est survenue lors de l’inscription.';

            }

        }

        $this->render('auth/register.html', [

            'title' => 'Inscription',

            'errors' => $errors,

            'old' => ['username' => htmlspecialchars($username), 'email' => htmlspecialchars($email)],

        ]);

    }

    private function getPasswordResetDir(): string

    {

        $dir = 'tmp/password-resets';

        if (!is_dir($dir)) {

            mkdir($dir, 0755, true);

        }

        return $dir;

    }

    private function createPasswordResetToken(array $user): string

    {

        $token = bin2hex(random_bytes(32));

        $payload = [

            'token' => $token,

            'user_id' => (int) $user['id'],

            'email' => $user['email'] ?? '',

            'expires_at' => date('c', time() + self::PASSWORD_RESET_TTL),

        ];

        file_put_contents($this->getPasswordResetDir() . '/' . $token . '.json', json_encode($payload, JSON_PRETTY_PRINT));

        return $token;

    }

    private function loadPasswordResetToken(string $token): ?array

    {

        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {

            return null;

        }

        $file = $this->getPasswordResetDir() . '/' . $token . '.json';

        if (!file_exists($file)) {

            return null;

        }

        $content = json_decode(file_get_contents($file), true);

        if (!is_array($content)) {

            return null;

        }

        if (empty($content['expires_at']) || strtotime($content['expires_at']) < time()) {

            @unlink($file);

            return null;

        }

        return $content;

    }

    private function removePasswordResetToken(string $token): void

    {

        $file = $this->getPasswordResetDir() . '/' . $token . '.json';

        if (file_exists($file)) {

            @unlink($file);

        }

    }

    private function sendPasswordResetEmail(string $email, string $token): void

    {

        $resetUrl = $this->buildAbsoluteUrl('/password/reset/' . $token);

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $subject = 'Réinitialisation de votre mot de passe sur ' . $host;

        $message = "Bonjour,\n\n";

        $message .= "Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.\n\n";

        $message .= "Cliquez sur ce lien pour choisir un nouveau mot de passe :\n\n";

        $message .= "$resetUrl\n\n";

        $message .= "Ce lien expire dans une heure. Si vous n'avez pas demandé la réinitialisation, ignorez ce message.\n\n";

        $message .= $host . "\n";

        $headers = implode("\r\n", [

            'From: "Écrivain" <noreply@' . $host . '>',

            'Content-Type: text/plain; charset=UTF-8',

            'X-Mailer: PHP/' . phpversion()

        ]) . "\r\n";

        if (function_exists('mail')) {

            if (!@mail($email, $subject, $message, $headers)) {

                error_log('AuthController: impossible d’envoyer l’e-mail de réinitialisation à ' . $email);

            }

        } else {

            error_log('AuthController: mail() indisponible pour envoyer l’e-mail de réinitialisation à ' . $email);

        }

    }

    private function buildAbsoluteUrl(string $path): string

    {

        $host = $_SERVER['HTTP_HOST'] ?? $this->f3->get('HOST') ?? 'localhost';

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')

            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')

            ? 'https'

            : 'http';

        $base = rtrim($this->f3->get('BASE') ?? '', '/');

        $cleanPath = '/' . ltrim($path, '/');

        return $scheme . '://' . $host . $base . $cleanPath;

    }

    public function logout()

    {

        session_destroy();

        $this->f3->reroute('/');

    }

    public function generateToken()

    {

        $currentUser = $this->currentUser();

        if (!$currentUser) {

            $this->f3->error(403, 'Action non autorisée');

            return;

        }

        $email = $currentUser['email'];

        if (empty($email)) {

            $this->f3->error(400, 'Utilisateur sans email');

            return;

        }

        // Get JWT secret from environment

        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;

        if (!$jwtSecret) {

            error_log('JWT_SECRET not configured');

            $this->f3->error(500, 'Configuration error');

            return;

        }

        // Generate unique token ID (jti claim)

        $tokenId = bin2hex(random_bytes(16));

        $iat = time();

        // Sign JWT with HMAC SHA256 (fixes vulnerability #3)

        $jwt = $this->encodeAutoLoginToken($tokenId, $currentUser['id'], $iat);

        // Store token metadata (for revocation)

        $userDir = $this->getUserDataDir($email);

        $jsonFile = $userDir . '/tokens.json';

        if (!is_dir($userDir)) {

            mkdir($userDir, 0755, true);

        }

        $tokens = [];

        if (file_exists($jsonFile)) {

            try {

                $encryptedContent = file_get_contents($jsonFile);

                $decryptedContent = $this->decryptData($encryptedContent);

                $data = json_decode($decryptedContent, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'])) {

                    $tokens = $data['tokens'];

                }

            } catch (Exception $e) {

                // File might be legacy unencrypted format - try direct JSON

                $content = file_get_contents($jsonFile);

                $data = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'])) {

                    $tokens = $data['tokens'];

                }

            }

        }

        // Store token metadata (jti => user_id mapping for revocation)

        $tokens[$tokenId] = [

            'user_id' => $currentUser['id'],

            'created_at' => date('Y-m-d H:i:s'),

            'iat' => $iat

        ];

        // Encrypt before writing (vulnerability #4 fix)

        $jsonData = json_encode(['tokens' => $tokens], JSON_PRETTY_PRINT);

        $encryptedData = $this->encryptData($jsonData);

        file_put_contents($jsonFile, $encryptedData);

        echo json_encode(['token' => $jwt]);

    }

    public function listTokens()

    {

        $currentUser = $this->currentUser();

        if (!$currentUser) {

            $this->f3->error(403);

            return;

        }

        $email = $currentUser['email'];

        $userId = $currentUser['id'];

        $jsonFile = $this->getUserDataDir($email) . '/tokens.json';

        $userTokens = [];

        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;

        if (file_exists($jsonFile)) {

            try {

                $encryptedContent = file_get_contents($jsonFile);

                $decryptedContent = $this->decryptData($encryptedContent);

                $data = json_decode($decryptedContent, true);

            } catch (Exception $e) {

                $content = file_get_contents($jsonFile);

                $data = json_decode($content, true);

            }

            if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'])) {

                foreach ($data['tokens'] as $tokenId => $info) {

                    if (isset($info['user_id']) && $info['user_id'] == $userId) {

                        $createdAt = $info['created_at'] ?? null;

                        $iat = $info['iat'] ?? ($createdAt ? strtotime($createdAt) : time());

                        $tokenValue = null;

                        if ($jwtSecret) {

                            try {

                                $tokenValue = $this->encodeAutoLoginToken($tokenId, $userId, $iat);

                            } catch (Exception $e) {

                                // If encoding fails, keep token hidden but continue listing metadata

                                $tokenValue = null;

                            }

                        }

                        $userTokens[] = [

                            'token_id' => $tokenId,

                            'created_at' => $createdAt,

                            'token' => $tokenValue

                        ];

                    }

                }

            }

        }

        // Sort by date desc

        usort($userTokens, function ($a, $b) {

            $bTime = strtotime($b['created_at'] ?? '') ?: 0;

            $aTime = strtotime($a['created_at'] ?? '') ?: 0;

            return $bTime - $aTime;

        });

        echo json_encode(['tokens' => $userTokens]);

    }

    public function revokeTokens()

    {

        $currentUser = $this->currentUser();

        if (!$currentUser) {

            $this->f3->error(403, 'Action non autorisée');

            return;

        }

        $email = $currentUser['email'];

        $userId = $currentUser['id'];

        $jsonFile = $this->getUserDataDir($email) . '/tokens.json';

        // Get specific token ID from body if exists

        $body = json_decode($this->f3->get('BODY'), true);

        $targetTokenId = $body['token_id'] ?? null;

        $targetTokenRaw = $body['token'] ?? null;

        if (!file_exists($jsonFile)) {

            echo json_encode(['count' => 0]);

            return;

        }

        try {

            $encryptedContent = file_get_contents($jsonFile);

            $decryptedContent = $this->decryptData($encryptedContent);

            $data = json_decode($decryptedContent, true);

        } catch (Exception $e) {

            $content = file_get_contents($jsonFile);

            $data = json_decode($content, true);

        }

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tokens'])) {

            echo json_encode(['count' => 0]);

            return;

        }

        $tokens = $data['tokens'];

        $count = 0;

        if ($targetTokenId && isset($tokens[$targetTokenId])) {

            unset($tokens[$targetTokenId]);

            $count++;

        } elseif ($targetTokenRaw) {

            $resolvedId = $this->resolveTokenIdFromJwt($targetTokenRaw);

            if ($resolvedId && isset($tokens[$resolvedId])) {

                unset($tokens[$resolvedId]);

                $count++;

            }

        } else {

            // Remove all tokens for this user

            $count = count($tokens);

            $tokens = [];

        }

        // Encrypt before writing

        $jsonData = json_encode(['tokens' => $tokens], JSON_PRETTY_PRINT);

        $encryptedData = $this->encryptData($jsonData);

        file_put_contents($jsonFile, $encryptedData);

        echo json_encode(['count' => $count]);

    }

    /**

     * Build and sign an auto-login JWT so we can redisplay the links.

     */

    private function encodeAutoLoginToken(string $tokenId, int $userId, int $iat): string

    {

        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;

        if (!$jwtSecret) {

            throw new Exception('JWT_SECRET not configured');

        }

        $payload = [

            'iat' => $iat,

            'jti' => $tokenId,

            'sub' => (string) $userId,

            'type' => 'auth_token'

        ];

        return \Firebase\JWT\JWT::encode($payload, $jwtSecret, 'HS256');

    }

    /**

     * Extract token ID (jti) from a provided JWT, returning null on failure.

     */

    private function resolveTokenIdFromJwt(string $token): ?string

    {

        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;

        if (!$jwtSecret || strpos($token, '.') === false) {

            return null;

        }

        try {

            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($jwtSecret, 'HS256'));

            if (isset($decoded->jti)) {

                return (string) $decoded->jti;

            }

        } catch (Exception $e) {

            // Ignore invalid tokens, we simply can't resolve the ID

        }

        return null;

    }

    /* Rate Limiting Implementation (File-based) */

    private function getRateLimitFile(string $ip): string

    {

        // Use a hash of IP to create filename

        $hash = md5($ip);

        $dir = 'tmp/security/rate_limit';

        if (!is_dir($dir)) {

            mkdir($dir, 0755, true);

        }

        return $dir . '/' . $hash . '.json';

    }

    private function isRateLimited(string $ip): bool

    {

        $file = $this->getRateLimitFile($ip);

        if (!file_exists($file)) {

            return false;

        }

        $data = json_decode(file_get_contents($file), true);

        if (!$data) {

            return false;

        }

        // Limit: 5 attempts in 15 minutes

        if ($data['attempts'] >= 5) {

            // Check if blocking time expired

            if (time() - $data['last_attempt'] < 900) { // 900s = 15m

                return true;

            } else {

                // Expired, reset

                @unlink($file);

                return false;

            }

        }

        return false;

    }

    private function incrementFailedLogin(string $ip): void

    {

        $file = $this->getRateLimitFile($ip);

        $attempts = 0;

        if (file_exists($file)) {

            $data = json_decode(file_get_contents($file), true);

            // If previous block expired, reset count, otherwise increment

            if (isset($data['last_attempt']) && (time() - $data['last_attempt'] > 900)) {

                $attempts = 0;

            } else {

                $attempts = $data['attempts'] ?? 0;

            }

        }

        $attempts++;

        $payload = [

            'attempts' => $attempts,

            'last_attempt' => time()

        ];

        file_put_contents($file, json_encode($payload));

    }

    /**
     * Send the weekly writing stats email if the user has opted in and 7 days have passed.
     */
    private function sendWeeklyStatsIfDue(array $user): void
    {

        if (empty($user['email'])) return;

        $configFile = $this->getUserDataDir($user['email']) . '/ai_config.json';

        if (!file_exists($configFile)) return;

        $config = json_decode(file_get_contents($configFile), true);

        if (!is_array($config)) return;

        $notifs = $config['notifications'] ?? [];

        if (empty($notifs['weekly_stats'])) return;

        // Only send once every 7 days
        $lastSent = $notifs['last_weekly_sent'] ?? null;

        if ($lastSent && (time() - strtotime($lastSent)) < 7 * 24 * 3600) return;

        $stats = $this->gatherWeeklyStats($user['id']);

        $notif = new NotificationService();

        if ($notif->sendWeeklyStatsEmail($user['email'], $stats)) {

            $config['notifications']['last_weekly_sent'] = date('Y-m-d H:i:s');

            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        }

    }

    /**
     * Gather writing and AI usage statistics for the last 7 days.
     */
    private function gatherWeeklyStats(int $userId): array
    {

        $since = date('Y-m-d', strtotime('-7 days'));

        $wordsResult = $this->db->exec(
            'SELECT SUM(word_count) as total FROM writing_stats WHERE user_id=? AND stat_date >= ?',
            [$userId, $since]
        );

        $sessionsResult = $this->db->exec(
            'SELECT COUNT(DISTINCT stat_date) as total FROM writing_stats WHERE user_id=? AND stat_date >= ?',
            [$userId, $since]
        );

        $tokensResult = $this->db->exec(
            'SELECT SUM(total_tokens) as total FROM ai_usage WHERE user_id=? AND DATE(created_at) >= ?',
            [$userId, $since]
        );

        return [
            'words_this_week' => (int) ($wordsResult[0]['total'] ?? 0),
            'sessions'        => (int) ($sessionsResult[0]['total'] ?? 0),
            'ai_tokens'       => (int) ($tokensResult[0]['total'] ?? 0),
        ];

    }

}

