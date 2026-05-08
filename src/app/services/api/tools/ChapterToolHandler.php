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

    public function listChapters(int $pid, ?int $actId): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $params = [$pid];
        $where  = 'c.project_id=?';
        if ($actId) { $where .= ' AND c.act_id=?'; $params[] = $actId; }
        $rows = $this->db->exec(
            "SELECT c.id, c.title, c.parent_id, a.title as act_title, a.id as act_id
             FROM chapters c LEFT JOIN acts a ON a.id=c.act_id
             WHERE $where ORDER BY a.order_index ASC, a.id ASC, c.order_index ASC, c.id ASC",
            $params
        );
        if (!$rows) return $this->ok("Aucun chapitre.");

        $md   = "# Chapitres du projet $pid\n\n";
        $currentAct = null;
        foreach ($rows as $r) {
            if ($r['parent_id']) continue;
            $act = $r['act_title'] ?? null;
            if ($act !== $currentAct) {
                $md .= "\n## " . ($act ?? 'Sans acte') . "\n";
                $currentAct = $act;
            }
            $md .= "- **{$r['title']}** (ID: {$r['id']})\n";
            foreach ($rows as $child) {
                if ($child['parent_id'] == $r['id']) {
                    $md .= "  - {$child['title']} (ID: {$child['id']})\n";
                }
            }
        }
        return $this->ok(trim($md));
    }

    public function getChapter(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id, c.title, c.content, c.updated_at, p.title as pt
             FROM chapters c JOIN projects p ON p.id=c.project_id
             WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $c    = $rows[0];
        $text = $this->htmlToText($c['content'] ?? '');
        $wc   = str_word_count($text);
        $md   = "# {$c['title']}\n_Projet : {$c['pt']} · {$wc} mots · Modifié : {$c['updated_at']}_\n\n";
        if ($text) $md .= $text . "\n\n";

        $subs = $this->db->exec(
            'SELECT id, title, content FROM chapters WHERE parent_id=? ORDER BY order_index ASC, id ASC',
            [$id]
        );
        foreach ($subs as $sub) {
            $subText = $this->htmlToText($sub['content'] ?? '');
            $md .= "## {$sub['title']} (ID: {$sub['id']})\n\n";
            if ($subText) $md .= $subText . "\n\n";
        }
        return $this->ok(trim($md));
    }

    public function createChapter(array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $content  = $a['content'] ?? '';
        $actId    = ($a['act_id'] ?? 0) ?: null;
        $parentId = ($a['parent_id'] ?? 0) ?: null;
        $pos      = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 as p FROM chapters WHERE project_id=?', [$pid]
        )[0]['p'];
        $this->db->exec(
            'INSERT INTO chapters (project_id, act_id, parent_id, title, content, order_index, created_at, updated_at)
             VALUES (?,?,?,?,?,?,NOW(),NOW())',
            [$pid, $actId, $parentId, $title, $content, $pos]
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        $wc = str_word_count(strip_tags($content));
        return $this->ok("Chapitre **{$title}** créé (ID: $id, $wc mots).");
    }

    public function updateChapter(array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT c.id FROM chapters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $fields = []; $vals = [];
        if (isset($a['title']))   { $fields[] = 'title=?';      $vals[] = trim($a['title']); }
        if (isset($a['content'])) { $fields[] = 'content=?'; $vals[] = $a['content']; }
        if (!$fields) return $this->fail('Rien à modifier.');
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE chapters SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Chapitre $id mis à jour.");
    }

    public function deleteChapter(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id FROM chapters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $this->db->exec('DELETE FROM chapters WHERE id=?', [$id]);
        return $this->ok("Chapitre $id supprimé.");
    }
}
