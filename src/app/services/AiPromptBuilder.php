<?php

/**
 * AiPromptBuilder — Construit les prompts système et utilisateur pour les requêtes IA.
 */
class AiPromptBuilder
{
    private array $defaults;
    private array $userConfig;
    private string $defaultSystem = "Tu es un assistant d'écriture créative.";

    public function __construct(array $defaults = [], array $userConfig = [])
    {
        $this->defaults = $defaults;
        $this->userConfig = $userConfig;
    }

    /**
     * Définit les prompts par défaut.
     */
    public function setDefaults(array $defaults): void
    {
        $this->defaults = $defaults;
    }

    /**
     * Définit la configuration utilisateur.
     */
    public function setUserConfig(array $userConfig): void
    {
        $this->userConfig = $userConfig;
    }

    /**
     * Construit le prompt système complet.
     *
     * @param string $task Type de tâche ('continue', 'rephrase', 'custom', etc.)
     * @param string $contextText Contexte document formaté
     * @param string $customSystemPrompt Prompt système personnalisé (optionnel)
     * @return string Prompt système complet
     */
    public function buildSystemPrompt(string $task, string $contextText = '', string $customSystemPrompt = ''): string
    {
        $configSystem = $this->userConfig['prompts']['system'] ?? '';

        // Si un prompt système est fourni dans la requête, l'utiliser
        if (!empty($customSystemPrompt)) {
            $system = $customSystemPrompt;
        } elseif (!empty($configSystem)) {
            $system = $configSystem;
        } elseif (!empty($this->defaults['system'])) {
            $system = $this->defaults['system'];
        } else {
            $system = $this->defaultSystem;
        }

        // Ajouter le contexte si présent
        if (!empty($contextText)) {
            $system .= " Contexte du document fourni ci-dessous. Respecte le ton, les noms et le style.\n" . $contextText;
        }

        // Ajouter la tâche spécifique
        $system = $this->appendTaskPrompt($system, $task);

        return $system;
    }

    /**
     * Ajoute le prompt spécifique à la tâche.
     */
    private function appendTaskPrompt(string $system, string $task): string
    {
        $taskPrompts = [
            'continue' => $this->userConfig['prompts']['continue'] ?? ($this->defaults['continue'] ?? ''),
            'rephrase' => $this->userConfig['prompts']['rephrase'] ?? ($this->defaults['rephrase'] ?? ''),
            'summarize_chapter' => $this->userConfig['prompts']['summarize_chapter'] ?? ($this->defaults['summarize_chapter'] ?? ''),
            'summarize_act' => $this->userConfig['prompts']['summarize_act'] ?? ($this->defaults['summarize_act'] ?? ''),
            'summarize_element' => $this->userConfig['prompts']['summarize_element'] ?? ($this->defaults['summarize_element'] ?? ''),
        ];

        if (!empty($taskPrompts[$task])) {
            $system .= " " . $taskPrompts[$task];
        }

        return $system;
    }

    /**
     * Construit le prompt utilisateur.
     *
     * @param string $task Type de tâche
     * @param string $userPrompt Prompt de l'utilisateur
     * @param string $content Contenu à inclure (pour certaines tâches)
     * @return string Prompt utilisateur complet
     */
    public function buildUserPrompt(string $task, string $userPrompt, string $content = ''): string
    {
        switch ($task) {
            case 'continue':
                if (empty($userPrompt)) {
                    return "Continue l'histoire.";
                }
                return $userPrompt;

            case 'rephrase':
                if (empty($userPrompt)) {
                    return "Reformule ce texte de manière plus élégante tout en conservant le sens.";
                }
                return $userPrompt;

            case 'custom':
                return $userPrompt;

            case 'summarize_chapter':
            case 'summarize_act':
            case 'summarize_element':
                return $this->buildSummarizeUserPrompt($task, $content);

            default:
                return $userPrompt;
        }
    }

    /**
     * Construit le prompt utilisateur pour une tâche de résumé.
     */
    private function buildSummarizeUserPrompt(string $task, string $content): string
    {
        $prefix = "";
        $contentLabel = "";

        switch ($task) {
            case 'summarize_chapter':
                $prefix = "Fais un résumé d'une dizaine de lignes";
                $contentLabel = "[CONTENU]";
                break;
            case 'summarize_act':
                $prefix = "Fais un résumé cohérent";
                $contentLabel = "[RÉSUMÉS DES CHAPITRES]";
                break;
            case 'summarize_element':
                $prefix = "Fais un résumé des éléments";
                $contentLabel = "[SOUS-ÉLÉMENTS]";
                break;
        }

        return $prefix . " du contenu suivant:\n\n" . $contentLabel . "\n" . $content;
    }

    /**
     * Détermine le nombre maximal de tokens pour une tâche.
     *
     * @param string $task Type de tâche
     * @param string $userPrompt Prompt de l'utilisateur
     * @return int Nombre maximal de tokens
     */
    public function getMaxTokens(string $task, string $userPrompt): int
    {
        switch ($task) {
            case 'continue':
                return 700;

            case 'rephrase':
                return min(600, (int)(mb_strlen($userPrompt) / 3) + 100);

            case 'custom':
                return 1000;

            case 'summarize_chapter':
            case 'summarize_act':
            case 'summarize_element':
                return 500;

            default:
                return 800;
        }
    }

    /**
     * Compresse un prompt trop long.
     *
     * @param string $prompt Prompt à comprimer
     * @param int $maxLength Longueur maximale (en caractères)
     * @return string Prompt compressé
     */
    public function compressPrompt(string $prompt, int $maxLength = 8000): string
    {
        if (mb_strlen($prompt) <= $maxLength) {
            return $prompt;
        }

        // Supprimer les espaces multiples
        $prompt = preg_replace('/\s+/', ' ', $prompt);

        if (mb_strlen($prompt) <= $maxLength) {
            return $prompt;
        }

        // Tronquer en gardant les 80% du début et 20% de la fin
        $keepStart = (int)($maxLength * 0.8);
        $keepEnd = $maxLength - $keepStart - 10; // 10 pour "..."
        $start = mb_substr($prompt, 0, $keepStart);
        $end = mb_substr($prompt, -$keepEnd);

        return $start . '...' . $end;
    }
}
