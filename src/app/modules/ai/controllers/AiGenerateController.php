<?php

/**
 * AiGenerateController — génération de texte, résumés et synonymes.
 */
class AiGenerateController extends AiBaseController
{
    /**
     * Génère du texte (continuation, reformulation, prompt personnalisé).
     * POST /ai/generate
     */
    public function generate()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes IA. Attendez quelques secondes avant de réessayer.']);
            return;
        }

        try {
            $json        = json_decode($this->f3->get('BODY'), true);
            $prompt      = $json['prompt'] ?? '';
            $task        = $json['task'] ?? 'continue';
            $context     = $json['context'] ?? '';
            $contextId   = $json['contextId'] ?? null;
            $contextType = $json['contextType'] ?? null;

            if (!$prompt && !$context && !$contextId) {
                echo json_encode(['error' => 'Texte requis']);
                return;
            }

            // --- Chargement du contexte document ---
            $docContext      = [];
            $fullContextText = '';

            if ($contextId && $contextType) {
                $db = $this->f3->get('DB');

                if ($contextType === 'chapter') {
                    $chapter = new \Chapter($db);
                    $chapter->load(['id=?', $contextId]);
                    if (!$chapter->dry()) {
                        $projectModel = new \Project($db);
                        if (!$projectModel->count(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']])) {
                            http_response_code(403);
                            echo json_encode(['error' => 'Accès non autorisé']);
                            return;
                        }
                        $projectTitle = $db->exec('SELECT title FROM projects WHERE id=?', [$chapter->project_id])[0]['title'] ?? '';
                        $actTitle     = '';
                        if ($chapter->act_id) {
                            $actTitle = $db->exec('SELECT title FROM acts WHERE id=?', [$chapter->act_id])[0]['title'] ?? '';
                        }
                        $docContext = [
                            'type' => 'chapter', 'id' => $chapter->id,
                            'project' => $projectTitle, 'act' => $actTitle,
                            'title' => $chapter->title, 'content' => $chapter->content,
                        ];
                    }
                } elseif ($contextType === 'section') {
                    $section = new \Section($db);
                    $section->load(['id=?', $contextId]);
                    if (!$section->dry()) {
                        $projectModel = new \Project($db);
                        if (!$projectModel->count(['id=? AND user_id=?', $section->project_id, $this->currentUser()['id']])) {
                            http_response_code(403);
                            echo json_encode(['error' => 'Accès non autorisé']);
                            return;
                        }
                        $projectTitle = $db->exec('SELECT title FROM projects WHERE id=?', [$section->project_id])[0]['title'] ?? '';
                        $docContext   = [
                            'type' => 'section', 'id' => $section->id,
                            'project' => $projectTitle, 'title' => $section->title,
                            'section_type' => $section->type, 'content' => $section->content,
                        ];
                    }
                }
            }

            if (!empty($docContext)) {
                if ($task === 'rephrase' || $task === 'custom') {
                    $fullContextText = "\n[Contexte] Projet: " . ($docContext['project'] ?? '')
                        . (!empty($docContext['act']) ? " | Acte: " . $docContext['act'] : '')
                        . " | " . ucfirst($docContext['type'] ?? 'document') . ": " . ($docContext['title'] ?? '');
                } else {
                    $rawContent = strip_tags($docContext['content'] ?? '');
                    $contentLen = mb_strlen($rawContent);
                    if ($contentLen > 3000) {
                        $rawContent = '…' . mb_substr($rawContent, $contentLen - 3000);
                    }
                    $lines = ["Projet: " . ($docContext['project'] ?? '')];
                    if (!empty($docContext['act'])) $lines[] = "Acte: " . $docContext['act'];
                    $lines[] = "Chapitre: " . ($docContext['title'] ?? '');
                    if (!empty($rawContent)) $lines[] = "Fin du texte actuel:\n" . $rawContent;
                    $fullContextText = "\n\n[CONTEXTE]\n" . implode("\n", $lines);
                }
            } elseif ($context) {
                $fullContextText = "\n\n[CONTEXTE EXTRAIT]\n" . $context;
            }

            $defaults   = $this->getDefaultPrompts();
            $userConfig = $this->getUserConfig();
            [$provider, $apiKey, $model] = $this->resolveAiProvider();
            $service = new AiService($provider, $apiKey, $model);

            $configSystem  = $userConfig['prompts']['system'] ?? '';
            $jsonSystemSent = array_key_exists('system_prompt', $json ?? []);
            $jsonSystem    = $json['system_prompt'] ?? '';

            if ($task === 'custom') {
                $system = $jsonSystemSent ? $jsonSystem : ($configSystem ?: $defaults['system']);
            } else {
                $system = !empty($configSystem) ? $configSystem : (!empty($jsonSystem) ? $jsonSystem : $defaults['system']);
            }

            if (!empty($fullContextText)) {
                $system .= " Contexte du document fourni ci-dessous. Respecte le ton, les noms et le style.\n" . $fullContextText;
            }

            if ($task === 'continue') {
                $configContinue = $userConfig['prompts']['continue'] ?? '';
                $system        .= " " . (!empty($configContinue) ? $configContinue : $defaults['continue']);
                $userPrompt     = $prompt ?: "Continue l'histoire.";
                $maxTokens      = 700;
            } elseif ($task === 'rephrase') {
                $configRephrase = $userConfig['prompts']['rephrase'] ?? '';
                $system        .= " " . (!empty($configRephrase) ? $configRephrase : $defaults['rephrase']);
                $userPrompt     = $prompt;
                $maxTokens      = min(600, (int)(mb_strlen($prompt) / 3) + 100);
            } elseif ($task === 'custom') {
                $userPrompt = $prompt;
                $maxTokens  = 1000;
            } else {
                $userPrompt = $prompt;
                $maxTokens  = 800;
            }

            $system = $this->compressPrompt($system);
            if (empty($system) && $task !== 'custom') {
                $system = "Tu es un assistant d'écriture créative.";
            }

            $t0      = microtime(true);
            $result  = $service->generate($system, $userPrompt, 0.7, $maxTokens);
            $elapsed = microtime(true) - $t0;

            if ($result['success']) {
                $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, $task);
                $this->notifyAiCompletionIfNeeded($elapsed, $task);
                echo json_encode(['text' => $result['text'], 'debug' => ['model' => $model, 'system' => $system, 'user' => $userPrompt]]);
            } else {
                echo json_encode(['error' => $result['error']]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Génère un résumé de chapitre à partir de ses sous-chapitres.
     * POST /ai/summarize-chapter
     */
    public function summarizeChapter()
    {
        $json      = json_decode($this->f3->get('BODY'), true);
        $chapterId = $json['chapter_id'] ?? null;

        if (!$chapterId) { $this->f3->error(400, 'ID de chapitre requis'); return; }

        $db      = $this->f3->get('DB');
        $chapter = new \Chapter($db);
        $chapter->load(['id=?', $chapterId]);

        if ($chapter->dry()) { $this->f3->error(404, 'Chapitre introuvable'); return; }

        $projectModel = new \Project($db);
        if (!$projectModel->count(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes IA. Attendez quelques secondes avant de réessayer.']);
            return;
        }

        // Migration paresseuse colonne resume
        try {
            $db->exec("ALTER TABLE chapters CHANGE description resume TEXT");
        } catch (\Exception $e) {
            try { $db->exec("ALTER TABLE chapters ADD COLUMN resume TEXT"); } catch (\Exception $e2) {}
        }

        $subChapters = $chapter->find(['parent_id=?', $chapterId], ['order' => 'order_index ASC, id ASC']);
        if (!$subChapters) {
            $content = $chapter->content;
        } else {
            $content = '';
            foreach ($subChapters as $sub) {
                $content .= "\n[" . $sub->title . "]\n" . mb_substr(strip_tags($sub->content ?? ''), 0, 2000);
            }
        }

        if (trim(strip_tags($content)) === '') {
            echo json_encode(['success' => false, 'error' => 'Contenu vide.']);
            return;
        }

        $userConfig   = $this->getUserConfig();
        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $configSystem = $userConfig['prompts']['system'] ?? '';
        $systemPrompt = !empty($configSystem) ? $configSystem : ($json['system_prompt'] ?? "Tu es un assistant d'écriture expert.");
        $taskPrompt   = $userConfig['prompts']['summarize_chapter'] ?? ($defaults['summarize_chapter'] ?? "Fais un résumé d'une dizaine de lignes du contenu suivant.");

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $t0           = microtime(true);
        $result       = $service->generate($systemPrompt, $taskPrompt . "\n\n[CONTENU]\n" . $content, 0.7, 500);
        $elapsed      = microtime(true) - $t0;

        if ($result['success']) {
            $chapter->resume = $result['text'];
            $chapter->save();
            $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'summarize_chapter');
            $this->notifyAiCompletionIfNeeded($elapsed, 'summarize_chapter');
            echo json_encode(['success' => true, 'summary' => $result['text']]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }

    /**
     * Génère un résumé d'acte à partir des résumés de ses chapitres.
     * POST /ai/summarize-act
     */
    public function summarizeAct()
    {
        $json  = json_decode($this->f3->get('BODY'), true);
        $actId = $json['act_id'] ?? null;

        if (!$actId) { $this->f3->error(400, "ID d'acte requis"); return; }

        $db  = $this->f3->get('DB');
        $act = new \Act($db);
        $act->load(['id=?', $actId]);

        if ($act->dry()) { $this->f3->error(404, 'Acte introuvable'); return; }

        $projectModel = new \Project($db);
        if (!$projectModel->count(['id=? AND user_id=?', $act->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes IA. Attendez quelques secondes avant de réessayer.']);
            return;
        }

        $chapterModel = new \Chapter($db);
        $chapters     = $chapterModel->find(['act_id=?', $actId], ['order' => 'order_index ASC, id ASC']);
        if (!$chapters) {
            echo json_encode(['success' => false, 'error' => 'Aucun chapitre dans cet acte.']);
            return;
        }

        $content = '';
        foreach ($chapters as $ch) {
            $chSummary = !empty($ch->resume) ? $ch->resume : substr(strip_tags($ch->content), 0, 500) . '...';
            $content  .= "\n\n[Chapitre: " . $ch->title . "]\n" . $chSummary;
        }

        $userConfig   = $this->getUserConfig();
        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $configSystem = $userConfig['prompts']['system'] ?? '';
        $systemPrompt = !empty($configSystem) ? $configSystem : ($json['system_prompt'] ?? $defaults['system']);
        $taskPrompt   = $userConfig['prompts']['summarize_act'] ?? $defaults['summarize_act'];

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $t0           = microtime(true);
        $result       = $service->generate($systemPrompt, $taskPrompt . "\n\n[RÉSUMÉS DES CHAPITRES]\n" . $content, 0.7, 500);
        $elapsed      = microtime(true) - $t0;

        if ($result['success']) {
            $act->resume = $result['text'];
            $act->save();
            $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'summarize_act');
            $this->notifyAiCompletionIfNeeded($elapsed, 'summarize_act');
            echo json_encode(['success' => true, 'summary' => $result['text']]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }

    /**
     * Génère un résumé d'élément parent à partir de ses sous-éléments.
     * POST /ai/summarize-element
     */
    public function summarizeElement()
    {
        $json      = json_decode($this->f3->get('BODY'), true);
        $elementId = $json['element_id'] ?? null;

        if (!$elementId) { $this->f3->error(400, "ID d'élément requis"); return; }

        $db           = $this->f3->get('DB');
        $elementModel = new \Element($db);
        $elementModel->load(['id=?', $elementId]);

        if ($elementModel->dry()) { $this->f3->error(404, 'Élément introuvable'); return; }

        $projectModel = new \Project($db);
        if (!$projectModel->count(['id=? AND user_id=?', $elementModel->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes IA. Attendez quelques secondes avant de réessayer.']);
            return;
        }

        $subModel = new \Element($db);
        $subs     = $subModel->find(['parent_id=?', $elementId], ['order' => 'order_index ASC, id ASC']);
        if (!$subs) {
            echo json_encode(['success' => false, 'error' => 'Aucun sous-élément pour cet élément.']);
            return;
        }

        $content = '';
        foreach ($subs as $sub) {
            $subSummary = !empty($sub->resume) ? $sub->resume : substr(strip_tags($sub->content), 0, 500) . '...';
            $content   .= "\n\n[" . $sub->title . "]\n" . $subSummary;
        }

        $userConfig   = $this->getUserConfig();
        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $configSystem = $userConfig['prompts']['system'] ?? '';
        $systemPrompt = !empty($configSystem) ? $configSystem : ($json['system_prompt'] ?? $defaults['system']);
        $taskPrompt   = $userConfig['prompts']['summarize_element']
            ?? ($defaults['summarize_element']
            ?? "Fais un résumé synthétique de cet élément à partir du contenu de ses sous-éléments.");

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $t0           = microtime(true);
        $result       = $service->generate($systemPrompt, $taskPrompt . "\n\n[" . $elementModel->title . "]\n[SOUS-ÉLÉMENTS]\n" . $content, 0.7, 500);
        $elapsed      = microtime(true) - $t0;

        if ($result['success']) {
            $elementModel->resume = $result['text'];
            $elementModel->save();
            $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'summarize_element');
            $this->notifyAiCompletionIfNeeded($elapsed, 'summarize_element');
            echo json_encode(['success' => true, 'summary' => $result['text']]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }

    /**
     * Répond à une question sur l'ensemble du projet.
     * POST /ai/ask
     */
    public function ask()
    {
        $json       = json_decode($this->f3->get('BODY'), true);
        $projectId  = $json['project_id'] ?? null;
        $userPrompt = $json['prompt'] ?? '';

        if (!$projectId || !$userPrompt) { $this->f3->error(400, 'Project ID and Prompt required'); return; }

        $db           = $this->f3->get('DB');
        $projectModel = new \Project($db);
        $projectModel->load(['id=? AND user_id=?', $projectId, $this->currentUser()['id']]);
        if ($projectModel->dry()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes IA. Attendez quelques secondes avant de réessayer.']);
            return;
        }

        $truncate = function(string $text, int $max): string {
            return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
        };

        // Cache de contexte projet en session (5 min)
        $ctxCacheKey = '_ai_ctx_' . $projectId;
        $ctxCached   = $_SESSION[$ctxCacheKey] ?? null;
        $contextText = '';

        if ($ctxCached && (time() - ($ctxCached['at'] ?? 0)) < 300) {
            $contextText = $ctxCached['text'];
        } else {
            $lines = ["Projet: " . $projectModel->title];

            $sectionModel = new \Section($db);
            foreach ($sectionModel->find(['project_id=?', $projectId]) ?: [] as $sec) {
                $content = $truncate(trim(strip_tags($sec->content ?? '')), 300);
                if (!empty($content)) $lines[] = $sec->title . ": " . $content;
            }

            $characterModel = new \Character($db);
            $charLines = [];
            foreach ($characterModel->find(['project_id=?', $projectId]) ?: [] as $char) {
                $desc  = $truncate(trim(strip_tags($char->description ?? '')), 150);
                $extra = [];
                if (!empty($char->group_name)) $extra[] = 'groupe: ' . $char->group_name;
                if (!empty($char->traits))     $extra[] = 'traits: ' . $truncate(trim($char->traits), 60);
                $line  = $char->name;
                if ($desc)  $line .= ': ' . $desc;
                if ($extra) $line .= ' [' . implode(', ', $extra) . ']';
                $charLines[] = $line;
            }
            if ($charLines) $lines[] = "Personnages: " . implode(" | ", $charLines);

            $noteModel = new \Note($db);
            foreach ($noteModel->find(['project_id=?', $projectId]) ?: [] as $note) {
                $content = $truncate(trim(strip_tags($note->content ?? '')), 150);
                if (!empty($content)) $lines[] = "Note/" . $note->title . ": " . $content;
            }

            $actModel = new \Act($db);
            $actMap   = [];
            foreach ($actModel->find(['project_id=?', $projectId], ['order' => 'order_index ASC']) ?: [] as $act) {
                $actMap[$act->id] = $act->title;
            }

            $chapterModel = new \Chapter($db);
            $chapters     = $chapterModel->find(['project_id=?', $projectId], ['order' => 'order_index ASC']);
            if ($chapters) {
                $lines[]    = "\nChapitres:";
                $currentAct = null;
                foreach ($chapters as $ch) {
                    $actId = $ch->act_id ?? null;
                    if ($actId && isset($actMap[$actId]) && $actMap[$actId] !== $currentAct) {
                        $currentAct = $actMap[$actId];
                        $lines[]    = "[" . $currentAct . "]";
                    }
                    $resume = $truncate(trim(strip_tags($ch->resume ?? '')), 300);
                    if (empty($resume)) $resume = $truncate(trim(strip_tags($ch->content ?? '')), 120);
                    $lines[] = "- " . $ch->title . ": " . ($resume ?: "(vide)");
                }
            }

            $elementModel = new \Element($db);
            $elements     = $elementModel->getTopLevelByProject($projectId);
            if ($elements) {
                $currentType = null;
                foreach ($elements as $el) {
                    $type = $el['element_type'] ?? 'Éléments';
                    if ($type !== $currentType) { $currentType = $type; $lines[] = "\n" . $type . ":"; }
                    $content = $truncate(trim(strip_tags($el['content'] ?? '')), 200);
                    $lines[] = "- " . $el['title'] . ($content ? ": " . $content : "");
                }
            }

            $contextText = implode("\n", $lines);
            $_SESSION[$ctxCacheKey] = ['text' => $contextText, 'at' => time()];
        }

        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service = new AiService($provider, $apiKey, $model);

        $system  = "Tu es un assistant éditorial expert. Tu as accès au résumé structuré du projet ci-dessous : "
            . "titre, sections (synopsis, pitch…), personnages, notes, chapitres et éléments personnalisés. "
            . "Réponds aux questions de l'auteur sur la cohérence, l'intrigue, les personnages ou le style. "
            . "Utilise un ton constructif et professionnel. Cite des références précises si pertinent.\n\n"
            . $contextText;

        $t0      = microtime(true);
        $result  = $service->generate($system, $userPrompt);
        $elapsed = microtime(true) - $t0;

        if ($result['success']) {
            $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'ask_project');
            $this->notifyAiCompletionIfNeeded($elapsed, 'ask_project');
            echo json_encode(['success' => true, 'answer' => $result['text']]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }

    /**
     * Retourne des synonymes pour un mot via IA + fallback statique.
     * GET /ai/synonyms/@word
     */
    public function synonyms()
    {
        $word = $this->f3->get('PARAMS.word');

        if (!$this->checkRateLimit('ai_synonyms', 20, 60)) {
            echo json_encode([]);
            return;
        }

        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service = new AiService($provider, $apiKey, $model);
        $results = $service->getSynonyms($word);

        if (empty($results)) {
            if (class_exists('Synonyms')) {
                $results = \Synonyms::get($word);
            }
        } else {
            $this->logAiUsage($model, 20, 20, 'synonyms');
        }

        echo json_encode($results);
    }
}
