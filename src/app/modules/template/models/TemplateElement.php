<?php

use KS\Mapper;

class TemplateElement extends Mapper
{
    const TABLE = 'template_elements';

    /**
     * Get all elements for a template, sorted by display_order.
     */
    public function getAllByTemplate(int $templateId): array
    {
        return $this->findAndCast(
            ['template_id=?', $templateId],
            ['order' => 'display_order ASC']
        );
    }

    /**
     * Get only enabled elements for a template.
     */
    public function getEnabledByTemplate(int $templateId): array
    {
        return $this->findAndCast(
            ['template_id=? AND is_enabled=?', $templateId, 1],
            ['order' => 'display_order ASC']
        );
    }

    /**
     * Reorder template elements.
     */
    public function reorder(int $templateId, array $orderedIds): bool
    {
        $this->db->begin();
        try {
            $index = 0;
            foreach ($orderedIds as $tid) {
                $this->db->exec(
                    'UPDATE template_elements SET display_order = ? WHERE id = ? AND template_id = ?',
                    [$index++, $tid, $templateId]
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
