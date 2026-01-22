<?php

class AuthController extends Controller
{
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
            'old' => ['username' => '']
        ]);
    }

    private function log($msg)
    {
        // Find project root by looking for 'vendor' dir
        $dir = __DIR__;
        while (!file_exists($dir . '/vendor') && dirname($dir) !== $dir) {
            $dir = dirname($dir);
        }
        $logDir = $dir . '/data';

        if (!is_dir($logDir) && is_writable($dir)) {
            mkdir($logDir, 0777, true);
        }

        $file = $logDir . '/auth_debug.log';
        // Fallback to /tmp if we can't write to data
        if (!is_writable($logDir)) {
            $file = sys_get_temp_dir() . '/auth_debug.log';
        }

        $time = date('[Y-m-d H:i:s] ');
        file_put_contents($file, $time . $msg . "\n", FILE_APPEND);
    }

    public function authenticate()
    {
        $this->log("Authenticate called.");
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $this->log("Username: " . $username);

        $errors = [];

        $userModel = new User();
        $user = $userModel->authenticate($username, $password);

        if ($user) {
            $this->log("User authenticated. Old SessionID: " . session_id());

            if (headers_sent($file, $line)) {
                $this->log("WARNING: Headers already sent at $file:$line");
            }

            // session_regenerate_id(true); // TEMP DISABLE
            $_SESSION['user_id'] = $user['id'];
            $this->log("Kept SessionID: " . session_id() . " | Set UserID: " . $_SESSION['user_id']);

            // Explicitly close session to ensure write before redirect
            session_write_close();

            $this->f3->reroute('/dashboard');
        } else {
            $this->log("Authentication failed for user: " . $username);
            $errors[] = 'Identifiants invalides.';
        }

        $this->render('auth/login.html', [
            'title' => 'Connexion',
            'errors' => $errors,
            'old' => ['username' => htmlspecialchars($username)],
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

        if ($userModel->count(['username=?', $username])) {
            $errors[] = 'Ce nom d’utilisateur est déjà utilisé.';
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

    public function logout()
    {
        session_destroy();
        $this->f3->reroute('/');
    }

    public function generateToken()
    {
        $logFile = 'data/token_gen_debug.log';
        $log = function ($msg) use ($logFile) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
        };

        $log("generateToken called.");

        $currentUser = $this->currentUser();
        if (!$currentUser) {
            $log("Error: No user logged in.");
            $this->f3->error(403, 'Action non autorisée');
            return;
        }

        $email = $currentUser['email'];
        if (empty($email)) {
            $log("Error: User without email. UserID: " . $currentUser['id']);
            $this->f3->error(400, 'Utilisateur sans email');
            return;
        }

        $dataDir = 'data';
        // User specific file
        $jsonFile = $dataDir . '/auth_' . $email . '_tokens.json';

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        $tokens = [];
        if (file_exists($jsonFile)) {
            $content = file_get_contents($jsonFile);
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'])) {
                $tokens = $data['tokens'];
            }
        }

        // Token format: base64(email).randomHex
        // This allows identifying the user file from the token itself
        $emailPrefix = base64_encode($email);
        $randomPart = bin2hex(random_bytes(32));
        $fullToken = $emailPrefix . '.' . $randomPart;

        $tokens[$fullToken] = [
            'user_id' => $currentUser['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($jsonFile, json_encode(['tokens' => $tokens], JSON_PRETTY_PRINT));

        echo json_encode(['token' => $fullToken]);
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
        $dataDir = 'data';
        $jsonFile = $dataDir . '/auth_' . $email . '_tokens.json';

        $userTokens = [];

        if (file_exists($jsonFile)) {
            $content = file_get_contents($jsonFile);
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'])) {
                foreach ($data['tokens'] as $token => $info) {
                    // Check user_id just in case, though file is user specific now
                    if (isset($info['user_id']) && $info['user_id'] == $userId) {
                        $userTokens[] = [
                            'token' => $token,
                            'created_at' => $info['created_at']
                        ];
                    }
                }
            }
        }

        // Sort by date desc
        usort($userTokens, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
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
        $dataDir = 'data';
        $jsonFile = $dataDir . '/auth_' . $email . '_tokens.json';

        // Get specific token from body if exists
        $body = json_decode($this->f3->get('BODY'), true);
        $targetToken = $body['token'] ?? null;

        if (!file_exists($jsonFile)) {
            echo json_encode(['count' => 0]);
            return;
        }

        $content = file_get_contents($jsonFile);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tokens'])) {
            echo json_encode(['count' => 0]);
            return;
        }

        $tokens = $data['tokens'];
        $count = 0;

        if ($targetToken) {
            // Remove specific token
            if (isset($tokens[$targetToken])) {
                unset($tokens[$targetToken]);
                $count++;
            }
        } else {
            // Remove all tokens for this user file
            $count = count($tokens);
            $tokens = [];
        }

        file_put_contents($jsonFile, json_encode(['tokens' => $tokens], JSON_PRETTY_PRINT));

        echo json_encode(['count' => $count]);
    }
}
