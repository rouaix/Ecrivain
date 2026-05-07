<?php

use KS\Mapper;

class Synopsis extends Mapper
{
    const TABLE = 'synopsis';

    public function getByProject(int $projectId): ?array
    {
        $rows = $this->findAndCast(['project_id=?', $projectId]);
        return $rows ? $rows[0] : null;
    }

    public function createForProject(int $projectId): int
    {
        $this->reset();
        $this->project_id = $projectId;
        $this->save();
        return (int) $this->id;
    }

    public function updateFields(int $projectId, array $fields): bool
    {
        $this->load(['project_id=?', $projectId]);
        if ($this->dry()) return false;

        $allowed = [
            'genre', 'subgenre', 'audience', 'tone', 'themes', 'comps',
            'status', 'structure_method',
            'logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
            'development', 'midpoint', 'crisis', 'climax', 'resolution',
            'is_exported',
        ];

        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $this->$key = $value;
            }
        }

        try {
            $this->save();
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
