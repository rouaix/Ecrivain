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
        // CORS — ChatGPT et autres clients MCP font des requêtes cross-origin
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = ['https://chatgpt.com', 'https://chat.openai.com', 'https://claude.ai'];
        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Pas de CSRF — authentification Bearer JWT
        $uid = $this->authenticateApiRequest();

        if (!$uid) {
            $base = rtrim((string) $f3->get('BASE'), '/');
            $scheme = $f3->get('SCHEME') ?: 'https';
            $host = $f3->get('HOST');
            $resourceMetadataUrl = $scheme . '://' . $host . $base . '/.well-known/oauth-protected-resource';
            http_response_code(401);
            header('Content-Type: application/json');
            header('WWW-Authenticate: Bearer realm="Ecrivain", resource_metadata="' . $resourceMetadataUrl . '"');
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

    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
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
            $this->tool('get_act',         'Contenu complet d\'un acte avec tous ses chapitres et sous-chapitres.',
                ['id' => $int], ['id']),
            $this->tool('create_act',      'Crée un acte dans un projet.',
                ['project_id' => $int, 'title' => $str, 'description' => $str], ['project_id', 'title']),
            $this->tool('update_act',      'Modifie un acte.',
                ['id' => $int, 'title' => $str, 'description' => $str], ['id']),
            $this->tool('delete_act',      'Supprime un acte.',
                ['id' => $int], ['id']),

            // Chapitres
            $this->tool('list_chapters',   'Liste les chapitres d\'un projet, optionnellement filtrés par acte.',
                ['project_id' => $int, 'act_id' => $int], ['project_id']),
            $this->tool('get_chapter',     'Contenu complet d\'un chapitre.',
                ['id' => $int], ['id']),
            $this->tool('create_chapter',  'Crée un chapitre dans un projet. Utiliser parent_id pour créer un sous-chapitre.',
                ['project_id' => $int, 'act_id' => $int, 'parent_id' => $int, 'title' => $str, 'content' => $str],
                ['project_id', 'title']),
            $this->tool('update_chapter',  'Modifie titre et/ou contenu d\'un chapitre.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_chapter',  'Supprime un chapitre.',
                ['id' => $int], ['id']),

            // Sections
            $this->tool('list_sections',   'Liste les sections d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('get_section',     'Contenu complet d\'une section.',
                ['id' => $int], ['id']),
            $this->tool('create_section',  'Crée une section.',
                ['project_id' => $int, 'title' => $str, 'content' => $str], ['project_id', 'title']),
            $this->tool('update_section',  'Modifie une section.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_section',  'Supprime une section.',
                ['id' => $int], ['id']),

            // Notes
            $this->tool('list_notes',      'Liste les notes d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('get_note',        'Contenu complet d\'une note.',
                ['id' => $int], ['id']),
            $this->tool('create_note',     'Crée une note.',
                ['project_id' => $int, 'title' => $str, 'content' => $str], ['project_id', 'title']),
            $this->tool('update_note',     'Modifie une note.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_note',     'Supprime une note.',
                ['id' => $int], ['id']),

            // Personnages
            $this->tool('list_characters', 'Liste les personnages d\'un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('get_character',   'Fiche complète d\'un personnage.',
                ['id' => $int], ['id']),
            $this->tool('create_character','Crée un personnage.',
                ['project_id' => $int, 'name' => $str, 'description' => $str], ['project_id', 'name']),
            $this->tool('update_character','Modifie un personnage.',
                ['id' => $int, 'name' => $str, 'description' => $str], ['id']),
            $this->tool('delete_character','Supprime un personnage.',
                ['id' => $int], ['id']),

            // Éléments
            $this->tool('list_element_types', 'Liste les types d\'éléments disponibles pour un projet (avec leur template_element_id). À appeler AVANT create_element pour connaître les IDs valides.',
                ['project_id' => $int], ['project_id']),
            $this->tool('list_elements',   'Liste les éléments d\'un projet groupés par type. Affiche les template_element_id nécessaires pour create_element.',
                ['project_id' => $int], ['project_id']),
            $this->tool('get_element',     'Contenu complet d\'un élément avec ses sous-éléments.',
                ['id' => $int], ['id']),
            $this->tool('create_element',  'Crée un élément personnalisé. template_element_id est obligatoire : récupérer les IDs disponibles via list_elements. Utiliser parent_id pour créer un sous-élément.',
                ['project_id' => $int, 'template_element_id' => $int, 'parent_id' => $int, 'title' => $str, 'content' => $str],
                ['project_id', 'title', 'template_element_id']),
            $this->tool('update_element',  'Modifie un élément.',
                ['id' => $int, 'title' => $str, 'content' => $str], ['id']),
            $this->tool('delete_element',  'Supprime un élément.',
                ['id' => $int], ['id']),

            // Images
            $this->tool('list_images',     'Liste les images attachées à un projet.',
                ['project_id' => $int], ['project_id']),
            $this->tool('delete_image',    'Supprime une image d\'un projet.',
                ['project_id' => $int, 'image_id' => $int], ['project_id', 'image_id']),

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
                'get_act'          => $this->toolGetAct($uid, (int) ($a['id'] ?? 0)),
                'create_act'       => $this->toolCreateAct($uid, $a),
                'update_act'       => $this->toolUpdateAct($uid, $a),
                'delete_act'       => $this->toolDeleteAct($uid, (int) ($a['id'] ?? 0)),

                'list_chapters'    => $this->toolListChapters($uid, (int) ($a['project_id'] ?? 0), isset($a['act_id']) ? (int) $a['act_id'] : null),
                'get_chapter'      => $this->toolGetChapter($uid, (int) ($a['id'] ?? 0)),
                'create_chapter'   => $this->toolCreateChapter($uid, $a),
                'update_chapter'   => $this->toolUpdateChapter($uid, $a),
                'delete_chapter'   => $this->toolDeleteChapter($uid, (int) ($a['id'] ?? 0)),

                'list_sections'    => $this->toolListSections($uid, (int) ($a['project_id'] ?? 0)),
                'get_section'      => $this->toolGetSection($uid, (int) ($a['id'] ?? 0)),
                'create_section'   => $this->toolCreateSection($uid, $a),
                'update_section'   => $this->toolUpdateSection($uid, $a),
                'delete_section'   => $this->toolDeleteSection($uid, (int) ($a['id'] ?? 0)),

                'list_notes'       => $this->toolListNotes($uid, (int) ($a['project_id'] ?? 0)),
                'get_note'         => $this->toolGetNote($uid, (int) ($a['id'] ?? 0)),
                'create_note'      => $this->toolCreateNote($uid, $a),
                'update_note'      => $this->toolUpdateNote($uid, $a),
                'delete_note'      => $this->toolDeleteNote($uid, (int) ($a['id'] ?? 0)),

                'list_characters'  => $this->toolListCharacters($uid, (int) ($a['project_id'] ?? 0)),
                'get_character'    => $this->toolGetCharacter($uid, (int) ($a['id'] ?? 0)),
                'create_character' => $this->toolCreateCharacter($uid, $a),
                'update_character' => $this->toolUpdateCharacter($uid, $a),
                'delete_character' => $this->toolDeleteCharacter($uid, (int) ($a['id'] ?? 0)),

                'list_element_types' => $this->toolListElementTypes($uid, (int) ($a['project_id'] ?? 0)),
                'list_elements'    => $this->toolListElements($uid, (int) ($a['project_id'] ?? 0)),
                'get_element'      => $this->toolGetElement($uid, (int) ($a['id'] ?? 0)),
                'create_element'   => $this->toolCreateElement($uid, $a),
                'update_element'   => $this->toolUpdateElement($uid, $a),
                'delete_element'   => $this->toolDeleteElement($uid, (int) ($a['id'] ?? 0)),

                'list_images'      => $this->toolListImages($uid, (int) ($a['project_id'] ?? 0)),
                'delete_image'     => $this->toolDeleteImage($uid, (int) ($a['project_id'] ?? 0), (int) ($a['image_id'] ?? 0)),

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
            if ($r['description']) $md .= $this->htmlToText($r['description']) . "\n";
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
        if ($p['description']) $md .= $this->htmlToText($p['description']) . "\n\n";
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

    private function toolGetAct(int $uid, int $id): array
    {
        $acts = $this->db->exec(
            'SELECT a.id, a.title, a.description, p.user_id
             FROM acts a JOIN projects p ON p.id=a.project_id
             WHERE a.id=?',
            [$id]
        );
        if (!$acts || $acts[0]['user_id'] != $uid) return $this->fail("Acte $id introuvable.");
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

    private function toolCreateAct(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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

    private function toolListChapters(int $uid, int $pid, ?int $actId): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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

        // Indexer par id et regrouper enfants sous parents
        $byId = []; foreach ($rows as $r) $byId[$r['id']] = $r;
        $md   = "# Chapitres du projet $pid\n\n";
        $currentAct = null;
        foreach ($rows as $r) {
            if ($r['parent_id']) continue; // traités sous leur parent
            $act = $r['act_title'] ?? null;
            if ($act !== $currentAct) {
                $md .= "\n## " . ($act ?? 'Sans acte') . "\n";
                $currentAct = $act;
            }
            $md .= "- **{$r['title']}** (ID: {$r['id']})\n";
            // sous-chapitres
            foreach ($rows as $child) {
                if ($child['parent_id'] == $r['id']) {
                    $md .= "  - {$child['title']} (ID: {$child['id']})\n";
                }
            }
        }
        return $this->ok(trim($md));
    }

    private function toolGetChapter(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id, c.title, c.content, c.updated_at, p.title as pt
             FROM chapters c JOIN projects p ON p.id=c.project_id
             WHERE c.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Chapitre $id introuvable.");
        $c    = $rows[0];
        $text = $this->htmlToText($c['content'] ?? '');
        $wc   = str_word_count($text);
        $md   = "# {$c['title']}\n_Projet : {$c['pt']} · {$wc} mots · Modifié : {$c['updated_at']}_\n\n";
        if ($text) $md .= $text . "\n\n";

        // Sous-chapitres
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

    private function toolCreateChapter(int $uid, array $a): array
    {
        $pid = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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
            $fields[] = 'content=?'; $vals[] = $a['content'];
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
            'SELECT id, title, updated_at FROM sections WHERE project_id=? ORDER BY order_index ASC, id ASC',
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
            'SELECT COALESCE(MAX(order_index),0)+1 as p FROM sections WHERE project_id=?', [$pid]
        )[0]['p'];
        $this->db->exec(
            'INSERT INTO sections (project_id, title, content, order_index, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())',
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
            if ($r['description']) $md .= $this->htmlToText($r['description']) . "\n";
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

    private function toolListElementTypes(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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

    private function toolListElements(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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

    private function toolGetElement(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT e.id, e.title, e.content, e.updated_at, p.title as pt
             FROM elements e JOIN projects p ON p.id=e.project_id
             WHERE e.id=? AND p.user_id=?',
            [$id, $uid]
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

    private function toolCreateElement(int $uid, array $a): array
    {
        $pid  = (int) ($a['project_id'] ?? 0);
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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

    // ── GET SECTION / NOTE / CHARACTER ───────────────────────────────────────

    private function toolGetSection(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT s.id, s.title, s.content, s.updated_at, p.title as pt
             FROM sections s JOIN projects p ON p.id=s.project_id
             WHERE s.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Section $id introuvable.");
        $s    = $rows[0];
        $text = $this->htmlToText($s['content'] ?? '');
        $md   = "# {$s['title']}\n_Projet : {$s['pt']} · Modifié : {$s['updated_at']}_\n\n";
        if ($text) $md .= $text . "\n";
        return $this->ok(trim($md));
    }

    private function toolGetNote(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT n.id, n.title, n.content, n.updated_at, p.title as pt
             FROM notes n JOIN projects p ON p.id=n.project_id
             WHERE n.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Note $id introuvable.");
        $n    = $rows[0];
        $text = $this->htmlToText($n['content'] ?? '');
        $md   = "# {$n['title']}\n_Projet : {$n['pt']} · Modifié : {$n['updated_at']}_\n\n";
        if ($text) $md .= $text . "\n";
        return $this->ok(trim($md));
    }

    private function toolGetCharacter(int $uid, int $id): array
    {
        $rows = $this->db->exec(
            'SELECT c.id, c.name, c.description, c.updated_at, p.title as pt
             FROM characters c JOIN projects p ON p.id=c.project_id
             WHERE c.id=? AND p.user_id=?',
            [$id, $uid]
        );
        if (!$rows) return $this->fail("Personnage $id introuvable.");
        $c    = $rows[0];
        $desc = $this->htmlToText($c['description'] ?? '');
        $md   = "# {$c['name']}\n_Projet : {$c['pt']} · Modifié : {$c['updated_at']}_\n\n";
        if ($desc) $md .= $desc . "\n";
        return $this->ok(trim($md));
    }

    // ── IMAGES ───────────────────────────────────────────────────────────────

    private function toolListImages(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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

    private function toolDeleteImage(int $uid, int $pid, int $imageId): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");
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

    // ── EXPORT MARKDOWN ──────────────────────────────────────────────────────

    private function toolExportMarkdown(int $uid, int $pid): array
    {
        if (!$this->ownsProject($uid, $pid)) return $this->fail("Projet $pid introuvable.");

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

        // Chapitres sans acte
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
