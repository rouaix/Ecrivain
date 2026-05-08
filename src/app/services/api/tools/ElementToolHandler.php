<?php

/**
 * ElementToolHandler — Gère les outils MCP liés aux éléments.
 */
class ElementToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Liste les types d'éléments disponibles pour un projet.
     */
    public function listElementTypes(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec(
            'SELECT te.id, te.element_type, te.config_json, te.display_order
             FROM template_elements te
             JOIN projects p ON p.template_id = te.template_id
             WHERE p.id = ? AND te.is_enabled = 1
             ORDER BY te.display_order ASC',
            [$pid]
        );
        
        if (!$rows) return $this->ok("Aucun type d'élément configuré pour ce projet.");
        
        $md = "# Types d'éléments disponibles (projet $pid)\n\n";
        $md .= "Utiliser `template_element_id` dans `create_element`.\n\n";
        foreach ($rows as $r) {
            $cfg   = $r['config_json'] ? json_decode($r['config_json'], true) : [];
            $label = $cfg['label_plural'] ?? $cfg['label'] ?? $r['element_type'];
            $md   .= "- **{$label}** — template_element_id: **{$r['id']}**\n";
        }
        return $this->ok($md);
    }

    /**
     * Liste tous les éléments d'un projet groupés par type.
     */
    public function listElements(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec(
            'SELECT e.id, e.title, e.parent_id, e.template_element_id,
                    te.element_type, te.config_json, te.display_order
             FROM elements e
             LEFT JOIN template_elements te ON te.id = e.template_element_id
             WHERE e.project_id=?
             ORDER BY te.display_order ASC, e.order_index ASC, e.id ASC',
            [$pid]
        );
        
        if (!$rows) return $this->ok("Aucun élément. Vérifiez que le projet a des types d'éléments configurés dans son template.");
        
        $md = "# Éléments du projet $pid\n\n";
        $byType = [];
        
        foreach ($rows as $r) {
            if ($r['parent_id']) continue;
            $cfg   = $r['config_json'] ? json_decode($r['config_json'], true) : [];
            $label = $cfg['label'] ?? $r['element_type'] ?? 'Élément';
            $teid  = $r['template_element_id'];
            $key   = "{$label} (template_element_id: {$teid})";
            $byType[$key][] = $r;
        }
        
        foreach ($byType as $typeLabel => $items) {
            $md .= "## {$typeLabel}\n\n";
            foreach ($items as $r) {
                $md .= "- **{$r['title']}** (ID: {$r['id']})\n";
                foreach ($rows as $sub) {
                    if ($sub['parent_id'] == $r['id']) {
                        $md .= "  - {$sub['title']} (ID: {$sub['id']})\n";
                    }
                }
            }
            $md .= "\n";
        }
        return $this->ok(trim($md));
    }

    /**
     * Récupère un élément par ID.
     */
    public function getElement(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT e.id, e.title, e.content, e.updated_at, p.title as pt
             FROM elements e JOIN projects p ON p.id=e.project_id
             WHERE e.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Élément $id introuvable.");
        
        $e    = $rows[0];
        $text = $this->htmlToText($e['content'] ?? '');
        $md   = "# {$e['title']}\n_Projet : {$e['pt']} · Modifié : {$e['updated_at']}_\n\n";
        if ($text) $md .= $text . "\n\n";
        
        $subs = $this->db->exec(
            'SELECT id, title, content FROM elements WHERE parent_id=? ORDER BY order_index ASC, id ASC',
            [$id]
        );
        foreach ($subs as $sub) {
            $subText = $this->htmlToText($sub['content'] ?? '');
            $md .= "## {$sub['title']} (ID: {$sub['id']})\n\n";
            if ($subText) $md .= $subText . "\n\n";
        }
        return $this->ok(trim($md));
    }

    /**
     * Crée un nouvel élément.
     */
    public function createElement(array $a): array
    {
        $pid  = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        
        $teid = (int) ($a['template_element_id'] ?? 0);
        if (!$teid) return $this->fail('template_element_id est obligatoire. Utilisez list_elements pour obtenir les IDs disponibles.');
        
        $check = $this->db->exec('SELECT id FROM template_elements WHERE id=?', [$teid]);
        if (!$check) return $this->fail("template_element_id $teid invalide.");
        
        $parentId = ($a['parent_id'] ?? 0) ?: null;
        $pos = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 as p FROM elements WHERE project_id=? AND template_element_id=? AND parent_id IS NULL',
            [$pid, $teid]
        )[0]['p'];
        
        $this->db->exec(
            'INSERT INTO elements (project_id, template_element_id, parent_id, title, content, order_index, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())',
            [$pid, $teid, $parentId, $title, $a['content'] ?? '', $pos]
        );
        
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Élément **{$title}** créé (ID: $id).");
    }

    /**
     * Met à jour un élément.
     */
    public function updateElement(array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT e.id FROM elements e JOIN projects p ON p.id=e.project_id WHERE e.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Élément $id introuvable.");
        
        $fields = []; $vals = [];
        if (isset($a['title']))   { $fields[] = 'title=?';   $vals[] = trim($a['title']); }
        if (isset($a['content'])) { $fields[] = 'content=?'; $vals[] = $a['content']; }
        if (!$fields) return $this->fail('Rien à modifier.');
        
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE elements SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Élément $id mis à jour.");
    }

    /**
     * Supprime un élément.
     */
    public function deleteElement(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT e.id FROM elements e JOIN projects p ON p.id=e.project_id WHERE e.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        
        if (!$rows) return $this->fail("Élément $id introuvable.");
        
        $this->db->exec('DELETE FROM elements WHERE id=?', [$id]);
        return $this->ok("Élément $id supprimé.");
    }
}
