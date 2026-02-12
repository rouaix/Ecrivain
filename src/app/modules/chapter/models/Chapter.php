<?php

use KS\Mapper;

class Chapter extends Mapper
{
    const TABLE = 'chapters';

    /**
     * Get all chapters for a given project sorted by order_index.
     * Includes act and parent information.
     * Sorting ensures proper hierarchy: acts -> parent chapters -> sub-chapters (grouped with parent)
     */
    public function getAllByProject(int $projectId): array
    {
        // Custom query with self-join to get parent order_index for proper sorting
        return $this->db->exec(
            'SELECT c.*, a.title as act_title, a.order_index as act_order
             FROM chapters c
             LEFT JOIN acts a ON c.act_id = a.id
             LEFT JOIN chapters parent ON c.parent_id = parent.id
             WHERE c.project_id = ?
             ORDER BY
                (a.order_index IS NULL) ASC,
                a.order_index ASC,
                COALESCE(parent.order_index, c.order_index) ASC,
                (c.parent_id IS NOT NULL) ASC,
                c.order_index ASC,
                c.id ASC',
            [$projectId]
        );
    }

    /**
     * Get only top-level chapters (no parent).
     */
    public function getTopLevelByProject(int $projectId): array
    {
        return $this->db->exec(
            'SELECT c.*, a.title as act_title 
             FROM chapters c LEFT JOIN acts a ON c.act_id = a.id 
             WHERE c.project_id = ? AND c.parent_id IS NULL 
             ORDER BY (a.order_index IS NULL) ASC, a.order_index ASC, c.order_index ASC, c.id ASC',
            [$projectId]
        );
    }

    /**
     * Get sub-chapters for a parent chapter.
     */
    public function getSubChapters(int $parentId): array
    {
        return $this->findAndCast(['parent_id=?', $parentId], ['order' => 'order_index ASC, id ASC']);
    }

    /**
     * Get the next order index for a new item in the given context.
     */
    public function getNextOrder(int $projectId, ?int $actId = null, ?int $parentId = null): int
    {
        if ($parentId) {
            $res = $this->db->exec('SELECT MAX(order_index) as m FROM chapters WHERE parent_id = ?', [$parentId]);
        } elseif ($actId) {
            $res = $this->db->exec('SELECT MAX(order_index) as m FROM chapters WHERE act_id = ? AND parent_id IS NULL', [$actId]);
        } else {
            $res = $this->db->exec('SELECT MAX(order_index) as m FROM chapters WHERE project_id = ? AND act_id IS NULL AND parent_id IS NULL', [$projectId]);
        }
        return (int) ($res[0]['m'] ?? 0) + 1;
    }

    /**
     * Shift order down for existing items to make room at the beginning.
     */
    public function shiftOrderDown(int $projectId, ?int $actId = null, ?int $parentId = null): bool
    {
        if ($parentId) {
            // If parent_id is set, it defines the group strictly. Ignore act/project to avoid mismatch.
            return (bool) $this->db->exec("UPDATE chapters SET order_index = order_index + 1 WHERE parent_id = ?", [$parentId]);
        }

        // Logical fallback for Act/Project level
        $criteria = 'project_id = ?';
        $params = [$projectId];

        if ($actId) {
            $criteria .= ' AND act_id = ?';
            $params[] = $actId;
        } else {
            $criteria .= ' AND act_id IS NULL';
        }

        $criteria .= ' AND parent_id IS NULL';

        return (bool) $this->db->exec("UPDATE chapters SET order_index = order_index + 1 WHERE $criteria", $params);
    }

    /**
     * Create a new chapter or sub-chapter.
     */
    public function create(int $projectId, string $title, ?int $actId = null, ?int $parentId = null)
    {
        $this->reset();
        $this->project_id = $projectId;
        $this->title = $title;
        $this->act_id = $actId;
        $this->parent_id = $parentId;
        $this->order_index = $this->getNextOrder($projectId, $actId, $parentId);
        $this->save();
        return $this->id;
    }

    /**
     * Reorder chapters.
     */
    public function reorder(int $projectId, array $orderedIds): bool
    {
        $this->db->begin();
        try {
            $index = 0;
            foreach ($orderedIds as $cid) {
                $this->db->exec(
                    'UPDATE chapters SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index++, $cid, $projectId]
                );
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
}