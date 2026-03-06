<?php

class OAuthController extends Controller
{
    private const CODE_TTL_SECONDS = 300;
    private const ACCESS_TOKEN_TTL_SECONDS = 3600;
    private const REFRESH_TOKEN_TTL_SECONDS = 2592000; // 30 jours

    public function beforeRoute(Base $f3)
    {
        // CORS : ChatGPT et autres clients MCP font des requêtes cross-origin
        $allowedOrigins = ['https://chatgpt.com', 'https://chat.openai.com', 'https://claude.ai'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowedOrigins, true) || $origin === '') {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');

        // Preflight OPTIONS → répondre 204 immédiatement
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    public function authorizationServerMetadata(): void
    {
        $base = $this->baseUrl();
        $metadata = [
            'issuer' => $base,
            'authorization_endpoint' => $base . '/oauth/authorize',
            'token_endpoint' => $base . '/oauth/token',
            'registration_endpoint' => $base . '/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'scopes_supported' => ['mcp'],
        ];

        $this->json($metadata);
    }

    public function openidConfiguration(): void
    {
        // Alias pratique : certaines intégrations testent cet endpoint même sans OIDC.
        $this->authorizationServerMetadata();
    }

    public function protectedResourceMetadata(): void
    {
        $base = $this->baseUrl();
        $metadata = [
            'resource' => $base . '/mcp',
            'authorization_servers' => [$base],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => ['mcp'],
        ];

        $this->json($metadata);
    }

    public function authorize(): void
    {
        $currentUser = $this->currentUser();
        if (!$currentUser) {
            $_SESSION['post_login_redirect'] = $this->currentUrl();
            $this->f3->reroute('/login');
            return;
        }

        $responseType = (string) $this->f3->get('GET.response_type');
        $clientId = trim((string) $this->f3->get('GET.client_id'));
        $redirectUri = trim((string) $this->f3->get('GET.redirect_uri'));
        $state = (string) $this->f3->get('GET.state');
        $codeChallenge = trim((string) $this->f3->get('GET.code_challenge'));
        $codeChallengeMethod = strtoupper(trim((string) $this->f3->get('GET.code_challenge_method')));
        $scope = trim((string) ($this->f3->get('GET.scope') ?: 'mcp'));
        $pkceEnabled = ($codeChallenge !== '');

        if ($responseType !== 'code') {
            $this->oauthError('unsupported_response_type', 'response_type doit être "code".');
            return;
        }
        if ($clientId === '' || $redirectUri === '') {
            $this->oauthError('invalid_request', 'client_id et redirect_uri sont requis.');
            return;
        }
        if (!$this->isAllowedRedirectUri($redirectUri)) {
            $this->oauthError('invalid_request', 'redirect_uri non autorisée.');
            return;
        }
        // PKCE S256 est recommandé mais optionnel : si fourni, la méthode doit être S256.
        // Sans PKCE, un client_secret sera exigé à l'échange du token.
        if ($pkceEnabled && $codeChallengeMethod !== 'S256') {
            $this->oauthError('invalid_request', 'code_challenge_method doit être S256.');
            return;
        }

        $code = bin2hex(random_bytes(32));

        $store = $this->loadOauthStore();
        $store = $this->pruneOauthStore($store);
        $store['codes'][$code] = [
            'user_id' => (int) $currentUser['id'],
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $pkceEnabled ? $codeChallenge : null,
            'pkce' => $pkceEnabled,
            'scope' => $scope,
            'expires_at' => time() + self::CODE_TTL_SECONDS,
        ];
        $this->saveOauthStore($store);

        $location = $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . 'code=' . rawurlencode($code);
        if ($state !== '') {
            $location .= '&state=' . rawurlencode($state);
        }

        header('Location: ' . $location, true, 302);
    }

    public function token(): void
    {
        // Support Basic Auth (client_secret_basic) en plus de POST body (client_secret_post)
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $_POST['client_id']     = $_POST['client_id']     ?: $_SERVER['PHP_AUTH_USER'];
            $_POST['client_secret'] = $_POST['client_secret'] ?: ($_SERVER['PHP_AUTH_PW'] ?? '');
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (str_starts_with($authHeader, 'Basic ')) {
                $decoded = base64_decode(substr($authHeader, 6));
                if ($decoded && str_contains($decoded, ':')) {
                    [$cid, $csec] = explode(':', $decoded, 2);
                    $_POST['client_id']     = $_POST['client_id']     ?: $cid;
                    $_POST['client_secret'] = $_POST['client_secret'] ?: $csec;
                }
            }
        }

        // Supporter aussi JSON body (certains clients envoient du JSON)
        if (empty($_POST) && !empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                foreach ($parsed as $k => $v) {
                    if (!isset($_POST[$k])) {
                        $_POST[$k] = $v;
                    }
                }
            }
        }

        $grantType = trim((string) ($_POST['grant_type'] ?? ''));

        switch ($grantType) {
            case 'authorization_code':
                $this->exchangeAuthorizationCode();
                return;
            case 'refresh_token':
                $this->exchangeRefreshToken();
                return;
            default:
                $this->oauthError('unsupported_grant_type', 'grant_type non supporté.', 400);
                return;
        }
    }

    public function registerClient(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            $this->oauthError('invalid_client_metadata', 'JSON invalide.', 400);
            return;
        }

        $redirectUris = $payload['redirect_uris'] ?? [];
        if (!is_array($redirectUris) || !$redirectUris) {
            $this->oauthError('invalid_redirect_uri', 'redirect_uris est requis.', 400);
            return;
        }

        foreach ($redirectUris as $uri) {
            if (!is_string($uri) || !$this->isAllowedRedirectUri($uri)) {
                $this->oauthError('invalid_redirect_uri', 'Une redirect_uri n\'est pas autorisée.', 400);
                return;
            }
        }

        $store = $this->loadOauthStore();
        $store = $this->pruneOauthStore($store);

        $clientId = 'ecrivain_' . bin2hex(random_bytes(12));
        $clientSecret = bin2hex(random_bytes(24));

        $store['clients'][$clientId] = [
            'client_name' => (string) ($payload['client_name'] ?? 'MCP client'),
            'redirect_uris' => array_values($redirectUris),
            'token_endpoint_auth_method' => (string) ($payload['token_endpoint_auth_method'] ?? 'client_secret_post'),
            'client_secret' => password_hash($clientSecret, PASSWORD_DEFAULT),
            'created_at' => time(),
        ];
        $this->saveOauthStore($store);

        $this->json([
            'client_id' => $clientId,
            'client_id_issued_at' => time(),
            'client_secret' => $clientSecret,
            'client_secret_expires_at' => 0,
            'redirect_uris' => array_values($redirectUris),
            'token_endpoint_auth_method' => (string) ($payload['token_endpoint_auth_method'] ?? 'client_secret_post'),
        ], 201);
    }

    private function exchangeAuthorizationCode(): void
    {
        $code = trim((string) ($_POST['code'] ?? ''));
        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        $redirectUri = trim((string) ($_POST['redirect_uri'] ?? ''));
        $codeVerifier = trim((string) ($_POST['code_verifier'] ?? ''));
        $clientSecret = (string) ($_POST['client_secret'] ?? '');

        if ($code === '' || $clientId === '' || $redirectUri === '') {
            $this->oauthError('invalid_request', 'code, client_id et redirect_uri sont requis.', 400);
            return;
        }

        $store = $this->pruneOauthStore($this->loadOauthStore());
        $entry = $store['codes'][$code] ?? null;

        if (!is_array($entry)) {
            $this->oauthError('invalid_grant', 'Code invalide.', 400);
            return;
        }

        if (($entry['client_id'] ?? '') !== $clientId || ($entry['redirect_uri'] ?? '') !== $redirectUri) {
            $this->oauthError('invalid_grant', 'client_id ou redirect_uri invalide.', 400);
            return;
        }

        $usedPkce = (bool) ($entry['pkce'] ?? false);

        if ($usedPkce) {
            // Flow PKCE : vérifier le code_verifier
            if (!$this->verifyPkceS256($codeVerifier, (string) ($entry['code_challenge'] ?? ''))) {
                $this->oauthError('invalid_grant', 'PKCE code_verifier invalide.', 400);
                return;
            }
        } else {
            // Flow sans PKCE : le client_secret est obligatoire
            if (!$this->isClientSecretValid($store, $clientId, $clientSecret, true)) {
                $this->oauthError('invalid_client', 'client_secret requis et invalide (PKCE absent).', 401);
                return;
            }
        }

        // Vérification client_secret supplémentaire pour les clients enregistrés avec secret (même avec PKCE)
        if ($usedPkce && !$this->isClientSecretValid($store, $clientId, $clientSecret)) {
            $this->oauthError('invalid_client', 'Client non autorisé.', 401);
            return;
        }

        $userId = (int) ($entry['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->oauthError('invalid_grant', 'Code invalide (utilisateur).', 400);
            return;
        }

        unset($store['codes'][$code]);

        $scope = (string) ($entry['scope'] ?? 'mcp');
        $accessToken = $this->issueLegacyCompatibleToken($userId, self::ACCESS_TOKEN_TTL_SECONDS);
        $refreshToken = bin2hex(random_bytes(32));

        $store['refresh_tokens'][$refreshToken] = [
            'user_id' => $userId,
            'client_id' => $clientId,
            'scope' => $scope,
            'expires_at' => time() + self::REFRESH_TOKEN_TTL_SECONDS,
        ];
        $this->saveOauthStore($store);

        $this->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
            'refresh_token' => $refreshToken,
            'scope' => $scope,
        ]);
    }

    private function exchangeRefreshToken(): void
    {
        $refreshToken = trim((string) ($_POST['refresh_token'] ?? ''));
        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        $clientSecret = (string) ($_POST['client_secret'] ?? '');

        if ($refreshToken === '') {
            $this->oauthError('invalid_request', 'refresh_token requis.', 400);
            return;
        }

        $store = $this->pruneOauthStore($this->loadOauthStore());
        $entry = $store['refresh_tokens'][$refreshToken] ?? null;

        if (!is_array($entry)) {
            $this->oauthError('invalid_grant', 'refresh_token invalide.', 400);
            return;
        }

        if ($clientId !== '' && ($entry['client_id'] ?? '') !== $clientId) {
            $this->oauthError('invalid_grant', 'client_id invalide.', 400);
            return;
        }

        $effectiveClientId = $clientId !== '' ? $clientId : (string) ($entry['client_id'] ?? '');
        if ($effectiveClientId !== '' && !$this->isClientSecretValid($store, $effectiveClientId, $clientSecret)) {
            $this->oauthError('invalid_client', 'Client non autorisé.', 401);
            return;
        }

        $userId = (int) ($entry['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->oauthError('invalid_grant', 'refresh_token invalide (utilisateur).', 400);
            return;
        }

        $scope = (string) ($entry['scope'] ?? 'mcp');
        $accessToken = $this->issueLegacyCompatibleToken($userId, self::ACCESS_TOKEN_TTL_SECONDS);
        $this->saveOauthStore($store);

        $this->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
            'scope' => $scope,
        ]);
    }

    private function issueLegacyCompatibleToken(int $userId, int $ttl): string
    {
        $userModel = new User();
        $userModel->load(['id=?', $userId]);
        if ($userModel->dry()) {
            throw new RuntimeException('Utilisateur introuvable pour émission du token.');
        }

        $jwtSecret = getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?? null;
        if (!$jwtSecret) {
            throw new RuntimeException('JWT_SECRET non configuré.');
        }

        $tokenId = bin2hex(random_bytes(16));
        $iat = time();
        $exp = $iat + $ttl;

        $payload = [
            'iat' => $iat,
            'exp' => $exp,
            'jti' => $tokenId,
            'sub' => (string) $userId,
            'type' => 'auth_token',
        ];

        $token = \Firebase\JWT\JWT::encode($payload, $jwtSecret, 'HS256');
        $this->persistTokenRecord((string) $userModel->email, $tokenId, $userId, $iat, $exp, true);

        return $token;
    }

    private function persistTokenRecord(string $email, string $tokenId, int $userId, int $iat, int $exp, bool $oauthIssued): void
    {
        $userDir = $this->getUserDataDir($email);
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        $file = $userDir . '/tokens.json';
        $tokens = [];

        if (file_exists($file)) {
            try {
                $data = json_decode($this->decryptData((string) file_get_contents($file)), true);
            } catch (Exception $e) {
                $data = json_decode((string) file_get_contents($file), true);
            }
            if (is_array($data) && isset($data['tokens']) && is_array($data['tokens'])) {
                $tokens = $data['tokens'];
            }
        }

        $tokens[$tokenId] = [
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s', $iat),
            'iat' => $iat,
            'exp' => $exp,
            'oauth_issued' => $oauthIssued,
        ];

        $payload = json_encode(['tokens' => $tokens], JSON_PRETTY_PRINT);
        if ($payload === false) {
            throw new RuntimeException('Impossible de sérialiser les tokens.');
        }

        file_put_contents($file, $this->encryptData($payload));
    }

    private function isAllowedRedirectUri(string $redirectUri): bool
    {
        if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($redirectUri);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        $allowedRaw = getenv('MCP_OAUTH_ALLOWED_REDIRECT_HOSTS') ?: ($_ENV['MCP_OAUTH_ALLOWED_REDIRECT_HOSTS'] ?? 'chatgpt.com,chat.openai.com');
        $allowedHosts = array_values(array_filter(array_map(static fn($h) => trim(strtolower($h)), explode(',', $allowedRaw))));
        foreach ($allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function isClientSecretValid(array $store, string $clientId, string $clientSecret, bool $required = false): bool
    {
        $client = $store['clients'][$clientId] ?? null;
        if (!is_array($client)) {
            // Client non enregistré : si le secret est requis (flow sans PKCE), refuser.
            return !$required;
        }

        $method = (string) ($client['token_endpoint_auth_method'] ?? 'client_secret_post');
        if ($method === 'none') {
            // Client public : si secret requis et méthode none, refuser.
            return !$required;
        }

        $hash = (string) ($client['client_secret'] ?? '');
        return $clientSecret !== '' && $hash !== '' && password_verify($clientSecret, $hash);
    }

    private function verifyPkceS256(string $verifier, string $challenge): bool
    {
        if ($challenge === '' || $verifier === '') {
            return false;
        }

        $computed = $this->base64UrlEncode(hash('sha256', $verifier, true));
        return hash_equals($challenge, $computed);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function oauthStorePath(): string
    {
        return $this->f3->get('ROOT') . '/data/oauth_store.json';
    }

    private function loadOauthStore(): array
    {
        $file = $this->oauthStorePath();
        if (!file_exists($file)) {
            return [
                'codes' => [],
                'refresh_tokens' => [],
                'clients' => [],
            ];
        }

        try {
            $decoded = json_decode($this->decryptData((string) file_get_contents($file)), true);
        } catch (Exception $e) {
            $decoded = json_decode((string) file_get_contents($file), true);
        }

        if (!is_array($decoded)) {
            return [
                'codes' => [],
                'refresh_tokens' => [],
                'clients' => [],
            ];
        }

        $decoded['codes'] = is_array($decoded['codes'] ?? null) ? $decoded['codes'] : [];
        $decoded['refresh_tokens'] = is_array($decoded['refresh_tokens'] ?? null) ? $decoded['refresh_tokens'] : [];
        $decoded['clients'] = is_array($decoded['clients'] ?? null) ? $decoded['clients'] : [];

        return $decoded;
    }

    private function saveOauthStore(array $store): void
    {
        $file = $this->oauthStorePath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = json_encode($store, JSON_PRETTY_PRINT);
        if ($payload === false) {
            throw new RuntimeException('Impossible de sérialiser le store OAuth.');
        }

        file_put_contents($file, $this->encryptData($payload));
    }

    private function pruneOauthStore(array $store): array
    {
        $now = time();

        foreach (($store['codes'] ?? []) as $key => $entry) {
            $exp = (int) ($entry['expires_at'] ?? 0);
            if ($exp <= $now) {
                unset($store['codes'][$key]);
            }
        }

        foreach (($store['refresh_tokens'] ?? []) as $key => $entry) {
            $exp = (int) ($entry['expires_at'] ?? 0);
            if ($exp <= $now) {
                unset($store['refresh_tokens'][$key]);
            }
        }

        return $store;
    }

    private function currentUrl(): string
    {
        $scheme = $this->f3->get('SCHEME') ?: 'https';
        $host = $this->f3->get('HOST');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }

    private function baseUrl(): string
    {
        $scheme = $this->f3->get('SCHEME') ?: 'https';
        $host = $this->f3->get('HOST');
        $base = rtrim((string) ($this->f3->get('BASE') ?? ''), '/');
        return $scheme . '://' . $host . $base;
    }

    private function oauthError(string $error, string $description, int $status = 400): void
    {
        $this->logOauth('error', "[$error] $description");
        $this->json([
            'error'             => $error,
            'error_description' => $description,
        ], $status);
    }

    private function logOauth(string $level, string $message): void
    {
        try {
            $logDir  = $this->f3->get('ROOT') . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $line = date('Y-m-d H:i:s') . " [$level] [oauth] "
                . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '')
                . ' origin=' . ($_SERVER['HTTP_ORIGIN'] ?? '-')
                . ' | ' . $message . "\n";
            file_put_contents($logDir . '/oauth.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // log non critique
        }
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        // CORS (aussi sur les réponses JSON directes si beforeRoute n'a pas été appelé)
        if (!headers_sent()) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedOrigins = ['https://chatgpt.com', 'https://chat.openai.com', 'https://claude.ai'];
            if (in_array($origin, $allowedOrigins, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            }
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
