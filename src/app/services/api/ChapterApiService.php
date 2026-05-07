<?php

/**
 * ChapterApiService — Service API pour les opérations CRUD sur les chapitres.
 */
class ChapterApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Récupère un chapitre par ID.
     */
    public function getChapter(int $id, int $userId): ?array
    {
        $chapter = $this->fetchService->fetchChapter($id);
        if (!$chapter) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$chapter['project_id'], $userId)) {
            return null;
        }
        return $chapter;
    }

    /**
     * Crée un nouveau chapitre.
     */
    public function createChapter(int $userId, array $body): array
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }

        $title = trim($body['title'] ?? '');
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }

        $aid = ($body['act_id'] ?? 0) ?: null;
        $parentId = ($body['parent_id'] ?? 0) ?: null;
        $content = $body['content'] ?? '';
        $resume = trim($body['resume'] ?? '');
        $wc = $this->fetchService->countWords($content);

        if ($aid && !$this->actBelongsToProject($aid, $pid, $userId)) {
            throw new \InvalidArgumentException('Cet acte n\'appartient pas à ce projet.');
        }

        if ($parentId && !$this->chapterBelongsToProject($parentId, $pid, $userId)) {
            throw new \InvalidArgumentException('Ce chapitre parent n\'appartient pas à ce projet.');
        }

        $res = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM chapters WHERE project_id = ? AND parent_id ' . ($parentId ? '= ?' : 'IS NULL'),
            $parentId ? [$pid, $parentId] : [$pid]
        );
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO chapters (project_id, act_id, parent_id, title, content, resume, word_count, order_index, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$pid, $aid, $parentId, $title, $content, $resume ?: null, $wc, $order]
        );
        $id = (int)$this->db->lastInsertId('chapters');
        return $this->fetchService->fetchChapter($id);
    }

    /**
     * Met à jour un chapitre.
     */
    public function updateChapter(int $id, int $userId, array $body): ?array
    {
        $oldChapter = $this->db->exec('SELECT project_id, content, word_count FROM chapters WHERE id = ?', [$id]);
        if (!$oldChapter) {
            return null;
        }
        $oldChapter = $oldChapter[0];
        if (!$this->hasProjectAccess((int)$oldChapter['project_id'], $userId)) {
            return null;
        }

        $fields = [];
        $params = [];
        if (isset($body['title'])) {
            $t = trim($body['title']);
            if ($t === '') throw new \InvalidArgumentException('Le titre ne peut pas être vide.');
            $fields[] = 'title = ?';
            $params[] = $t;
        }
        if (array_key_exists('content', $body)) {
            // Save version before overwriting
            $this->saveChapterVersion($id, $oldChapter['content'], (int)$oldChapter['word_count']);
            $wc = $this->fetchService->countWords($body['content']);
            $fields[] = 'content = ?';
            $params[] = $body['content'];
            $fields[] = 'word_count = ?';
            $params[] = $wc;
        }
        if (array_key_exists('resume', $body)) {
            $fields[] = 'resume = ?';
            $params[] = trim($body['resume']) ?: null;
        }
        if (isset($body['act_id'])) {
            $aid = ($body['act_id'] ?? 0) ?: null;
            if ($aid && !$this->actBelongsToProject($aid, (int)$oldChapter['project_id'], $userId)) {
                throw new \InvalidArgumentException('Cet acte n\'appartient pas à ce projet.');
            }
            $fields[] = 'act_id = ?';
            $params[] = $aid;
        }
        if (isset($body['parent_id'])) {
            $parentId = ($body['parent_id'] ?? 0) ?: null;
            if ($parentId && !$this->chapterBelongsToProject($parentId, (int)$oldChapter['project_id'], $userId)) {
                throw new \InvalidArgumentException('Ce chapitre parent n\'appartient pas à ce projet.');
            }
            $fields[] = 'parent_id = ?';
            $params[] = $parentId;
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('Aucun champ à mettre à jour.');
        }

        $params[] = $id;
        $this->db->exec('UPDATE chapters SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        return $this->fetchService->fetchChapter($id);
    }

    /**
     * Supprime un chapitre.
     */
    public function deleteChapter(int $id, int $userId): bool
    {
        $chapter = $this->db->exec('SELECT project_id FROM chapters WHERE id = ?', [$id]);
        if (!$chapter) {
            return false;
        }
        if (!$this->hasProjectAccess((int)$chapter[0]['project_id'], $userId)) {
            return false;
        }
        $this->db->exec('DELETE FROM chapters WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Met à jour l'ordre des chapitres.
     */
    public function reorderChapters(int $userId, array $body): bool
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            return false;
        }

        if (!isset($body['order']) || !is_array($body['order'])) {
            throw new \InvalidArgumentException('Le paramètre order est requis et doit être un tableau.');
        }

        foreach ($body['order'] as $index => $chapId) {
            $cid = (int)$chapId;
            $chapter = $this->db->exec('SELECT project_id FROM chapters WHERE id = ?', [$cid]);
            if (!$chapter || (int)$chapter[0]['project_id'] !== $pid) {
                return false;
            }
            $this->db->exec('UPDATE chapters SET order_index = ? WHERE id = ?', [$index, $cid]);
        }
        return true;
    }

    /**
     * Récupère le synopsis d'un projet (crée s'il n'existe pas).
     */
    public function fetchOrCreateSynopsis(int $projectId, int $userId): array
    {
        if (!$this->hasProjectAccess($projectId, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }
        $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id = ?', [$projectId]);
        if (!$rows) {
            $this->db->exec('INSERT INTO synopsis (project_id) VALUES (?)', [$projectId]);
            $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id = ?', [$projectId]);
        }
        return $rows[0];
    }

    /**
     * Met à jour le synopsis d'un projet.
     */
    public function updateSynopsis(int $projectId, int $userId, array $body): array
    {
        if (!$this->hasProjectAccess($projectId, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }

        $allowed = [
            'genre', 'subgenre', 'audience', 'tone', 'themes', 'comps',
            'status', 'structure_method',
            'logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
            'development', 'midpoint', 'crisis', 'climax', 'resolution',
        ];

        $rows = $this->db->exec('SELECT id FROM synopsis WHERE project_id = ?', [$projectId]);
        if (!$rows) {
            $this->db->exec('INSERT INTO synopsis (project_id) VALUES (?)', [$projectId]);
        }

        $fields = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = ?";
                $params[] = $body[$f];
            }
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('Aucun champ valide fourni.');
        }

        $params[] = $projectId;
        $this->db->exec('UPDATE synopsis SET ' . implode(', ', $fields) . ' WHERE project_id = ?', $params);
        return $this->fetchOrCreateSynopsis($projectId, $userId);
    }

    /**
     * Sauvegarde une version d'un chapitre.
     */
    public function saveChapterVersion(int $chapterId, ?string $content, int $wordCount): void
    {
        if (empty($content)) return;
        $this->db->exec(
            'INSERT INTO chapter_versions (chapter_id, content, word_count) VALUES (?, ?, ?)',
            [$chapterId, $content, $wordCount]
        );
        // Keep max 10 versions per chapter
        $this->db->exec(
            'DELETE FROM chapter_versions WHERE chapter_id = ?
             AND id NOT IN (SELECT id FROM (
                 SELECT id FROM chapter_versions WHERE chapter_id = ?
                 ORDER BY created_at DESC LIMIT 10
             ) AS keep)',
            [$chapterId, $chapterId]
        );
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

    /**
     * Vérifie si un acte appartient à un projet.
     */
    private function actBelongsToProject(int $actId, int $projectId, int $userId): bool
    {
        return !empty($this->db->exec(
            'SELECT a.id FROM acts a JOIN projects p ON p.id = a.project_id WHERE a.id = ? AND p.id = ? AND p.user_id = ?',
            [$actId, $projectId, $userId]
        ));
    }

    /**
     * Vérifie si un chapitre appartient à un projet.
     */
    private function chapterBelongsToProject(int $chapterId, int $projectId, int $userId): bool
    {
        return !empty($this->db->exec(
            'SELECT c.id FROM chapters c JOIN projects p ON p.id = c.project_id WHERE c.id = ? AND p.id = ? AND p.user_id = ?',
            [$chapterId, $projectId, $userId]
        ));
    }
}
