<?php

/**
 * ElementApiService — Service API pour les opérations CRUD sur les éléments.
 */
class ElementApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Liste les types d'éléments pour un projet.
     */
    public function listElementTypes(int $projectId): array
    {
        $rows = $this->db->exec(
            'SELECT te.id, te.element_type, te.config_json, te.display_order
             FROM template_elements te
             JOIN projects p ON p.template_id = te.template_id
             WHERE p.id = ? AND te.is_enabled = 1
             ORDER BY te.display_order ASC',
            [$projectId]
        );
        $types = [];
        foreach ($rows as $r) {
            $cfg = $r['config_json'] ? json_decode($r['config_json'], true) : [];
            $types[] = [
                'id' => (int)$r['id'],
                'element_type' => $r['element_type'],
                'label' => $cfg['label_plural'] ?? $cfg['label'] ?? $r['element_type'],
                'label_singular' => $cfg['label_singular'] ?? $cfg['label'] ?? $r['element_type'],
                'display_order' => (int)$r['display_order'],
            ];
        }
        return ['types' => $types];
    }

    /**
     * Liste les éléments d'un projet avec pagination.
     */
    public function listElements(int $projectId, int $offset, int $limit): array
    {
        $total = (int)$this->db->exec(
            'SELECT COUNT(*) AS n FROM elements WHERE project_id = ?',
            [$projectId]
        )[0]['n'];
        $rows = $this->db->exec(
            'SELECT e.id, e.title, e.parent_id, e.order_index, e.template_element_id,
                    te.element_type, te.config_json
             FROM elements e
             LEFT JOIN template_elements te ON te.id = e.template_element_id
             WHERE e.project_id = ?
             ORDER BY te.display_order ASC, e.order_index ASC, e.id ASC
             LIMIT ? OFFSET ?',
            [$projectId, $limit, $offset]
        );
        foreach ($rows as &$r) {
            $cfg = json_decode($r['config_json'] ?? '{}', true);
            $r['type_label'] = $cfg['label_singular'] ?? $cfg['label'] ?? $r['element_type'];
            unset($r['config_json']);
        }
        return [$rows, $total];
    }

    /**
     * Récupère un élément par ID.
     */
    public function getElement(int $id, int $userId): ?array
    {
        $element = $this->fetchService->fetchElement($id);
        if (!$element) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$element['project_id'], $userId)) {
            return null;
        }
        return $element;
    }

    /**
     * Crée un nouvel élément.
     */
    public function createElement(int $userId, array $body): array
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            throw new \RuntimeException('Accès refusé.');
        }

        $title = trim($body['title'] ?? '');
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }

        $templateElementId = (int)($body['template_element_id'] ?? 0);
        $parentId = ($body['parent_id'] ?? 0) ?: null;

        if ($templateElementId) {
            $te = $this->db->exec('SELECT id FROM template_elements WHERE id = ?', [$templateElementId]);
            if (!$te) {
                throw new \InvalidArgumentException('Type d\'élément invalide.');
            }
        }

        if ($parentId) {
            $parent = $this->db->exec('SELECT project_id FROM elements WHERE id = ?', [$parentId]);
            if (!$parent || (int)$parent[0]['project_id'] !== $pid) {
                throw new \InvalidArgumentException('Élément parent invalide.');
            }
        }

        $res = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM elements WHERE project_id = ? AND template_element_id = ?',
            [$pid, $templateElementId]
        );
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO elements (project_id, title, template_element_id, parent_id, order_index, content, resume, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$pid, $title, $templateElementId, $parentId, $order,
             $body['content'] ?? null, trim($body['resume'] ?? '') ?: null, trim($body['comment'] ?? '') ?: null]
        );
        $id = (int)$this->db->lastInsertId('elements');
        return $this->fetchService->fetchElement($id);
    }

    /**
     * Met à jour un élément.
     */
    public function updateElement(int $id, int $userId, array $body): ?array
    {
        $element = $this->db->exec('SELECT project_id FROM elements WHERE id = ?', [$id]);
        if (!$element) {
            return null;
        }
        if (!$this->hasProjectAccess((int)$element[0]['project_id'], $userId)) {
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
        if (array_key_exists('resume', $body)) {
            $fields[] = 'resume = ?';
            $params[] = trim($body['resume']) ?: null;
        }
        if (array_key_exists('comment', $body)) {
            $fields[] = 'comment = ?';
            $params[] = trim($body['comment']) ?: null;
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('Aucun champ à mettre à jour.');
        }

        $params[] = $id;
        $this->db->exec('UPDATE elements SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        return $this->fetchService->fetchElement($id);
    }

    /**
     * Supprime un élément.
     */
    public function deleteElement(int $id, int $userId): bool
    {
        $element = $this->db->exec('SELECT project_id FROM elements WHERE id = ?', [$id]);
        if (!$element) {
            return false;
        }
        if (!$this->hasProjectAccess((int)$element[0]['project_id'], $userId)) {
            return false;
        }
        $this->db->exec('DELETE FROM elements WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Met à jour l'ordre des éléments.
     */
    public function reorderElements(int $userId, array $body): bool
    {
        $pid = (int)($body['project_id'] ?? 0);
        if (!$this->hasProjectAccess($pid, $userId)) {
            return false;
        }

        if (!isset($body['order']) || !is_array($body['order'])) {
            throw new \InvalidArgumentException('Le paramètre order est requis et doit être un tableau.');
        }

        foreach ($body['order'] as $index => $elId) {
            $eid = (int)$elId;
            $element = $this->db->exec('SELECT project_id, template_element_id FROM elements WHERE id = ?', [$eid]);
            if (!$element || (int)$element[0]['project_id'] !== $pid) {
                return false;
            }
            $this->db->exec('UPDATE elements SET order_index = ? WHERE id = ?', [$index, $eid]);
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
