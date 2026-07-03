<?php

/**
 * AgentActionController — CRUD des actions d'un agent.
 * Chaque action = une tâche exécutable (label, instruction, mode de sortie).
 */
class AgentActionController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Ajoute une action à un agent.
     * POST /agent/@id/action/add
     */
    public function add()
    {
        $agentId = (int) $this->f3->get('PARAMS.id');
        $userId  = (int) $this->currentUser()['id'];

        if (!$this->ownsAgent($agentId, $userId)) {
            $this->f3->error(404);
            return;
        }

        $data = $this->readForm();
        if ($data['label'] === '') {
            $_SESSION['error'] = "Le libellé de l'action est obligatoire.";
            $this->f3->reroute('/agent/' . $agentId . '/edit');
            return;
        }

        $next = (int) ($this->db->exec(
            'SELECT COALESCE(MAX(order_index), -1) + 1 AS n FROM ai_agent_actions WHERE agent_id=?',
            [$agentId]
        )[0]['n'] ?? 0);

        $action              = new AgentAction();
        $action->agent_id    = $agentId;
        $action->label       = $data['label'];
        $action->action_key  = $data['action_key'];
        $action->instruction = $data['instruction'];
        $action->output_mode = $data['output_mode'];
        $action->max_tokens  = $data['max_tokens'];
        $action->order_index = $next;
        $action->save();

        $_SESSION['success'] = 'Action ajoutée.';
        $this->f3->reroute('/agent/' . $agentId . '/edit');
    }

    /**
     * Met à jour une action.
     * POST /agent/action/@aid/update
     */
    public function update()
    {
        $actionId = (int) $this->f3->get('PARAMS.aid');
        $userId   = (int) $this->currentUser()['id'];

        $agentId = $this->actionAgentIdIfOwned($actionId, $userId);
        if ($agentId === null) {
            $this->f3->error(404);
            return;
        }

        $data = $this->readForm();
        if ($data['label'] === '') {
            $_SESSION['error'] = "Le libellé de l'action est obligatoire.";
            $this->f3->reroute('/agent/' . $agentId . '/edit');
            return;
        }

        $action = new AgentAction();
        $action->load(['id=?', $actionId]);
        $action->label       = $data['label'];
        $action->action_key  = $data['action_key'];
        $action->instruction = $data['instruction'];
        $action->output_mode = $data['output_mode'];
        $action->max_tokens  = $data['max_tokens'];
        $action->save();

        $_SESSION['success'] = 'Action mise à jour.';
        $this->f3->reroute('/agent/' . $agentId . '/edit');
    }

    /**
     * Supprime une action.
     * GET /agent/action/@aid/delete
     */
    public function delete()
    {
        $actionId = (int) $this->f3->get('PARAMS.aid');
        $userId   = (int) $this->currentUser()['id'];

        $agentId = $this->actionAgentIdIfOwned($actionId, $userId);
        if ($agentId === null) {
            $this->f3->error(404);
            return;
        }

        $action = new AgentAction();
        $action->load(['id=?', $actionId]);
        if (!$action->dry()) {
            $action->erase();
        }

        $_SESSION['success'] = 'Action supprimée.';
        $this->f3->reroute('/agent/' . $agentId . '/edit');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Vrai si l'agent appartient à l'utilisateur. */
    private function ownsAgent(int $agentId, int $userId): bool
    {
        return (int) ($this->db->exec(
            'SELECT COUNT(*) AS n FROM ai_agents WHERE id=? AND user_id=?',
            [$agentId, $userId]
        )[0]['n'] ?? 0) > 0;
    }

    /** Retourne l'agent_id de l'action si son agent appartient à l'utilisateur, sinon null. */
    private function actionAgentIdIfOwned(int $actionId, int $userId): ?int
    {
        $rows = $this->db->exec(
            'SELECT act.agent_id FROM ai_agent_actions act
             JOIN ai_agents a ON a.id = act.agent_id
             WHERE act.id=? AND a.user_id=?',
            [$actionId, $userId]
        );
        return $rows ? (int) $rows[0]['agent_id'] : null;
    }

    /** Lit et normalise les champs du formulaire action. */
    private function readForm(): array
    {
        $mode = $_POST['output_mode'] ?? 'display';
        if (!in_array($mode, AgentAction::OUTPUT_MODES, true)) {
            $mode = 'display';
        }
        $maxTokens = (int) ($_POST['max_tokens'] ?? 800);
        $maxTokens = max(50, min(32000, $maxTokens));

        return [
            'label'       => trim($_POST['label'] ?? ''),
            'action_key'  => mb_substr(trim($_POST['action_key'] ?? ''), 0, 60),
            'instruction' => trim($_POST['instruction'] ?? ''),
            'output_mode' => $mode,
            'max_tokens'  => $maxTokens,
        ];
    }
}
