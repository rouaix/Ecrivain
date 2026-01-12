<?php

/**
 * Character model manages character sheets associated with a project.
 *
 * Table structure:
 *
 *     CREATE TABLE IF NOT EXISTS characters (
 *         id INTEGER PRIMARY KEY AUTOINCREMENT,
 *         project_id INTEGER NOT NULL,
 *         name TEXT NOT NULL,
 *         description TEXT,
 *         created_at TEXT DEFAULT CURRENT_TIMESTAMP,
 *         updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
 *         FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
 *     );
 */
class Character
{
    protected $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Retrieve all characters for a project, sorted by name.
     *
     * @param int $projectId
     * @return array
     */
    public function getAllByProject(int $projectId): array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM characters WHERE project_id = ? ORDER BY name ASC',
            [$projectId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Create a new character sheet.
     *
     * @param int $projectId
     * @param string $name
     * @param string|null $description
     * @return int|false
     */
    public function create(int $projectId, string $name, ?string $description = null)
    {
        try {
            $this->db->execute_query(
                'INSERT INTO characters (project_id, name, description) VALUES (?, ?, ?)',
                [$projectId, $name, $description]
            );
            return (int) $this->db->insert_id;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Find a character by ID. Optionally ensure it belongs to a given project.
     *
     * @param int $id
     * @param int|null $projectId
     * @return array|null
     */
    public function find(int $id, ?int $projectId = null): ?array
    {
        $sql = 'SELECT * FROM characters WHERE id = ?';
        $params = [$id];
        if ($projectId !== null) {
            $sql .= ' AND project_id = ?';
            $params[] = $projectId;
        }
        $result = $this->db->execute_query($sql . ' LIMIT 1', $params);
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update a character sheet.
     *
     * @param int $id
     * @param string $name
     * @param string|null $description
     * @return bool
     */
    public function update(int $id, string $name, ?string $description = null): bool
    {
        try {
            $this->db->execute_query(
                'UPDATE characters SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$name, $description, $id]
            );
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Delete a character by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->execute_query('DELETE FROM characters WHERE id = ?', [$id]);
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}