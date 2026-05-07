<?php

require_once __DIR__ . '/../../../core/TokenService.php';
require_once __DIR__ . '/../../../services/auth/AuthService.php';
require_once __DIR__ . '/../../../services/auth/PasswordResetService.php';
require_once __DIR__ . '/../../../services/auth/RegistrationService.php';
require_once __DIR__ . '/../../../services/auth/WeeklyStatsService.php';
require_once __DIR__ . '/../../../services/auth/JwtTokenService.php';

class AuthController extends Controller
{

    private const PASSWORD_RESET_TTL = 3600;
    private ?AuthService $authService = null;
    private ?RegistrationService $registrationService = null;
    private ?PasswordResetService $passwordResetService = null;
    private ?WeeklyStatsService $weeklyStatsService = null;
    private ?JwtTokenService $jwtTokenService = null;

    private function getAuthService(): AuthService
    {
        if ($this->authService === null) {
            $this->authService = new AuthService($this->f3->get('DB'), $this->f3);
        }
        return $this->authService;
    }

    private function getRegistrationService(): RegistrationService
    {
        if ($this->registrationService === null) {
            $this->registrationService = new RegistrationService($this->f3->get('DB'), $this->f3);
        }
        return $this->registrationService;
    }

    private function getPasswordResetService(): PasswordResetService
    {
        if ($this->passwordResetService === null) {
            $resetDir = 'tmp/password-resets';
            if (!is_dir($resetDir)) {
                mkdir($resetDir, 0755, true);
            }
            $this->passwordResetService = new PasswordResetService($this->f3->get('DB'), $this->f3, $resetDir);
        }
        return $this->passwordResetService;
    }

    private function getWeeklyStatsService(): WeeklyStatsService
    {
        if ($this->weeklyStatsService === null) {
            $this->weeklyStatsService = new WeeklyStatsService($this->f3->get('DB'));
        }
        return $this->weeklyStatsService;
    }

    private function getJwtTokenService(): JwtTokenService
    {
        if ($this->jwtTokenService === null) {
            $this->jwtTokenService = new JwtTokenService($this->tokenService());
        }
        return $this->jwtTokenService;
    }

    public function beforeRoute(Base $f3)
    {

        parent::beforeRoute($f3);

        // Redirect if already logged in for login/register pages

        $pattern = $f3->get('PATTERN');

        if ($this->currentUser() && ($pattern === '/login' || $pattern === '/register')) {

            $f3->reroute('/dashboard');

        }

    }

    public function home()
    {
        if ($this->currentUser()) {
            $this->f3->reroute('/dashboard');
        }

        $this->render('auth/home.html', [
            'title' => 'Logiciel d\'écriture créative gratuit avec IA',
            'metaDescription' => 'Écrivain est un logiciel d\'écriture créative gratuit et open-source. Structurez vos romans en actes et chapitres, gérez vos personnages, collaborez, utilisez l\'IA (OpenAI, Gemini, Anthropic, Mistral) et exportez en PDF, DOCX ou HTML.',
        ]);
    }

    public function login()
    {

        // Render with F3 Template

        $flashError = $_SESSION['error'] ?? '';
        unset($_SESSION['error']);

        $this->render('auth/login.html', [

            'title' => 'Connexion',

            'errors' => $flashError ? [$flashError] : [],

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
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];

        // Rate Limiting Check
        $ip = $this->f3->get('IP');
        if ($this->getAuthService()->isRateLimited($ip)) {
            $errors[] = 'Trop de tentatives de connexion. Veuillez réessayer dans 15 minutes.';

            $this->render('auth/login.html', [
                'title' => 'Connexion',
                'errors' => $errors,
                'success' => '',
                'old' => ['username' => htmlspecialchars($username)],
            ]);
            return;
        }

        $user = $this->getAuthService()->authenticateUser($username, $password);

        if ($user) {
            $this->getAuthService()->initializeUserSession($user);

            // Send weekly stats email if enabled and due (non-blocking)
            try {
                $this->getWeeklyStatsService()->sendIfDue($user['id'], $user['email'], $user['username']);
            } catch (\Throwable $e) {
                error_log('AuthController: weekly stats notification failed — ' . $e->getMessage());
            }

            $redirectAfterLogin = $_SESSION['post_login_redirect'] ?? '';
            unset($_SESSION['post_login_redirect']);

            $this->f3->reroute($redirectAfterLogin ?: '/dashboard');
        } else {
            $this->getAuthService()->incrementFailedLogin($ip);
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
        $user = null;

        if ($identifier === '') {
            $errors[] = 'Merci de saisir votre nom d\'utilisateur ou votre adresse e-mail.';
        } else {
            if (strpos($identifier, '@') !== false) {
                $validatedEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
                if (!$validatedEmail) {
                    $errors[] = 'Adresse e-mail invalide.';
                } else {
                    $identifier = $validatedEmail;
                }
            }

            if (empty($errors)) {
                $userModel = $this->userModel();
                $user = $userModel->findByUsernameOrEmail($identifier);
                if (!$user || empty($user['email'])) {
                    $errors[] = 'Aucun compte ne correspond à cet identifiant.';
                }
            }
        }

        if (empty($errors) && $user) {
            $token = $this->getPasswordResetService()->createResetToken($user);
            $resetUrl = $this->getPasswordResetService()->buildResetUrl($token);
            $this->getPasswordResetService()->sendResetEmail($user['email'], $resetUrl);
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
        $reset = $this->getPasswordResetService()->loadResetToken($token);

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
        $reset = $this->getPasswordResetService()->loadResetToken($token);

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
        } else {
            $validation = $this->getPasswordResetService()->validatePasswordStrength($password);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if (empty($errors) && $reset) {
            if ($this->getPasswordResetService()->resetPassword((int)$reset['user_id'], $password)) {
                $this->getPasswordResetService()->removeResetToken($token);

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
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirmation' => $_POST['password'] ?? '', // Same as password for now
            'email' => trim($_POST['email'] ?? ''),
        ];

        $validation = $this->getRegistrationService()->validateRegistration($data);

        if (!$validation['valid']) {
            $errors = array_values($validation['errors']);
            $this->render('auth/register.html', [
                'title' => 'Inscription',
                'errors' => $errors,
                'old' => ['username' => htmlspecialchars($data['username']), 'email' => htmlspecialchars($data['email'])],
            ]);
            return;
        }

        $user = $this->getRegistrationService()->createUser($validation['data']);

        if ($user) {
            $this->getAuthService()->initializeUserSession($user);
            $this->f3->reroute('/dashboard');
        } else {
            $this->render('auth/register.html', [
                'title' => 'Inscription',
                'errors' => ['Une erreur est survenue lors de l’inscription.'],
                'old' => ['username' => htmlspecialchars($data['username']), 'email' => htmlspecialchars($data['email'])],
            ]);
        }
    }


    public function logout()
    {
        $this->getAuthService()->destroySession();
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

        $result = $this->getJwtTokenService()->generateToken((int)$currentUser['id'], $email);
        echo json_encode($result);
    }

    public function listTokens()
    {
        $currentUser = $this->currentUser();

        if (!$currentUser) {
            $this->f3->error(403);
            return;
        }

        $result = $this->getJwtTokenService()->listTokens((int)$currentUser['id'], $currentUser['email']);
        echo json_encode($result);
    }

    public function revokeTokens()
    {
        $currentUser = $this->currentUser();

        if (!$currentUser) {
            $this->f3->error(403, 'Action non autorisée');
            return;
        }

        $body = json_decode($this->f3->get('BODY'), true);
        $targetTokenId = $body['token_id'] ?? null;
        $targetTokenRaw = $body['token'] ?? null;

        $result = $this->getJwtTokenService()->revokeTokens(
            (int)$currentUser['id'],
            $currentUser['email'],
            $targetTokenId,
            $targetTokenRaw
        );
        echo json_encode($result);
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

        if (empty($user['email']))
            return;

        $configFile = $this->getUserDataDir($user['email']) . '/ai_config.json';

        if (!file_exists($configFile))
            return;

        $config = json_decode(file_get_contents($configFile), true);

        if (!is_array($config))
            return;

        $notifs = $config['notifications'] ?? [];

        if (empty($notifs['weekly_stats']))
            return;

        // Only send once every 7 days
        $lastSent = $notifs['last_weekly_sent'] ?? null;

        if ($lastSent && (time() - strtotime($lastSent)) < 7 * 24 * 3600)
            return;

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
            'sessions' => (int) ($sessionsResult[0]['total'] ?? 0),
            'ai_tokens' => (int) ($tokensResult[0]['total'] ?? 0),
        ];

    }

}

