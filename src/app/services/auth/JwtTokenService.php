<?php

/**
 * JwtTokenService — Service de gestion des tokens JWT pour l'auto-login.
 *
 * Responsabilités :
 *  - Générer des tokens JWT pour l'auto-login
 *  - Lister les tokens JWT d'un utilisateur
 *  - Révoquer des tokens JWT
 *  - Gérer la persistance dans les fichiers tokens.json
 */
class JwtTokenService
{
    private TokenService $tokenService;
    private string $rootPath;

    public function __construct(TokenService $tokenService, string $rootPath = '')
    {
        $this->tokenService = $tokenService;
        $this->rootPath = $rootPath;
    }

    /**
     * Sanitize email for use in filesystem paths.
     * Prevents path traversal attacks.
     */
    private function sanitizeEmailForPath(string $email): string
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
     * Get safe user data directory path.
     */
    private function getUserDataDir(string $email): string
    {
        if (!$email) {
            return 'data/anonymous';
        }
        $safeEmail = $this->sanitizeEmailForPath($email);
        return 'data/' . $safeEmail;
    }

    /**
     * Get the tokens.json file path for a user.
     */
    private function getTokenFilePath(string $email): string
    {
        $userDir = $this->getUserDataDir($email);
        return $userDir . '/tokens.json';
    }

    /**
     * Génère un token JWT pour l'auto-login.
     *
     * @param int $userId ID de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @return array Tableau contenant le token JWT
     */
    public function generateToken(int $userId, string $email): array
    {
        // Generate unique token ID (jti claim)
        $tokenId = bin2hex(random_bytes(16));
        $iat = time();

        // Sign JWT with HMAC SHA256
        $jwt = $this->tokenService->encodeAuthJwt($tokenId, $userId, $iat);

        // Store token metadata (for revocation)
        $jsonFile = $this->getTokenFilePath($email);
        $userDir = $this->getUserDataDir($email);

        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        $data = $this->tokenService->readTokenFile($jsonFile);
        $tokens = $data['tokens'] ?? [];

        $tokens[$tokenId] = [
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'iat' => $iat,
        ];

        $this->tokenService->writeTokenFile($jsonFile, ['tokens' => $tokens]);

        return ['token' => $jwt];
    }

    /**
     * Liste les tokens JWT d'un utilisateur.
     *
     * @param int $userId ID de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @return array Tableau contenant la liste des tokens
     */
    public function listTokens(int $userId, string $email): array
    {
        $jsonFile = $this->getTokenFilePath($email);
        $userTokens = [];

        $data = $this->tokenService->readTokenFile($jsonFile);

        if ($data && isset($data['tokens'])) {
            foreach ($data['tokens'] as $tokenId => $info) {
                if (isset($info['user_id']) && $info['user_id'] == $userId) {
                    $createdAt = $info['created_at'] ?? null;
                    $iat = $info['iat'] ?? ($createdAt ? strtotime($createdAt) : time());
                    $tokenValue = null;

                    try {
                        $tokenValue = $this->tokenService->encodeAuthJwt($tokenId, $userId, $iat);
                    } catch (\Exception $e) {
                        // If encoding fails, keep token hidden but continue listing metadata
                        $tokenValue = null;
                    }

                    $userTokens[] = [
                        'token_id' => $tokenId,
                        'created_at' => $createdAt,
                        'token' => $tokenValue
                    ];
                }
            }
        }

        // Sort by date desc
        usort($userTokens, function ($a, $b) {
            $bTime = strtotime($b['created_at'] ?? '') ?: 0;
            $aTime = strtotime($a['created_at'] ?? '') ?: 0;
            return $bTime - $aTime;
        });

        return ['tokens' => $userTokens];
    }

    /**
     * Résout un token ID à partir d'un JWT brut.
     *
     * @param string $token Token JWT brut
     * @return string|null Token ID (jti) ou null si échec
     */
    public function resolveTokenIdFromJwt(string $token): ?string
    {
        $decoded = $this->tokenService->decodeJwt($token);
        return $decoded ? ($decoded->jti ?? null) : null;
    }

    /**
     * Révoque des tokens JWT.
     *
     * @param int $userId ID de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @param string|null $targetTokenId ID du token à révoquer
     * @param string|null $targetTokenRaw Token JWT brut à révoquer
     * @return array Tableau contenant le nombre de tokens révoqués
     */
    public function revokeTokens(
        int $userId,
        string $email,
        ?string $targetTokenId = null,
        ?string $targetTokenRaw = null
    ): array {
        $jsonFile = $this->getTokenFilePath($email);

        if (!file_exists($jsonFile)) {
            return ['count' => 0];
        }

        $data = $this->tokenService->readTokenFile($jsonFile);

        if (!$data || !isset($data['tokens'])) {
            return ['count' => 0];
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

        $this->tokenService->writeTokenFile($jsonFile, ['tokens' => $tokens]);

        return ['count' => $count];
    }
}
