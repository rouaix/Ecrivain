<?php

use KS\Mapper;

class Element extends Mapper
{
    const TABLE = 'elements';

    /**
     * Get all elements for a given project sorted by order_index.
     * Includes template_element configuration and parent information.
     * Sorting ensures proper hierarchy: template_element -> parent elements -> sub-elements (grouped with parent)
     */
    public function getAllByProject(int $projectId): array
    {
        // Custom query with self-join to get parent order_index for proper sorting
        return $this->db->exec(
            'SELECT e.*, te.element_type, te.config_json, te.display_order
             FROM elements e
             LEFT JOIN template_elements te ON e.template_element_id = te.id
             LEFT JOIN elements parent ON e.parent_id = parent.id
             WHERE e.project_id = ?
             ORDER BY
                te.display_order ASC,
                COALESCE(parent.order_index, e.order_index) ASC,
                (e.parent_id IS NOT NULL) ASC,
                e.order_index ASC,
                e.id ASC',
            [$projectId]
        );
    }

    /**
     * Get only top-level elements (no parent).
     */
    public function getTopLevelByProject(int $projectId): array
    {
        return $this->db->exec(
            'SELECT e.*, te.element_type, te.config_json, te.display_order
             FROM elements e
             LEFT JOIN template_elements te ON e.template_element_id = te.id
             WHERE e.project_id = ? AND e.parent_id IS NULL
             ORDER BY te.display_order ASC, e.order_index ASC, e.id ASC',
            [$projectId]
        );
    }

    /**
     * Get elements by template_element_id (for grouping by type in display).
     */
    public function getByTemplateElement(int $projectId, int $templateElementId): array
    {
        return $this->db->exec(
            'SELECT e.*, parent.order_index as parent_order_index
             FROM elements e
             LEFT JOIN elements parent ON e.parent_id = parent.id
             WHERE e.project_id = ? AND e.template_element_id = ?
             ORDER BY
                COALESCE(parent.order_index, e.order_index) ASC,
                (e.parent_id IS NOT NULL) ASC,
                e.order_index ASC,
                e.id ASC',
            [$projectId, $templateElementId]
        );
    }

    /**
     * Get sub-elements for a parent element.
     */
    public function getSubElements(int $parentId): array
    {
        return $this->findAndCast(['parent_id=?', $parentId], ['order' => 'order_index ASC, id ASC']);
    }

    /**
     * Get the next order index for a new item in the given context.
     */
    public function getNextOrder(int $projectId, int $templateElementId, ?int $parentId = null): int
    {
        if ($parentId) {
            $res = $this->db->exec('SELECT MAX(order_index) as m FROM elements WHERE parent_id = ?', [$parentId]);
        } else {
            $res = $this->db->exec('SELECT MAX(order_index) as m FROM elements WHERE project_id = ? AND template_element_id = ? AND parent_id IS NULL', [$projectId, $templateElementId]);
        }
        return (int) ($res[0]['m'] ?? -1) + 1;
    }

    /**
     * Shift order down for existing items to make room at the beginning.
     */
    public function shiftOrderDown(int $projectId, int $templateElementId, ?int $parentId = null): bool
    {
        if ($parentId) {
            // If parent_id is set, it defines the group strictly.
            return (bool) $this->db->exec("UPDATE elements SET order_index = order_index + 1 WHERE parent_id = ?", [$parentId]);
        }

        // Logical fallback for template_element level
        $criteria = 'project_id = ? AND template_element_id = ? AND parent_id IS NULL';
        $params = [$projectId, $templateElementId];

        return (bool) $this->db->exec("UPDATE elements SET order_index = order_index + 1 WHERE $criteria", $params);
    }

    /**
     * Create a new element or sub-element.
     */
    public function create(int $projectId, int $templateElementId, string $title, ?int $parentId = null)
    {
        $this->reset();
        $this->project_id = $projectId;
        $this->template_element_id = $templateElementId;
        $this->title = $title;
        $this->parent_id = $parentId;
        $this->order_index = $this->getNextOrder($projectId, $templateElementId, $parentId);
        $this->save();
        return $this->id;
    }

    /**
     * Change template_element_id for an element and all its direct sub-elements.
     * Positions the element at the end of the new type's list.
     */
    public function changeType(int $elementId, int $newTemplateElementId, int $projectId): void
    {
        // Get next order position in the target type (excluding the element itself)
        $res = $this->db->exec(
            'SELECT MAX(order_index) as m FROM elements WHERE project_id = ? AND template_element_id = ? AND parent_id IS NULL AND id != ?',
            [$projectId, $newTemplateElementId, $elementId]
        );
        $newOrder = (int) ($res[0]['m'] ?? -1) + 1;

        // Update type for element and all its sub-elements
        $this->db->exec(
            'UPDATE elements SET template_element_id = ? WHERE id = ? OR parent_id = ?',
            [$newTemplateElementId, $elementId, $elementId]
        );

        // Place element at the end of the new type
        $this->db->exec(
            'UPDATE elements SET order_index = ? WHERE id = ?',
            [$newOrder, $elementId]
        );
    }

    /**
     * Reorder elements.
     */
    public function reorder(int $projectId, array $orderedIds): bool
    {
        $this->db->begin();
        try {
            $index = 0;
            foreach ($orderedIds as $eid) {
                $this->db->exec(
                    'UPDATE elements SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index++, $eid, $projectId]
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
