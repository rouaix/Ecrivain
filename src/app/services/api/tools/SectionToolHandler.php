<?php

/**
 * SectionToolHandler — Gère les outils MCP liés aux sections.
 */
class SectionToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Liste toutes les sections d'un projet.
     */
    public function listSections(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec(
            'SELECT id, title, updated_at FROM sections WHERE project_id=? ORDER BY order_index ASC, id ASC',
            [$pid]
        );
        
        if (!$rows) return $this->ok("Aucune section.");
        
        $md = "# Sections du projet $pid\n\n";
        foreach ($rows as $r) {
            $md .= "- **{$r['title']}** (ID: {$r['id']}) — {$r['updated_at']}\n";
        }
        return $this->ok($md);
    }

    /**
     * Récupère une section par ID.
     */
    public function getSection(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT s.id, s.title, s.content, s.updated_at, p.title as pt
             FROM sections s JOIN projects p ON p.id=s.project_id
             WHERE s.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Section $id introuvable.");
        
        $s    = $rows[0];
        $text = $this->htmlToText($s['content'] ?? '');
        $md   = "# {$s['title']}\n_Projet : {$s['pt']} · Modifié : {$s['updated_at']}_\n\n";
        if ($text) $md .= $text . "\n";
        return $this->ok(trim($md));
    }

    /**
     * Crée une nouvelle section.
     */
    public function createSection(array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        
        $pos = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 as p FROM sections WHERE project_id=?', [$pid]
        )[0]['p'];
        
        $this->db->exec(
            'INSERT INTO sections (project_id, title, content, order_index, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())',
            [$pid, $title, $a['content'] ?? '', $pos]
        );
        
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Section **{$title}** créée (ID: $id).");
    }

    /**
     * Met à jour une section.
     */
    public function updateSection(array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT s.id FROM sections s JOIN projects p ON p.id=s.project_id WHERE s.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Section $id introuvable.");
        
        $fields = []; $vals = [];
        if (isset($a['title']))   { $fields[] = 'title=?';   $vals[] = trim($a['title']); }
        if (isset($a['content'])) { $fields[] = 'content=?'; $vals[] = $a['content']; }
        if (!$fields) return $this->fail('Rien à modifier.');
        
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE sections SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Section $id mise à jour.");
    }

    /**
     * Supprime une section.
     */
    public function deleteSection(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT s.id FROM sections s JOIN projects p ON p.id=s.project_id WHERE s.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Section $id introuvable.");
        
        $this->db->exec('DELETE FROM sections WHERE id=?', [$id]);
        return $this->ok("Section $id supprimée.");
    }
}
