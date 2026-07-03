<?php

use KS\Mapper;

/**
 * AgentAction — une action exécutable par un agent (« Corriger », « Résumer »…).
 * output_mode : display | replace | append.
 */
class AgentAction extends Mapper
{
    const TABLE = 'ai_agent_actions';

    /** Valeurs autorisées pour output_mode. */
    const OUTPUT_MODES = ['display', 'replace', 'append'];

    /** Retourne toutes les actions d'un agent, dans l'ordre défini. */
    public function getAllByAgent(int $agentId): array
    {
        return $this->findAndCast(
            ['agent_id=?', $agentId],
            ['order' => 'order_index ASC, id ASC']
        );
    }

    /** Charge une action par id. La propriété (agent → user) est vérifiée côté contrôleur. */
    public function loadById(int $id): ?array
    {
        $rows = $this->findAndCast(['id=?', $id]);
        return $rows[0] ?? null;
    }
}
