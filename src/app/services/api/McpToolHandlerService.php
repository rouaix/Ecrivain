<?php

/**
 * McpToolHandlerService — Gère les handlers d'outils MCP.
 * Centralise toute la logique des outils pour éviter un contrôleur trop volumineux.
 */
class McpToolHandlerService
{
    private \DB\SQL $db;
    private Base $f3;
    private int $userId;

    public function __construct(\DB\SQL $db, Base $f3, int $userId)
    {
        $this->db = $db;
        $this->f3 = $f3;
        $this->userId = $userId;
    }

    /**
     * Exécute un outil MCP.
     */
    public function callTool(string $name, array $arguments): array
    {
        try {
            return match ($name) {
                // Projets
                'list_projects'    => $this->toolListProjects(),
                'get_project'      => $this->toolGetProject((int) ($arguments['id'] ?? 0)),
                'create_project'   => $this->toolCreateProject($arguments),
                'update_project'   => $this->toolUpdateProject($arguments),
                'delete_project'   => $this->toolDeleteProject((int) ($arguments['id'] ?? 0)),

                // Actes
                'list_acts'        => $this->toolListActs((int) ($arguments['project_id'] ?? 0)),
                'get_act'          => $this->toolGetAct((int) ($arguments['id'] ?? 0)),
                'create_act'       => $this->toolCreateAct($arguments),
                'update_act'       => $this->toolUpdateAct($arguments),
                'delete_act'       => $this->toolDeleteAct((int) ($arguments['id'] ?? 0)),

                // Chapitres
                'list_chapters'    => $this->toolListChapters((int) ($arguments['project_id'] ?? 0), isset($arguments['act_id']) ? (int) $arguments['act_id'] : null),
                'get_chapter'      => $this->toolGetChapter((int) ($arguments['id'] ?? 0)),
                'create_chapter'   => $this->toolCreateChapter($arguments),
                'update_chapter'   => $this->toolUpdateChapter($arguments),
                'delete_chapter'   => $this->toolDeleteChapter((int) ($arguments['id'] ?? 0)),

                // Sections
                'list_sections'    => $this->toolListSections((int) ($arguments['project_id'] ?? 0)),
                'get_section'      => $this->toolGetSection((int) ($arguments['id'] ?? 0)),
                'create_section'   => $this->toolCreateSection($arguments),
                'update_section'   => $this->toolUpdateSection($arguments),
                'delete_section'   => $this->toolDeleteSection((int) ($arguments['id'] ?? 0)),

                // Notes
                'list_notes'       => $this->toolListNotes((int) ($arguments['project_id'] ?? 0)),
                'get_note'         => $this->toolGetNote((int) ($arguments['id'] ?? 0)),
                'create_note'      => $this->toolCreateNote($arguments),
                'update_note'      => $this->toolUpdateNote($arguments),
                'delete_note'      => $this->toolDeleteNote((int) ($arguments['id'] ?? 0)),

                // Personnages
                'list_characters'  => $this->toolListCharacters((int) ($arguments['project_id'] ?? 0)),
                'get_character'    => $this->toolGetCharacter((int) ($arguments['id'] ?? 0)),
                'create_character' => $this->toolCreateCharacter($arguments),
                'update_character' => $this->toolUpdateCharacter($arguments),
                'delete_character' => $this->toolDeleteCharacter((int) ($arguments['id'] ?? 0)),

                // Éléments
                'list_element_types' => $this->toolListElementTypes((int) ($arguments['project_id'] ?? 0)),
                'list_elements'    => $this->toolListElements((int) ($arguments['project_id'] ?? 0)),
                'get_element'      => $this->toolGetElement((int) ($arguments['id'] ?? 0)),
                'create_element'   => $this->toolCreateElement($arguments),
                'update_element'   => $this->toolUpdateElement($arguments),
                'delete_element'   => $this->toolDeleteElement((int) ($arguments['id'] ?? 0)),

                // Images
                'list_images'      => $this->toolListImages((int) ($arguments['project_id'] ?? 0)),
                'delete_image'     => $this->toolDeleteImage((int) ($arguments['project_id'] ?? 0), (int) ($arguments['image_id'] ?? 0)),

                // Synopsis
                'get_synopsis'     => $this->toolGetSynopsis((int) ($arguments['project_id'] ?? 0)),
                'update_synopsis'  => $this->toolUpdateSynopsis((int) ($arguments['project_id'] ?? 0), $arguments),

                // Export
                'export_markdown'  => $this->toolExportMarkdown((int) ($arguments['project_id'] ?? 0)),

                // Recherche
                'search'           => $this->toolSearch($arguments['query'] ?? ''),

                default            => $this->fail('Outil inconnu : ' . $name),
            };
        } catch (\Throwable $e) {
            error_log('McpToolHandlerService::callTool error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function ok(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false];
    }

    private function fail(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => '**Erreur :** ' . $message]], 'isError' => true];
    }

    private function ownsProject(int $pid): bool
    {
        return !empty($this->db->exec(
            'SELECT id FROM projects WHERE id=? AND user_id=?', [$pid, $this->userId]
        ));
    }

    private function ownsProjectForItem(int $pid, int $id, string $table, string $joinCol = 'project_id'): bool
    {
        $rows = $this->db->exec(
            "SELECT p.user_id FROM $table t JOIN projects p ON p.id=t.$joinCol WHERE t.id=?",
            [$id]
        );
        return !empty($rows) && $rows[0]['user_id'] == $this->userId;
    }

    private function htmlToText(string $html): string
    {
        return ContentTransformer::htmlToText($html);
    }

    // =========================================================================
    // PROJETS
    // =========================================================================

    private function toolListProjects(): array
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

    private function toolGetProject(int $pid): array
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

    private function toolCreateProject(array $a): array
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

    private function toolUpdateProject(array $a): array
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

    private function toolDeleteProject(int $id): array
    {
        if (!$this->ownsProject($id)) return $this->fail("Projet $id introuvable.");
        $this->db->exec('DELETE FROM projects WHERE id=? AND user_id=?', [$id, $this->userId]);
        return $this->ok("Projet $id supprimé.");
    }

    // =========================================================================
    // ACTES
    // =========================================================================

    private function toolListActs(int $pid): array
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

    private function toolGetAct(int $id): array
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

    private function toolCreateAct(array $a): array
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

    private function toolUpdateAct(array $a): array
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

    private function toolDeleteAct(int $id): array
    {
        $act = $this->db->exec('SELECT project_id FROM acts WHERE id=?', [$id]);
        if (!$act || !$this->ownsProject((int)$act[0]['project_id'])) {
            return $this->fail("Acte $id introuvable.");
        }
        $this->db->exec('DELETE FROM acts WHERE id=?', [$id]);
        return $this->ok("Acte $id supprimé.");
    }

    // =========================================================================
    // CHAPITRES
    // =========================================================================

    private function toolListChapters(int $pid, ?int $actId): array
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

    private function toolGetChapter(int $id): array
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

    private function toolCreateChapter(array $a): array
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

    private function toolUpdateChapter(array $a): array
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

    private function toolDeleteChapter(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id FROM chapters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $this->db->exec('DELETE FROM chapters WHERE id=?', [$id]);
        return $this->ok("Chapitre $id supprimé.");
    }

    // =========================================================================
    // SECTIONS
    // =========================================================================

    private function toolListSections(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, title, updated_at FROM sections WHERE project_id=? ORDER BY order_index ASC, id ASC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucune section.");
        $md = "# Sections du projet $pid\n\n";
        foreach ($rows as $r) $md .= "- **{$r['title']}** (ID: {$r['id']}) — {$r['updated_at']}\n";
        return $this->ok($md);
    }

    private function toolGetSection(int $id): array
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

    private function toolCreateSection(array $a): array
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

    private function toolUpdateSection(array $a): array
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

    private function toolDeleteSection(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT s.id FROM sections s JOIN projects p ON p.id=s.project_id WHERE s.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Section $id introuvable.");
        $this->db->exec('DELETE FROM sections WHERE id=?', [$id]);
        return $this->ok("Section $id supprimée.");
    }

    // =========================================================================
    // NOTES
    // =========================================================================

    private function toolListNotes(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, title, updated_at FROM notes WHERE project_id=? ORDER BY updated_at DESC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucune note.");
        $md = "# Notes du projet $pid\n\n";
        foreach ($rows as $r) $md .= "- **{$r['title']}** (ID: {$r['id']}) — {$r['updated_at']}\n";
        return $this->ok($md);
    }

    private function toolGetNote(int $id): array
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

    private function toolCreateNote(array $a): array
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

    private function toolUpdateNote(array $a): array
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

    private function toolDeleteNote(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT n.id FROM notes n JOIN projects p ON p.id=n.project_id WHERE n.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Note $id introuvable.");
        $this->db->exec('DELETE FROM notes WHERE id=?', [$id]);
        return $this->ok("Note $id supprimée.");
    }

    // =========================================================================
    // PERSONNAGES
    // =========================================================================

    private function toolListCharacters(int $pid): array
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

    private function toolGetCharacter(int $id): array
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

    private function toolCreateCharacter(array $a): array
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

    private function toolUpdateCharacter(array $a): array
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

    private function toolDeleteCharacter(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id FROM characters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Personnage $id introuvable.");
        $this->db->exec('DELETE FROM characters WHERE id=?', [$id]);
        return $this->ok("Personnage $id supprimé.");
    }

    // =========================================================================
    // ÉLÉMENTS
    // =========================================================================

    private function toolListElementTypes(int $pid): array
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

    private function toolListElements(int $pid): array
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

    private function toolGetElement(int $id): array
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

    private function toolCreateElement(array $a): array
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

    private function toolUpdateElement(array $a): array
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

    private function toolDeleteElement(int $id): array
    {
        $rows = $this->db->exec(
            'SELECT e.id FROM elements e JOIN projects p ON p.id=e.project_id WHERE e.id=? AND p.user_id=?',
            [$id, $this->userId]
        );
        if (!$rows) return $this->fail("Élément $id introuvable.");
        $this->db->exec('DELETE FROM elements WHERE id=?', [$id]);
        return $this->ok("Élément $id supprimé.");
    }

    // =========================================================================
    // IMAGES
    // =========================================================================

    private function toolListImages(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, filename, filesize, uploaded_at FROM project_files WHERE project_id=? ORDER BY uploaded_at DESC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucune image dans ce projet.");
        $md = "# Images du projet $pid\n\n";
        foreach ($rows as $r) {
            $kb = round($r['filesize'] / 1024, 1);
            $md .= "- **{$r['filename']}** (ID: {$r['id']}) — {$kb} Ko · {$r['uploaded_at']}\n";
        }
        return $this->ok($md);
    }

    private function toolDeleteImage(int $pid, int $imageId): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, filename, filepath FROM project_files WHERE id=? AND project_id=?',
            [$imageId, $pid]
        );
        if (!$rows) return $this->fail("Image $imageId introuvable.");
        $filepath = $this->f3->get('BASEPATH') . $rows[0]['filepath'];
        if (file_exists($filepath)) unlink($filepath);
        $this->db->exec('DELETE FROM project_files WHERE id=?', [$imageId]);
        return $this->ok("Image {$rows[0]['filename']} (ID: $imageId) supprimée.");
    }

    // =========================================================================
    // SYNOPSIS
    // =========================================================================

    private function formatSynopsis(array $s): string
    {
        $md = "# Synopsis du projet (ID: {$s['project_id']})\n\n";

        $meta = [];
        if (!empty($s['genre']))            $meta[] = "**Genre** : {$s['genre']}";
        if (!empty($s['subgenre']))         $meta[] = "**Sous-genre** : {$s['subgenre']}";
        if (!empty($s['audience']))         $meta[] = "**Public** : {$s['audience']}";
        if (!empty($s['tone']))             $meta[] = "**Ton** : {$s['tone']}";
        if (!empty($s['themes']))           $meta[] = "**Thèmes** : {$s['themes']}";
        if (!empty($s['comps']))            $meta[] = "**Comparables** : {$s['comps']}";
        if (!empty($s['status']))           $meta[] = "**Statut** : {$s['status']}";
        if (!empty($s['structure_method'])) $meta[] = "**Méthode** : {$s['structure_method']}";
        if ($meta) $md .= implode(' | ', $meta) . "\n\n";

        if (!empty($s['logline']))    $md .= "## Logline\n{$s['logline']}\n\n";
        if (!empty($s['pitch']))      $md .= "## Pitch\n" . $this->htmlToText($s['pitch']) . "\n\n";

        $beats = [
            'situation'   => 'Situation initiale',
            'trigger_evt' => 'Élément déclencheur',
            'plot_point1' => 'Point tournant 1',
            'development' => 'Développement',
            'midpoint'    => 'Midpoint',
            'crisis'      => 'Crise',
            'climax'      => 'Climax',
            'resolution'  => 'Résolution',
        ];
        $hasBeats = false;
        foreach ($beats as $k => $_) { if (!empty($s[$k])) { $hasBeats = true; break; } }
        if ($hasBeats) {
            $md .= "## Structure narrative\n\n";
            foreach ($beats as $k => $label) {
                if (!empty($s[$k])) $md .= "**{$label}** : " . $this->htmlToText($s[$k]) . "\n\n";
            }
        }

        return trim($md);
    }

    private function toolGetSynopsis(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id=?', [$pid]);
        if (!$rows) {
            $this->db->exec('INSERT INTO synopsis (project_id) VALUES (?)', [$pid]);
            $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id=?', [$pid]);
        }
        $s = $rows[0];
        return $this->ok($this->formatSynopsis($s));
    }

    private function toolUpdateSynopsis(int $pid, array $a): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");

        $allowed = [
            'genre', 'subgenre', 'audience', 'tone', 'themes', 'comps',
            'status', 'structure_method',
            'logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
            'development', 'midpoint', 'crisis', 'climax', 'resolution',
        ];

        $rows = $this->db->exec('SELECT id FROM synopsis WHERE project_id=?', [$pid]);
        if (!$rows) {
            $this->db->exec('INSERT INTO synopsis (project_id) VALUES (?)', [$pid]);
        }

        $fields = []; $vals = [];
        foreach ($allowed as $f) {
            if (isset($a[$f])) { $fields[] = "$f=?"; $vals[] = $a[$f]; }
        }
        if (!$fields) return $this->fail('Aucun champ valide fourni.');

        $fields[] = 'updated_at=NOW()';
        $vals[] = $pid;
        $this->db->exec('UPDATE synopsis SET ' . implode(',', $fields) . ' WHERE project_id=?', $vals);

        $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id=?', [$pid]);
        return $this->ok($this->formatSynopsis($rows[0]));
    }

    // =========================================================================
    // EXPORT MARKDOWN
    // =========================================================================

    private function toolExportMarkdown(int $pid): array
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

    // =========================================================================
    // RECHERCHE
    // =========================================================================

    private function toolSearch(string $query): array
    {
        if (strlen(trim($query)) < 2) return $this->fail('Requête trop courte (min. 2 caractères).');
        $like = '%' . $query . '%';
        $results = [];

        $rows = $this->db->exec(
            'SELECT c.id, c.title, p.title as pt
             FROM chapters c JOIN projects p ON p.id=c.project_id
             WHERE p.user_id=? AND (c.title LIKE ? OR c.content LIKE ?) LIMIT 20',
            [$this->userId, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Chapitre** {$r['title']} (ID: {$r['id']}) — _{$r['pt']}_";

        $rows = $this->db->exec(
            'SELECT n.id, n.title, p.title as pt
             FROM notes n JOIN projects p ON p.id=n.project_id
             WHERE p.user_id=? AND (n.title LIKE ? OR n.content LIKE ?) LIMIT 10',
            [$this->userId, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Note** {$r['title']} (ID: {$r['id']}) — _{$r['pt']}_";

        $rows = $this->db->exec(
            'SELECT c.id, c.name, p.title as pt
             FROM characters c JOIN projects p ON p.id=c.project_id
             WHERE p.user_id=? AND (c.name LIKE ? OR c.description LIKE ?) LIMIT 10',
            [$this->userId, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Personnage** {$r['name']} (ID: {$r['id']}) — _{$r['pt']}_";

        if (!$results) return $this->ok("Aucun résultat pour « $query ».");
        return $this->ok("# Résultats pour « $query »\n\n" . implode("\n", $results));
    }
}
