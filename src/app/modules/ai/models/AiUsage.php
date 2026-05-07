<?php

use KS\Mapper;

class AiUsage extends Mapper
{
    const TABLE = 'ai_usage';

    /**
     * Get usage statistics for a user.
     *
     * @param int $userId
     * @return array
     */
    public function getStatsByUser(int $userId): array
    {
        return $this->db->exec(
            'SELECT 
                model_name, 
                SUM(prompt_tokens) as total_prompt, 
                SUM(completion_tokens) as total_completion, 
                SUM(total_tokens) as total_tokens,
                COUNT(*) as request_count
             FROM ai_usage 
             WHERE user_id = ? 
             GROUP BY model_name',
            [$userId]
        );
    }

    /**
     * Get recent usage history for a user.
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRecentUsage(int $userId, int $limit = 20): array
    {
        return $this->db->exec(
            'SELECT * FROM ai_usage
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$userId, $limit]
        );
    }

    /**
     * Get total tokens consumed today by a user.
     */
    public function getTodayTotalByUser(int $userId): int
    {
        $result = $this->db->exec(
            'SELECT SUM(total_tokens) as total
             FROM ai_usage
             WHERE user_id = ? AND DATE(created_at) = CURDATE()',
            [$userId]
        );
        return (int) ($result[0]['total'] ?? 0);
    }
}
