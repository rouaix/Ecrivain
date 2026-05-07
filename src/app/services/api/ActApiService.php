<?php

/**
 * ActApiService — Service API pour les opérations CRUD sur les actes.
 */
class ActApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Liste les actes d'un projet avec pagination.
     */
    public function listActs(int $projectId, int $offset, int $limit): array
    {
        $total = (int)$this->db->exec(
            'SELECT COUNT(*) AS n FROM acts WHERE project_id = ?',
            [$projectId]
        )[0]['n'];
        $rows = $this->db->exec(
            'SELECT a.id, a.title, a.description, a.resume, a.order_index,
                    COUNT(c.id) AS chapters_count
             FROM acts a
             LEFT JOIN chapters c ON c.act_id = a.id
             WHERE a.project_id = ?
             GROUP BY a.id
             ORDER BY a.order_index ASC, a.id ASC
             LIMIT ? OFFSET ?',
            [$projectId, $limit, $offset]
        );
        return [$rows, $total];
    }

    /**
     * Crée un nouvel acte.
     */
    public function createAct(int $projectId, int $userId, array $body): array
    {
        if (!$this->isOwner($projectId, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }
        $title = trim($body['title'] ?? '');
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }

        $res = $this->db->exec('SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM acts WHERE project_id = ?', [$projectId]);
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO acts (project_id, title, description, order_index) VALUES (?, ?, ?, ?)',
            [$projectId, $title, trim($body['description'] ?? '') ?: null, $order]
        );
        $id = (int)$this->db->lastInsertId('acts');
        return $this->fetchService->fetchAct($id);
    }

    /**
     * Récupère un acte par ID.
     */
    public function getAct(int $id, int $userId): ?array
    {
        $act = $this->fetchService->fetchAct($id);
        if (!$act) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$act['project_id'], $userId)) {
            return null;
        }
        return $act;
    }

    /**
     * Met à jour un acte.
     */
    public function updateAct(int $id, int $userId, array $body): ?array
    {
        $act = $this->db->exec('SELECT project_id FROM acts WHERE id = ?', [$id]);
        if (!$act) {
            return null;
        }
        if (!$this->isOwner((int)$act[0]['project_id'], $userId)) {
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
        if (array_key_exists('description', $body)) {
            $fields[] = 'description = ?';
            $params[] = trim($body['description']) ?: null;
        }
        if (array_key_exists('resume', $body)) {
            $fields[] = 'resume = ?';
            $params[] = trim($body['resume']) ?: null;
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('Aucun champ à mettre à jour.');
        }

        $params[] = $id;
        $this->db->exec('UPDATE acts SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        return $this->fetchService->fetchAct($id);
    }

    /**
     * Supprime un acte.
     */
    public function deleteAct(int $id, int $userId): bool
    {
        $act = $this->db->exec('SELECT project_id FROM acts WHERE id = ?', [$id]);
        if (!$act) {
            return false;
        }
        if (!$this->isOwner((int)$act[0]['project_id'], $userId)) {
            return false;
        }
        $this->db->exec('DELETE FROM acts WHERE id = ?', [$id]);
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

    /**
     * Vérifie si l'utilisateur est propriétaire d'un projet.
     */
    public function isOwner(int $projectId, int $userId): bool
    {
        return $this->hasProjectAccess($projectId, $userId);
    }
}
