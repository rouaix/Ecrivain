<?php

/**
 * AiBaseController — base partagée pour tous les contrôleurs IA.
 *
 * Contient :
 *  - beforeRoute (auth)
 *  - helpers protégés : getUserConfig, getUserConfigFile, getDefaultPrompts,
 *    getModels, compressPrompt, notifyAiCompletionIfNeeded
 */
class AiBaseController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers partagés
    // ──────────────────────────────────────────────────────────────────────────

    protected function getUserConfig(): array
    {
        $file     = $this->getUserConfigFile();
        $defaults = $this->getDefaultPrompts();
        $config   = [
            'active_provider' => 'openai',
            'providers'       => [],
            'prompts'         => [
                'system'            => $defaults['system'],
                'continue'          => $defaults['continue'],
                'rephrase'          => $defaults['rephrase'],
                'summarize_chapter' => $defaults['summarize_chapter'],
                'summarize_act'     => $defaults['summarize_act'],
                'custom'            => $defaults['custom_prompts'],
            ],
            'notifications' => [
                'ai_completion_notify'         => false,
                'ai_completion_threshold_secs' => 30,
                'usage_alert_enabled'          => false,
                'usage_alert_threshold'        => 100000,
                'usage_alert_sent_date'        => null,
                'weekly_stats'                 => false,
                'last_weekly_sent'             => null,
            ],
        ];

        if ($file && file_exists($file)) {
            $loaded = json_decode(file_get_contents($file), true);

            if (is_array($loaded)) {
                if (isset($loaded['active_provider'])) {
                    $config = array_merge($config, $loaded);
                } else {
                    // Migration depuis l'ancienne structure à clé unique
                    $activeProvider = $loaded['provider'] ?? 'openai';
                    $config['active_provider'] = $activeProvider;

                    $oldApiKeys = $loaded['api_keys'] ?? [];
                    if (!empty($loaded['api_key'])) {
                        $oldApiKeys[$activeProvider] = $loaded['api_key'];
                    }
                    foreach ($oldApiKeys as $prov => $key) {
                        $config['providers'][$prov] = [
                            'api_key' => $key,
                            'model'   => ($prov === $activeProvider) ? ($loaded['model'] ?? 'gpt-4o') : 'gpt-4o',
                        ];
                    }

                    $config['prompts']['system']            = $loaded['system'] ?? $defaults['system'];
                    $config['prompts']['continue']          = $loaded['continue'] ?? $defaults['continue'];
                    $config['prompts']['rephrase']          = $loaded['rephrase'] ?? $defaults['rephrase'];
                    $config['prompts']['summarize_chapter'] = $loaded['summarize_chapter'] ?? $defaults['summarize_chapter'];
                    $config['prompts']['summarize_act']     = $loaded['summarize_act'] ?? $defaults['summarize_act'];
                    $config['prompts']['custom']            = $loaded['custom_prompts'] ?? [];
                }
            }
        }

        return $config;
    }

    protected function getUserConfigFile(): ?string
    {
        $user = $this->currentUser();
        if (!$user || empty($user['email'])) {
            return null;
        }
        $dir = $this->getUserDataDir($user['email']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/ai_config.json';
    }

    protected function getDefaultPrompts(): array
    {
        $file = $this->f3->get('ROOT') . '/data/ai_prompts.json';
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
            if (is_array($json)) {
                return [
                    'system'            => $json['system'] ?? '',
                    'continue'          => $json['continue'] ?? '',
                    'rephrase'          => $json['rephrase'] ?? '',
                    'summarize_chapter' => $json['summarize_chapter'] ?? '',
                    'summarize_act'     => $json['summarize_act'] ?? '',
                    'custom_prompts'    => $json['custom'] ?? [],
                    'synopsis_system'          => $json['synopsis_system'] ?? '',
                    'synopsis_from_idea'       => $json['synopsis_from_idea'] ?? '',
                    'synopsis_generate_beat'   => $json['synopsis_generate_beat'] ?? '',
                    'synopsis_suggest_logline' => $json['synopsis_suggest_logline'] ?? '',
                    'synopsis_evaluate'        => $json['synopsis_evaluate'] ?? '',
                    'synopsis_enrich_beat'     => $json['synopsis_enrich_beat'] ?? '',
                ];
            }
        }

        return [
            'system'            => "Tu es un assistant d'écriture créative expert.",
            'continue'          => "Continue le texte suivant...",
            'rephrase'          => "Reformule le texte suivant...",
            'summarize_chapter' => "Fais un résumé...",
            'summarize_act'     => "Fais un résumé...",
            'custom_prompts'    => [],
        ];
    }

    protected function getModels(): array
    {
        $file = $this->f3->get('ROOT') . '/app/ai_models.json';
        if (!file_exists($file)) {
            error_log('ai_models.json not found at ' . $file);
            return [];
        }
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            error_log('ai_models.json is invalid JSON');
            return [];
        }
        return array_filter($data, fn($k) => $k[0] !== '_', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Compresse un prompt pour réduire la consommation de tokens.
     */
    protected function compressPrompt(string $prompt): string
    {
        $p = str_replace(["\r\n", "\r"], "\n", $prompt);
        $p = preg_replace('/^[^\n]{3,80}:\s*$/m', '', $p) ?? $p;
        $p = preg_replace('/[\x{2022}\xB7]\s+/u', '; ', $p) ?? $p;
        $p = str_replace("\n", ' ', $p);
        $p = preg_replace('/ {2,}/', ' ', $p) ?? $p;
        $p = preg_replace('/;\s*;/', ';', $p) ?? $p;
        return trim($p, " ;");
    }

    /**
     * Envoie une notification si l'appel IA a dépassé le seuil configuré.
     */
    protected function notifyAiCompletionIfNeeded(float $duration, string $task): void
    {
        $user = $this->currentUser();
        if (!$user || empty($user['email'])) return;

        $config = $this->getUserConfig();
        $notifs = $config['notifications'] ?? [];
        if (empty($notifs['ai_completion_notify'])) return;

        $threshold = (float) ($notifs['ai_completion_threshold_secs'] ?? 30);
        if ($duration < $threshold) return;

        $notif = new NotificationService();
        $notif->sendAiCompletionEmail($user['email'], $task, $duration);
    }

    /**
     * Résout le provider, la clé API et le modèle depuis la config utilisateur.
     * Retourne [$provider, $apiKey, $model].
     */
    protected function resolveAiProvider(): array
    {
        $config   = $this->getUserConfig();
        $provider = $config['active_provider'] ?? 'openai';
        $apiKey   = $config['providers'][$provider]['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model    = $config['providers'][$provider]['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');
        return [$provider, $apiKey, $model];
    }
}
