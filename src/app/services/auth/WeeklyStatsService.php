<?php

/**
 * WeeklyStatsService — Service de statistiques hebdomadaires.
 */
class WeeklyStatsService
{
    private \DB\SQL $db;

    public function __construct(\DB\SQL $db)
    {
        $this->db = $db;
    }

    /**
     * Vérifie si l'envoi des stats hebdomadaires est dû pour un utilisateur.
     */
    public function isDue(int $userId): bool
    {
        $row = $this->db->exec(
            'SELECT last_stats_email FROM users WHERE id = ?',
            [$userId]
        );
        
        if (!$row) {
            return false;
        }
        
        $lastSent = strtotime($row[0]['last_stats_email'] ?? '1970-01-01');
        $now = time();
        $oneWeekAgo = $now - (7 * 24 * 60 * 60);
        
        return $lastSent < $oneWeekAgo;
    }

    /**
     * Met à jour la date du dernier envoi de stats.
     */
    public function markAsSent(int $userId): void
    {
        $this->db->exec(
            'UPDATE users SET last_stats_email = NOW() WHERE id = ?',
            [$userId]
        );
    }

    /**
     * Récupère les statistiques d'écriture de la semaine pour un utilisateur.
     */
    public function gatherStats(int $userId): array
    {
        $startOfWeek = date('Y-m-d', strtotime('last monday'));
        
        // Total words written this week
        $wordCount = $this->db->exec(
            'SELECT SUM(word_count) AS total
             FROM chapter_versions cv
             JOIN chapters c ON c.id = cv.chapter_id
             JOIN projects p ON p.id = c.project_id
             WHERE p.user_id = ? AND cv.created_at >= ?',
            [$userId, $startOfWeek]
        );
        
        // Chapters created
        $chaptersCreated = $this->db->exec(
            'SELECT COUNT(*) AS count
             FROM chapters c
             JOIN projects p ON p.id = c.project_id
             WHERE p.user_id = ? AND c.created_at >= ?',
            [$userId, $startOfWeek]
        );
        
        // Projects created
        $projectsCreated = $this->db->exec(
            'SELECT COUNT(*) AS count FROM projects WHERE user_id = ? AND created_at >= ?',
            [$userId, $startOfWeek]
        );
        
        // Active days
        $activeDays = $this->db->exec(
            "SELECT COUNT(DISTINCT DATE(created_at)) AS count
             FROM chapter_versions cv
             JOIN chapters c ON c.id = cv.chapter_id
             JOIN projects p ON p.id = c.project_id
             WHERE p.user_id = ? AND cv.created_at >= ?",
            [$userId, $startOfWeek]
        );
        
        return [
            'start_date' => $startOfWeek,
            'end_date' => date('Y-m-d'),
            'words_written' => (int)($wordCount[0]['total'] ?? 0),
            'chapters_created' => (int)($chaptersCreated[0]['count'] ?? 0),
            'projects_created' => (int)($projectsCreated[0]['count'] ?? 0),
            'active_days' => (int)($activeDays[0]['count'] ?? 0),
        ];
    }

    /**
     * Envoie l'email de statistiques hebdomadaires.
     */
    public function sendWeeklyEmail(int $userId, string $email, string $username): bool
    {
        $stats = $this->gatherStats($userId);
        
        $subject = 'Votre semaine sur Écrivain';
        
        $message = "Bonjour $username,\n\n" .
                   "Voici votre bilan d'écriture pour la semaine du {$stats['start_date']} au {$stats['end_date']} :\n\n" .
                   "- Mots écrits : {$stats['words_written']}\n" .
                   "- Chapitres créés : {$stats['chapters_created']}\n" .
                   "- Projets créés : {$stats['projects_created']}\n" .
                   "- Jours actifs : {$stats['active_days']}\n\n" .
                   "Bonne écriture !\n\n" .
                   "L'équipe Écrivain";
        
        $mailer = new Mailer();
        return $mailer->send($email, $subject, $message);
    }

    /**
     * Vérifie et envoie les stats hebdomadaires si dû.
     */
    public function sendIfDue(int $userId, string $email, string $username): bool
    {
        if (!$this->isDue($userId)) {
            return false;
        }
        
        $result = $this->sendWeeklyEmail($userId, $email, $username);
        if ($result) {
            $this->markAsSent($userId);
        }
        
        return $result;
    }
}
