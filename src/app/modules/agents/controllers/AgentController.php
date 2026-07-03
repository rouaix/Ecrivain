<?php

/**
 * AgentController — galerie (vignettes) et CRUD des agents IA.
 *
 * Les agents sont globaux par utilisateur : réutilisables sur tous ses projets.
 * L'exécution (choix d'une action + des données) est gérée par AgentRunController (étapes 5→7).
 */
class AgentController extends Controller
{
    /** Providers proposés dans le formulaire (clé vide = hérite du provider actif de l'utilisateur). */
    private const PROVIDERS = ['' => 'Provider par défaut', 'openai' => 'OpenAI', 'gemini' => 'Gemini', 'anthropic' => 'Anthropic', 'mistral' => 'Mistral'];

    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Galerie des agents sous forme de vignettes.
     * GET /agents
     */
    public function gallery()
    {
        $userId     = (int) $this->currentUser()['id'];
        $agentModel = new Agent();

        // Seeding paresseux : premier accès sans aucun agent → on crée les agents par défaut.
        if ($agentModel->count(['user_id=?', $userId]) === 0) {
            Agent::seedDefaults($this->f3->get('DB'), $userId);
        }

        $agents = $agentModel->getAllByUser($userId);

        // Compte des actions par agent (pour le badge de vignette).
        $actionCounts = [];
        foreach ($this->f3->get('DB')->exec(
            'SELECT agent_id, COUNT(*) AS n FROM ai_agent_actions
             WHERE agent_id IN (SELECT id FROM ai_agents WHERE user_id=?) GROUP BY agent_id',
            [$userId]
        ) as $row) {
            $actionCounts[(int) $row['agent_id']] = (int) $row['n'];
        }
        foreach ($agents as &$a) {
            $a['action_count'] = $actionCounts[(int) $a['id']] ?? 0;
        }
        unset($a);

        $this->render('agents/gallery.html', [
            'title'       => 'Agents IA',
            'agents'      => $agents,
            'pageSection' => 'Agents IA',
        ]);
    }

    /**
     * Formulaire de création.
     * GET /agent/create
     */
    public function create()
    {
        $this->render('agents/edit.html', [
            'title'     => 'Nouvel agent',
            'agent'     => $this->blankAgent(),
            'actions'   => [],
            'providers' => self::PROVIDERS,
            'errors'    => [],
        ]);
    }

    /**
     * Enregistrement d'un nouvel agent.
     * POST /agent/create
     */
    public function store()
    {
        $userId = (int) $this->currentUser()['id'];
        $data   = $this->readForm();

        if ($data['name'] === '') {
            $this->render('agents/edit.html', [
                'title'     => 'Nouvel agent',
                'agent'     => $data + ['id' => null],
                'actions'   => [],
                'providers' => self::PROVIDERS,
                'errors'    => ['Le nom est obligatoire'],
            ]);
            return;
        }

        $agent               = new Agent();
        $agent->user_id      = $userId;
        $agent->name         = $data['name'];
        $agent->role         = $data['role'];
        $agent->description  = $data['description'];
        $agent->icon         = $data['icon'];
        $agent->color        = $data['color'];
        $agent->system_prompt = $data['system_prompt'];
        $agent->provider     = $data['provider'] !== '' ? $data['provider'] : null;
        $agent->model        = $data['model'] !== '' ? $data['model'] : null;
        $agent->temperature  = $data['temperature'];
        $agent->is_active    = $data['is_active'];
        $agent->save();

        $this->f3->reroute('/agent/' . $agent->id . '/edit');
    }

    /**
     * Formulaire d'édition.
     * GET /agent/@id/edit
     */
    public function edit()
    {
        $id     = (int) $this->f3->get('PARAMS.id');
        $userId = (int) $this->currentUser()['id'];

        $agent = (new Agent())->loadOwned($id, $userId);
        if (!$agent) {
            $this->f3->error(404);
            return;
        }

        $this->render('agents/edit.html', [
            'title'     => 'Modifier — ' . $agent['name'],
            'agent'     => $agent,
            'actions'   => (new AgentAction())->getAllByAgent($id),
            'providers' => self::PROVIDERS,
            'errors'    => [],
        ]);
    }

    /**
     * Mise à jour d'un agent.
     * POST /agent/@id/edit
     */
    public function update()
    {
        $id     = (int) $this->f3->get('PARAMS.id');
        $userId = (int) $this->currentUser()['id'];

        $agentModel = new Agent();
        $agentModel->load(['id=? AND user_id=?', $id, $userId]);
        if ($agentModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $data = $this->readForm();
        if ($data['name'] === '') {
            $this->render('agents/edit.html', [
                'title'     => 'Modifier',
                'agent'     => $data + ['id' => $id],
                'actions'   => (new AgentAction())->getAllByAgent($id),
                'providers' => self::PROVIDERS,
                'errors'    => ['Le nom est obligatoire'],
            ]);
            return;
        }

        $agentModel->name          = $data['name'];
        $agentModel->role          = $data['role'];
        $agentModel->description   = $data['description'];
        $agentModel->icon          = $data['icon'];
        $agentModel->color         = $data['color'];
        $agentModel->system_prompt = $data['system_prompt'];
        $agentModel->provider      = $data['provider'] !== '' ? $data['provider'] : null;
        $agentModel->model         = $data['model'] !== '' ? $data['model'] : null;
        $agentModel->temperature   = $data['temperature'];
        $agentModel->is_active     = $data['is_active'];
        $agentModel->save();

        $_SESSION['success'] = 'Agent enregistré.';
        $this->f3->reroute('/agent/' . $id . '/edit');
    }

    /**
     * Suppression d'un agent (les actions et runs sont supprimés en cascade).
     * GET /agent/@id/delete
     */
    public function delete()
    {
        $id     = (int) $this->f3->get('PARAMS.id');
        $userId = (int) $this->currentUser()['id'];

        $agentModel = new Agent();
        $agentModel->load(['id=? AND user_id=?', $id, $userId]);
        if (!$agentModel->dry()) {
            $agentModel->erase();
        }
        $this->f3->reroute('/agents');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Lit et normalise les champs du formulaire agent. */
    private function readForm(): array
    {
        $temp = (float) str_replace(',', '.', $_POST['temperature'] ?? '0.7');
        $temp = max(0.0, min(2.0, $temp));

        return [
            'name'          => trim($_POST['name'] ?? ''),
            'role'          => mb_substr(trim($_POST['role'] ?? ''), 0, 80),
            'description'   => trim($_POST['description'] ?? ''),
            'icon'          => mb_substr(trim($_POST['icon'] ?? 'robot'), 0, 40) ?: 'robot',
            'color'         => mb_substr(trim($_POST['color'] ?? '#6366f1'), 0, 20) ?: '#6366f1',
            'system_prompt' => trim($_POST['system_prompt'] ?? ''),
            'provider'      => array_key_exists($_POST['provider'] ?? '', self::PROVIDERS) ? ($_POST['provider'] ?? '') : '',
            'model'         => mb_substr(trim($_POST['model'] ?? ''), 0, 60),
            'temperature'   => $temp,
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    /** Valeurs par défaut pour un nouvel agent. */
    private function blankAgent(): array
    {
        return [
            'id' => null, 'name' => '', 'role' => '', 'description' => '',
            'icon' => 'robot', 'color' => '#6366f1', 'system_prompt' => '',
            'provider' => '', 'model' => '', 'temperature' => 0.7, 'is_active' => 1,
        ];
    }
}
