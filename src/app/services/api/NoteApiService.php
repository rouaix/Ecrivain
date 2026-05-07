<?php

/**
 * NoteApiService — Service API pour les opérations CRUD sur les notes.
 */
class NoteApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Liste les notes d'un projet avec pagination.
     */
    public function listNotes(int $projectId, int $offset, int $limit): array
    {
        $total = (int)$this->db->exec(
            'SELECT COUNT(*) AS n FROM notes WHERE project_id = ?',
            [$projectId]
        )[0]['n'];
        $rows = $this->db->exec(
            'SELECT id, title, comment, order_index, updated_at
             FROM notes WHERE project_id = ? ORDER BY order_index ASC, id ASC
             LIMIT ? OFFSET ?',
            [$projectId, $limit, $offset]
        );
        return [$rows, $total];
    }

    /**
     * Récupère une note par ID.
     */
    public function getNote(int $id, int $userId): ?array
    {
        $note = $this->fetchService->fetchNote($id);
        if (!$note) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$note['project_id'], $userId)) {
            return null;
        }
        return $note;
    }

    /**
     * Crée une nouvelle note.
     */
    public function createNote(int $userId, array $body): array
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }

        $title = trim($body['title'] ?? '');
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }

        $res = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM notes WHERE project_id = ?',
            [$pid]
        );
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO notes (project_id, title, content, comment, order_index) VALUES (?, ?, ?, ?, ?)',
            [$pid, $title, $body['content'] ?? null, trim($body['comment'] ?? '') ?: null, $order]
        );
        $id = (int)$this->db->lastInsertId('notes');
        return $this->fetchService->fetchNote($id);
    }

    /**
     * Met à jour une note.
     */
    public function updateNote(int $id, int $userId, array $body): ?array
    {
        $note = $this->db->exec('SELECT project_id FROM notes WHERE id = ?', [$id]);
        if (!$note) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$note[0]['project_id'], $userId)) {
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
        $this->db->exec('UPDATE notes SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        return $this->fetchService->fetchNote($id);
    }

    /**
     * Supprime une note.
     */
    public function deleteNote(int $id, int $userId): bool
    {
        $note = $this->db->exec('SELECT project_id FROM notes WHERE id = ?', [$id]);
        if (!$note) {
            return false;
        }
        if (!$this->hasProjectAccess((int)$note[0]['project_id'], $userId)) {
            return false;
        }
        $this->db->exec('DELETE FROM notes WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Met à jour l'ordre des notes.
     */
    public function reorderNotes(int $userId, array $body): bool
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            return false;
        }

        if (!isset($body['order']) || !is_array($body['order'])) {
            throw new \InvalidArgumentException('Le paramètre order est requis et doit être un tableau.');
        }

        foreach ($body['order'] as $index => $noteId) {
            $nid = (int)$noteId;
            $note = $this->db->exec('SELECT project_id FROM notes WHERE id = ?', [$nid]);
            if (!$note || (int)$note[0]['project_id'] !== $pid) {
                return false;
            }
            $this->db->exec('UPDATE notes SET order_index = ? WHERE id = ?', [$index, $nid]);
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
