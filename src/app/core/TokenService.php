<?php

/**
 * TokenService — single source of truth for JWT auth token operations.
 *
 * Responsibilities:
 *  - Encode / decode JWTs (HMAC-SHA256)
 *  - Encrypt / decrypt token files (AES-256-GCM)
 *  - Validate tokens in their three historical formats
 *  - Read / write per-user token files
 *
 * Controller::checkAutoLogin() and Controller::authenticateApiRequest()
 * delegate to this class, keeping the security logic in one place.
 */
class TokenService
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LEN = 16;

    public function __construct(private readonly string $secret) {}

    // ──────────────────────────────────────────────────────────────────────────
    // JWT encode / decode
    // ──────────────────────────────────────────────────────────────────────────

    public function encodeAuthJwt(string $tokenId, int $userId, int $iat): string
    {
        if (!$this->secret) {
            throw new \RuntimeException('JWT_SECRET not configured');
        }
        $payload = [
            'iat'  => $iat,
            'jti'  => $tokenId,
            'sub'  => (string) $userId,
            'type' => 'auth_token',
        ];
        return \Firebase\JWT\JWT::encode($payload, $this->secret, 'HS256');
    }

    /** Decode a JWT. Returns the payload on success, null on any error (expired, tampered, …). */
    public function decodeJwt(string $token): ?\stdClass
    {
        if (!$this->secret || substr_count($token, '.') !== 2) {
            return null;
        }
        try {
            return \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Token file helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Check whether a JTI exists (= not revoked) inside an encrypted token file.
     */
    public function isTokenInFile(string $filePath, string $jti): bool
    {
        $data = $this->readTokenFile($filePath);
        return $data !== null && isset($data['tokens'][$jti]);
    }

    /**
     * Read and parse a per-user tokens.json file.
     * Handles both the current encrypted format and the legacy plain-JSON format.
     *
     * @return array|null Decoded data or null on failure.
     */
    public function readTokenFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }
        $raw = file_get_contents($filePath);
        // Try decrypting first (current format).
        try {
            $json = $this->decrypt($raw);
        } catch (\Throwable) {
            // Legacy unencrypted format — fall through.
            $json = $raw;
        }
        $data = json_decode($json, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }

    /**
     * Persist a token array to disk (always encrypted).
     */
    public function writeTokenFile(string $filePath, array $data): void
    {
        $json      = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $encrypted = $this->encrypt($json);
        file_put_contents($filePath, $encrypted, LOCK_EX);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Legacy format validators (backward-compat)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Check the legacy global auth_tokens.json file (unencrypted, pre-per-user era).
     * Returns userId or null.
     */
    public function validateGlobalFallback(string $token, string $globalFile): ?int
    {
        if (!file_exists($globalFile)) {
            return null;
        }
        $data = json_decode(file_get_contents($globalFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tokens'][$token])) {
            return null;
        }
        return (int) $data['tokens'][$token]['user_id'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AES-256-GCM symmetric encryption (key derived from JWT_SECRET)
    // ──────────────────────────────────────────────────────────────────────────

    public function encrypt(string $data): string
    {
        $key   = $this->deriveKey();
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv    = random_bytes($ivLen);
        $tag   = '';

        $encrypted = openssl_encrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $encryptedData): string
    {
        $key   = $this->deriveKey();
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $raw   = base64_decode($encryptedData, true);

        if ($raw === false || strlen($raw) < $ivLen + self::TAG_LEN) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv         = substr($raw, 0, $ivLen);
        $tag        = substr($raw, $ivLen, self::TAG_LEN);
        $ciphertext = substr($raw, $ivLen + self::TAG_LEN);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    private function deriveKey(): string
    {
        return hash('sha256', $this->secret . 'encryption', true);
    }
}
