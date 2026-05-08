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

    public function listActs(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT a.id, a.title, a.description, COUNT(c.id) as nb
             FROM acts a LEFT JOIN chapters c ON c.act_id=a.id
             WHERE a.project_id=? GROUP BY a.id ORDER BY a.order_index ASC, a.id ASC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucun acte.");
        $md = "# Actes du projet $pid\n\n";
        foreach ($rows as $r) {
            $md .= "## {$r['title']} (ID: {$r['id']}) — {$r['nb']} chapitre(s)\n";
            if ($r['description']) $md .= $this->htmlToText($r['description']) . "\n";
            $md .= "\n";
        }
        return $this->ok(trim($md));
    }

    public function getAct(int $id): array
    {
        $acts = $this->db->exec(
            'SELECT a.id, a.title, a.description, p.user_id
             FROM acts a JOIN projects p ON p.id=a.project_id
             WHERE a.id=?',
            [$id]
        );
        if (!$acts || $acts[0]['user_id'] != $this->userId) return $this->fail("Acte $id introuvable.");
        $act = $acts[0];

        $rows = $this->db->exec(
            'SELECT id, title, content, parent_id FROM chapters WHERE act_id=? ORDER BY order_index ASC, id ASC',
            [$id]
        ) ?: [];

        $md = "# {$act['title']}\n";
        if ($act['description']) $md .= $this->htmlToText($act['description']) . "\n";
        $md .= "\n";

        foreach ($rows as $c) {
            if ($c['parent_id']) continue;
            $md .= "## {$c['title']} (ID: {$c['id']})\n\n";
            $text = $this->htmlToText($c['content'] ?? '');
            if ($text) $md .= $text . "\n\n";
            foreach ($rows as $sub) {
                if ($sub['parent_id'] != $c['id']) continue;
                $md .= "### {$sub['title']} (ID: {$sub['id']})\n\n";
                $subText = $this->htmlToText($sub['content'] ?? '');
                if ($subText) $md .= $subText . "\n\n";
            }
        }
        return $this->ok(trim($md));
    }

    public function createAct(array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $pos = $this->db->exec('SELECT COALESCE(MAX(order_index),0)+1 as p FROM acts WHERE project_id=?', [$pid])[0]['p'];
        $this->db->exec(
            'INSERT INTO acts (project_id, title, description, order_index, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())',
            [$pid, $title, trim($a['description'] ?? ''), $pos]
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Acte **{$title}** créé (ID: $id).");
    }

    public function updateAct(array $a): array
    {
        $id = (int) ($a['id'] ?? 0);
        $act = $this->db->exec('SELECT project_id FROM acts WHERE id=?', [$id]);
        if (!$act || $act[0]['project_id'] && !$this->ownsProject((int)$act[0]['project_id'])) {
            return $this->fail("Acte $id introuvable.");
        }
        $fields = []; $vals = [];
        if (isset($a['title']))       { $fields[] = 'title=?';       $vals[] = trim($a['title']); }
        if (isset($a['description'])) { $fields[] = 'description=?'; $vals[] = trim($a['description']); }
        if (isset($a['order_index'])) { $fields[] = 'order_index=?';  $vals[] = (int)$a['order_index']; }
        if (!$fields) return $this->fail('Rien à modifier.');
        $vals[] = $id;
        $this->db->exec('UPDATE acts SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Acte $id mis à jour.");
    }

    public function deleteAct(int $id): array
    {
        $act = $this->db->exec('SELECT project_id FROM acts WHERE id=?', [$id]);
        if (!$act || !$this->ownsProject((int)$act[0]['project_id'])) {
            return $this->fail("Acte $id introuvable.");
        }
        $this->db->exec('DELETE FROM acts WHERE id=?', [$id]);
        return $this->ok("Acte $id supprimé.");
    }
}
