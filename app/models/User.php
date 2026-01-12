<?php

/**
 * User model to handle CRUD operations for application users.
 *
 * The table structure is expected to look like this:
 *
 *     CREATE TABLE IF NOT EXISTS users (
 *         id INTEGER PRIMARY KEY AUTOINCREMENT,
 *         username TEXT NOT NULL UNIQUE,
 *         password TEXT NOT NULL,
 *         email TEXT,
 *         created_at TEXT DEFAULT CURRENT_TIMESTAMP
 *     );
 */
class User
{
    /**
     * MySQLi instance for database access.
     *
     * @var mysqli
     */
    protected $db;

    /**
     * Construct the model with a MySQLi connection.
     *
     * @param mysqli $db
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new user. The password will be hashed using
     * PHP’s `password_hash()` function for security.
     *
     * @param string $username
     * @param string $password
     * @param string|null $email
     * @return int|false The inserted ID on success, or false on failure
     */
    public function create(string $username, string $password, ?string $email = null)
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $this->db->execute_query(
                'INSERT INTO users (username, password, email) VALUES (?, ?, ?)',
                [$username, $hash, $email]
            );
            return (int) $this->db->insert_id;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Find a user record by username.
     *
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username): ?array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM users WHERE username = ? LIMIT 1',
            [$username]
        );
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Validate a user’s credentials. Returns the user array on success,
     * otherwise returns null.
     *
     * @param string $username
     * @param string $password
     * @return array|null
     */
    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);
        if (!$user) {
            return null;
        }
        if (password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }
}