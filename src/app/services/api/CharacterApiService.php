<?php

/**
 * CharacterApiService — Service API pour les opérations CRUD sur les personnages.
 */
class CharacterApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Liste les personnages d'un projet avec pagination.
     */
    public function listCharacters(int $projectId, int $offset, int $limit): array
    {
        $total = (int)$this->db->exec(
            'SELECT COUNT(*) AS n FROM characters WHERE project_id = ?',
            [$projectId]
        )[0]['n'];
        $rows = $this->db->exec(
            'SELECT id, name, description, comment, created_at, updated_at
             FROM characters WHERE project_id = ? ORDER BY name ASC
             LIMIT ? OFFSET ?',
            [$projectId, $limit, $offset]
        );
        return [$rows, $total];
    }

    /**
     * Récupère un personnage par ID.
     */
    public function getCharacter(int $id, int $userId): ?array
    {
        $character = $this->fetchService->fetchCharacter($id);
        if (!$character) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$character['project_id'], $userId)) {
            return null;
        }
        return $character;
    }

    /**
     * Crée un nouveau personnage.
     */
    public function createCharacter(int $userId, array $body): array
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }

        $name = trim($body['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }

        $this->db->exec(
            'INSERT INTO characters (project_id, name, description, comment) VALUES (?, ?, ?, ?)',
            [$pid, $name, trim($body['description'] ?? '') ?: null, trim($body['comment'] ?? '') ?: null]
        );
        $id = (int)$this->db->lastInsertId('characters');
        return $this->fetchService->fetchCharacter($id);
    }

    /**
     * Met à jour un personnage.
     */
    public function updateCharacter(int $id, int $userId, array $body): ?array
    {
        $character = $this->db->exec('SELECT project_id FROM characters WHERE id = ?', [$id]);
        if (!$character) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$character[0]['project_id'], $userId)) {
            return null;
        }

        $fields = [];
        $params = [];
        if (isset($body['name'])) {
            $n = trim($body['name']);
            if ($n === '') throw new \InvalidArgumentException('Le nom ne peut pas être vide.');
            $fields[] = 'name = ?';
            $params[] = $n;
        }
        if (array_key_exists('description', $body)) {
            $fields[] = 'description = ?';
            $params[] = trim($body['description']) ?: null;
        }
        if (array_key_exists('comment', $body)) {
            $fields[] = 'comment = ?';
            $params[] = trim($body['comment']) ?: null;
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('Aucun champ à mettre à jour.');
        }

        $params[] = $id;
        $this->db->exec('UPDATE characters SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        return $this->fetchService->fetchCharacter($id);
    }

    /**
     * Supprime un personnage.
     */
    public function deleteCharacter(int $id, int $userId): bool
    {
        $character = $this->db->exec('SELECT project_id FROM characters WHERE id = ?', [$id]);
        if (!$character) {
            return false;
        }
        if (!$this->hasProjectAccess((int)$character[0]['project_id'], $userId)) {
            return false;
        }
        $this->db->exec('DELETE FROM characters WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Met à jour l'ordre des personnages.
     */
    public function reorderCharacters(int $userId, array $body): bool
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            return false;
        }

        if (!isset($body['order']) || !is_array($body['order'])) {
            throw new \InvalidArgumentException('Le paramètre order est requis et doit être un tableau.');
        }

        foreach ($body['order'] as $index => $charId) {
            $cid = (int)$charId;
            $character = $this->db->exec('SELECT project_id FROM characters WHERE id = ?', [$cid]);
            if (!$character || (int)$character[0]['project_id'] !== $pid) {
                return false;
            }
            $this->db->exec('UPDATE characters SET order_index = ? WHERE id = ?', [$index, $cid]);
        }
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
