<?php

/**
 * ExportToolHandler — Gère les outils MCP liés à l'export.
 */
class ExportToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Exporte un projet complet en Markdown.
     */
    public function exportMarkdown(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");

        $p = $this->db->exec(
            'SELECT title, description FROM projects WHERE id=?', [$pid]
        )[0];
        $md = "# {$p['title']}\n\n";
        if ($p['description']) $md .= $this->htmlToText($p['description']) . "\n\n";

        $acts = $this->db->exec(
            'SELECT id, title FROM acts WHERE project_id=? ORDER BY order_index ASC, id ASC', [$pid]
        );
        foreach ($acts as $act) {
            $md .= "## {$act['title']}\n\n";
            $chapters = $this->db->exec(
                'SELECT id, title, content, parent_id FROM chapters WHERE act_id=? ORDER BY order_index ASC, id ASC',
                [$act['id']]
            );
            foreach ($chapters as $c) {
                if ($c['parent_id']) continue;
                $md .= "### {$c['title']}\n\n" . $this->htmlToText($c['content'] ?? '') . "\n\n";
                foreach ($chapters as $sub) {
                    if ($sub['parent_id'] == $c['id']) {
                        $md .= "#### {$sub['title']}\n\n" . $this->htmlToText($sub['content'] ?? '') . "\n\n";
                    }
                }
            }
        }

        $orphans = $this->db->exec(
            'SELECT id, title, content, parent_id FROM chapters WHERE project_id=? AND (act_id IS NULL OR act_id=0)
             ORDER BY order_index ASC, id ASC',
            [$pid]
        );
        foreach ($orphans as $c) {
            if ($c['parent_id']) continue;
            $md .= "### {$c['title']}\n\n" . $this->htmlToText($c['content'] ?? '') . "\n\n";
            foreach ($orphans as $sub) {
                if ($sub['parent_id'] == $c['id']) {
                    $md .= "#### {$sub['title']}\n\n" . $this->htmlToText($sub['content'] ?? '') . "\n\n";
                }
            }
        }

        return $this->ok(trim($md));
    }
}
