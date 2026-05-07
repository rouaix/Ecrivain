<?php

/**
 * RegistrationService — Service d'inscription des utilisateurs.
 */
class RegistrationService
{
    private \DB\SQL $db;
    private Base $f3;

    public function __construct(\DB\SQL $db, Base $f3)
    {
        $this->db = $db;
        $this->f3 = $f3;
    }

    /**
     * Valide les données d'inscription.
     */
    public function validateRegistration(array $data): array
    {
        $errors = [];
        
        // Username
        $username = trim($data['username'] ?? '');
        if (empty($username)) {
            $errors['username'] = 'Le nom d\'utilisateur est obligatoire.';
        } elseif (strlen($username) < 3) {
            $errors['username'] = 'Le nom d\'utilisateur doit contenir au moins 3 caractères.';
        } elseif (strlen($username) > 50) {
            $errors['username'] = 'Le nom d\'utilisateur ne peut pas dépasser 50 caractères.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres et underscores.';
        }
        
        // Email
        $email = trim($data['email'] ?? '');
        if (empty($email)) {
            $errors['email'] = 'L\'email est obligatoire.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'L\'email n\'est pas valide.';
        } elseif ($this->emailExists($email)) {
            $errors['email'] = 'Cet email est déjà utilisé.';
        }
        
        // Password
        $password = $data['password'] ?? '';
        if (empty($password)) {
            $errors['password'] = 'Le mot de passe est obligatoire.';
        } else {
            $passwordValidation = $this->validatePasswordStrength($password);
            if (!$passwordValidation['valid']) {
                $errors['password'] = implode(' ', $passwordValidation['errors']);
            }
        }
        
        // Password confirmation
        $passwordConfirm = $data['password_confirmation'] ?? '';
        if ($password !== $passwordConfirm) {
            $errors['password_confirmation'] = 'Les mots de passe ne correspondent pas.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => [
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ],
        ];
    }

    /**
     * Crée un nouvel utilisateur.
     */
    public function createUser(array $userData): array
    {
        $username = $userData['username'];
        $email = $userData['email'];
        $password = password_hash($userData['password'], PASSWORD_BCRYPT);
        
        $this->db->exec(
            'INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())',
            [$username, $email, $password]
        );
        
        $userId = (int)$this->db->lastInsertId('users');
        
        // Create default template for the user
        $this->db->exec(
            'INSERT INTO user_templates (user_id, name, config) VALUES (?, ?, ?)',
            [$userId, 'Par défaut', json_encode(['type' => 'standard'])]
        );
        
        return [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
        ];
    }

    /**
     * Vérifie si un email existe déjà.
     */
    public function emailExists(string $email): bool
    {
        $result = $this->db->exec(
            'SELECT COUNT(*) AS count FROM users WHERE email = ?',
            [$email]
        );
        return (int)($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Valide la force d'un mot de passe.
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'doit contenir au moins 8 caractères';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'doit contenir au moins une majuscule';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'doit contenir au moins une minuscule';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'doit contenir au moins un chiffre';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Envoie un email de bienvenue.
     */
    public function sendWelcomeEmail(string $email, string $username): bool
    {
        $subject = 'Bienvenue sur Écrivain !';
        $message = "Bonjour $username,\n\n" .
                   "Bienvenue sur Écrivain, votre outil d'écriture créative !\n\n" .
                   "Vous pouvez maintenant commencer à écrire votre premier projet.\n\n" .
                   "Cordialement,\n" .
                   "L'équipe Écrivain";
        
        $mailer = new Mailer();
        return $mailer->send($email, $subject, $message);
    }
}
