<?php

/**
 * ProjectToolHandler — Gère les outils MCP liés aux projets.
 */
class ProjectToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Liste tous les projets de l'utilisateur.
     */
    public function listProjects(): array
    {
        $rows = $this->db->exec(
            'SELECT id, title, description, updated_at FROM projects WHERE user_id=? ORDER BY updated_at DESC',
            [$this->userId]
        );
        if (!$rows) return $this->ok("Aucun projet.");
        
        $md = "# Vos projets\n\n";
        foreach ($rows as $p) {
            $updated = date('Y-m-d H:i', strtotime($p['updated_at'] ?? 'now'));
            $md .= "## [{$p['title']}](project:{$p['id']})\n";
            $md .= "> Mis à jour : $updated\n\n";
            if (!empty($p['description'])) {
                $md .= "{$p['description']}\n\n";
            }
            $md .= "---\n\n";
        }
        return $this->ok(trim($md));
    }

    /**
     * Récupère un projet par ID.
     */
    public function getProject(int $id): array
    {
        if (!$id) return $this->fail("ID manquant.");
        if (!$this->ownsProject($id)) return $this->fail("Accès refusé.");

        $rows = $this->db->exec('SELECT id, title, description, created_at, updated_at FROM projects WHERE id=?', [$id]);
        if (!$rows || empty($rows[0])) return $this->fail("Projet introuvable.");

        $p = $rows[0];
        $created = date('Y-m-d H:i', strtotime($p['created_at'] ?? 'now'));
        $updated = date('Y-m-d H:i', strtotime($p['updated_at'] ?? 'now'));

        $md = "# {$p['title']}\n\n";
        $md .= "**Créé :** $created  \n";
        $md .= "**Mis à jour :** $updated  \n\n";
        if (!empty($p['description'])) {
            $md .= "## Description\n\n{$p['description']}\n\n";
        }
        return $this->ok($md);
    }

    /**
     * Crée un nouveau projet.
     */
    public function createProject(array $args): array
    {
        $title = trim($args['title'] ?? '');
        if (!$title) return $this->fail("Le titre est obligatoire.");

        $desc = trim($args['description'] ?? '');
        try {
            $this->db->exec(
                'INSERT INTO projects (user_id, title, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
                [$this->userId, $title, $desc]
            );
            $id = (int)$this->db->lastInsertId('projects');
            return $this->ok("Projet créé : [{$title}](project:{$id})");
        } catch (\Exception $e) {
            return $this->fail("Échec de création : " . $e->getMessage());
        }
    }

    /**
     * Met à jour un projet.
     */
    public function updateProject(array $args): array
    {
        $id = (int)($args['id'] ?? 0);
        if (!$id) return $this->fail("ID manquant.");
        if (!$this->ownsProject($id)) return $this->fail("Accès refusé.");

        $updates = [];
        $params = [];
        if (isset($args['title'])) {
            $updates[] = 'title = ?';
            $params[] = trim($args['title']);
        }
        if (isset($args['description'])) {
            $updates[] = 'description = ?';
            $params[] = trim($args['description']);
        }
        if (empty($updates)) return $this->fail("Aucune donnée à mettre à jour.");

        $params[] = $id;
        $params[] = $this->userId;
        try {
            $this->db->exec(
                'UPDATE projects SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id=? AND user_id=?',
                $params
            );
            return $this->ok("Projet mis à jour.");
        } catch (\Exception $e) {
            return $this->fail("Échec de mise à jour : " . $e->getMessage());
        }
    }

    /**
     * Supprime un projet.
     */
    public function deleteProject(int $id): array
    {
        if (!$id) return $this->fail("ID manquant.");
        if (!$this->ownsProject($id)) return $this->fail("Accès refusé.");

        try {
            $this->db->exec('DELETE FROM projects WHERE id=? AND user_id=?', [$id, $this->userId]);
            return $this->ok("Projet supprimé.");
        } catch (\Exception $e) {
            return $this->fail("Échec de suppression : " . $e->getMessage());
        }
    }
}
