<?php

/**
 * ProjectService — centralise les requêtes liées aux projets et au dashboard.
 *
 * Extrait de ProjectController::dashboard() qui cumulait ~110 lignes de requêtes
 * et de logique métier mélangées.
 *
 * Usage :
 *   $svc = new ProjectService($this->db);
 *   $projects = $svc->getOwnedProjects($user['id']);
 */
class ProjectService
{
    public function __construct(private \DB\SQL $db) {}

    /**
     * Projets dont l'utilisateur est propriétaire, enrichis avec pages_count et status.
     */
    public function getOwnedProjects(int $userId): array
    {
        $projectModel = new Project();
        $projects = $projectModel->findAndCast(['user_id=?', $userId], ['order' => 'created_at DESC']) ?: [];

        foreach ($projects as &$proj) {
            $wpp = $proj['words_per_page'] ?: 350;
            $proj['pages_count'] = ceil($proj['target_words'] / $wpp);
            $proj['status']      = $proj['status'] ?? 'active';
        }
        unset($proj);

        return $projects;
    }

    /**
     * Projets auxquels l'utilisateur participe comme collaborateur accepté.
     */
    public function getSharedProjects(int $userId): array
    {
        return $this->db->exec(
            'SELECT p.*, pc.accepted_at, u.username AS owner_username
             FROM projects p
             JOIN project_collaborators pc ON pc.project_id = p.id
             JOIN users u ON u.id = p.user_id
             WHERE pc.user_id = ? AND pc.status = "accepted"
             ORDER BY p.updated_at DESC',
            [$userId]
        ) ?: [];
    }

    /**
     * Invitations en attente reçues par l'utilisateur.
     */
    public function getPendingInvitations(int $userId): array
    {
        return $this->db->exec(
            'SELECT pc.id, p.title AS project_title, u.username AS owner_username
             FROM project_collaborators pc
             JOIN projects p ON p.id = pc.project_id
             JOIN users u ON u.id = pc.owner_id
             WHERE pc.user_id = ? AND pc.status = "pending"',
            [$userId]
        ) ?: [];
    }

    /**
     * Map [project_id => [tag, tag, ...]] pour une liste de projets.
     *
     * @param int[] $projectIds
     */
    public function getTagsForProjects(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->exec(
            "SELECT ptl.project_id, pt.name
             FROM project_tags pt
             JOIN project_tag_links ptl ON ptl.tag_id = pt.id
             WHERE ptl.project_id IN ($placeholders)
             ORDER BY pt.name ASC",
            $projectIds
        ) ?: [];

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['project_id']][] = $r['name'];
        }
        return $map;
    }

    /**
     * Toutes les étiquettes distinctes de l'utilisateur (pour la barre de filtre).
     *
     * @return array<array{name: string}>
     */
    public function getAllTags(int $userId): array
    {
        return $this->db->exec(
            'SELECT DISTINCT pt.name FROM project_tags pt
             JOIN project_tag_links ptl ON ptl.tag_id = pt.id
             JOIN projects p ON p.id = ptl.project_id
             WHERE p.user_id = ?
             ORDER BY pt.name ASC',
            [$userId]
        ) ?: [];
    }

    /**
     * Calcule les mots écrits aujourd'hui (delta par rapport à hier) et le pourcentage de l'objectif.
     *
     * @param int $userId
     * @param int $dailyGoal  Objectif journalier en mots (0 = désactivé)
     * @return array{wordsToday: int, dailyPct: int}
     */
    public function getDailyProgress(int $userId, int $dailyGoal): array
    {
        if ($dailyGoal <= 0) {
            return ['wordsToday' => 0, 'dailyPct' => 0];
        }

        $todayStr = date('Y-m-d');

        $snapRows = $this->db->exec(
            'SELECT ws.chapter_id, ws.word_count
             FROM writing_stats ws
             WHERE ws.user_id = ? AND ws.stat_date = ?',
            [$userId, $todayStr]
        ) ?: [];

        $prevRows = $this->db->exec(
            'SELECT ws.chapter_id, ws.word_count
             FROM writing_stats ws
             WHERE ws.user_id = ? AND ws.stat_date = DATE_SUB(?, INTERVAL 1 DAY)',
            [$userId, $todayStr]
        ) ?: [];

        $prevMap = [];
        foreach ($prevRows as $r) {
            $prevMap[$r['chapter_id']] = (int) $r['word_count'];
        }

        $wordsToday = 0;
        foreach ($snapRows as $r) {
            $delta = (int) $r['word_count'] - ($prevMap[$r['chapter_id']] ?? 0);
            if ($delta > 0) {
                $wordsToday += $delta;
            }
        }

        $dailyPct = min(100, (int) round($wordsToday / $dailyGoal * 100));

        return ['wordsToday' => $wordsToday, 'dailyPct' => $dailyPct];
    }
}
