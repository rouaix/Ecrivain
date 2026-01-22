<?php

use KS\Mapper;

class Note extends Mapper
{
    const TABLE = 'notes';

    /**
     * Get all notes for a given project.
     */
    public function getAllByProject(int $projectId): array
    {
        return $this->findAndCast(['project_id=?', $projectId], ['order' => 'order_index ASC']);
    }

    /**
     * Create a new note.
     */
    public function create(int $projectId, ?string $title = null, ?string $content = null, ?string $imagePath = null, int $orderIndex = 0)
    {
        $this->reset();
        $this->project_id = $projectId;
        $this->title = $title;
        $this->content = $content;
        $this->image_path = $imagePath;
        $this->order_index = $orderIndex;

        try {
            $this->save();
            return $this->id;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Batch update the order of notes for a project.
     */
    public function reorder(int $projectId, array $order): bool
    {
        $this->db->begin();
        try {
            foreach ($order as $index => $id) {
                $this->db->exec(
                    'UPDATE notes SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index, $id, $projectId]
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
