<?php

/**
 * Chapter model manages chapter data within a project. Chapters store
 * the textual content of a manuscript. Each chapter belongs to a project
 * and has an order_index that can be used to sort chapters.
 *
 * Table structure:
 *
 *     CREATE TABLE IF NOT EXISTS chapters (
 *         id INTEGER PRIMARY KEY AUTOINCREMENT,
 *         project_id INTEGER NOT NULL,
 *         act_id INTEGER,
 *         title TEXT NOT NULL,
 *         content TEXT,
 *         order_index INTEGER DEFAULT 0,
 *         created_at TEXT DEFAULT CURRENT_TIMESTAMP,
 *         updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
 *         FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
 *         FOREIGN KEY (act_id) REFERENCES acts(id) ON DELETE CASCADE
 *     );
 */
class Chapter
{
    protected $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Get all chapters for a given project sorted by order_index.
     * Includes act and parent information.
     *
     * @param int $projectId
     * @return array
     */
    public function getAllByProject(int $projectId): array
    {
        $result = $this->db->execute_query(
            'SELECT c.*, a.title as act_title, a.order_index as act_order FROM chapters c LEFT JOIN acts a ON c.act_id = a.id WHERE c.project_id = ? ORDER BY (a.order_index IS NULL) ASC, a.order_index ASC, c.order_index ASC, c.id ASC',
            [$projectId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get only top-level chapters (no parent) for a given project or act.
     */
    public function getTopLevelByProject(int $projectId): array
    {
        $result = $this->db->execute_query(
            'SELECT c.*, a.title as act_title FROM chapters c LEFT JOIN acts a ON c.act_id = a.id WHERE c.project_id = ? AND c.parent_id IS NULL ORDER BY (a.order_index IS NULL) ASC, a.order_index ASC, c.order_index ASC, c.id ASC',
            [$projectId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get sub-chapters for a parent chapter.
     */
    public function getSubChapters(int $parentId): array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM chapters WHERE parent_id = ? ORDER BY order_index ASC, id ASC',
            [$parentId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Create a new chapter or sub-chapter.
     *
     * @param int $projectId
     * @param string $title
     * @param int|null $actId
     * @param int|null $parentId
     * @return int|false
     */
    public function create(int $projectId, string $title, ?int $actId = null, ?int $parentId = null)
    {
        // Determine next order index
        if ($parentId) {
            $result = $this->db->execute_query('SELECT MAX(order_index) FROM chapters WHERE parent_id = ?', [$parentId]);
        } elseif ($actId) {
            $result = $this->db->execute_query('SELECT MAX(order_index) FROM chapters WHERE act_id = ? AND parent_id IS NULL', [$actId]);
        } else {
            $result = $this->db->execute_query('SELECT MAX(order_index) FROM chapters WHERE project_id = ? AND act_id IS NULL AND parent_id IS NULL', [$projectId]);
        }
        $row = $result->fetch_row();
        $max = (int) ($row[0] ?? 0);
        $nextOrder = $max + 1;

        try {
            $this->db->execute_query(
                'INSERT INTO chapters (project_id, act_id, parent_id, title, order_index) VALUES (?, ?, ?, ?, ?)',
                [$projectId, $actId, $parentId, $title, $nextOrder]
            );
            return (int) $this->db->insert_id;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Retrieve a chapter by its ID. Optionally ensure it belongs to a specific project.
     *
     * @param int $id
     * @param int|null $projectId
     * @return array|null
     */
    public function find(int $id, ?int $projectId = null): ?array
    {
        $sql = 'SELECT * FROM chapters WHERE id = ?';
        $params = [$id];
        if ($projectId !== null) {
            $sql .= ' AND project_id = ?';
            $params[] = $projectId;
        }
        $result = $this->db->execute_query($sql . ' LIMIT 1', $params);
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update a chapter’s title, content, act, and parent.
     */
    public function update(int $id, string $title, string $content, ?int $actId = null, ?int $parentId = null): bool
    {
        try {
            $this->db->execute_query(
                'UPDATE chapters SET title = ?, content = ?, act_id = ?, parent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$title, $content, $actId, $parentId, $id]
            );
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Delete a chapter by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->execute_query('DELETE FROM chapters WHERE id = ?', [$id]);
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Reorder chapters for a given project. Accepts an array of chapter IDs
     * in the desired order and updates the order_index accordingly. Returns
     * true on success.
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
            foreach ($orderedIds as $cid) {
                $this->db->execute_query(
                    'UPDATE chapters SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index++, $cid, $projectId]
                );
            }
            $this->db->commit();
            return true;
        } catch (mysqli_sql_exception $e) {
            $this->db->rollback();
            return false;
        }
    }
}