<?php

/**
 * ProjectApiService — Service API pour les opérations CRUD sur les projets.
 * Centralise la logique métier des projets pour l'API REST.
 */
class ProjectApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Liste les projets d'un utilisateur avec pagination.
     */
    public function listProjects(int $userId, int $offset, int $limit): array
    {
        $total = (int)$this->db->exec(
            'SELECT COUNT(*) AS n FROM projects WHERE user_id = ?',
            [$userId]
        )[0]['n'];
        $rows = $this->db->exec(
            'SELECT id, title, description, created_at, updated_at
             FROM projects WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?',
            [$userId, $limit, $offset]
        );
        return [$rows, $total];
    }

    /**
     * Crée un nouveau projet.
     */
    public function createProject(int $userId, array $body): array
    {
        $title = trim($body['title'] ?? '');
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }
        $description = trim($body['description'] ?? '');

        // Use default template
        $tpl = $this->db->exec('SELECT id FROM templates WHERE is_default = 1 LIMIT 1');
        $templateId = $tpl ? (int)$tpl[0]['id'] : null;

        $this->db->exec(
            'INSERT INTO projects (user_id, title, description, template_id) VALUES (?, ?, ?, ?)',
            [$userId, $title, $description ?: null, $templateId]
        );
        $id = (int)$this->db->lastInsertId('projects');
        return $this->fetchService->fetchProject($id);
    }

    /**
     * Récupère un projet par ID.
     */
    public function getProject(int $id, int $userId): ?array
    {
        $project = $this->fetchService->fetchProject($id);
        if (!$project) {
            return null;
        }
        // Vérification d'accès
        $hasAccess = !empty($this->db->exec(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$id, $userId]
        ));
        return $hasAccess ? $project : null;
    }

    /**
     * Met à jour un projet.
     */
    public function updateProject(int $id, int $userId, array $body): ?array
    {
        // Vérification propriétaire
        $isOwner = !empty($this->db->exec(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$id, $userId]
        ));
        if (!$isOwner) {
            return null;
        }

        $fields = [];
        $params = [];
        if (isset($body['title'])) {
            $title = trim($body['title']);
            if ($title === '') throw new \InvalidArgumentException('Le titre ne peut pas être vide.');
            $fields[] = 'title = ?';
            $params[] = $title;
        }
        if (array_key_exists('description', $body)) {
            $fields[] = 'description = ?';
            $params[] = trim($body['description']) ?: null;
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('Aucun champ à mettre à jour.');
        }

        $params[] = $id;
        $this->db->exec('UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        return $this->fetchService->fetchProject($id);
    }

    /**
     * Supprime un projet.
     */
    public function deleteProject(int $id, int $userId): bool
    {
        // Vérification propriétaire
        $isOwner = !empty($this->db->exec(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$id, $userId]
        ));
        if (!$isOwner) {
            return false;
        }
        $this->db->exec('DELETE FROM projects WHERE id = ?', [$id]);
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
