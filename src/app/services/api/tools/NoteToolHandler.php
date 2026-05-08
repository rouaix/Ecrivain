<?php

/**
 * NoteToolHandler — Gère les outils MCP liés aux notes.
 */
class NoteToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Liste toutes les notes d'un projet.
     */
    public function listNotes(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec(
            'SELECT id, title, updated_at FROM notes WHERE project_id=? ORDER BY updated_at DESC',
            [$pid]
        );
        
        if (!$rows) return $this->ok("Aucune note.");
        
        $md = "# Notes du projet $pid\n\n";
        foreach ($rows as $r) {
            $md .= "- **{$r['title']}** (ID: {$r['id']}) — {$r['updated_at']}\n";
        }
        return $this->ok($md);
    }

    /**
     * Récupère une note par ID.
     */
    public function getNote(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT n.id, n.title, n.content, n.updated_at, p.title as pt
             FROM notes n JOIN projects p ON p.id=n.project_id
             WHERE n.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Note $id introuvable.");
        
        $n    = $rows[0];
        $text = $this->htmlToText($n['content'] ?? '');
        $md   = "# {$n['title']}\n_Projet : {$n['pt']} · Modifié : {$n['updated_at']}_\n\n";
        if ($text) $md .= $text . "\n";
        return $this->ok(trim($md));
    }

    /**
     * Crée une nouvelle note.
     */
    public function createNote(array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        
        $this->db->exec(
            'INSERT INTO notes (project_id, title, content, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())',
            [$pid, $title, $a['content'] ?? '']
        );
        
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Note **{$title}** créée (ID: $id).");
    }

    /**
     * Met à jour une note.
     */
    public function updateNote(array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT n.id FROM notes n JOIN projects p ON p.id=n.project_id WHERE n.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Note $id introuvable.");
        
        $fields = []; $vals = [];
        if (isset($a['title']))   { $fields[] = 'title=?';   $vals[] = trim($a['title']); }
        if (isset($a['content'])) { $fields[] = 'content=?'; $vals[] = $a['content']; }
        if (!$fields) return $this->fail('Rien à modifier.');
        
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE notes SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Note $id mise à jour.");
    }

    /**
     * Supprime une note.
     */
    public function deleteNote(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT n.id FROM notes n JOIN projects p ON p.id=n.project_id WHERE n.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Note $id introuvable.");
        
        $this->db->exec('DELETE FROM notes WHERE id=?', [$id]);
        return $this->ok("Note $id supprimée.");
    }
}
