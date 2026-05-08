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

    public function listProjects(): array
    {
        $rows = $this->db->exec(
            'SELECT id, title, description, updated_at FROM projects WHERE user_id=? ORDER BY updated_at DESC',
            [$this->userId]
        );
        if (!$rows) return $this->ok("Aucun projet.");
        $md = "# Vos projets\n\n";
        foreach ($rows as $r) {
            $md .= "## {$r['title']} (ID: {$r['id']})\n";
            if ($r['description']) $md .= $this->htmlToText($r['description']) . "\n";
            $md .= "_Modifié : {$r['updated_at']}_\n\n";
        }
        return $this->ok(trim($md));
    }

    public function getProject(int $pid): array
    {
        $rows = $this->db->exec(
            'SELECT id, title, description, created_at, updated_at FROM projects WHERE id=? AND user_id=?',
            [$pid, $this->userId]
        );
        if (!$rows) return $this->fail("Projet $pid introuvable.");
        $p  = $rows[0];
        $md = "# {$p['title']} (ID: {$p['id']})\n\n";
        if ($p['description']) $md .= $this->htmlToText($p['description']) . "\n\n";
        $md .= "_Créé : {$p['created_at']} · Modifié : {$p['updated_at']}_";
        return $this->ok($md);
    }

    public function createProject(array $a): array
    {
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $desc = trim($a['description'] ?? '');
        $this->db->exec(
            'INSERT INTO projects (user_id, title, description, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())',
            [$this->userId, $title, $desc]
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Projet **{$title}** créé (ID: $id).");
    }

    public function updateProject(array $a): array
    {
        $id = (int) ($a['id'] ?? 0);
        if (!$this->ownsProject($id)) return $this->fail("Projet $id introuvable.");
        $fields = []; $vals = [];
        if (isset($a['title']))       { $fields[] = 'title=?';       $vals[] = trim($a['title']); }
        if (isset($a['description'])) { $fields[] = 'description=?'; $vals[] = trim($a['description']); }
        if (!$fields) return $this->fail('Rien à modifier.');
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE projects SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Projet $id mis à jour.");
    }

    public function deleteProject(int $id): array
    {
        if (!$this->ownsProject($id)) return $this->fail("Projet $id introuvable.");
        $this->db->exec('DELETE FROM projects WHERE id=? AND user_id=?', [$id, $this->userId]);
        return $this->ok("Projet $id supprimé.");
    }
}
