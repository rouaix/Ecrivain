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
    public function authenticate(string $username, string $password): ?array
    {
        $this->load(['username=?', $username]);
        if ($this->dry()) {
            return null;
        }

        if (password_verify($password, $this->password)) {
            return $this->cast();
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
}