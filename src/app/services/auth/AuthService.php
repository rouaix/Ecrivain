<?php

/**
 * AuthService — Service d'authentification.
 */
class AuthService
{
    private \DB\SQL $db;
    private Base $f3;

    public function __construct(\DB\SQL $db, Base $f3)
    {
        $this->db = $db;
        $this->f3 = $f3;
    }

    /**
     * Authentifie un utilisateur.
     * Retourne l'utilisateur ou null si échec.
     */
    public function authenticateUser(string $username, string $password): ?array
    {
        $userModel = new User();
        return $userModel->authenticate($username, $password);
    }

    /**
     * Initialise une nouvelle session pour un utilisateur.
     */
    public function initializeUserSession(array $user): void
    {
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(false);
        $_SESSION['user_id'] = $user['id'];
        session_write_close();
    }

    /**
     * Détruit la session actuelle.
     */
    public function destroySession(): void
    {
        session_unset();
        session_destroy();
    }

    /**
     * Vérifie si un utilisateur est actuellement connecté.
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Récupère l'utilisateur courant.
     */
    public function getCurrentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $userId = (int)$_SESSION['user_id'];
        $userModel = new User();
        return $userModel->findById($userId);
    }

    /**
     * Vérifie si le rate limiting est actif pour une IP.
     */
    public function isRateLimited(string $ip): bool
    {
        $file = $this->getRateLimitFile($ip);
        if (!file_exists($file)) {
            return false;
        }
        $data = json_decode(file_get_contents($file), true);
        $lastAttempt = $data['last_attempt'] ?? 0;
        $attempts = $data['attempts'] ?? 0;
        $now = time();
        
        // Reset after TTL
        if ($now - $lastAttempt > self::PASSWORD_RESET_TTL) {
            return false;
        }
        
        return $attempts >= 5;
    }

    /**
     * Incrémente le compteur de tentatives échouées pour une IP.
     */
    public function incrementFailedLogin(string $ip): void
    {
        $file = $this->getRateLimitFile($ip);
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : ['attempts' => 0, 'last_attempt' => 0];
        $data['attempts']++;
        $data['last_attempt'] = time();
        file_put_contents($file, json_encode($data));
    }

    /**
     * Chemin du fichier de rate limiting pour une IP.
     */
    private function getRateLimitFile(string $ip): string
    {
        $safeIp = preg_replace('/[^a-zA-Z0-9\.]/', '_', $ip);
        return sys_get_temp_dir() . '/rate_limit_' . $safeIp . '.json';
    }

    /**
     * Génère un token API pour un utilisateur.
     */
    public function generateApiToken(int $userId, string $name): array
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_BCRYPT);
        
        $this->db->exec(
            'INSERT INTO api_tokens (user_id, name, token_hash, created_at) VALUES (?, ?, ?, NOW())',
            [$userId, $name, $hashedToken]
        );
        
        $tokenId = (int)$this->db->lastInsertId('api_tokens');
        
        return [
            'id' => $tokenId,
            'name' => $name,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Liste les tokens API d'un utilisateur.
     */
    public function listApiTokens(int $userId): array
    {
        $rows = $this->db->exec(
            'SELECT id, name, created_at FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
        return $rows ?: [];
    }

    /**
     * Révoque un token API.
     */
    public function revokeApiToken(int $tokenId, int $userId): bool
    {
        $result = $this->db->exec(
            'DELETE FROM api_tokens WHERE id = ? AND user_id = ?',
            [$tokenId, $userId]
        );
        return $result !== false;
    }

    /**
     * TTL pour le rate limiting (en secondes).
     */
    private const PASSWORD_RESET_TTL = 3600;
}
