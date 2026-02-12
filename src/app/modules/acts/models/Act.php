<?php

use KS\Mapper;

class Act extends Mapper
{
    const TABLE = 'acts';

    /**
     * Get all acts for a given project sorted by order_index.
     */
    public function getAllByProject(int $projectId): array
    {
        return $this->findAndCast(['project_id=?', $projectId], ['order' => 'order_index ASC, id ASC']);
    }

    /**
     * Create a new act.
     */
    public function create(int $projectId, string $title, ?string $description = null)
    {
        $result = $this->db->exec('SELECT MAX(order_index) as max_order FROM acts WHERE project_id = ?', [$projectId]);
        $max = (int) ($result[0]['max_order'] ?? 0);

        $this->reset();
        $this->project_id = $projectId;
        $this->title = $title;
        $this->description = $description;
        $this->order_index = $max + 1;
        $this->save();
        return $this->id;
    }

    /**
     * Reorder acts.
     */
    public function reorder(int $projectId, array $orderedIds): bool
    {
        $this->db->begin();
        try {
            $index = 0;
            foreach ($orderedIds as $aid) {
                $this->db->exec(
                    'UPDATE acts SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index++, $aid, $projectId]
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
