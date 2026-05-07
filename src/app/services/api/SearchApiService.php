<?php

/**
 * SearchApiService — Service API pour les opérations de recherche.
 */
class SearchApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Effectue une recherche dans les projets accessibles à l'utilisateur.
     */
    public function search(int $userId, string $query, ?int $projectId = null): array
    {
        $q = trim($query);
        if ($q === '') {
            throw new \InvalidArgumentException('Paramètre q obligatoire.');
        }
        if (strlen($q) < 2) {
            throw new \InvalidArgumentException('La recherche doit contenir au moins 2 caractères.');
        }

        $results = [];
        $like = '%' . $q . '%';

        // Build project scope: only projects accessible to the user
        if ($projectId) {
            if (!$this->hasProjectAccess($projectId, $userId)) {
                throw new \RuntimeException('Accès refusé.');
            }
            $projectIds = [$projectId];
        } else {
            $ownedRows = $this->db->exec('SELECT id FROM projects WHERE user_id = ?', [$userId]);
            $collabRows = $this->db->exec(
                'SELECT project_id AS id FROM project_collaborators WHERE user_id = ? AND status = "accepted"',
                [$userId]
            );
            $projectIds = array_column(array_merge($ownedRows, $collabRows), 'id');
        }

        if (empty($projectIds)) {
            return ['query' => $q, 'results' => []];
        }

        $inList = implode(',', array_map('intval', $projectIds));

        // Chapters
        $rows = $this->db->exec(
            "SELECT 'chapter' AS type, id, project_id, title,
                    SUBSTRING(content, GREATEST(1, LOCATE(?, content)-60), 120) AS excerpt
             FROM chapters WHERE project_id IN ($inList) AND (title LIKE ? OR content LIKE ?)",
            [$q, $like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'chapter', 'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'], 'excerpt' => strip_tags($r['excerpt'])];
        }

        // Characters
        $rows = $this->db->exec(
            "SELECT id, project_id, name AS title, description AS excerpt
             FROM characters WHERE project_id IN ($inList) AND (name LIKE ? OR description LIKE ?)",
            [$like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'character', 'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'], 'excerpt' => substr(strip_tags($r['excerpt'] ?? ''), 0, 120)];
        }

        // Notes
        $rows = $this->db->exec(
            "SELECT id, project_id, title,
                    SUBSTRING(content, GREATEST(1, LOCATE(?, content)-60), 120) AS excerpt
             FROM notes WHERE project_id IN ($inList) AND (title LIKE ? OR content LIKE ?)",
            [$q, $like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'note', 'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'] ?? '(sans titre)', 'excerpt' => strip_tags($r['excerpt'])];
        }

        // Elements
        $rows = $this->db->exec(
            "SELECT e.id, e.project_id, e.title,
                    SUBSTRING(e.content, GREATEST(1, LOCATE(?, e.content)-60), 120) AS excerpt,
                    te.element_type
             FROM elements e
             LEFT JOIN template_elements te ON te.id = e.template_element_id
             WHERE e.project_id IN ($inList) AND (e.title LIKE ? OR e.content LIKE ?)",
            [$q, $like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'element', 'element_type' => $r['element_type'],
                          'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'], 'excerpt' => strip_tags($r['excerpt'])];
        }

        return ['query' => $q, 'results' => $results];
    }

    /**
     * Vérifie si l'utilisateur a accès à un projet.
     */
    public function hasProjectAccess(int $projectId, int $userId): bool
    {
        return !empty($this->db->exec(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$projectId, $userId]
        ));
    }
}
