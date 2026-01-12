<?php

/**
 * Act model manages acts within a project. Acts group chapters together
 * (e.g., Act 1 contains Chapters 1-8, Act 2 contains Chapters 9-15, etc.)
 *
 * Table structure:
 *
 *     CREATE TABLE IF NOT EXISTS acts (
 *         id INT PRIMARY KEY AUTO_INCREMENT,
 *         project_id INT NOT NULL,
 *         title VARCHAR(255) NOT NULL,
 *         description TEXT,
 *         order_index INT DEFAULT 0,
 *         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *         updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *         FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
 *     );
 */
class Act
{
    protected $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Get all acts for a given project sorted by order_index.
     *
     * @param int $projectId
     * @return array
     */
    public function getAllByProject(int $projectId): array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM acts WHERE project_id = ? ORDER BY order_index ASC, id ASC',
            [$projectId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Create a new act. If order_index is not specified it will be
     * appended at the end based on the highest existing order_index.
     *
     * @param int $projectId
     * @param string $title
     * @param string|null $description
     * @return int|false
     */
    public function create(int $projectId, string $title, ?string $description = null)
    {
        // Determine next order index
        $result = $this->db->execute_query('SELECT MAX(order_index) FROM acts WHERE project_id = ?', [$projectId]);
        $row = $result->fetch_row();
        $max = (int) ($row[0] ?? 0);
        $nextOrder = $max + 1;

        try {
            $this->db->execute_query(
                'INSERT INTO acts (project_id, title, description, order_index) VALUES (?, ?, ?, ?)',
                [$projectId, $title, $description, $nextOrder]
            );
            return (int) $this->db->insert_id;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Retrieve an act by its ID. Optionally ensure it belongs to a specific project.
     *
     * @param int $id
     * @param int|null $projectId
     * @return array|null
     */
    public function find(int $id, ?int $projectId = null): ?array
    {
        $sql = 'SELECT * FROM acts WHERE id = ?';
        $params = [$id];
        if ($projectId !== null) {
            $sql .= ' AND project_id = ?';
            $params[] = $projectId;
        }
        $result = $this->db->execute_query($sql . ' LIMIT 1', $params);
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update an act's title and description.
     *
     * @param int $id
     * @param string $title
     * @param string|null $description
     * @return bool
     */
    public function update(int $id, string $title, ?string $description = null): bool
    {
        try {
            $this->db->execute_query(
                'UPDATE acts SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$title, $description, $id]
            );
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Delete an act by ID. Note: This will also delete all chapters in this act
     * due to the CASCADE constraint.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->execute_query('DELETE FROM acts WHERE id = ?', [$id]);
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Reorder acts for a given project. Accepts an array of act IDs
     * in the desired order and updates the order_index accordingly.
     *
     * @param int $projectId
     * @param array $orderedIds
     * @return bool
     */
    public function reorder(int $projectId, array $orderedIds): bool
    {
        $this->db->begin_transaction();
        try {
            $index = 0;
            foreach ($orderedIds as $aid) {
                $this->db->execute_query(
                    'UPDATE acts SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index++, $aid, $projectId]
                );
            }
            $this->db->commit();
            return true;
        } catch (mysqli_sql_exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Get all chapters for a specific act.
     *
     * @param int $actId
     * @return array
     */
    public function getChapters(int $actId): array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM chapters WHERE act_id = ? ORDER BY order_index ASC, id ASC',
            [$actId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
