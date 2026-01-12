<?php

/**
 * Project model encapsulates CRUD operations for writing projects.
 *
 * The table structure is expected to look like this:
 *
 *     CREATE TABLE IF NOT EXISTS projects (
 *         id INTEGER PRIMARY KEY AUTOINCREMENT,
 *         user_id INTEGER NOT NULL,
 *         title TEXT NOT NULL,
 *         description TEXT,
 *         target_words INTEGER DEFAULT 0,
 *         words_per_page INTEGER DEFAULT 350,
 *         target_pages INTEGER DEFAULT 0,
 *         created_at TEXT DEFAULT CURRENT_TIMESTAMP,
 *         updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
 *         FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
 *     );
 */
class Project
{
    protected $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Fetch all projects owned by a given user.
     *
     * @param int $userId
     * @return array
     */
    public function getAllByUser(int $userId): array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Create a new project. Returns the inserted project ID on success.
     *
     * @param int $userId
     * @param string $title
     * @param string|null $description
     * @param int|null $targetWords
     * @param int|null $wordsPerPage
     * @param int|null $targetPages
     * @return int|false
     */
    public function create(int $userId, string $title, ?string $description = null, ?int $targetWords = 0, ?int $wordsPerPage = 350, ?int $targetPages = 0)
    {
        try {
            $this->db->execute_query(
                'INSERT INTO projects (user_id, title, description, target_words, words_per_page, target_pages) VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $title, $description, $targetWords, $wordsPerPage, $targetPages]
            );
            return (int) $this->db->insert_id;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Find a project by ID. Optionally ensure it belongs to a particular user.
     *
     * @param int $id
     * @param int|null $userId
     * @return array|null
     */
    public function find(int $id, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM projects WHERE id = ?';
        $params = [$id];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $result = $this->db->execute_query($sql . ' LIMIT 1', $params);
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update a project.
     *
     * @param int $id
     * @param string $title
     * @param string|null $description
     * @param int|null $targetWords
     * @param int|null $wordsPerPage
     * @param int|null $targetPages
     * @return bool
     */
    public function update(int $id, string $title, ?string $description = null, ?int $targetWords = 0, ?int $wordsPerPage = 350, ?int $targetPages = 0): bool
    {
        try {
            $this->db->execute_query(
                'UPDATE projects SET title = ?, description = ?, target_words = ?, words_per_page = ?, target_pages = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$title, $description, $targetWords, $wordsPerPage, $targetPages, $id]
            );
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Delete a project by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->execute_query('DELETE FROM projects WHERE id = ?', [$id]);
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}