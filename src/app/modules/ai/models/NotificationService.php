<?php

class NotificationService
{
    private string $fromEmail;
    private string $fromName = 'Écrivain';

    public function __construct()
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $this->fromEmail = 'noreply@' . $host;
    }

    /**
     * Notify user that a long AI generation has completed.
     */
    public function sendAiCompletionEmail(string $toEmail, string $taskType, float $durationSecs): bool
    {
        $taskLabels = [
            'summarize_chapter' => 'Résumé de chapitre',
            'summarize_act'     => "Résumé d'acte",
            'ask_project'       => 'Question éditoriale',
            'continue'          => 'Continuation de texte',
            'rephrase'          => 'Reformulation',
            'custom'            => 'Génération personnalisée',
        ];

        $label    = $taskLabels[$taskType] ?? ucfirst($taskType);
        $duration = round($durationSecs);

        $subject = "Génération IA terminée — {$label}";

        $body  = "Bonjour,\n\n";
        $body .= "Votre génération IA vient de se terminer.\n\n";
        $body .= "Tâche    : {$label}\n";
        $body .= "Durée    : {$duration} secondes\n\n";
        $body .= "Vous pouvez reprendre votre session d'écriture.\n\n";
        $body .= $this->signature();

        return $this->send($toEmail, $subject, $body);
    }

    /**
     * Alert user that their daily AI token usage has exceeded a threshold.
     */
    public function sendUsageAlertEmail(string $toEmail, int $tokensToday, int $threshold): bool
    {
        $subject = "Alerte usage IA — Seuil atteint";

        $body  = "Bonjour,\n\n";
        $body .= "Votre consommation de tokens IA a dépassé le seuil configuré.\n\n";
        $body .= "Tokens utilisés aujourd'hui : " . number_format($tokensToday, 0, ',', ' ') . "\n";
        $body .= "Seuil configuré              : " . number_format($threshold, 0, ',', ' ') . " tokens\n\n";
        $body .= "Consultez le détail sur la page Usage IA de l'application.\n\n";
        $body .= $this->signature();

        return $this->send($toEmail, $subject, $body);
    }

    /**
     * Send the weekly writing summary.
     *
     * @param array{words_this_week: int, sessions: int, ai_tokens: int} $stats
     */
    public function sendWeeklyStatsEmail(string $toEmail, array $stats): bool
    {
        $subject = "Votre bilan d'écriture de la semaine";

        $body  = "Bonjour,\n\n";
        $body .= "Voici votre bilan d'écriture pour les 7 derniers jours.\n\n";

        if (!empty($stats['words_this_week'])) {
            $body .= "Mots écrits cette semaine  : " . number_format($stats['words_this_week'], 0, ',', ' ') . "\n";
        } else {
            $body .= "Mots écrits cette semaine  : 0\n";
        }

        if (isset($stats['sessions'])) {
            $body .= "Jours d'écriture           : " . $stats['sessions'] . "\n";
        }

        if (!empty($stats['ai_tokens'])) {
            $body .= "Tokens IA utilisés         : " . number_format($stats['ai_tokens'], 0, ',', ' ') . "\n";
        }

        $body .= "\nBonne écriture pour la semaine à venir !\n\n";
        $body .= $this->signature();

        return $this->send($toEmail, $subject, $body);
    }

    // ── Private ─────────────────────────────────────────────────────────────

    private function signature(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'Écrivain';
        return "— L'application {$host}\n";
    }

    private function send(string $to, string $subject, string $body): bool
    {
        if (!function_exists('mail')) {
            error_log("NotificationService: mail() indisponible");
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = implode("\r\n", [
            'From: "' . $this->fromName . '" <' . $this->fromEmail . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion(),
        ]) . "\r\n";

        $result = @mail($to, $encodedSubject, $body, $headers);

        if (!$result) {
            error_log("NotificationService: échec envoi email à {$to} (sujet: {$subject})");
        }

        return (bool) $result;
    }
}
