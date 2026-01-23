<?php

class AiController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Display usage statistics.
     */
    public function usage()
    {
        $user = $this->currentUser();
        $usageModel = new AiUsage();

        $stats = $usageModel->getStatsByUser($user['id']);
        $recent = $usageModel->getRecentUsage($user['id']);

        // Calculate estimated cost
        foreach ($stats as &$stat) {
            $cost = 0;
            $model = $stat['model_name'];
            $prompt = $stat['total_prompt'];
            $completion = $stat['total_completion'];

            // Pricing per 1M tokens (USD, approximated)
            // gpt-4o: 5.00 / 15.00
            // gpt-3.5-turbo: 0.50 / 1.50

            if (strpos($model, 'gpt-4.1-mini') !== false) {
                // GPT-4.1 Mini (~$1.08/1M actual cost)
                // Adjusted to 1.00 / 2.00
                $cost = ($prompt / 1000000 * 1.00) + ($completion / 1000000 * 2.00);
            } elseif (strpos($model, 'gpt-4o-mini') !== false) {
                // Extremely cheap: 0.15 / 0.60
                $cost = ($prompt / 1000000 * 0.15) + ($completion / 1000000 * 0.60);
            } elseif (strpos($model, 'gpt-4o') !== false || strpos($model, 'gpt-4.1') !== false || strpos($model, 'o1-mini') !== false) {
                // GPT-4o Standard / GPT-4.1 / o1-mini
                // Approx 5.00 / 15.00
                $cost = ($prompt / 1000000 * 5.00) + ($completion / 1000000 * 15.00);
            } elseif (strpos($model, 'o1') !== false) {
                // o1 Standard: 15.00 / 60.00
                $cost = ($prompt / 1000000 * 15.00) + ($completion / 1000000 * 60.00);
            } elseif (strpos($model, 'gpt-3.5') !== false) {
                $cost = ($prompt / 1000000 * 0.50) + ($completion / 1000000 * 1.50);
            } elseif (strpos($model, 'gpt-4') !== false) {
                // Older GPT-4 (more expensive usually)
                $cost = ($prompt / 1000000 * 30.00) + ($completion / 1000000 * 60.00);
            }

            $stat['estimated_cost'] = $cost;
        }

        $this->render('ai/usage.html', [
            'title' => 'Consommation IA',
            'stats' => $stats,
            'recent' => $recent
        ]);
    }

    /**
     * API Endpoint to log usage from client-side (e.g. LanguageTool)
     * For future use if we want to track front-end calls.
     */
    public function logUsage()
    {
        $json = json_decode($this->f3->get('BODY'), true);
        if (!$json) {
            $this->f3->error(400);
            return;
        }

        $model = $json['model'] ?? 'unknown';
        $promptTokens = (int) ($json['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($json['completion_tokens'] ?? 0);
        $feature = $json['feature'] ?? 'unknown';

        $this->logAiUsage($model, $promptTokens, $completionTokens, $feature);
        echo json_encode(['status' => 'ok']);
    }

    /**
     * Generate text via OpenAI.
     */
    public function generate()
    {
        $json = json_decode($this->f3->get('BODY'), true);
        $prompt = $json['prompt'] ?? '';
        $task = $json['task'] ?? 'continue'; // continue, rephrase
        $context = $json['context'] ?? '';
        $contextId = $json['contextId'] ?? null;
        $contextType = $json['contextType'] ?? null;

        if (!$prompt && !$context && !$contextId) {
            $this->f3->error(400, 'Texte requis');
            return;
        }

        // --- Context Handling ---
        $docContext = [];
        $fullContextText = "";

        if ($contextId && $contextType) {
            $db = $this->f3->get('DB');
            // Reuse the existing DB connection from F3
            // Assuming Controller has access to DB, usually via $this->db if set in parent, or we grab it from F3
            // Checking parent Controller: usually sets $this->db. If not, $f3->get('DB') works if key exists.
            // Let's assume $this->db is available as it extends Controller. 
            // If not, use new \DB\SQL(...) or get from registry.
            // Based on previous files, typically models take $this->db.

            // Wait, standard Controller typically doesn't auto-set public $db unless defined.
            // Let's check if $this->db exists or use global.
            // Safer to use $this->f3->get('DB') or if models are autoloaded, just new Chapter().
            // Models in F3 usually require DB in constructor -> new Chapter($db).

            $db = $this->f3->get('DB');

            if ($contextType === 'chapter') {
                $chapter = new \Chapter($db);
                $chapter->load(['id=?', $contextId]);
                if (!$chapter->dry()) {
                    // Get Project & Act info
                    $projectTitle = $db->exec('SELECT title FROM projects WHERE id=?', [$chapter->project_id])[0]['title'] ?? '';
                    $actTitle = '';
                    if ($chapter->act_id) {
                        $actTitle = $db->exec('SELECT title FROM acts WHERE id=?', [$chapter->act_id])[0]['title'] ?? '';
                    }

                    $docContext = [
                        'type' => 'chapter',
                        'id' => $chapter->id,
                        'project' => $projectTitle,
                        'act' => $actTitle,
                        'title' => $chapter->title,
                        'content' => $chapter->content // Full content
                    ];
                }
            } elseif ($contextType === 'section') {
                $section = new \Section($db);
                $section->load(['id=?', $contextId]);
                if (!$section->dry()) {
                    $projectTitle = $db->exec('SELECT title FROM projects WHERE id=?', [$section->project_id])[0]['title'] ?? '';
                    $docContext = [
                        'type' => 'section',
                        'id' => $section->id,
                        'project' => $projectTitle,
                        'title' => $section->title,
                        'section_type' => $section->type,
                        'content' => $section->content
                    ];
                }
            }
        }

        // --- FULL MANUSCRIPT EXPORT INJECTION ---
        // Removed due to OpenAI Token Limits (User Request)
        $fullManuscriptJson = "";

        // Export JSON
        if (!empty($docContext)) {
            $jsonDir = 'data'; // Relative to entry point or absolute? 'src/data' requested.
            // F3 "data" dir usually at root or app/data. User said "src/data".
            // Let's use absolute path relative to project root.
            $fsDataDir = 'data'; // Assuming standard F3 structure where index.php in www/ or root
            // Actually user root is p:\Projets\Ecrivain\src
            // We are in src/app/modules/ai/controllers.
            // Best to use 'data/context.json' if mapped, or 'app/../data'.
            // Let's try 'data/context.json' and if it fails, fix path.
            // Note: User explicitly asked for "src/data".

            $jsonPath = 'data/context.json';

            // Sanitize content for JSON (remove too much HTML if needed, but OpenAI handles it)
            // strip_tags might be safer for "Reformuler" to limit noise, but "Continuer" might need markup.
            // Let's keep it as is, but maybe limit length if huge? 
            // GPT-4o has 128k context, should be fine for a chapter.

            file_put_contents($jsonPath, json_encode($docContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Prepare prompt injection
            // We append the JSON structure (maybe simplified) or just refer to it?
            // "que tu ajoute ce ficher à la requete pour chat gpt".
            // Since we can't attach files easily in this flow, we embed it.

            $fullContextText = "\n\n[CONTEXTE DU DOCUMENT (JSON)]\n" . json_encode($docContext, JSON_UNESCAPED_UNICODE);
        } else {
            // Fallback to client-provided context snippet
            if ($context) {
                $fullContextText = "\n\n[CONTEXTE EXTRAIT]\n" . $context;
            }
        }

        // DEBUG LOGGING
        $logFile = 'data/ai_debug.log';
        $log = function ($msg) use ($logFile) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
        };
        $log("generate() called. Task: $task");

        // Load prompts
        $defaults = $this->getDefaultPrompts();
        $prompts = $defaults;

        $configFile = $this->getUserConfigFile();
        $log("Config file path: " . ($configFile ?: 'NULL'));

        $userConfig = [];

        if ($configFile && file_exists($configFile)) {
            $log("Config file exists. Loading...");
            $userConfig = json_decode(file_get_contents($configFile), true);
            if (is_array($userConfig)) {
                $prompts = array_merge($defaults, $userConfig);
                $log("Config loaded successfully.");
            } else {
                $log("Error decoding JSON config.");
            }
        } else {
            $log("Config file not found. Checking legacy...");
            // Fallback to global
            $file = 'app/ai_prompts.json';
            if (file_exists($file)) {
                $saved = json_decode(file_get_contents($file), true);
                if (is_array($saved)) {
                    $prompts = array_merge($defaults, $saved);
                }
            }
        }

        $provider = $userConfig['provider'] ?? 'openai';
        $apiKey = $userConfig['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        $log("Provider: $provider | Model: $model | API Key present: " . ($apiKey ? 'YES' : 'NO'));

        // Instantiate generic AI Service
        $service = new AiService($provider, $apiKey, $model);

        $system = $json['system_prompt'] ?? $prompts['system'];
        // Append context to system prompt or user prompt?
        // System is better for "You are an assistant with access to this document..."

        $system .= " Une copie du document en cours est fournie au format JSON ci-dessous. Utilise ce contexte pour respecter le ton, les noms et le style.\n" . $fullContextText;

        $userPrompt = "";

        if ($task === 'continue') {
            $system .= " " . $prompts['continue'];
            $userPrompt = $prompt ?: "Continue l'histoire."; // Prompt might be empty if just "Continue" button
        } elseif ($task === 'rephrase') {
            $system .= " " . $prompts['rephrase'];
            $userPrompt = $prompt;
        } elseif ($task === 'custom') {
            // For custom task, we just use the user provided prompt as is.
            $userPrompt = $prompt;
        }

        $result = $service->generate($system, $userPrompt);

        if (!$result['success']) {
            $log("Generation failed: " . ($result['error'] ?? 'Unknown error'));
        } else {
            $log("Generation success. Tokens: " . strlen($result['text']));
        }

        if ($result['success']) {
            $text = $result['text'];
            // Log usage (approx)
            // 4 chars = 1 token. Input + Output.
            // Input includes the huge context now!
            $promptTokens = ceil((strlen($system) + strlen($userPrompt)) / 4);
            $completionTokens = ceil(strlen($text) / 4);

            $this->logAiUsage($model, $promptTokens, $completionTokens, $task);

            echo json_encode([
                'text' => $text,
                'debug' => [
                    'provider' => $provider,
                    'system' => $system,
                    'user' => $userPrompt,
                    'model' => $model,
                    'context_id' => $contextId,
                    'context_type' => $contextType,
                    'doc_context_found' => !empty($docContext),
                    'doc_context_json' => $docContext
                ]
            ]);
        } else {
            // Return 200 with error field to be handled gracefully by frontend
            echo json_encode(['error' => $result['error']]);
        }
    }

    /**
     * Generate a summary for a chapter based on its sub-chapters.
     */
    public function summarizeChapter()
    {
        $json = json_decode($this->f3->get('BODY'), true);
        $chapterId = $json['chapter_id'] ?? null;

        if (!$chapterId) {
            $this->f3->error(400, 'ID de chapitre requis');
            return;
        }

        $db = $this->f3->get('DB');
        $chapter = new \Chapter($db);
        $chapter->load(['id=?', $chapterId]);

        if ($chapter->dry()) {
            $this->f3->error(404, 'Chapitre introuvable');
            return;
        }

        // Lazy Migration: Ensure resume column exists (rename description if needed)
        try {
            // Try to rename 'description' to 'resume' if it exists
            $db->exec("ALTER TABLE chapters CHANGE description resume TEXT");
        } catch (\Exception $e) {
            // If rename failed (likely column description doesn't exist), ensure 'resume' exists
            try {
                $db->exec("ALTER TABLE chapters ADD COLUMN resume TEXT");
            } catch (\Exception $e2) { /* Column likely exists */
            }
        }

        // Load sub-chapters
        $subChapters = $chapter->find(['parent_id=?', $chapterId], ['order' => 'order_index ASC, id ASC']);

        if (!$subChapters) {
            // If no sub-chapters, maybe summarize the chapter itself? 
            // Request said: "envoyer à l'ia tous les sous-chapitres". 
            // If none, let's error or just use own content? 
            // Let's assume strict sub-chapters based on request, but falling back to own content is safe.
            $content = $chapter->content;
        } else {
            $content = "";
            foreach ($subChapters as $sub) {
                $content .= "\n\n[Sous-chapitre: " . $sub->title . "]\n" . $sub->content;
            }
        }

        // Safety check on empty content
        if (trim(strip_tags($content)) === '') {
            echo json_encode(['success' => false, 'error' => 'Contenu vide.']);
            return;
        }

        // Prepare Prompt
        // Load User Config for API Key/Provider
        $configFile = $this->getUserConfigFile();
        $userConfig = [];
        if ($configFile && file_exists($configFile)) {
            $userConfig = json_decode(file_get_contents($configFile), true);
        }

        $provider = $userConfig['provider'] ?? 'openai';
        $apiKey = $userConfig['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        $service = new AiService($provider, $apiKey, $model);

        $systemPrompt = $json['system_prompt'] ?? ($userConfig['system'] ?? "Tu es un assistant d'écriture expert.");
        $taskPrompt = $userConfig['summarize_chapter'] ?? ($prompts['summarize_chapter'] ?? "Fais un résumé d'une dizaine de lignes du contenu suivant qui est une agrégation de sous-chapitres. Le résumé doit être captivant et bien écrit.");

        $fullPrompt = $taskPrompt . "\n\n[CONTENU]\n" . $content;

        // Using generate(system, user)
        $result = $service->generate($systemPrompt, $fullPrompt);

        if ($result['success']) {
            $summary = $result['text'];

            // Save to Chapter Resume
            $chapter->resume = $summary;
            $chapter->save();

            // Log usage
            $promptTokens = ceil((strlen($systemPrompt) + strlen($fullPrompt)) / 4);
            $completionTokens = ceil(strlen($summary) / 4);
            $this->logAiUsage($model, $promptTokens, $completionTokens, 'summarize_chapter');

            echo json_encode(['success' => true, 'summary' => $summary]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }

    /**
     * Generate a summary for an act based on its chapters.
     */
    public function summarizeAct()
    {
        $json = json_decode($this->f3->get('BODY'), true);
        $actId = $json['act_id'] ?? null;

        if (!$actId) {
            $this->f3->error(400, 'ID d\'acte requis');
            return;
        }

        $db = $this->f3->get('DB');
        $act = new \Act($db);
        $act->load(['id=?', $actId]);

        if ($act->dry()) {
            $this->f3->error(404, 'Acte introuvable');
            return;
        }

        // Load chapters
        $chapterModel = new \Chapter($db);
        $chapters = $chapterModel->find(['act_id=?', $actId], ['order' => 'order_index ASC, id ASC']);

        if (!$chapters) {
            echo json_encode(['success' => false, 'error' => 'Aucun chapitre dans cet acte.']);
            return;
        }

        $content = "";
        foreach ($chapters as $ch) {
            // Use existing resume if available, otherwise snippet of content
            $chSummary = !empty($ch->resume) ? $ch->resume : substr(strip_tags($ch->content), 0, 500) . "...";
            $content .= "\n\n[Chapitre: " . $ch->title . "]\n" . $chSummary;
        }

        // Prepare Prompt
        $configFile = $this->getUserConfigFile();
        $userConfig = [];
        if ($configFile && file_exists($configFile)) {
            $userConfig = json_decode(file_get_contents($configFile), true);
        }

        $defaults = $this->getDefaultPrompts();
        $prompts = array_merge($defaults, $userConfig);

        $provider = $userConfig['provider'] ?? 'openai';
        $apiKey = $userConfig['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        $service = new AiService($provider, $apiKey, $model);

        $systemPrompt = $json['system_prompt'] ?? ($userConfig['system'] ?? $defaults['system']);
        $taskPrompt = $prompts['summarize_act'];

        $fullPrompt = $taskPrompt . "\n\n[RÉSUMÉS DES CHAPITRES]\n" . $content;

        $result = $service->generate($systemPrompt, $fullPrompt);

        if ($result['success']) {
            $summary = $result['text'];

            // Save to Act Resume
            $act->resume = $summary;
            $act->save();

            // Log usage
            $promptTokens = ceil((strlen($systemPrompt) + strlen($fullPrompt)) / 4);
            $completionTokens = ceil(strlen($summary) / 4);
            $this->logAiUsage($model, $promptTokens, $completionTokens, 'summarize_act');

            echo json_encode(['success' => true, 'summary' => $summary]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }

    /**
     * Get AI synonyms.
     */
    public function synonyms()
    {
        $word = $this->f3->get('PARAMS.word');

        // Load User Config
        $configFile = $this->getUserConfigFile();
        $userConfig = [];
        if ($configFile && file_exists($configFile)) {
            $userConfig = json_decode(file_get_contents($configFile), true);
        }

        $provider = $userConfig['provider'] ?? 'openai';
        $apiKey = $userConfig['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        $service = new AiService($provider, $apiKey, $model);
        $results = [];

        $results = $service->getSynonyms($word);

        if (empty($results)) {
            // Fallback to static if AI fails or no key
            if (class_exists('Synonyms')) {
                $results = \Synonyms::get($word);
            }
        } else {
            // Log usage
            $this->logAiUsage($model, 20, 20, 'synonyms');
        }

        echo json_encode($results);
    }

    /**
     * Configuration page for AI prompts.
     */
    public function config()
    {
        $defaults = $this->getDefaultPrompts();
        $current = $defaults;

        $file = $this->getUserConfigFile();
        if ($file && file_exists($file)) {
            $saved = json_decode(file_get_contents($file), true);
            if (is_array($saved)) {
                $current = array_merge($defaults, $saved);
            }
        } else {
            // Fallback to legacy global file if user file doesn't exist
            $legacyFile = 'app/ai_prompts.json';
            if (file_exists($legacyFile)) {
                $saved = json_decode(file_get_contents($legacyFile), true);
                if (is_array($saved)) {
                    // Only merge prompts, not provider settings potentially
                    $current = array_merge($current, array_intersect_key($saved, ['system' => 1, 'continue' => 1, 'rephrase' => 1, 'summarize_chapter' => 1, 'summarize_act' => 1]));
                }
            }
        }
        if (!isset($current['custom_prompts']) || !is_array($current['custom_prompts'])) {
            $current['custom_prompts'] = [];
        }

        $this->render('ai/config.html', [
            'title' => 'Configuration IA',
            'config' => $current,
            'models' => $this->getModels(),
            'success' => $this->f3->get('GET.success')
        ]);
    }

    /**
     * Save AI configuration.
     */
    public function saveConfig()
    {
        $file = $this->getUserConfigFile();
        if (!$file) {
            $this->f3->error(500, 'Impossible de définir le fichier de configuration utilisateur.');
            return;
        }

        $labels = $this->f3->get('POST.custom_prompt_label');
        $values = $this->f3->get('POST.custom_prompt_value');
        $customPrompts = [];
        if (is_array($labels) && is_array($values)) {
            $count = min(count($labels), count($values));
            for ($i = 0; $i < $count; $i++) {
                $label = trim((string) $labels[$i]);
                $prompt = trim((string) $values[$i]);
                if ($label !== '' && $prompt !== '') {
                    $customPrompts[] = [
                        'label' => $label,
                        'prompt' => $prompt
                    ];
                }
            }
        }

        $data = [
            'provider' => $this->f3->get('POST.provider'),
            'api_key' => $this->f3->get('POST.api_key'),
            'model' => $this->f3->get('POST.model'),
            'system' => $this->f3->get('POST.system'),
            'continue' => $this->f3->get('POST.continue'),
            'rephrase' => $this->f3->get('POST.rephrase'),
            'summarize_chapter' => $this->f3->get('POST.summarize_chapter'),
            'summarize_act' => $this->f3->get('POST.summarize_act'),
            'custom_prompts' => $customPrompts
        ];

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->f3->reroute('/ai/config?success=1');
    }

    private function getDefaultPrompts()
    {
        return [
            'provider' => 'openai',
            'api_key' => '',
            'model' => 'gpt-4o',
            'system' => "Tu es un assistant d'écriture créative expert.",
            'continue' => "Continue le texte suivant de manière cohérente, dans le même style. N'ajoute pas de guillemets autour de tout le texte généré sauf si nécessaire.",
            'rephrase' => "Reformule le texte suivant pour améliorer le style, la clarté et l'élégance, sans changer le sens.",
            'summarize_chapter' => "Fais un résumé d'une dizaine de lignes du contenu suivant qui est une agrégation de sous-chapitres. Le résumé doit être captivant et bien écrit.",
            'summarize_act' => "Fais un résumé d'une dizaine de lignes pour cet Acte, basé sur les résumés de ses chapitres ci-dessous. Le résumé doit donner une bonne vue d'ensemble de l'arc narratif de l'acte.",
            'custom_prompts' => []
        ];
    }

    private function getModels()
    {
        return [
            'openai' => [
                'gpt-4-0613',
                'gpt-4',
                'gpt-3.5-turbo',
                'gpt-5.2-codex',
                'gpt-4o-mini-tts-2025-12-15',
                'gpt-realtime-mini-2025-12-15',
                'gpt-audio-mini-2025-12-15',
                'chatgpt-image-latest',
                'davinci-002',
                'babbage-002',
                'gpt-3.5-turbo-instruct',
                'gpt-3.5-turbo-instruct-0914',
                'dall-e-3',
                'dall-e-2',
                'gpt-4-1106-preview',
                'gpt-3.5-turbo-1106',
                'tts-1-hd',
                'tts-1-1106',
                'tts-1-hd-1106',
                'text-embedding-3-small',
                'text-embedding-3-large',
                'gpt-4-0125-preview',
                'gpt-4-turbo-preview',
                'gpt-3.5-turbo-0125',
                'gpt-4-turbo',
                'gpt-4-turbo-2024-04-09',
                'gpt-4o',
                'gpt-4o-2024-05-13',
                'gpt-4o-mini-2024-07-18',
                'gpt-4o-mini',
                'gpt-4o-2024-08-06',
                'chatgpt-4o-latest',
                'gpt-4o-audio-preview',
                'gpt-4o-realtime-preview',
                'omni-moderation-latest',
                'omni-moderation-2024-09-26',
                'gpt-4o-realtime-preview-2024-12-17',
                'gpt-4o-audio-preview-2024-12-17',
                'gpt-4o-mini-realtime-preview-2024-12-17',
                'gpt-4o-mini-audio-preview-2024-12-17',
                'o1-2024-12-17',
                'o1',
                'gpt-4o-mini-realtime-preview',
                'gpt-4o-mini-audio-preview',
                'o3-mini',
                'o3-mini-2025-01-31',
                'gpt-4o-2024-11-20',
                'gpt-4o-search-preview-2025-03-11',
                'gpt-4o-search-preview',
                'gpt-4o-mini-search-preview-2025-03-11',
                'gpt-4o-mini-search-preview',
                'gpt-4o-transcribe',
                'gpt-4o-mini-transcribe',
                'o1-pro-2025-03-19',
                'o1-pro',
                'gpt-4o-mini-tts',
                'o3-2025-04-16',
                'o4-mini-2025-04-16',
                'o3',
                'o4-mini',
                'gpt-4.1-2025-04-14',
                'gpt-4.1',
                'gpt-4.1-mini-2025-04-14',
                'gpt-4.1-mini',
                'gpt-4.1-nano-2025-04-14',
                'gpt-4.1-nano',
                'gpt-image-1',
                'gpt-4o-realtime-preview-2025-06-03',
                'gpt-4o-audio-preview-2025-06-03',
                'gpt-4o-transcribe-diarize',
                'gpt-5-chat-latest',
                'gpt-5-2025-08-07',
                'gpt-5',
                'gpt-5-mini-2025-08-07',
                'gpt-5-mini',
                'gpt-5-nano-2025-08-07',
                'gpt-5-nano',
                'gpt-audio-2025-08-28',
                'gpt-realtime',
                'gpt-realtime-2025-08-28',
                'gpt-audio',
                'gpt-5-codex',
                'gpt-image-1-mini',
                'gpt-5-pro-2025-10-06',
                'gpt-5-pro',
                'gpt-audio-mini',
                'gpt-audio-mini-2025-10-06',
                'gpt-5-search-api',
                'gpt-realtime-mini',
                'gpt-realtime-mini-2025-10-06',
                'sora-2',
                'sora-2-pro',
                'gpt-5-search-api-2025-10-14',
                'gpt-5.1-chat-latest',
                'gpt-5.1-2025-11-13',
                'gpt-5.1',
                'gpt-5.1-codex',
                'gpt-5.1-codex-mini',
                'gpt-5.1-codex-max',
                'gpt-image-1.5',
                'gpt-5.2-2025-12-11',
                'gpt-5.2',
                'gpt-5.2-pro-2025-12-11',
                'gpt-5.2-pro',
                'gpt-5.2-chat-latest',
                'gpt-4o-mini-transcribe-2025-12-15',
                'gpt-4o-mini-transcribe-2025-03-20',
                'gpt-4o-mini-tts-2025-03-20',
                'gpt-3.5-turbo-16k',
                'tts-1',
                'whisper-1',
                'text-embedding-ada-002'
            ],
            'gemini' => ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-pro'],
            'mistral' => ['mistral-large-latest', 'mistral-medium', 'mistral-small'],
            'anthropic' => ['claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307']
        ];
    }

    private function getUserConfigFile()
    {
        $user = $this->currentUser();
        if (!$user || empty($user['email']))
            return null;

        $dir = 'data/' . $user['email'];
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir . '/ai_config.json';
    }
}
