<?php

/**
 * CharacterMentionService — Gère la recherche et l'organisation des mentions de personnages dans les chapitres.
 */
class CharacterMentionService
{
    private \DB\SQL $db;

    public function __construct(\DB\SQL $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les chapitres où un personnage est mentionné et construit une timeline.
     *
     * @param int $characterId ID du personnage
     * @param int $projectId ID du projet
     * @param string $characterName Nom du personnage à rechercher
     * @param array $allChapters Liste de tous les chapitres du projet (optionnel, pour optimisation)
     * @return array ['mentions' => [...], 'timeline' => [...], 'mentionedIds' => [...], 'mentionCount' => int]
     */
    public function getMentionsAndTimeline(int $characterId, int $projectId, string $characterName, ?array $allChapters = null): array
    {
        // Charger tous les chapitres si non fournis
        if ($allChapters === null) {
            $allChapters = $this->db->exec(
                'SELECT c.id, c.title, c.order_index, c.act_id,
                        a.title AS act_title, a.order_index AS act_order
                 FROM chapters c
                 LEFT JOIN acts a ON a.id = c.act_id
                 WHERE c.project_id = ?
                 ORDER BY COALESCE(a.order_index, 0) ASC, c.order_index ASC, c.id ASC',
                [$projectId]
            ) ?: [];
        }

        // Chapitres où le nom du personnage est mentionné
        $mentionedIds = [];
        $mentions = [];

        if ($characterName && $allChapters) {
            $rows = $this->db->exec(
                'SELECT c.id, c.title, c.order_index, c.act_id,
                        a.title AS act_title, a.order_index AS act_order
                 FROM chapters c
                 LEFT JOIN acts a ON a.id = c.act_id
                 WHERE c.project_id = ?
                   AND (c.content LIKE ? OR c.resume LIKE ?)
                 ORDER BY COALESCE(a.order_index, 0) ASC, c.order_index ASC, c.id ASC',
                [$projectId, '%' . $characterName . '%', '%' . $characterName . '%']
            ) ?: [];

            foreach ($rows as $row) {
                $mentionedIds[$row['id']] = true;
                $actKey = $row['act_id'] ?? 0;
                $actLabel = $row['act_title'] ?: 'Sans acte';
                if (!isset($mentions[$actKey])) {
                    $mentions[$actKey] = ['label' => $actLabel, 'chapters' => []];
                }
                $mentions[$actKey]['chapters'][] = $row;
            }
        }

        // Construire la timeline: tous les chapitres groupés par acte, avec flag de présence
        $timeline = [];
        foreach ($allChapters as $ch) {
            $actKey = $ch['act_id'] ?? 0;
            $actLabel = $ch['act_title'] ?: 'Sans acte';
            if (!isset($timeline[$actKey])) {
                $timeline[$actKey] = ['label' => $actLabel, 'chapters' => []];
            }
            $ch['mentioned'] = isset($mentionedIds[$ch['id']]) ? 1 : 0;
            $timeline[$actKey]['chapters'][] = $ch;
        }

        return [
            'mentions' => array_values($mentions),
            'timeline' => array_values($timeline),
            'mentionedIds' => $mentionedIds,
            'mentionCount' => count($mentionedIds),
        ];
    }

    /**
     * Récupère uniquement la liste des chapitres où un personnage est mentionné.
     *
     * @param int $projectId ID du projet
     * @param string $characterName Nom du personnage
     * @return array Liste des chapitres mentionnés
     */
    public function getMentionedChapters(int $projectId, string $characterName): array
    {
        if (empty($characterName)) {
            return [];
        }

        return $this->db->exec(
            'SELECT c.id, c.title, c.order_index, c.act_id,
                    a.title AS act_title, a.order_index AS act_order
             FROM chapters c
             LEFT JOIN acts a ON a.id = c.act_id
             WHERE c.project_id = ?
               AND (c.content LIKE ? OR c.resume LIKE ?)
             ORDER BY COALESCE(a.order_index, 0) ASC, c.order_index ASC, c.id ASC',
            [$projectId, '%' . $characterName . '%', '%' . $characterName . '%']
        ) ?: [];
    }

    /**
     * Vérifie si un personnage est mentionné dans un chapitre spécifique.
     *
     * @param int $chapterId ID du chapitre
     * @param string $characterName Nom du personnage
     * @return bool
     */
    public function isMentionedInChapter(int $chapterId, string $characterName): bool
    {
        if (empty($characterName)) {
            return false;
        }

        $count = $this->db->exec(
            'SELECT COUNT(*) AS n FROM chapters c
             WHERE c.id = ? AND (c.content LIKE ? OR c.resume LIKE ?)',
            [$chapterId, '%' . $characterName . '%', '%' . $characterName . '%']
        );

        return (int)($count[0]['n'] ?? 0) > 0;
    }
}
