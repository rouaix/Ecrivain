<?php

/**
 * AgentRunController — exécution d'un agent sur des données choisies.
 *
 * Étend AiBaseController pour réutiliser : getUserConfig, checkRateLimit,
 * logAiUsage, notifyAiCompletionIfNeeded.
 * Réutilise AiService (appel provider) et AiContextService (chargement des données).
 */
class AgentRunController extends AiBaseController
{
    /** Types de données sélectionnables et leur libellé. */
    private const TYPES = [
        'chapter'   => 'Chapitres',
        'act'       => 'Actes',
        'section'   => 'Sections',
        'note'      => 'Notes',
        'element'   => 'Éléments',
        'character' => 'Personnages',
        'synopsis'  => 'Synopsis',
    ];

    /**
     * Page d'exécution d'un agent.
     * GET /agent/@id/run
     */
    public function form()
    {
        $id     = (int) $this->f3->get('PARAMS.id');
        $userId = (int) $this->currentUser()['id'];

        $agent = (new Agent())->loadOwned($id, $userId);
        if (!$agent) { $this->f3->error(404); return; }

        $actions  = (new AgentAction())->getAllByAgent($id);
        $projects = $this->db->exec('SELECT id, title FROM projects WHERE user_id=? ORDER BY title ASC', [$userId]);

        $this->render('agents/run.html', [
            'title'    => 'Exécuter — ' . $agent['name'],
            'agent'    => $agent,
            'actions'  => $actions,
            'projects' => $projects,
            'types'    => self::TYPES,
        ]);
    }

    /**
     * Liste JSON des éléments d'un projet pour un type donné.
     * GET /agent/run-items?project_id=&type=
     */
    public function items()
    {
        header('Content-Type: application/json');
        $userId    = (int) $this->currentUser()['id'];
        $projectId = (int) $this->f3->get('GET.project_id');
        $type      = (string) $this->f3->get('GET.type');

        if (!$this->ownsProject($projectId, $userId) || !isset(self::TYPES[$type])) {
            echo json_encode(['items' => []]);
            return;
        }

        echo json_encode(['items' => $this->listItems($type, $projectId)]);
    }

    /**
     * Exécute l'agent.
     * POST /agent/run  (JSON body)
     */
    public function execute()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('agent_run', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Patientez quelques secondes.']);
            return;
        }

        $userId = (int) $this->currentUser()['id'];
        $body   = json_decode($this->f3->get('BODY'), true) ?: [];

        $agentId   = (int) ($body['agent_id'] ?? 0);
        $actionId  = (int) ($body['action_id'] ?? 0);
        $projectId = (int) ($body['project_id'] ?? 0);
        $type      = (string) ($body['type'] ?? '');
        $ids       = array_values(array_filter(array_map('intval', (array) ($body['ids'] ?? []))));
        $extra     = trim((string) ($body['extra'] ?? ''));

        $agent = (new Agent())->loadOwned($agentId, $userId);
        if (!$agent) { echo json_encode(['success' => false, 'error' => 'Agent introuvable.']); return; }

        $action = $this->loadActionForAgent($actionId, $agentId);
        if (!$action) { echo json_encode(['success' => false, 'error' => 'Action invalide.']); return; }

        // Contexte des données sélectionnées
        $contextText = '';
        if ($ids && isset(self::TYPES[$type])) {
            if (!$this->ownsProject($projectId, $userId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accès non autorisé au projet.']);
                return;
            }
            $contextText = $this->buildContext($type, $ids, $userId);
        }

        if ($contextText === '' && $extra === '') {
            echo json_encode(['success' => false, 'error' => 'Sélectionnez des données ou saisissez une instruction.']);
            return;
        }

        // Résolution provider/modèle (l'agent peut surcharger la config utilisateur)
        $config   = $this->getUserConfig();
        $provider = !empty($agent['provider']) ? $agent['provider'] : ($config['active_provider'] ?? 'openai');
        $apiKey   = $config['providers'][$provider]['api_key'] ?? '';
        $model    = !empty($agent['model']) ? $agent['model'] : ($config['providers'][$provider]['model'] ?? 'gpt-4o');

        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => "Aucune clé API configurée pour le provider « $provider ». Réglez-la dans la configuration IA."]);
            return;
        }

        $system = $this->compressPrompt((string) $agent['system_prompt']);
        if ($system === '') { $system = "Tu es un assistant d'écriture."; }

        $userPrompt = (string) $action['instruction'];
        if ($extra !== '') { $userPrompt .= "\n\n[CONSIGNE COMPLÉMENTAIRE]\n" . $extra; }
        if ($contextText !== '') { $userPrompt .= "\n\n[DONNÉES]\n" . $contextText; }

        $service = new AiService($provider, $apiKey, $model);
        $t0      = microtime(true);
        $result  = $service->generate($system, $userPrompt, (float) $agent['temperature'], (int) $action['max_tokens']);
        $elapsed = microtime(true) - $t0;

        if (empty($result['success'])) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA.']);
            return;
        }

        $text = (string) $result['text'];
        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'agent:' . ($action['action_key'] ?: 'run'));
        $this->notifyAiCompletionIfNeeded($elapsed, 'agent ' . $agent['name']);

        // Write-back éventuel (une seule source, type compatible)
        $applied = false;
        $applyMessage = '';
        if ($action['output_mode'] !== 'display' && count($ids) === 1 && AgentOutputService::supports($type)) {
            $out = (new AgentOutputService($this->db, $userId))->apply($action['output_mode'], $type, $ids[0], $text);
            $applied      = $out['applied'];
            $applyMessage = $out['message'];
        } elseif ($action['output_mode'] !== 'display') {
            $applyMessage = "Écriture non appliquée : sélectionnez une seule source de type compatible.";
        }

        // Historique
        try {
            $run                    = new AgentRun();
            $run->agent_id          = $agentId;
            $run->action_id         = $actionId;
            $run->user_id           = $userId;
            $run->project_id        = $projectId ?: null;
            $run->context_type      = $type ?: null;
            $run->context_ids       = json_encode($ids);
            $run->input_excerpt     = mb_substr($extra, 0, 500);
            $run->output            = $text;
            $run->model             = $model;
            $run->prompt_tokens     = $result['prompt_tokens'] ?? 0;
            $run->completion_tokens = $result['completion_tokens'] ?? 0;
            $run->save();
        } catch (\Throwable $e) {
            // l'historique ne doit jamais bloquer la réponse
        }

        echo json_encode([
            'success'     => true,
            'text'        => $text,
            'output_mode' => $action['output_mode'],
            'applied'     => $applied,
            'message'     => $applyMessage,
        ]);
    }

    /**
     * Historique des exécutions d'un agent.
     * GET /agent/@id/history
     */
    public function history()
    {
        $id     = (int) $this->f3->get('PARAMS.id');
        $userId = (int) $this->currentUser()['id'];

        $agent = (new Agent())->loadOwned($id, $userId);
        if (!$agent) { $this->f3->error(404); return; }

        $this->render('agents/history.html', [
            'title' => 'Historique — ' . $agent['name'],
            'agent' => $agent,
            'runs'  => (new AgentRun())->getByAgent($id, 50),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function ownsProject(int $projectId, int $userId): bool
    {
        return (int) ($this->db->exec(
            'SELECT COUNT(*) AS n FROM projects WHERE id=? AND user_id=?',
            [$projectId, $userId]
        )[0]['n'] ?? 0) > 0;
    }

    /** Charge une action en vérifiant qu'elle appartient bien à l'agent. */
    private function loadActionForAgent(int $actionId, int $agentId): ?array
    {
        $rows = (new AgentAction())->findAndCast(['id=? AND agent_id=?', $actionId, $agentId]);
        return $rows[0] ?? null;
    }

    /** Liste [{id,title}] des éléments d'un type pour un projet. */
    private function listItems(string $type, int $projectId): array
    {
        switch ($type) {
            case 'chapter':
                // Liste hiérarchique : chaque chapitre suivi de ses sous-chapitres (indentés).
                $rows = $this->db->exec(
                    'SELECT id, title, parent_id FROM chapters WHERE project_id=? ORDER BY order_index ASC, id ASC',
                    [$projectId]
                );
                return $this->buildChapterHierarchy($rows);
            case 'act':
                $rows = $this->db->exec('SELECT id, title FROM acts WHERE project_id=? ORDER BY order_index ASC, id ASC', [$projectId]);
                break;
            case 'section':
                $rows = $this->db->exec('SELECT id, title FROM sections WHERE project_id=? ORDER BY id ASC', [$projectId]);
                break;
            case 'note':
                $rows = $this->db->exec('SELECT id, title FROM notes WHERE project_id=? ORDER BY id ASC', [$projectId]);
                break;
            case 'element':
                $rows = $this->db->exists('elements')
                    ? $this->db->exec('SELECT id, title FROM elements WHERE project_id=? ORDER BY id ASC', [$projectId])
                    : [];
                break;
            case 'character':
                $rows = $this->db->exec('SELECT id, name AS title FROM characters WHERE project_id=? ORDER BY name ASC', [$projectId]);
                break;
            case 'synopsis':
                $rows = $this->db->exists('synopsis')
                    ? array_map(
                        fn($r) => ['id' => $r['id'], 'title' => 'Synopsis'],
                        $this->db->exec('SELECT id FROM synopsis WHERE project_id=?', [$projectId])
                      )
                    : [];
                break;
            default:
                $rows = [];
        }

        return array_map(fn($r) => ['id' => (int) $r['id'], 'title' => (string) ($r['title'] ?: '(sans titre)')], $rows);
    }

    /**
     * Ordonne les chapitres en hiérarchie : chaque chapitre de 1er niveau suivi de ses
     * sous-chapitres. Retourne [{id, title, sub}] (sub=true pour un sous-chapitre).
     */
    private function buildChapterHierarchy(array $rows): array
    {
        $children = [];
        $tops     = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['parent_id'] ?? 0);
            if ($pid > 0) {
                $children[$pid][] = $r;
            } else {
                $tops[] = $r;
            }
        }

        $mk = fn($r, $sub) => [
            'id'    => (int) $r['id'],
            'title' => (string) ($r['title'] ?: '(sans titre)'),
            'sub'   => $sub,
        ];

        $out    = [];
        $placed = [];
        foreach ($tops as $t) {
            $out[] = $mk($t, false);
            $placed[(int) $t['id']] = true;
            foreach ($children[(int) $t['id']] ?? [] as $c) {
                $out[] = $mk($c, true);
                $placed[(int) $c['id']] = true;
            }
        }
        // Sous-chapitres dont le parent n'est pas dans ce projet : ajoutés à la fin.
        foreach ($rows as $r) {
            if (empty($placed[(int) $r['id']])) {
                $out[] = $mk($r, (int) ($r['parent_id'] ?? 0) > 0);
            }
        }

        return $out;
    }

    /** Construit le texte de contexte à partir des données sélectionnées (limité en taille). */
    private function buildContext(string $type, array $ids, int $userId): string
    {
        $ctxService = new AiContextService($this->db, $userId);
        $parts      = [];
        $budget     = 6000; // caractères max pour maîtriser les tokens

        foreach ($ids as $id) {
            if ($budget <= 0) break;

            if ($type === 'character') {
                $rows = $this->db->exec(
                    'SELECT c.name, c.description FROM characters c
                     JOIN projects p ON p.id = c.project_id
                     WHERE c.id=? AND p.user_id=?',
                    [$id, $userId]
                );
                if (!$rows) continue;
                $title = $rows[0]['name'];
                $content = strip_tags((string) $rows[0]['description']);
            } else {
                $ctx = $ctxService->loadDocumentContext($type, $id);
                if (empty($ctx)) continue;
                $title = $ctx['title'] ?? '';
                if ($type === 'synopsis') {
                    $content = $this->synopsisToText($ctx);
                } else {
                    $content = strip_tags((string) ($ctx['content'] ?? ''));
                }
            }

            $content = trim($content);
            if (mb_strlen($content) > $budget) {
                $content = mb_substr($content, 0, $budget) . '…';
            }
            $budget -= mb_strlen($content);
            $parts[] = '[' . $title . "]\n" . ($content !== '' ? $content : '(vide)');
        }

        return implode("\n\n", $parts);
    }

    /** Aplati les beats d'un synopsis en texte. */
    private function synopsisToText(array $ctx): string
    {
        $fields = ['logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
                   'development', 'midpoint', 'crisis', 'climax', 'resolution'];
        $lines = [];
        foreach ($fields as $f) {
            $v = trim(strip_tags((string) ($ctx[$f] ?? '')));
            if ($v !== '') { $lines[] = ucfirst($f) . ': ' . $v; }
        }
        return implode("\n", $lines);
    }
}
