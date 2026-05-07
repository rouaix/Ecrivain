<?php

/**
 * PasswordResetService — Service de réinitialisation de mot de passe.
 */
class PasswordResetService
{
    private \DB\SQL $db;
    private Base $f3;
    private string $resetDir;

    public function __construct(\DB\SQL $db, Base $f3, string $resetDir)
    {
        $this->db = $db;
        $this->f3 = $f3;
        $this->resetDir = rtrim($resetDir, '/') . '/';
    }

    /**
     * Vérifie si un email existe dans le système.
     */
    public function userExists(string $email): bool
    {
        $userModel = new User();
        return $userModel->emailExists($email);
    }

    /**
     * Crée un token de réinitialisation pour un utilisateur.
     */
    public function createResetToken(array $user): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenData = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'token' => $token,
            'created_at' => time(),
            'expires_at' => time() + 3600, // 1 hour
        ];
        
        $file = $this->resetDir . $token . '.json';
        file_put_contents($file, json_encode($tokenData));
        
        return $token;
    }

    /**
     * Charge un token de réinitialisation.
     */
    public function loadResetToken(string $token): ?array
    {
        $file = $this->resetDir . $token . '.json';
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['expires_at']) || $data['expires_at'] < time()) {
            $this->removeResetToken($token);
            return null;
        }
        
        return $data;
    }

    /**
     * Supprime un token de réinitialisation.
     */
    public function removeResetToken(string $token): void
    {
        $file = $this->resetDir . $token . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur.
     */
    public function resetPassword(int $userId, string $newPassword): bool
    {
        $userModel = new User();
        $user = $userModel->findById($userId);
        if (!$user) {
            return false;
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $result = $this->db->exec(
            'UPDATE users SET password = ? WHERE id = ?',
            [$hashedPassword, $userId]
        );
        
        return $result !== false;
    }

    /**
     * Valide la force d'un mot de passe.
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Envoie l'email de réinitialisation de mot de passe.
     */
    public function sendResetEmail(string $email, string $resetUrl): bool
    {
        $subject = 'Réinitialisation de votre mot de passe - Écrivain';
        $message = "Bonjour,\n\n" .
                   "Vous avez demandé une réinitialisation de votre mot de passe.\n\n" .
                   "Pour continuer, veuillez cliquer sur le lien suivant :\n" .
                   $resetUrl . "\n\n" .
                   "Si vous n'avez pas demandé cela, veuillez ignorer cet email.\n\n" .
                   "Cordialement,\n" .
                   "L'équipe Écrivain";
        
        $mailer = new Mailer();
        return $mailer->send($email, $subject, $message);
    }

    /**
     * Construit l'URL absolue pour la réinitialisation.
     */
    public function buildResetUrl(string $token): string
    {
        $base = $this->f3->get('BASE');
        return $base . '/reset-password/' . $token;
    }
}
