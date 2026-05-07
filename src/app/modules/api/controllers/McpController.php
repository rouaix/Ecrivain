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
    private ?McpToolHandlerService $toolHandlerService = null;

    private function getToolHandlerService(): McpToolHandlerService
    {
        if ($this->toolHandlerService === null) {
            $this->toolHandlerService = new McpToolHandlerService($this->db, $this->f3, $this->userId);
        }
        return $this->toolHandlerService;
    }

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

        // GET /mcp sans auth = discovery (CIMD) — retourner les capabilities sans authentification
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$this->hasAuthorizationHeader()) {
            header('Content-Type: application/json');
            echo json_encode($this->buildInitializeResult(null));
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

    // ── Point d'entrée unique ──────────────────────────────────────────────

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
                $requestedVersion = $params['protocolVersion'] ?? '2025-03-26';
                echo json_encode($this->buildInitializeResult($id, $requestedVersion));
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

    private function buildInitializeResult(mixed $id, string $requestedVersion = '2025-03-26'): array
    {
        // Versions supportées, de la plus récente à la plus ancienne
        $supported = ['2025-03-26', '2024-11-05'];
        $version   = in_array($requestedVersion, $supported, true) ? $requestedVersion : $supported[0];

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
            'protocolVersion' => $version,
            'capabilities'    => [
                'tools' => new \stdClass(),
            ],
            'serverInfo'      => ['name' => 'ecrivain', 'version' => '1.0.0'],
        ]];
    }

    private function hasAuthorizationHeader(): bool
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return true;
        }
        // Apache peut passer le header via la variable REDIRECT_HTTP_AUTHORIZATION
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return true;
        }
        return false;
    }

    private function htmlToText(string $html): string
    {
        return ContentTransformer::htmlToText($html);
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
                ['project_id' => $int, 'name' => $str, 'description' => $str, 'comment' => $str], ['project_id', 'name']),
            $this->tool('update_character','Modifie un personnage.',
                ['id' => $int, 'name' => $str, 'description' => $str, 'comment' => $str], ['id']),
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

            // Synopsis
            $this->tool('get_synopsis',    'Lit le synopsis d\'un projet (métadonnées + beats narratifs). Crée le synopsis automatiquement s\'il n\'existe pas encore.',
                ['project_id' => $int], ['project_id']),
            $this->tool('update_synopsis', 'Met à jour le synopsis d\'un projet. Champs optionnels : genre, subgenre, audience, tone, themes, comps, status, structure_method, logline, pitch, situation, trigger_evt, plot_point1, development, midpoint, crisis, climax, resolution.',
                ['project_id' => $int, 'genre' => $str, 'subgenre' => $str, 'audience' => $str,
                 'tone' => $str, 'themes' => $str, 'comps' => $str, 'status' => $str,
                 'structure_method' => $str, 'logline' => $str, 'pitch' => $str,
                 'situation' => $str, 'trigger_evt' => $str, 'plot_point1' => $str,
                 'development' => $str, 'midpoint' => $str, 'crisis' => $str,
                 'climax' => $str, 'resolution' => $str], ['project_id']),

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

    private function callTool(string $name, array $arguments): array
    {
        return $this->getToolHandlerService()->callTool($name, $arguments);
    }

}
