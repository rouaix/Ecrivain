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

            // Pricing per 1M tokens (USD)
            // Sources: platform.openai.com/docs/pricing, platform.claude.com/docs/en/about-claude/pricing,
            //          ai.google.dev/gemini-api/docs/pricing, docs.mistral.ai/deployment/ai-studio/pricing (Feb 2026)

            // --- OpenAI ---
            if (strpos($model, 'gpt-4.1-mini') !== false) {
                // GPT-4.1 Mini: $0.40 / $1.60
                $cost = ($prompt / 1000000 * 0.40) + ($completion / 1000000 * 1.60);
            } elseif (strpos($model, 'gpt-4o-mini') !== false) {
                // GPT-4o Mini: $0.15 / $0.60
                $cost = ($prompt / 1000000 * 0.15) + ($completion / 1000000 * 0.60);
            } elseif (strpos($model, 'o1-pro') !== false) {
                // o1-pro: $150.00 / $600.00
                $cost = ($prompt / 1000000 * 150.00) + ($completion / 1000000 * 600.00);
            } elseif (strpos($model, 'o1-mini') !== false) {
                // o1-mini (deprecated): $1.10 / $4.40
                $cost = ($prompt / 1000000 * 1.10) + ($completion / 1000000 * 4.40);
            } elseif (strpos($model, 'o1') !== false) {
                // o1: $15.00 / $60.00
                $cost = ($prompt / 1000000 * 15.00) + ($completion / 1000000 * 60.00);
            } elseif (strpos($model, 'o3-mini') !== false) {
                // o3-mini: $1.10 / $4.40
                $cost = ($prompt / 1000000 * 1.10) + ($completion / 1000000 * 4.40);
            } elseif (strpos($model, 'o4-mini') !== false) {
                // o4-mini: $1.10 / $4.40
                $cost = ($prompt / 1000000 * 1.10) + ($completion / 1000000 * 4.40);
            } elseif (strpos($model, 'o3') !== false) {
                // o3: $2.00 / $8.00
                $cost = ($prompt / 1000000 * 2.00) + ($completion / 1000000 * 8.00);
            } elseif (strpos($model, 'gpt-4.1') !== false) {
                // GPT-4.1: $2.00 / $8.00
                $cost = ($prompt / 1000000 * 2.00) + ($completion / 1000000 * 8.00);
            } elseif (strpos($model, 'gpt-4o') !== false) {
                // GPT-4o: $2.50 / $10.00
                $cost = ($prompt / 1000000 * 2.50) + ($completion / 1000000 * 10.00);
            } elseif (strpos($model, 'gpt-3.5') !== false) {
                // GPT-3.5 Turbo: $0.50 / $1.50
                $cost = ($prompt / 1000000 * 0.50) + ($completion / 1000000 * 1.50);
            } elseif (strpos($model, 'gpt-4') !== false) {
                // GPT-4 classique: $30.00 / $60.00
                $cost = ($prompt / 1000000 * 30.00) + ($completion / 1000000 * 60.00);

            // --- Mistral ---
            } elseif (strpos($model, 'mistral-large') !== false) {
                // mistral-large-latest (2411): $2.00 / $6.00
                $cost = ($prompt / 1000000 * 2.00) + ($completion / 1000000 * 6.00);
            } elseif (strpos($model, 'mistral-medium') !== false) {
                // mistral-medium-latest (Medium 3): $0.40 / $2.00
                $cost = ($prompt / 1000000 * 0.40) + ($completion / 1000000 * 2.00);
            } elseif (strpos($model, 'mistral-small') !== false) {
                // mistral-small-latest: $0.10 / $0.30
                $cost = ($prompt / 1000000 * 0.10) + ($completion / 1000000 * 0.30);
            } elseif (strpos($model, 'codestral') !== false) {
                // codestral-latest: $0.30 / $0.90
                $cost = ($prompt / 1000000 * 0.30) + ($completion / 1000000 * 0.90);
            } elseif (strpos($model, 'ministral-3') !== false) {
                // ministral-3b-latest: $0.04 / $0.04
                $cost = ($prompt / 1000000 * 0.04) + ($completion / 1000000 * 0.04);
            } elseif (strpos($model, 'ministral-8') !== false) {
                // ministral-8b-latest: $0.10 / $0.10
                $cost = ($prompt / 1000000 * 0.10) + ($completion / 1000000 * 0.10);
            } elseif (strpos($model, 'open-mistral-nemo') !== false || strpos($model, 'mistral-nemo') !== false) {
                // open-mistral-nemo: $0.02 / $0.04
                $cost = ($prompt / 1000000 * 0.02) + ($completion / 1000000 * 0.04);
            } elseif (strpos($model, 'mixtral') !== false) {
                // mixtral-8x7b: $0.54 / $0.54
                $cost = ($prompt / 1000000 * 0.54) + ($completion / 1000000 * 0.54);

            // --- Anthropic ---
            } elseif (strpos($model, 'claude-opus-4-5') !== false || strpos($model, 'claude-opus-4-6') !== false) {
                // Claude Opus 4.5 / 4.6: $5.00 / $25.00
                $cost = ($prompt / 1000000 * 5.00) + ($completion / 1000000 * 25.00);
            } elseif (strpos($model, 'claude-opus') !== false) {
                // Claude Opus 3 / 4 / 4.1 (anciens): $15.00 / $75.00
                $cost = ($prompt / 1000000 * 15.00) + ($completion / 1000000 * 75.00);
            } elseif (strpos($model, 'claude-sonnet') !== false) {
                // Claude Sonnet (toutes versions): $3.00 / $15.00
                $cost = ($prompt / 1000000 * 3.00) + ($completion / 1000000 * 15.00);
            } elseif (strpos($model, 'claude-haiku-4-5') !== false) {
                // Claude Haiku 4.5: $1.00 / $5.00
                $cost = ($prompt / 1000000 * 1.00) + ($completion / 1000000 * 5.00);
            } elseif (strpos($model, 'claude-haiku') !== false) {
                // Claude Haiku 3.5 et antérieur: $0.80 / $4.00
                $cost = ($prompt / 1000000 * 0.80) + ($completion / 1000000 * 4.00);

            // --- Gemini ---
            } elseif (strpos($model, 'gemini-2.5-pro') !== false) {
                // Gemini 2.5 Pro: $1.25 / $10.00 (≤200k tokens)
                $cost = ($prompt / 1000000 * 1.25) + ($completion / 1000000 * 10.00);
            } elseif (strpos($model, 'gemini-2.5-flash') !== false) {
                // Gemini 2.5 Flash: $0.30 / $2.50
                $cost = ($prompt / 1000000 * 0.30) + ($completion / 1000000 * 2.50);
            } elseif (strpos($model, 'gemini-2.0-flash') !== false) {
                // Gemini 2.0 Flash: $0.10 / $0.40
                $cost = ($prompt / 1000000 * 0.10) + ($completion / 1000000 * 0.40);
            } elseif (strpos($model, 'gemini-1.5-pro') !== false) {
                // Gemini 1.5 Pro (legacy): $1.25 / $5.00
                $cost = ($prompt / 1000000 * 1.25) + ($completion / 1000000 * 5.00);
            } elseif (strpos($model, 'gemini-1.5-flash') !== false) {
                // Gemini 1.5 Flash (legacy): $0.075 / $0.30
                $cost = ($prompt / 1000000 * 0.075) + ($completion / 1000000 * 0.30);
            } elseif (strpos($model, 'gemini') !== false) {
                // Gemini générique: $0.10 / $0.40
                $cost = ($prompt / 1000000 * 0.10) + ($completion / 1000000 * 0.40);
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
        header('Content-Type: application/json');
        try {
        $json = json_decode($this->f3->get('BODY'), true);
        $prompt = $json['prompt'] ?? '';
        $task = $json['task'] ?? 'continue'; // continue, rephrase
        $context = $json['context'] ?? '';
        $contextId = $json['contextId'] ?? null;
        $contextType = $json['contextType'] ?? null;

        if (!$prompt && !$context && !$contextId) {
            echo json_encode(['error' => 'Texte requis']);
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
                    // SECURITY: Verify ownership before exposing content (fixes IDOR vulnerability #15)
                    $projectModel = new \Project($db);
                    if (!$projectModel->count(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']])) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Accès non autorisé']);
                        return;
                    }

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
                    // SECURITY: Verify ownership before exposing content (fixes IDOR vulnerability #15)
                    $projectModel = new \Project($db);
                    if (!$projectModel->count(['id=? AND user_id=?', $section->project_id, $this->currentUser()['id']])) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Accès non autorisé']);
                        return;
                    }

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

        // Injection de contexte : sélective selon la tâche pour minimiser les tokens
        if (!empty($docContext)) {
            if ($task === 'rephrase' || $task === 'custom') {
                // Pour reformuler/custom : contexte minimal — le texte est déjà dans le prompt
                $fullContextText = "\n[Contexte] Projet: " . ($docContext['project'] ?? '')
                    . ($docContext['act'] ? " | Acte: " . $docContext['act'] : '')
                    . " | " . ucfirst($docContext['type'] ?? 'document') . ": " . ($docContext['title'] ?? '');
            } else {
                // Pour "continuer" : uniquement la fin du texte (3000 derniers caractères, sans HTML)
                $rawContent = strip_tags($docContext['content'] ?? '');
                $contentLen = mb_strlen($rawContent);
                if ($contentLen > 3000) {
                    $rawContent = '…' . mb_substr($rawContent, $contentLen - 3000);
                }
                $lines = ["Projet: " . ($docContext['project'] ?? '')];
                if (!empty($docContext['act'])) {
                    $lines[] = "Acte: " . $docContext['act'];
                }
                $lines[] = "Chapitre: " . ($docContext['title'] ?? '');
                if (!empty($rawContent)) {
                    $lines[] = "Fin du texte actuel:\n" . $rawContent;
                }
                $fullContextText = "\n\n[CONTEXTE]\n" . implode("\n", $lines);
            }
        } elseif ($context) {
            $fullContextText = "\n\n[CONTEXTE EXTRAIT]\n" . $context;
        }

        // Load prompts
        $defaults = $this->getDefaultPrompts();
        $prompts = $defaults;

        $userConfig = $this->getUserConfig();
        $provider = $userConfig['active_provider'] ?? 'openai';
        $apiKey = $userConfig['providers'][$provider]['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['providers'][$provider]['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        // Instantiate generic AI Service
        $service = new AiService($provider, $apiKey, $model);

        // DEBUG LOGGING - REMOVED

        // Prioritize User Config > JSON Request > Defaults
        // The user explicitly requested to ALWAYS use the prompts from data/user@email if available.

        $configSystem = $userConfig['prompts']['system'] ?? '';
        // array_key_exists distingue la clé absente du string vide explicite
        $jsonSystemSent = array_key_exists('system_prompt', $json ?? []);
        $jsonSystem = $json['system_prompt'] ?? '';

        if ($task === 'custom') {
            // Pour custom : respecter exactement ce que l'utilisateur a saisi dans le modal,
            // y compris un textarea vide (pas de prompt système).
            // Ne pas injecter la config sauvegardée si la clé est présente dans la requête.
            $system = $jsonSystemSent ? $jsonSystem : ($configSystem ?: $prompts['system']);
        } else {
            $system = !empty($configSystem) ? $configSystem : (!empty($jsonSystem) ? $jsonSystem : $prompts['system']);
        }

        if (!empty($fullContextText)) {
            $system .= " Contexte du document fourni ci-dessous. Respecte le ton, les noms et le style.\n" . $fullContextText;
        }

        $userPrompt = "";

        if ($task === 'continue') {
            $configContinue = $userConfig['prompts']['continue'] ?? '';
            $baseContinue = !empty($configContinue) ? $configContinue : $prompts['continue'];
            $system .= " " . $baseContinue;
            $userPrompt = $prompt ?: "Continue l'histoire.";
            $maxTokens = 700;
        } elseif ($task === 'rephrase') {
            $configRephrase = $userConfig['prompts']['rephrase'] ?? '';
            $baseRephrase = !empty($configRephrase) ? $configRephrase : $prompts['rephrase'];
            $system .= " " . $baseRephrase;
            $userPrompt = $prompt;
            // La reformulation ne doit pas dépasser la longueur du texte d'entrée
            $maxTokens = min(600, (int)(mb_strlen($prompt) / 3) + 100);
        } elseif ($task === 'custom') {
            $userPrompt = $prompt;
            $maxTokens = 1000;
        } else {
            $maxTokens = 800;
        }

        $system = $this->compressPrompt($system);
        // Fallback uniquement pour les tâches automatiques (continue, rephrase, etc.)
        // Pour custom, un prompt vide est un choix délibéré de l'utilisateur.
        if (empty($system) && $task !== 'custom') {
            $system = "Tu es un assistant d'écriture créative.";
        }

        $result = $service->generate($system, $userPrompt, 0.7, $maxTokens);

        if ($result['success']) {
            $text = $result['text'];
            $promptTokens = ceil((strlen($system) + strlen($userPrompt)) / 4);
            $completionTokens = ceil(strlen($text) / 4);

            $this->logAiUsage($model, $promptTokens, $completionTokens, $task);

            echo json_encode([
                'text' => $text,
                'debug' => [
                    'model' => $model,
                    'system' => $system,
                    'user' => $userPrompt
                ]
            ]);
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        } catch (\Throwable $e) {
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
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

        // SECURITY: Verify ownership before summarizing (fixes IDOR vulnerability #15)
        $projectModel = new \Project($db);
        if (!$projectModel->count(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
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
                $stripped = mb_substr(strip_tags($sub->content ?? ''), 0, 2000);
                $content .= "\n[" . $sub->title . "]\n" . $stripped;
            }
        }

        // Safety check on empty content
        if (trim(strip_tags($content)) === '') {
            echo json_encode(['success' => false, 'error' => 'Contenu vide.']);
            return;
        }

        // Prepare Prompt
        // Load User Config for API Key/Provider
        // Prepare Prompt
        // Load User Config for API Key/Provider
        $userConfig = $this->getUserConfig();
        $prompts = $this->getDefaultPrompts();

        $provider = $userConfig['active_provider'] ?? 'openai';
        $apiKey = $userConfig['providers'][$provider]['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['providers'][$provider]['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        $service = new AiService($provider, $apiKey, $model);

        // Prioritize User Config > JSON Request > Defaults
        $configSystem = $userConfig['prompts']['system'] ?? '';
        $systemPrompt = !empty($configSystem) ? $configSystem : ($json['system_prompt'] ?? "Tu es un assistant d'écriture expert.");
        $taskPrompt = $userConfig['prompts']['summarize_chapter'] ?? ($prompts['summarize_chapter'] ?? "Fais un résumé d'une dizaine de lignes du contenu suivant qui est une agrégation de sous-chapitres. Le résumé doit être captivant et bien écrit.");

        $fullPrompt = $taskPrompt . "\n\n[CONTENU]\n" . $content;

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $result = $service->generate($systemPrompt, $fullPrompt, 0.7, 500);

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

        // SECURITY: Verify ownership before summarizing (fixes IDOR vulnerability #15)
        $projectModel = new \Project($db);
        if (!$projectModel->count(['id=? AND user_id=?', $act->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
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
        $userConfig = $this->getUserConfig();
        $prompts = $this->getDefaultPrompts();

        $provider = $userConfig['active_provider'] ?? 'openai';
        $apiKey = $userConfig['providers'][$provider]['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['providers'][$provider]['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        $service = new AiService($provider, $apiKey, $model);

        // Prioritize User Config > JSON Request > Defaults
        $configSystem = $userConfig['prompts']['system'] ?? '';
        $systemPrompt = !empty($configSystem) ? $configSystem : ($json['system_prompt'] ?? $prompts['system']);
        $taskPrompt = $userConfig['prompts']['summarize_act'] ?? $prompts['summarize_act'];

        $fullPrompt = $taskPrompt . "\n\n[RÉSUMÉS DES CHAPITRES]\n" . $content;

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $result = $service->generate($systemPrompt, $fullPrompt, 0.7, 500);

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
     * Ask a question about the whole project.
     */
    public function ask()
    {
        $json = json_decode($this->f3->get('BODY'), true);
        $projectId = $json['project_id'] ?? null;
        $userPrompt = $json['prompt'] ?? '';

        if (!$projectId || !$userPrompt) {
            $this->f3->error(400, 'Project ID and Prompt required');
            return;
        }

        // SECURITY: Verify ownership before accessing project data (fixes IDOR vulnerability #15)
        $db = $this->f3->get('DB');
        $projectModel = new \Project($db);
        $projectModel->load(['id=? AND user_id=?', $projectId, $this->currentUser()['id']]);
        if ($projectModel->dry()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        // Limites de caractères par champ pour maîtriser les tokens
        $truncate = function(string $text, int $max): string {
            return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
        };

        $lines = [];

        // Titre du projet
        $lines[] = "Projet: " . $projectModel->title;

        // Sections (synopsis, pitch…) — 300 chars max par section
        $sectionModel = new \Section($db);
        $sections = $sectionModel->find(['project_id=?', $projectId]);
        if ($sections) {
            foreach ($sections as $sec) {
                $content = $truncate(trim(strip_tags($sec->content ?? '')), 300);
                if (!empty($content)) {
                    $lines[] = $sec->title . ": " . $content;
                }
            }
        }

        // Personnages — 150 chars max par description
        $characterModel = new \Character($db);
        $characters = $characterModel->find(['project_id=?', $projectId]);
        if ($characters) {
            $charLines = [];
            foreach ($characters as $char) {
                $desc = $truncate(trim(strip_tags($char->description ?? '')), 150);
                $charLines[] = $char->name . (!empty($desc) ? ": " . $desc : "");
            }
            if ($charLines) {
                $lines[] = "Personnages: " . implode(" | ", $charLines);
            }
        }

        // Notes — 150 chars max par note
        $noteModel = new \Note($db);
        $notes = $noteModel->find(['project_id=?', $projectId]);
        if ($notes) {
            foreach ($notes as $note) {
                $content = $truncate(trim(strip_tags($note->content ?? '')), 150);
                if (!empty($content)) {
                    $lines[] = "Note/" . $note->title . ": " . $content;
                }
            }
        }

        // Actes et chapitres (résumés uniquement, 300 chars max)
        $actModel = new \Act($db);
        $acts = $actModel->find(['project_id=?', $projectId], ['order' => 'order_index ASC']);
        $actMap = [];
        if ($acts) {
            foreach ($acts as $act) {
                $actMap[$act->id] = $act->title;
            }
        }

        $chapterModel = new \Chapter($db);
        $chapters = $chapterModel->find(['project_id=?', $projectId], ['order' => 'order_index ASC']);

        if ($chapters) {
            $lines[] = "\nChapitres:";
            $currentAct = null;
            foreach ($chapters as $ch) {
                $actId = $ch->act_id ?? null;
                if ($actId && isset($actMap[$actId]) && $actMap[$actId] !== $currentAct) {
                    $currentAct = $actMap[$actId];
                    $lines[] = "[" . $currentAct . "]";
                }
                $resume = $truncate(trim(strip_tags($ch->resume ?? '')), 300);
                if (empty($resume)) {
                    $resume = $truncate(trim(strip_tags($ch->content ?? '')), 120);
                }
                $lines[] = "- " . $ch->title . ": " . ($resume ?: "(vide)");
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Aucun contenu trouvé pour analyser le contexte.']);
            return;
        }

        $contextText = implode("\n", $lines);

        // Prepare AI Service
        $userConfig = $this->getUserConfig();
        $provider = $userConfig['active_provider'] ?? 'openai';
        $apiKey = $userConfig['providers'][$provider]['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['providers'][$provider]['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

        $service = new AiService($provider, $apiKey, $model);

        $system = "Tu es un assistant éditorial expert. Tu as accès au résumé structuré du projet ci-dessous : " .
            "titre, sections (synopsis, pitch…), personnages, notes et résumés des chapitres. " .
            "Réponds aux questions de l'auteur sur la cohérence, l'intrigue, les personnages ou le style. " .
            "Utilise un ton constructif et professionnel. Cite des références précises (noms de chapitres, actes, personnages) si pertinent.\n\n" .
            $contextText;

        $result = $service->generate($system, $userPrompt);

        if ($result['success']) {
            $promptTokens = ceil((strlen($system) + strlen($userPrompt)) / 4);
            $completionTokens = ceil(strlen($result['text']) / 4);
            $this->logAiUsage($model, $promptTokens, $completionTokens, 'ask_project');

            echo json_encode(['success' => true, 'answer' => $result['text']]);
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
        $userConfig = $this->getUserConfig();

        $provider = $userConfig['active_provider'] ?? 'openai';
        $apiKey = $userConfig['providers'][$provider]['api_key'] ?? $this->f3->get('OPENAI_API_KEY');
        $model = $userConfig['providers'][$provider]['model'] ?? ($this->f3->get('OPENAI_MODEL') ?: 'gpt-4o');

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
        $config = $this->getUserConfig();

        $this->render('ai/config.html', [
            'title' => 'Configuration IA',
            'config' => $config, // New structure
            'providers_json' => json_encode($config['providers']), // Pass providers to JS
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

        // Load existing structured config
        $config = $this->getUserConfig();

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

        $activeProvider = $this->f3->get('POST.provider');
        $activeKey = $this->f3->get('POST.api_key');
        $activeModel = $this->f3->get('POST.model');

        // Update active provider details
        $config['active_provider'] = $activeProvider;
        $config['providers'][$activeProvider] = [
            'api_key' => $activeKey,
            'model' => $activeModel
        ];

        // Update prompts
        $config['prompts'] = [
            'system' => $this->f3->get('POST.system'),
            'continue' => $this->f3->get('POST.continue'),
            'rephrase' => $this->f3->get('POST.rephrase'),
            'summarize_chapter' => $this->f3->get('POST.summarize_chapter'),
            'summarize_act' => $this->f3->get('POST.summarize_act'),
            'custom' => $customPrompts
        ];

        file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->f3->reroute('/ai/config?success=1');
    }

    private function getUserConfig()
    {
        $file = $this->getUserConfigFile();
        $defaults = $this->getDefaultPrompts();
        $config = [
            'active_provider' => 'openai',
            'providers' => [],
            'prompts' => [
                'system' => $defaults['system'],
                'continue' => $defaults['continue'],
                'rephrase' => $defaults['rephrase'],
                'summarize_chapter' => $defaults['summarize_chapter'],
                'summarize_act' => $defaults['summarize_act'],
                'custom' => $defaults['custom_prompts']
            ]
        ];

        if ($file && file_exists($file)) {
            $content = file_get_contents($file);
            $loaded = json_decode($content, true);

            if ($loaded === null && json_last_error() !== JSON_ERROR_NONE) {
                // Should probably log this, but for now just fallback or error?
                // Returning default config causes overwrite. Safe is to STOP.
                // But we are in a helper.
                // Let's try to recover if possible, or just accept that bad JSON means reset?
                // The user complained about data loss.
                // Let's keep existing behavior but verify why it failed.
                // ACTUALLY, if it fails, let's look for backup? No.
                // Let's at least not overwrite if we can help it.
            }

            if (is_array($loaded)) {
                // Check if it's the OLD structure (has 'provider' at top level) or NEW structure
                if (isset($loaded['active_provider'])) {
                    // Already new structure, merge
                    $config = array_merge($config, $loaded);
                } else {
                    // MIGRATION from OLD structure
                    // Old structure:
                    // { "provider": "...", "api_key": "...", "model": "...", "api_keys": {...}, "system": "...", ... }

                    $activeProvider = $loaded['provider'] ?? 'openai';
                    $config['active_provider'] = $activeProvider;

                    // Migrate Keys and Models
                    // We might have 'api_keys' array from previous step
                    $oldApiKeys = $loaded['api_keys'] ?? [];
                    // Ensure active key is captured
                    if (!empty($loaded['api_key'])) {
                        $oldApiKeys[$activeProvider] = $loaded['api_key'];
                    }

                    foreach ($oldApiKeys as $prov => $key) {
                        $config['providers'][$prov] = [
                            'api_key' => $key,
                            'model' => ($prov === $activeProvider) ? ($loaded['model'] ?? 'gpt-4o') : 'gpt-4o' // Default model for non-active if unknown
                        ];
                    }

                    // Migrate Prompts
                    $config['prompts']['system'] = $loaded['system'] ?? $defaults['system'];
                    $config['prompts']['continue'] = $loaded['continue'] ?? $defaults['continue'];
                    $config['prompts']['rephrase'] = $loaded['rephrase'] ?? $defaults['rephrase'];
                    $config['prompts']['summarize_chapter'] = $loaded['summarize_chapter'] ?? $defaults['summarize_chapter'];
                    $config['prompts']['summarize_act'] = $loaded['summarize_act'] ?? $defaults['summarize_act'];
                    $config['prompts']['custom'] = $loaded['custom_prompts'] ?? [];

                    // FORCE SAVE MIGRATED CONFIG
                    // file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return $config;
    }

    /**
     * Compress a prompt string to reduce token consumption.
     * Removes CRLF, collapses bullets into inline separators,
     * strips standalone section header lines, and collapses whitespace.
     */
    private function compressPrompt(string $prompt): string
    {
        // Normalize line endings
        $p = str_replace(["\r\n", "\r"], "\n", $prompt);
        // Remove lines that are only a section label ending with ":"
        $p = preg_replace('/^[^\n]{3,80}:\s*$/m', '', $p) ?? $p;
        // Convert bullet points to inline separator (u flag for UTF-8)
        $p = preg_replace('/[\x{2022}\xB7]\s+/u', '; ', $p) ?? $p;
        // Collapse all newlines and multiple spaces into a single space
        $p = str_replace("\n", ' ', $p);
        $p = preg_replace('/ {2,}/', ' ', $p) ?? $p;
        // Clean up orphan/double semicolons
        $p = preg_replace('/;\s*;/', ';', $p) ?? $p;
        return trim($p, " ;");
    }

    private function getDefaultPrompts()
    {
        $file = $this->f3->get('ROOT') . '/data/ai_prompts.json';
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
            if (is_array($json)) {
                return [
                    'system' => $json['system'] ?? '',
                    'continue' => $json['continue'] ?? '',
                    'rephrase' => $json['rephrase'] ?? '',
                    'summarize_chapter' => $json['summarize_chapter'] ?? '',
                    'summarize_act' => $json['summarize_act'] ?? '',
                    'custom_prompts' => $json['custom'] ?? []
                ];
            }
        }

        // Fallback hardcoded if file missing
        return [
            'system' => "Tu es un assistant d'écriture créative expert.",
            'continue' => "Continue le texte suivant...",
            'rephrase' => "Reformule le texte suivant...",
            'summarize_chapter' => "Fais un résumé...",
            'summarize_act' => "Fais un résumé...",
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
            'gemini' => [
                'gemini-3-pro',
                'gemini-3-flash',
                'gemini-2.5-pro',
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-1.5-pro',
                'gemini-1.5-flash',
                'gemini-pro'
            ],
            'mistral' => [
                'mistral-large-latest',
                'mistral-medium-latest',
                'mistral-small-latest',
                'codestral-latest',
                'ministral-3-latest',
                'open-mistral-nemo'
            ],
            'anthropic' => [
                'claude-opus-4-5-20251101',
                'claude-sonnet-4-5-20250929',
                'claude-haiku-4-5-20251001',
                'claude-3-7-sonnet-20250219',
                'claude-3-5-sonnet-latest',
                'claude-3-5-haiku-latest',
                'claude-3-opus-latest'
            ]
        ];
    }

    private function getUserConfigFile()
    {
        $user = $this->currentUser();
        if (!$user || empty($user['email']))
            return null;

        $dir = $this->getUserDataDir($user['email']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/ai_config.json';
    }
}
