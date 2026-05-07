<?php

use KS\Mapper;

class User extends Mapper
{
    const TABLE = 'users';

    /**
     * Authenticate a user.
     *
     * @param string $username
     * @param string $password
     * @return array|null
     */
    public function authenticate(string $identifier, string $password): ?array
    {
        $user = $this->findByUsernameOrEmail($identifier);
        if (!$user) {
            return null;
        }

        if (password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    /**
     * Register a new user.
     *
     * @param string $username
     * @param string $password
     * @param string|null $email
     * @return bool
     */
    public function register(string $username, string $password, ?string $email = null): bool
    {
        $this->username = $username;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->email = $email;
        try {
            $this->save();
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Fetch a user by username or email.
     *
     * @param string $identifier
     * @return array|null
     */
    public function findByUsernameOrEmail(string $identifier): ?array
    {
        $this->load(['(username=? OR email=?)', $identifier, $identifier]);
        if ($this->dry()) {
            return null;
        }
        return $this->cast();
    }

    /**
     * Update the password for an existing user.
     *
     * @param int $userId
     * @param string $password
     * @return bool
     */
    public function resetPassword(int $userId, string $password): bool
    {
        $this->load(['id=?', $userId]);
        if ($this->dry()) {
            return false;
        }

        $this->password = password_hash($password, PASSWORD_DEFAULT);
        try {
            $this->save();
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
