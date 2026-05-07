<?php

/**
 * SectionApiService — Service API pour les opérations CRUD sur les sections.
 */
class SectionApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Liste les sections d'un projet avec pagination.
     */
    public function listSections(int $projectId, int $offset, int $limit): array
    {
        $total = (int)$this->db->exec(
            'SELECT COUNT(*) AS n FROM sections WHERE project_id = ?',
            [$projectId]
        )[0]['n'];
        $rows = $this->db->exec(
            'SELECT id, type, title, comment, image_path, order_index, updated_at
             FROM sections WHERE project_id = ?
             ORDER BY order_index ASC, id ASC LIMIT ? OFFSET ?',
            [$projectId, $limit, $offset]
        );
        return [$rows, $total];
    }

    /**
     * Récupère une section par ID.
     */
    public function getSection(int $id, int $userId): ?array
    {
        $section = $this->fetchService->fetchSection($id);
        if (!$section) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$section['project_id'], $userId)) {
            return null;
        }
        return $section;
    }

    /**
     * Crée une nouvelle section.
     */
    public function createSection(int $userId, array $body): array
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }

        $type = trim($body['type'] ?? '');
        $validTypes = ['cover','preface','introduction','prologue','postface','appendices','back_cover'];
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException('Type de section invalide.');
        }

        $res = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM sections WHERE project_id = ?',
            [$pid]
        );
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO sections (project_id, type, title, content, comment, order_index) VALUES (?, ?, ?, ?, ?, ?)',
            [$pid, $type, trim($body['title'] ?? '') ?: null, $body['content'] ?? null, trim($body['comment'] ?? '') ?: null, $order]
        );
        $id = (int)$this->db->lastInsertId('sections');
        return $this->fetchService->fetchSection($id);
    }

    /**
     * Met à jour une section.
     */
    public function updateSection(int $id, int $userId, array $body): ?array
    {
        $section = $this->db->exec('SELECT project_id FROM sections WHERE id = ?', [$id]);
        if (!$section) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$section[0]['project_id'], $userId)) {
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
            $fields[] = 'content = ?';
            $params[] = $body['content'];
        }
        if (array_key_exists('comment', $body)) {
            $fields[] = 'comment = ?';
            $params[] = trim($body['comment']) ?: null;
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('Aucun champ à mettre à jour.');
        }

        $params[] = $id;
        $this->db->exec('UPDATE sections SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        return $this->fetchService->fetchSection($id);
    }

    /**
     * Met à jour l'ordre des sections.
     */
    public function reorderSections(int $userId, array $body): bool
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            return false;
        }

        if (!isset($body['order']) || !is_array($body['order'])) {
            throw new \InvalidArgumentException('Le paramètre order est requis et doit être un tableau.');
        }

        foreach ($body['order'] as $index => $secId) {
            $sid = (int)$secId;
            $section = $this->db->exec('SELECT project_id FROM sections WHERE id = ?', [$sid]);
            if (!$section || (int)$section[0]['project_id'] !== $pid) {
                return false;
            }
            $this->db->exec('UPDATE sections SET order_index = ? WHERE id = ?', [$index, $sid]);
        }
        return true;
    }

    /**
     * Supprime une section.
     */
    public function deleteSection(int $id, int $userId): bool
    {
        $section = $this->db->exec('SELECT project_id FROM sections WHERE id = ?', [$id]);
        if (!$section) {
            return false;
        }
        if (!$this->hasProjectAccess((int)$section[0]['project_id'], $userId)) {
            return false;
        }
        $this->db->exec('DELETE FROM sections WHERE id = ?', [$id]);
        return true;
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
