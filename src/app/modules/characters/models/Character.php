<?php

use KS\Mapper;

class Character extends Mapper
{
    const TABLE = 'characters';

    /**
     * Retrieve all characters for a project, sorted by name.
     */
    public function getAllByProject(int $projectId): array
    {
        return $this->findAndCast(['project_id=?', $projectId], ['order' => 'name ASC']);
    }

    /**
     * Create a new character sheet.
     */
    public function create(int $projectId, string $name, ?string $description = null)
    {
        $this->reset();
        $this->project_id = $projectId;
        $this->name = $name;
        $this->description = $description;
        try {
            $this->save();
            return $this->id;
        } catch (\PDOException $e) {
            return false;
        }
    }

    // find/update/delete handled by Mapper methods
}