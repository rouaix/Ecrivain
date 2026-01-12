<?php

/**
 * Section model manages special sections within a project (cover, preface, introduction, etc.)
 * These sections appear before and after chapters in a specific order.
 *
 * Table structure:
 *
 *     CREATE TABLE IF NOT EXISTS sections (
 *         id INT PRIMARY KEY AUTO_INCREMENT,
 *         project_id INT NOT NULL,
 *         type VARCHAR(50) NOT NULL,
 *         title VARCHAR(255),
 *         content LONGTEXT,
 *         image_path VARCHAR(255),
 *         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *         updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *         FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
 *         UNIQUE KEY unique_section_per_project (project_id, type)
 *     );
 *
 * Section types (in order):
 * - cover (Couverture)
 * - preface (Préface)
 * - introduction (Introduction)
 * - prologue (Prologue)
 * - postface (Postface)
 * - appendices (Annexes)
 * - notes (Notes)
 * - back_cover (Dos du livre)
 */
class Section
{
    protected $db;

    // Define section types and their display order
    const SECTION_TYPES = [
        'cover' => ['name' => 'Couverture', 'order' => 1, 'position' => 'before'],
        'preface' => ['name' => 'Préface', 'order' => 2, 'position' => 'before'],
        'introduction' => ['name' => 'Introduction', 'order' => 3, 'position' => 'before'],
        'prologue' => ['name' => 'Prologue', 'order' => 4, 'position' => 'before'],
        'postface' => ['name' => 'Postface', 'order' => 5, 'position' => 'after'],
        'appendices' => ['name' => 'Annexes', 'order' => 6, 'position' => 'after'],
        'notes' => ['name' => 'Notes', 'order' => 7, 'position' => 'after'],
        'back_cover' => ['name' => 'Dos du livre', 'order' => 8, 'position' => 'after'],
    ];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Get all sections for a given project sorted by their defined order.
     *
     * @param int $projectId
     * @return array
     */
    public function getAllByProject(int $projectId): array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM sections WHERE project_id = ? ORDER BY order_index ASC',
            [$projectId]
        );
        $sections = $result->fetch_all(MYSQLI_ASSOC);

        // Sort primarily by order_index, then by the predefined type order for unranked items
        usort($sections, function ($a, $b) {
            $idxA = $a['order_index'] ?? 0;
            $idxB = $b['order_index'] ?? 0;

            if ($idxA !== $idxB) {
                return $idxA - $idxB;
            }

            $typeOrderA = self::SECTION_TYPES[$a['type']]['order'] ?? 999;
            $typeOrderB = self::SECTION_TYPES[$b['type']]['order'] ?? 999;
            return $typeOrderA - $typeOrderB;
        });

        return $sections;
    }

    /**
     * Get sections that appear before chapters.
     *
     * @param int $projectId
     * @return array
     */
    public function getBeforeChapters(int $projectId): array
    {
        $all = $this->getAllByProject($projectId);
        return array_filter($all, function ($section) {
            return self::SECTION_TYPES[$section['type']]['position'] === 'before';
        });
    }

    /**
     * Get sections that appear after chapters.
     *
     * @param int $projectId
     * @return array
     */
    public function getAfterChapters(int $projectId): array
    {
        $all = $this->getAllByProject($projectId);
        return array_filter($all, function ($section) {
            return self::SECTION_TYPES[$section['type']]['position'] === 'after';
        });
    }

    /**
     * Create or update a section. If a section of the same type exists for this project,
     * it will be updated instead of creating a duplicate.
     *
     * @param int $projectId
     * @param string $type
     * @param string|null $title
     * @param string|null $content
     * @param string|null $imagePath
     * @param int $orderIndex
     * @return int|false
     */
    public function create(int $projectId, string $type, ?string $title = null, ?string $content = null, ?string $imagePath = null, int $orderIndex = 0)
    {
        try {
            $this->db->execute_query(
                'INSERT INTO sections (project_id, type, title, content, image_path, order_index) VALUES (?, ?, ?, ?, ?, ?)',
                [$projectId, $type, $title, $content, $imagePath, $orderIndex]
            );
            return (int) $this->db->insert_id;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Create or update a section. For single-entry types, updates existing.
     * For multi-entry types (notes, appendices), creates new unless ID is provided.
     *
     * @param int $projectId
     * @param string $type
     * @param string|null $title
     * @param string|null $content
     * @param string|null $imagePath
     * @param int|null $id
     * @return int|false
     */
    public function createOrUpdate(int $projectId, string $type, ?string $title = null, ?string $content = null, ?string $imagePath = null, ?int $id = null)
    {
        if ($id) {
            return $this->update($id, $title, $content, $imagePath) ? $id : false;
        }

        // Multi-entry types always create new if no ID provided
        if ($type === 'notes' || $type === 'appendices') {
            return $this->create($projectId, $type, $title, $content, $imagePath);
        }

        // Single-entry types check for existing
        $existing = $this->findByType($projectId, $type);
        if ($existing) {
            return $this->update($existing['id'], $title, $content, $imagePath) ? $existing['id'] : false;
        }

        return $this->create($projectId, $type, $title, $content, $imagePath);
    }

    /**
     * Find a section by its ID.
     *
     * @param int $id
     * @param int|null $projectId
     * @return array|null
     */
    public function find(int $id, ?int $projectId = null): ?array
    {
        $sql = 'SELECT * FROM sections WHERE id = ?';
        $params = [$id];
        if ($projectId !== null) {
            $sql .= ' AND project_id = ?';
            $params[] = $projectId;
        }
        $result = $this->db->execute_query($sql . ' LIMIT 1', $params);
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Find a section by its type for a specific project.
     *
     * @param int $projectId
     * @param string $type
     * @return array|null
     */
    public function findByType(int $projectId, string $type): ?array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM sections WHERE project_id = ? AND type = ? LIMIT 1',
            [$projectId, $type]
        );
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update a section's content.
     *
     * @param int $id
     * @param string|null $title
     * @param string|null $content
     * @param string|null $imagePath
     * @return bool
     */
    public function update(int $id, ?string $title = null, ?string $content = null, ?string $imagePath = null): bool
    {
        try {
            $this->db->execute_query(
                'UPDATE sections SET title = ?, content = ?, image_path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$title, $content, $imagePath, $id]
            );
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Delete a section by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->execute_query('DELETE FROM sections WHERE id = ?', [$id]);
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Batch update the order of sections for a project.
     *
     * @param int $projectId
     * @param array $order Array of section IDs in the new order
     * @return bool
     */
    public function reorder(int $projectId, array $order): bool
    {
        $this->db->begin_transaction();
        try {
            foreach ($order as $index => $id) {
                $this->db->execute_query(
                    'UPDATE sections SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index, $id, $projectId]
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
     * Get the display name for a section type.
     *
     * @param string $type
     * @return string
     */
    public static function getTypeName(string $type): string
    {
        return self::SECTION_TYPES[$type]['name'] ?? $type;
    }

    /**
     * Get all available section types.
     *
     * @return array
     */
    public static function getAvailableTypes(): array
    {
        return self::SECTION_TYPES;
    }
}
