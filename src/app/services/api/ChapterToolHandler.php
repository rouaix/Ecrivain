<?php

/**
 * ChapterToolHandler — Gère les outils MCP liés aux chapitres.
 */
class ChapterToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Liste les chapitres d'un projet ou d'un acte.
     */
    public function listChapters(int $projectId, ?int $actId = null): array
    {
        if (!$projectId || !$this->ownsProject($projectId)) {
            return $this->fail("Accès refusé ou projet introuvable.");
        }

        $where = 'project_id=?';
        $params = [$projectId];
        if ($actId) {
            $where .= ' AND act_id=?';
            $params[] = $actId;
        }

        $rows = $this->db->exec(
            "SELECT id, title, content, order_index, act_id, parent_id, word_count "
            . "FROM chapters WHERE $where ORDER BY order_index ASC, id ASC",
            $params
        );
        if (!$rows) return $this->ok("Aucun chapitre dans ce projet." . ($actId ? " cet acte" : ""));

        $md = "# Chapitres" . ($actId ? " de l'acte" : " du projet") . "  \n\n";
        foreach ($rows as $c) {
            $title = $this->htmlToText($c['title'] ?? 'Sans titre');
            $indentation = $c['parent_id'] ? "  - " : "";
            $md .= "$indentation## [{$title}](chapter:{$c['id']})\n";
            $md .= "> Mots : {$c['word_count']} | Position : {$c['order_index']}\n\n";
            if (!empty($c['content'])) {
                $content = $this->htmlToText($c['content']);
                $md .= mb_substr($content, 0, 200) . (mb_strlen($content) > 200 ? '...' : '') . "\n\n";
            }
            $md .= "---\n\n";
        }
        return $this->ok(trim($md));
    }

    /**
     * Récupère un chapitre par ID.
     */
    public function getChapter(int $id): array
    {
        if (!$id) return $this->fail("ID manquant.");

        $rows = $this->db->exec(
            'SELECT c.id, c.title, c.content, c.order_index, c.act_id, c.parent_id, c.word_count, ';
            . 'c.created_at, c.updated_at, a.title as act_title, p.title as project_title, p.id as project_id '
            . 'FROM chapters c '
            . 'LEFT JOIN acts a ON a.id=c.act_id '
            . 'JOIN projects p ON p.id=c.project_id '
            . 'WHERE c.id=?',
            [$id]
        );
        if (!$rows || empty($rows[0])) return $this->fail("Chapitre introuvable.");

        $c = $rows[0];
        if (!$this->ownsProject((int)$c['project_id'])) {
            return $this->fail("Accès refusé.");
        }

        $md = "# {$c['title']}\n\n";
        $md .= "**Projet :** {$c['project_title']}\n\n";
        if (!empty($c['act_title'])) {
            $md .= "**Acte :** {$c['act_title']}\n\n";
        }
        $md .= "**Position :** {$c['order_index']}\n\n";
        $md .= "**Nombre de mots :** {$c['word_count']}\n\n";
        if (!empty($c['content'])) {
            $md .= "## Contenu\n\n" . $this->htmlToText($c['content']) . "\n\n";
        }
        return $this->ok($md);
    }

    /**
     * Crée un nouveau chapitre.
     */
    public function createChapter(array $args): array
    {
        $projectId = (int)($args['project_id'] ?? 0);
        $title = trim($args['title'] ?? '');

        if (!$projectId) return $this->fail("project_id obligatoire.");
        if (!$title) return $this->fail("Le titre est obligatoire.");
        if (!$this->ownsProject($projectId)) return $this->fail("Accès refusé.");

        $actId = isset($args['act_id']) ? (int)$args['act_id'] : null;
        $parentId = isset($args['parent_id']) ? (int)$args['parent_id'] : null;

        // Vérifier que l'acte appartient au projet
        if ($actId && !$this->db->exec('SELECT id FROM acts WHERE id=? AND project_id=?', [$actId, $projectId])) {
            return $this->fail("Acte introuvable dans ce projet.");
        }

        // Vérifier que le parent appartient au projet
        if ($parentId && !$this->db->exec('SELECT id FROM chapters WHERE id=? AND project_id=?', [$parentId, $projectId])) {
            return $this->fail("Chapitre parent introuvable dans ce projet.");
        }

        $nextOrder = (int)$this->db->exec(
            'SELECT MAX(order_index) + 1 as next_order FROM chapters WHERE ' .
            ($actId ? 'act_id=? AND project_id=?' : 'project_id=? AND act_id IS NULL'),
            $actId ? [$actId, $projectId] : [$projectId]
        );
        $nextOrder = $nextOrder[0]['next_order'] ?? 0;

        $content = trim($args['content'] ?? '');

        try {
            $this->db->exec(
                'INSERT INTO chapters (project_id, act_id, parent_id, title, content, order_index) '
                . 'VALUES (?, ?, ?, ?, ?, ?)',
                [$projectId, $actId, $parentId, $title, $content, $nextOrder]
            );
            $id = (int)$this->db->lastInsertId('chapters');
            return $this->ok("Chapitre créé : [{$title}](chapter:{$id})");
        } catch (\Exception $e) {
            return $this->fail("Échec de création : " . $e->getMessage());
        }
    }

    /**
     * Met à jour un chapitre.
     */
    public function updateChapter(array $args): array
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
        if (isset($args['act_id'])) {
            $updates[] = 'act_id = ?';
            $params[] = $args['act_id'] !== null ? (int)$args['act_id'] : null;
        }
        if (isset($args['parent_id'])) {
            $updates[] = 'parent_id = ?';
            $params[] = $args['parent_id'] !== null ? (int)$args['parent_id'] : null;
        }
        if (isset($args['order_index'])) {
            $updates[] = 'order_index = ?';
            $params[] = (int)$args['order_index'];
        }
        if (empty($updates)) return $this->fail("Aucune donnée à mettre à jour.");

        $params[] = $id;
        try {
            $this->db->exec(
                'UPDATE chapters SET ' . implode(', ', $updates) . ' WHERE id=?',
                $params
            );
            return $this->ok("Chapitre mis à jour.");
        } catch (\Exception $e) {
            return $this->fail("Échec de mise à jour : " . $e->getMessage());
        }
    }

    /**
     * Supprime un chapitre.
     */
    public function deleteChapter(int $id): array
    {
        if (!$id) return $this->fail("ID manquant.");

        $rows = $this->db->exec('SELECT project_id FROM chapters WHERE id=?', [$id]);
        if (!$rows || empty($rows[0])) return $this->fail("Chapitre introuvable.");

        $projectId = (int)$rows[0]['project_id'];
        if (!$this->ownsProject($projectId)) return $this->fail("Accès refusé.");

        try {
            $this->db->exec('DELETE FROM chapters WHERE id=?', [$id]);
            return $this->ok("Chapitre supprimé.");
        } catch (\Exception $e) {
            return $this->fail("Échec de suppression : " . $e->getMessage());
        }
    }
}
