<?php

/**
 * ActToolHandler — Gère les outils MCP liés aux actes.
 */
class ActToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Liste les actes d'un projet.
     */
    public function listActs(int $projectId): array
    {
        if (!$projectId || !$this->ownsProject($projectId)) {
            return $this->fail("Accès refusé ou projet introuvable.");
        }

        $rows = $this->db->exec(
            'SELECT id, title, content, order_index FROM acts WHERE project_id=? ORDER BY order_index ASC, id ASC',
            [$projectId]
        );
        if (!$rows) return $this->ok("Aucun acte dans ce projet.");

        $md = "# Actes du projet  \n\n";
        foreach ($rows as $a) {
            $title = $this->htmlToText($a['title'] ?? 'Sans titre');
            $md .= "## [{$title}](act:{$a['id']})\n\n";
            if (!empty($a['content'])) {
                $md .= $this->htmlToText($a['content']) . "\n\n";
            }
            $md .= "---\n\n";
        }
        return $this->ok(trim($md));
    }

    /**
     * Récupère un acte par ID.
     */
    public function getAct(int $id): array
    {
        if (!$id) return $this->fail("ID manquant.");

        $rows = $this->db->exec(
            'SELECT a.id, a.title, a.content, a.order_index, a.project_id, p.title as project_title '
            . 'FROM acts a JOIN projects p ON p.id=a.project_id WHERE a.id=?',
            [$id]
        );
        if (!$rows || empty($rows[0])) return $this->fail("Acte introuvable.");

        $a = $rows[0];
        if (!$this->ownsProject((int)$a['project_id'])) {
            return $this->fail("Accès refusé.");
        }

        $md = "# {$a['title']}\n\n";
        $md .= "**Projet :** {$a['project_title']}\n\n";
        $md .= "**Position :** {$a['order_index']}\n\n";
        if (!empty($a['content'])) {
            $md .= "## Contenu\n\n" . $this->htmlToText($a['content']) . "\n\n";
        }
        return $this->ok($md);
    }

    /**
     * Crée un nouvel acte.
     */
    public function createAct(array $args): array
    {
        $projectId = (int)($args['project_id'] ?? 0);
        $title = trim($args['title'] ?? '');

        if (!$projectId) return $this->fail("project_id obligatoire.");
        if (!$title) return $this->fail("Le titre est obligatoire.");
        if (!$this->ownsProject($projectId)) return $this->fail("Accès refusé.");

        $nextOrder = (int)$this->db->exec(
            'SELECT MAX(order_index) + 1 as next_order FROM acts WHERE project_id=?',
            [$projectId]
        );
        $nextOrder = $nextOrder[0]['next_order'] ?? 0;

        $content = trim($args['content'] ?? '');

        try {
            $this->db->exec(
                'INSERT INTO acts (project_id, title, content, order_index) VALUES (?, ?, ?, ?)',
                [$projectId, $title, $content, $nextOrder]
            );
            $id = (int)$this->db->lastInsertId('acts');
            return $this->ok("Acte créé : [{$title}](act:{$id})");
        } catch (\Exception $e) {
            return $this->fail("Échec de création : " . $e->getMessage());
        }
    }

    /**
     * Met à jour un acte.
     */
    public function updateAct(array $args): array
    {
        $id = (int)($args['id'] ?? 0);
        if (!$id) return $this->fail("ID manquant.");

        $updates = [];
        $params = [];
        if (isset($args['title'])) {
            $updates[] = 'title = ?';
            $params[] = trim($args['title']);
        }
        if (isset($args['content'])) {
            $updates[] = 'content = ?';
            $params[] = trim($args['content']);
        }
        if (isset($args['order_index'])) {
            $updates[] = 'order_index = ?';
            $params[] = (int)$args['order_index'];
        }
        if (empty($updates)) return $this->fail("Aucune donnée à mettre à jour.");

        $params[] = $id;
        try {
            $this->db->exec(
                'UPDATE acts SET ' . implode(', ', $updates) . ' WHERE id=?',
                $params
            );
            return $this->ok("Acte mis à jour.");
        } catch (\Exception $e) {
            return $this->fail("Échec de mise à jour : " . $e->getMessage());
        }
    }

    /**
     * Supprime un acte.
     */
    public function deleteAct(int $id): array
    {
        if (!$id) return $this->fail("ID manquant.");

        $rows = $this->db->exec('SELECT project_id FROM acts WHERE id=?', [$id]);
        if (!$rows || empty($rows[0])) return $this->fail("Acte introuvable.");

        $projectId = (int)$rows[0]['project_id'];
        if (!$this->ownsProject($projectId)) return $this->fail("Accès refusé.");

        try {
            $this->db->exec('DELETE FROM acts WHERE id=?', [$id]);
            return $this->ok("Acte supprimé.");
        } catch (\Exception $e) {
            return $this->fail("Échec de suppression : " . $e->getMessage());
        }
    }
}
