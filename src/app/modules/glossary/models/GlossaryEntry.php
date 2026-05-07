<?php

use KS\Mapper;

class GlossaryEntry extends Mapper
{
    const TABLE = 'glossary_entries';

    public static array $categories = [
        'lieu'          => 'Lieu',
        'personnage'    => 'Personnage',
        'organisation'  => 'Organisation',
        'objet'         => 'Objet',
        'terme'         => 'Terme / Concept',
        'autre'         => 'Autre',
    ];

    public function getAllByProject(int $projectId): array
    {
        return $this->findAndCast(
            ['project_id=?', $projectId],
            ['order' => 'category ASC, term ASC']
        ) ?: [];
    }

    public function getTermsJson(int $projectId): array
    {
        return $this->db->exec(
            'SELECT id, term, category, definition FROM glossary_entries WHERE project_id=? ORDER BY term ASC',
            [$projectId]
        ) ?: [];
    }
}
