<?php

/**
 * AiConfigController — configuration IA et statistiques d'usage.
 */
class AiConfigController extends AiBaseController
{
    /**
     * Affiche les statistiques de consommation IA.
     */
    public function usage()
    {
        $user       = $this->currentUser();
        $usageModel = new AiUsage();
        $stats      = $usageModel->getStatsByUser($user['id']);
        $recent     = $usageModel->getRecentUsage($user['id']);

        $pricing = new AiPricingService();
        foreach ($stats as &$stat) {
            $stat['estimated_cost'] = $pricing->computeCost(
                $stat['model_name'],
                (int) $stat['total_prompt'],
                (int) $stat['total_completion']
            );
        }
        unset($stat);

        $this->render('ai/usage.html', [
            'title'  => 'Consommation IA',
            'stats'  => $stats,
            'recent' => $recent,
        ]);
    }

    /**
     * Endpoint API pour enregistrer une consommation depuis le client (ex. LanguageTool).
     */
    public function logUsage()
    {
        $json = json_decode($this->f3->get('BODY'), true);
        if (!$json) {
            $this->f3->error(400);
            return;
        }

        $model            = $json['model'] ?? 'unknown';
        $promptTokens     = (int) ($json['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($json['completion_tokens'] ?? 0);
        $feature          = $json['feature'] ?? 'unknown';

        $this->logAiUsage($model, $promptTokens, $completionTokens, $feature);
        echo json_encode(['status' => 'ok']);
    }

    /**
     * Page de configuration des prompts et providers IA.
     */
    public function config()
    {
        $config = $this->getUserConfig();

        $this->render('ai/config.html', [
            'title'          => 'Configuration IA',
            'config'         => $config,
            'providers_json' => json_encode($config['providers']),
            'models'         => $this->getModels(),
            'success'        => $this->f3->get('GET.success'),
        ]);
    }

    /**
     * Sauvegarde la configuration IA de l'utilisateur.
     */
    public function saveConfig()
    {
        $file = $this->getUserConfigFile();
        if (!$file) {
            $this->f3->error(500, 'Impossible de définir le fichier de configuration utilisateur.');
            return;
        }

        $config = $this->getUserConfig();

        $labels       = $this->f3->get('POST.custom_prompt_label');
        $values       = $this->f3->get('POST.custom_prompt_value');
        $customPrompts = [];
        if (is_array($labels) && is_array($values)) {
            $count = min(count($labels), count($values));
            for ($i = 0; $i < $count; $i++) {
                $label  = trim((string) $labels[$i]);
                $prompt = trim((string) $values[$i]);
                if ($label !== '' && $prompt !== '') {
                    $customPrompts[] = ['label' => $label, 'prompt' => $prompt];
                }
            }
        }

        $activeProvider = $this->f3->get('POST.provider');
        $config['active_provider']                    = $activeProvider;
        $config['providers'][$activeProvider] = [
            'api_key' => $this->f3->get('POST.api_key'),
            'model'   => $this->f3->get('POST.model'),
        ];

        $config['prompts'] = [
            'system'            => $this->f3->get('POST.system'),
            'continue'          => $this->f3->get('POST.continue'),
            'rephrase'          => $this->f3->get('POST.rephrase'),
            'summarize_chapter' => $this->f3->get('POST.summarize_chapter'),
            'summarize_act'     => $this->f3->get('POST.summarize_act'),
            'custom'            => $customPrompts,
        ];

        $prevNotifs             = $config['notifications'] ?? [];
        $config['notifications'] = [
            'ai_completion_notify'         => (bool) $this->f3->get('POST.notify_ai_completion'),
            'ai_completion_threshold_secs' => max(10, (int) ($this->f3->get('POST.ai_completion_threshold') ?: 30)),
            'usage_alert_enabled'          => (bool) $this->f3->get('POST.usage_alert_enabled'),
            'usage_alert_threshold'        => max(0, (int) ($this->f3->get('POST.usage_alert_threshold') ?: 100000)),
            'usage_alert_sent_date'        => $prevNotifs['usage_alert_sent_date'] ?? null,
            'weekly_stats'                 => (bool) $this->f3->get('POST.weekly_stats'),
            'last_weekly_sent'             => $prevNotifs['last_weekly_sent'] ?? null,
        ];

        file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->f3->reroute('/ai/config?success=1');
    }
}
