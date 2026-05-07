<?php

use KS\Mapper;

class Scenario extends Mapper
{
    const TABLE = 'scenarios';

    /**
     * Get all scenarios for a given project.
     */
    public function getAllByProject(int $projectId): array
    {
        return $this->findAndCast(['project_id=?', $projectId], ['order' => 'order_index ASC, id ASC']);
    }
}
