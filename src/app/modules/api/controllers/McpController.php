<?php

/**
 * McpController — Serveur MCP via HTTP (Streamable HTTP transport MCP 2024-11-05)
 *
 * Endpoint : POST /mcp  (et GET /mcp pour probe)
 * Auth     : Authorization: Bearer <jwt>
 * Config Claude Desktop :
 *   { "mcpServers": { "ecrivain": { "url": "https://…/mcp",
 *       "headers": { "Authorization": "Bearer TOKEN" } } } }
 */

class McpController extends Controller
{

    private int $userId;

    public function beforeRoute(Base $f3): void
    {
        // Pas de CSRF — authentification Bearer JWT
        $uid = $this->authenticateApiRequest();

        if (!$uid) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32001, 'message' => 'Non autorisé : token invalide ou absent.'],
            ]);
            exit;
        }

        $this->userId            = $uid;
        $_SESSION['user_id']     = $uid;
    }

    // ── Point d'entrée unique ────────────────────────────────────────────────

    public function handle(): void
    {
        header('Content-Type: application/json');

        // GET = probe de disponibilité
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode($this->buildInitializeResult(null));
            return;
        }

        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true);

        if (!is_array($body)) {
            echo json_encode(['jsonrpc' => '2.0', 'id' => null,
                'error' => ['code' => -32700, 'message' => 'Parse error']]);
            return;
        }

        $id     = $body['id']     ?? null;
        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];

        switch ($method) {

            case 'initialize':
                echo json_encode($this->buildInitializeResult($id));
                break;

            case 'notifications/initialized':
            case 'initialized':
                // notification : pas de réponse attendue
                echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => new \stdClass()]);
                break;

            case 'tools/list':
                echo json_encode(['jsonrpc' => '2.0', 'id' => $id,
                    'result' => ['tools' => $this->buildToolsList()]]);
                break;

            case 'tools/call':
                $result = $this->callTool($params['name'] ?? '', $params['arguments'] ?? []);
                echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
                break;

            case 'ping':
                echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => new \stdClass()]);
                break;

            default:
                echo json_encode(['jsonrpc' => '2.0', 'id' => $id,
                    'error' => ['code' => -32601, 'message' => 'Méthode inconnue : ' . $method]]);
        }
    }

    // ── Helpers MCP ─────────────────────────────────────────────────────────

    private function buildInitializeResult(mixed $id): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => new \stdClass()],
            'serverInfo'      => ['name' => 'ecrivain', 'version' => '1.0.0'],
        ]];
    }

    private function ok(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false];
    }

    private function fail(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => '**Erreur :** ' . $message]], 'isError' => true];
    }

    private function ownsProject(int $uid, int $pid): bool
    {
        return !empty($this->db->exec(
            'SELECT id FROM projects WHERE id=? AND user_id=?', [$pid, $uid]
        ));
    }

    // ── Liste des outils ─────────────────────────────────────────────────────

    private function buildToolsList(): array
    {
        $int = ['type' => 'integer'];
        $str = ['type' => 'string'];

        return [
            // Projets
            $this->tool('list_projects',   'Liste tous vos projets.',
                [], []),
            $this->tool('get_project',     'Détails complets d\'un projet.',
                ['id' => $int], ['id']),
            $this->tool('create_project',  'Crée un nouveau projet.',
                ['title' => $str, 'description' => $str], ['title']),
            $this->tool('update_project',  'Modifie titre ou description d\'un projet.',
                ['id' => $int, 'title' => $str, 'description' => $str], ['id']),
            $this->tool('delete_project',  'Supprime un projet et tout son contenu.',
                ['id' => $int], ['id']),

            // Actes
            $this->tool('list_acts',       'Liste les actes d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('create_act',      'Crée un acte dans un projet.',
                ['project_id' => $int, 'title' => $str, 'description' => $str], ['project_id', 'title']),
            $this->tool('update_act',      'Modifie un acte.',
                ['id' => $int, 'title' => $str, 'description' => $str], ['id']),
            $this->tool('delete_act',      'Supprime un acte.',
                ['id' => $int], ['id']),

            // Chapitres
            $this->tool('get_chapter',     'Contenu complet d\'un chapitre.',
                ['id' => $int], ['id']),
            $this->tool('create_chapter',  'Crée un chapitre dans un projet.',
                ['project_id' => $int, 'act_id' => $int, 'title' => $str, 'content' => $str],
                ['project_id', 'title', 'act_id']),
            $this->tool('update_chapter',  'Modifie titre et/ou contenu d\'un chapitre.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_chapter',  'Supprime un chapitre.',
                ['id' => $int], ['id']),

            // Sections
            $this->tool('list_sections',   'Liste les sections d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('create_section',  'Crée une section.',
                ['project_id' => $int, 'title' => $str, 'content' => $str], ['project_id', 'title']),
            $this->tool('update_section',  'Modifie une section.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_section',  'Supprime une section.',
                ['id' => $int], ['id']),

            // Notes
            $this->tool('list_notes',      'Liste les notes d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('create_note',     'Crée une note.',
                ['project_id' => $int, 'title' => $str, 'content' => $str], ['project_id', 'title']),
            $this->tool('update_note',     'Modifie une note.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_note',     'Supprime une note.',
                ['id' => $int], ['id']),

            // Personnages
            $this->tool('list_characters', 'Liste les personnages d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('create_character','Crée un personnage.',
                ['project_id' => $int, 'name' => $str, 'description' => $str], ['project_id', 'name']),
            $this->tool('update_character','Modifie un personnage.',
                ['id' => $int, 'name' => $str, 'description' => $str], ['id']),
            $this->tool('delete_character','Supprime un personnage.',
                ['id' => $int], ['id']),

            // Éléments
            $this->tool('list_elements',   'Liste les éléments d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('create_element',  'Crée un élément.',
                ['project_id' => $int, 'title' => $str, 'content' => $str], ['project_id', 'title']),
            $this->tool('update_element',  'Modifie un élément.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_element',  'Supprime un élément.',
                ['id' => $int], ['id']),

            // Export & recherche
            $this->tool('export_markdown', 'Exporte un projet complet en Markdown.',
                ['project_id' => $int], ['project_id']),
            $this->tool('search',          'Recherche dans tous vos projets.',
                ['query' => $str], ['query']),
        ];
    }

    private function tool(string $name, string $desc, array $props, array $required): array
    {
        return [
            'name'        => $name,
            'description' => $desc,
            'inputSchema' => [
                'type'       => 'object',
                'properties' => $props ?: new \stdClass(),
                'required'   => $required,
            ],
        ];
    }

    // ── Dispatcher ───────────────────────────────────────────────────────────

    private function callTool(string $name, array $a): array
    {
        $uid = $this->userId;
        try {
            return match ($name) {
                'list_projects'    => $this->toolListProjects($uid),
                'get_project'      => $this->toolGetProject($uid, (int) ($a['id'] ?? 0)),
                'create_project'   => $this->toolCreateProject($uid, $a),
                'update_project'   => $this->toolUpdateProject($uid, $a),
                'delete_project'   => $this->toolDeleteProject($uid, (int) ($a['id'] ?? 0)),

                'list_acts'        => $this->toolListActs($uid, (int) ($a['project_id'] ?? 0)),
                'create_act'       => $this->toolCreateAct($uid, $a),
                'update_act'       => $this->toolUpdateAct($uid, $a),
                'delete_act'       => $this->toolDeleteAct($uid, (int) ($a['id'] ?? 0)),

                'get_chapter'      => $this->toolGetChapter($uid, (int) ($a['id'] ?? 0)),
                'create_chapter'   => $this->toolCreateChapter($uid, $a),
                'update_chapter'   => $this->toolUpdateChapter($uid, $a),
                'delete_chapter'   => $this->toolDeleteChapter($uid, (int) ($a['id'] ?? 0)),

                'list_sections'    => $this->toolListSections($uid, (int) ($a['project_id'] ?? 0)),
                'create_section'   => $this->toolCreateSection($uid, $a),
                'update_section'   => $this->toolUpdateSection($uid, $a),
                'delete_section'   => $this->toolDeleteSection($uid, (int) ($a['id'] ?? 0)),

                'list_notes'       => $this->toolListNotes($uid, (int) ($a['project_id'] ?? 0)),
                'create_note'      => $this->toolCreateNote($uid, $a),
                'update_note'      => $this->toolUpdateNote($uid, $a),
                'delete_note'      => $this->toolDeleteNote($uid, (int) ($a['id'] ?? 0)),

                'list_characters'  => $this->toolListCharacters($uid, (int) ($a['project_id'] ?? 0)),
                'create_character' => $this->toolCreateCharacter($uid, $a),
                'update_character' => $this->toolUpdateCharacter($uid, $a),
                'delete_character' => $this->toolDeleteCharacter($uid, (int) ($a['id'] ?? 0)),

                'list_elements'    => $this->toolListElements($uid, (int) ($a['project_id'] ?? 0)),
                'create_element'   => $this->toolCreateElement($uid, $a),
                'update_element'   => $this->toolUpdateElement($uid, $a),
                'delete_element'   => $this->toolDeleteElement($uid, (int) ($a['id'] ?? 0)),

                'export_markdown'  => $this->toolExportMarkdown($uid, (int) ($a['project_id'] ?? 0)),
                'search'           => $this->toolSearch($uid, $a['query'] ?? ''),

                default            => $this->fail('Outil inconnu : ' . $name),
            };
        } catch (\Throwable $e) {
            error_log('McpController::callTool error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }

    // ── PROJETS ──────────────────────────────────────────────────────────────

    private function toolListProjects(int $uid): array
    {
        $rows = $this->db->exec(
            'SELECT id, title, description, updated_at FROM projects WHERE user_id=? ORDER BY updated_at DESC',
            [$uid]
        );
        if (!$rows) return $this->ok("Aucun projet.");
        $md = "# Vos projets\n\n";
        foreach ($rows as $r) {
            $md .= "## {$r['title']} (ID: {$r['id']})\n";
            if ($r['description']) $md .= strip_tags($r['description']) . "\n";
            $md .= "_Modifié : {$r['updated_at']}_\n\n";
        }
        return $this->ok(trim($md));
    }

    private function toolGetProject(int $uid, int $pid): array
    {
        $rows = $this->db->exec(
            'SELECT id, title, description, created_at, updated_at FROM projects WHERE id=? AND user_id=?',
            [$pid, $uid]
        );
        if (!$rows) return $this->fail("Projet $pid introuvable.");
        $p  = $rows[0];
        $md = "# {$p['title']} (ID: {$p['id']})\n\n";
        if ($p['description']) $md .= strip_tags($p['description']) . "\n\n";
        $md .= "_Créé : {$p['created_at']} · Modifié : {$p['updated_at']}_";
        return $this->ok($md);
    }

    private function toolCreateProject(int $uid, array $a): array
    {
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $desc = trim($a['description'] ?? '');
        $this->db->exec(
            'INSERT INTO projects (user_id, title, description, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())',
            [$uid, $title, $desc]
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Projet **{$title}** créé (ID: $id).");
    }

    private function toolUpdateProject(int $uid, array $a): array
    {
        $id = (int) ($a['id'] ?? 0);
        if (!$this->ownsProject($uid, $id)) return $this->fail("Projet $id introuvable.");
        $fields = []; $vals = [];
        if (isset($a['title']))       { $fields[] = 'title=?';       $vals[] = trim($a['title']); }
        if (isset($a['description'])) { $fields[] = 'description=?'; $vals[] = trim($a['description']); }
        if (!$fields) return $this->fail('Rien à modifier.');
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE projects SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Projet $id mis à jour.");
    }

    private function toolDeleteProject(int $uid, int $id): array
    {
        if (!$this->ownsProject($uid, $id)) return $this->fail("Projet $id introuvable.");
        $this->db->exec('DELETE FROM projects WHERE id=? AND user_id=?', [$id, $uid]);
        return $this->ok("Projet $id supprimé.");
    }

    // ── ACTES ────────────────────────────────────────────────────────────────

    private function toolListActs(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT a.id, a.title, a.description, COUNT(c.id) as nb
             FROM acts a LEFT JOIN chapters c ON c.act_id=a.id
             WHERE a.project_id=? GROUP BY a.id ORDER BY a.position ASC, a.id ASC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucun acte.");
        $md = "# Actes du projet $pid\n\n";
        foreach ($rows as $r) {
            $md .= "## {$r['title']} (ID: {$r['id']}) — {$r['nb']} chapitre(s)\n";
            if ($r['description']) $md .= strip_tags($r['description']) . "\n";
            $md .= "\n";
        }
        return $this->ok(trim($md));
    }

    private function toolCreateAct(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $pos = $this->db->exec('SELECT COALESCE(MAX(position),0)+1 as p FROM acts WHERE project_id=?', [$pid])[0]['p'];
        $this->db->exec(
            'INSERT INTO acts (project_id, title, description, position, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())',
            [$pid, $title, trim($a['description'] ?? ''), $pos]
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Acte **{$title}** créé (ID: $id).");
    }

    private function toolUpdateAct(int $uid, array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT a.id FROM acts a JOIN projects p ON p.id=a.project_id WHERE a.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Acte $id introuvable.");
        $fields = []; $vals = [];
        if (isset($a['title']))       { $fields[] = 'title=?';       $vals[] = trim($a['title']); }
        if (isset($a['description'])) { $fields[] = 'description=?'; $vals[] = trim($a['description']); }
        if (!$fields) return $this->fail('Rien à modifier.');
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE acts SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Acte $id mis à jour.");
    }

    private function toolDeleteAct(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT a.id FROM acts a JOIN projects p ON p.id=a.project_id WHERE a.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Acte $id introuvable.");
        $this->db->exec('DELETE FROM acts WHERE id=?', [$id]);
        return $this->ok("Acte $id supprimé.");
    }

    // ── CHAPITRES ────────────────────────────────────────────────────────────

    private function toolGetChapter(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id, c.title, c.content, c.word_count, c.updated_at, p.title as pt
             FROM chapters c JOIN projects p ON p.id=c.project_id
             WHERE c.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $c  = $rows[0];
        $md = "# {$c['title']}\n_Projet : {$c['pt']} · {$c['word_count']} mots · Modifié : {$c['updated_at']}_\n\n";
        $md .= strip_tags($c['content'] ?? '');
        return $this->ok($md);
    }

    private function toolCreateChapter(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $content = $a['content'] ?? '';
        $wc      = str_word_count(strip_tags($content));
        $actId   = ($a['act_id'] ?? 0) ?: null;
        $pos     = $this->db->exec(
            'SELECT COALESCE(MAX(position),0)+1 as p FROM chapters WHERE project_id=?', [$pid]
        )[0]['p'];
        $this->db->exec(
            'INSERT INTO chapters (project_id, act_id, title, content, word_count, position, created_at, updated_at)
             VALUES (?,?,?,?,?,?,NOW(),NOW())',
            [$pid, $actId, $title, $content, $wc, $pos]
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Chapitre **{$title}** créé (ID: $id, $wc mots).");
    }

    private function toolUpdateChapter(int $uid, array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT c.id FROM chapters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $fields = []; $vals = [];
        if (isset($a['title']))   { $fields[] = 'title=?';      $vals[] = trim($a['title']); }
        if (isset($a['content'])) {
            $wc = str_word_count(strip_tags($a['content']));
            $fields[] = 'content=?';    $vals[] = $a['content'];
            $fields[] = 'word_count=?'; $vals[] = $wc;
        }
        if (!$fields) return $this->fail('Rien à modifier.');
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE chapters SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Chapitre $id mis à jour.");
    }

    private function toolDeleteChapter(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id FROM chapters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $this->db->exec('DELETE FROM chapters WHERE id=?', [$id]);
        return $this->ok("Chapitre $id supprimé.");
    }

    // ── SECTIONS ─────────────────────────────────────────────────────────────

    private function toolListSections(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, title, updated_at FROM sections WHERE project_id=? ORDER BY position ASC, id ASC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucune section.");
        $md = "# Sections du projet $pid\n\n";
        foreach ($rows as $r) $md .= "- **{$r['title']}** (ID: {$r['id']}) — {$r['updated_at']}\n";
        return $this->ok($md);
    }

    private function toolCreateSection(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $pos = $this->db->exec(
            'SELECT COALESCE(MAX(position),0)+1 as p FROM sections WHERE project_id=?', [$pid]
        )[0]['p'];
        $this->db->exec(
            'INSERT INTO sections (project_id, title, content, position, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())',
            [$pid, $title, $a['content'] ?? '', $pos]
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Section **{$title}** créée (ID: $id).");
    }

    private function toolUpdateSection(int $uid, array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT s.id FROM sections s JOIN projects p ON p.id=s.project_id WHERE s.id=? AND p.user_id=?',
            [$id, $uid]
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

    private function toolDeleteSection(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT s.id FROM sections s JOIN projects p ON p.id=s.project_id WHERE s.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Section $id introuvable.");
        $this->db->exec('DELETE FROM sections WHERE id=?', [$id]);
        return $this->ok("Section $id supprimée.");
    }

    // ── NOTES ────────────────────────────────────────────────────────────────

    private function toolListNotes(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, title, updated_at FROM notes WHERE project_id=? ORDER BY updated_at DESC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucune note.");
        $md = "# Notes du projet $pid\n\n";
        foreach ($rows as $r) $md .= "- **{$r['title']}** (ID: {$r['id']}) — {$r['updated_at']}\n";
        return $this->ok($md);
    }

    private function toolCreateNote(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $this->db->exec(
            'INSERT INTO notes (project_id, title, content, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())',
            [$pid, $title, $a['content'] ?? '']
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Note **{$title}** créée (ID: $id).");
    }

    private function toolUpdateNote(int $uid, array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT n.id FROM notes n JOIN projects p ON p.id=n.project_id WHERE n.id=? AND p.user_id=?',
            [$id, $uid]
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

    private function toolDeleteNote(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT n.id FROM notes n JOIN projects p ON p.id=n.project_id WHERE n.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Note $id introuvable.");
        $this->db->exec('DELETE FROM notes WHERE id=?', [$id]);
        return $this->ok("Note $id supprimée.");
    }

    // ── PERSONNAGES ──────────────────────────────────────────────────────────

    private function toolListCharacters(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, name, description FROM characters WHERE project_id=? ORDER BY name ASC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucun personnage.");
        $md = "# Personnages du projet $pid\n\n";
        foreach ($rows as $r) {
            $md .= "## {$r['name']} (ID: {$r['id']})\n";
            if ($r['description']) $md .= strip_tags($r['description']) . "\n";
            $md .= "\n";
        }
        return $this->ok(trim($md));
    }

    private function toolCreateCharacter(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $name = trim($a['name'] ?? '');
        if (!$name) return $this->fail('Nom requis.');
        $this->db->exec(
            'INSERT INTO characters (project_id, name, description, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())',
            [$pid, $name, $a['description'] ?? '']
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Personnage **{$name}** créé (ID: $id).");
    }

    private function toolUpdateCharacter(int $uid, array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT c.id FROM characters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Personnage $id introuvable.");
        $fields = []; $vals = [];
        if (isset($a['name']))        { $fields[] = 'name=?';        $vals[] = trim($a['name']); }
        if (isset($a['description'])) { $fields[] = 'description=?'; $vals[] = $a['description']; }
        if (!$fields) return $this->fail('Rien à modifier.');
        $fields[] = 'updated_at=NOW()'; $vals[] = $id;
        $this->db->exec('UPDATE characters SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
        return $this->ok("Personnage $id mis à jour.");
    }

    private function toolDeleteCharacter(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id FROM characters c JOIN projects p ON p.id=c.project_id WHERE c.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Personnage $id introuvable.");
        $this->db->exec('DELETE FROM characters WHERE id=?', [$id]);
        return $this->ok("Personnage $id supprimé.");
    }

    // ── ÉLÉMENTS ─────────────────────────────────────────────────────────────

    private function toolListElements(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $rows = $this->db->exec(
            'SELECT id, title, updated_at FROM elements WHERE project_id=? ORDER BY title ASC',
            [$pid]
        );
        if (!$rows) return $this->ok("Aucun élément.");
        $md = "# Éléments du projet $pid\n\n";
        foreach ($rows as $r) $md .= "- **{$r['title']}** (ID: {$r['id']}) — {$r['updated_at']}\n";
        return $this->ok($md);
    }

    private function toolCreateElement(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
        $title = trim($a['title'] ?? '');
        if (!$title) return $this->fail('Titre requis.');
        $this->db->exec(
            'INSERT INTO elements (project_id, title, content, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())',
            [$pid, $title, $a['content'] ?? '']
        );
        $id = $this->db->exec('SELECT LAST_INSERT_ID() as id')[0]['id'];
        return $this->ok("Élément **{$title}** créé (ID: $id).");
    }

    private function toolUpdateElement(int $uid, array $a): array
    {
        $id   = (int) ($a['id'] ?? 0);
        $rows = $this->db->exec(
            'SELECT e.id FROM elements e JOIN projects p ON p.id=e.project_id WHERE e.id=? AND p.user_id=?',
            [$id, $uid]
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

    private function toolDeleteElement(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT e.id FROM elements e JOIN projects p ON p.id=e.project_id WHERE e.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Élément $id introuvable.");
        $this->db->exec('DELETE FROM elements WHERE id=?', [$id]);
        return $this->ok("Élément $id supprimé.");
    }

    // ── EXPORT MARKDOWN ──────────────────────────────────────────────────────

    private function toolExportMarkdown(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");

        $p = $this->db->exec(
            'SELECT title, description FROM projects WHERE id=?', [$pid]
        )[0];
        $md = "# {$p['title']}\n\n";
        if ($p['description']) $md .= strip_tags($p['description']) . "\n\n";

        $acts = $this->db->exec(
            'SELECT id, title FROM acts WHERE project_id=? ORDER BY position ASC, id ASC', [$pid]
        );
        foreach ($acts as $act) {
            $md .= "## {$act['title']}\n\n";
            $chapters = $this->db->exec(
                'SELECT title, content FROM chapters WHERE act_id=? ORDER BY position ASC, id ASC',
                [$act['id']]
            );
            foreach ($chapters as $c) {
                $md .= "### {$c['title']}\n\n" . strip_tags($c['content'] ?? '') . "\n\n";
            }
        }

        // Chapitres sans acte
        $orphans = $this->db->exec(
            'SELECT title, content FROM chapters WHERE project_id=? AND (act_id IS NULL OR act_id=0)
             ORDER BY position ASC, id ASC',
            [$pid]
        );
        foreach ($orphans as $c) {
            $md .= "### {$c['title']}\n\n" . strip_tags($c['content'] ?? '') . "\n\n";
        }

        return $this->ok(trim($md));
    }

    // ── RECHERCHE ────────────────────────────────────────────────────────────

    private function toolSearch(int $uid, string $query): array
    {
        if (strlen(trim($query)) < 2) return $this->fail('Requête trop courte (min. 2 caractères).');
        $like = '%' . $query . '%';
        $results = [];

        $rows = $this->db->exec(
            'SELECT c.id, c.title, p.title as pt
             FROM chapters c JOIN projects p ON p.id=c.project_id
             WHERE p.user_id=? AND (c.title LIKE ? OR c.content LIKE ?) LIMIT 20',
            [$uid, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Chapitre** {$r['title']} (ID: {$r['id']}) — _{$r['pt']}_";

        $rows = $this->db->exec(
            'SELECT n.id, n.title, p.title as pt
             FROM notes n JOIN projects p ON p.id=n.project_id
             WHERE p.user_id=? AND (n.title LIKE ? OR n.content LIKE ?) LIMIT 10',
            [$uid, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Note** {$r['title']} (ID: {$r['id']}) — _{$r['pt']}_";

        $rows = $this->db->exec(
            'SELECT c.id, c.name, p.title as pt
             FROM characters c JOIN projects p ON p.id=c.project_id
             WHERE p.user_id=? AND (c.name LIKE ? OR c.description LIKE ?) LIMIT 10',
            [$uid, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Personnage** {$r['name']} (ID: {$r['id']}) — _{$r['pt']}_";

        if (!$results) return $this->ok("Aucun résultat pour « $query ».");
        return $this->ok("# Résultats pour « $query »\n\n" . implode("\n", $results));
    }

}
