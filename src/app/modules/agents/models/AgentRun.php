<?php

use KS\Mapper;

/**
 * AgentRun — trace d'une exécution d'agent (historique).
 */
class AgentRun extends Mapper
{
    const TABLE = 'ai_agent_runs';

    /** Historique des exécutions d'un agent, plus récentes en premier. */
    public function getByAgent(int $agentId, int $limit = 50): array
    {
        return $this->findAndCast(
            ['agent_id=?', $agentId],
            ['order' => 'created_at DESC, id DESC', 'limit' => $limit]
        );
    }
}
