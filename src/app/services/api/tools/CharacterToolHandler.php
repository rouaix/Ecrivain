<?php

/**
 * CharacterToolHandler — Gère les outils MCP liés aux personnages.
 */
class CharacterToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Liste tous les personnages d'un projet.
     */
    public function listCharacters(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec(
            'SELECT id, name, description FROM characters WHERE project_id=? ORDER BY name ASC',
            [$pid]
        );
        
        if (!$rows) return $this->ok("Aucun personnage.");
        
        $md = "# Personnages du projet $pid\n\n";
        foreach ($rows as $r) {
            $md .= "## {$r['name']} (ID: {$r['id']})\n";
            if ($r['description']) $md .= $this->htmlToText($r['description']) . "\n";
            $md .= "\n";
        }
        return $this->ok(trim($md));
    }

    /**
     * Récupère un personnage par ID.
     */
    public function getCharacter(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id, c.name, c.description, c.comment, c.updated_at, p.title as pt
             FROM characters c JOIN projects p ON p.id=c.project_id
             WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Personnage $id introuvable.");
        
        $c    = $rows[0];
        $desc = $this->htmlToText($c['description'] ?? '');
        $note = $this->htmlToText($c['comment'] ?? '');
        $md   = "# {$c['name']}\n_Projet : {$c['pt']} · Modifié : {$c['updated_at']}_\n\n";
        if ($desc) $md .= "## Description\n\n" . $desc . "\n\n";
        if ($note) $md .= "## Notes\n\n" . $note . "\n";
        return $this->ok(trim($md));
    }

    /**
     * Crée un nouveau personnage.
     */
    public function createCharacter(array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $name = trim($a['name'] ?? '');
        if (!$name) return $this->fail('Nom requis.');
        
        $this->db->exec(
            'INSERT INTO characters (project_id, name, description, comment, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())',
            [$pid, $name, $a['description'] ?? '', $a['comment'] ?? '']
        );
        
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Personnage **{$name}** créé (ID: $id).");
    }

    /**
     * Met à jour un personnage.
     */
    public function updateCharacter(array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT c.id FROM characters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Personnage $id introuvable.");
        
        $fields = []; $vals = [];
        if (isset($a['name']))        { $fields[] = 'name=?';        $vals[] = trim($a['name']); }
        if (isset($a['description'])) { $fields[] = 'description=?'; $vals[] = $a['description']; }
        if (isset($a['comment']))     { $fields[] = 'comment=?';     $vals[] = $a['comment']; }
        if (!$fields) return $this->fail('Rien à modifier.');
        
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE characters SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Personnage $id mis à jour.");
    }

    /**
     * Supprime un personnage.
     */
    public function deleteCharacter(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id FROM characters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Personnage $id introuvable.");
        
        $this->db->exec('DELETE FROM characters WHERE id=?', [$id]);
        return $this->ok("Personnage $id supprimé.");
    }
}
