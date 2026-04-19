<?php

/**
 * AiSynopsisController — génération et évaluation de synopsis avec l'IA.
 */
class AiSynopsisController extends AiBaseController
{
    /**
     * Génère un synopsis complet depuis une idée.
     * POST /ai/synopsis/generate-from-idea
     */
    public function synopsisFromIdea()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Attendez quelques secondes.']);
            return;
        }

        $json     = json_decode($this->f3->get('BODY'), true);
        $idea     = trim($json['idea'] ?? '');
        $pid      = (int) ($json['project_id'] ?? 0);
        $genre    = trim($json['genre'] ?? '');
        $audience = trim($json['audience'] ?? '');
        $method   = trim($json['structure_method'] ?? 'libre');

        if (!$idea || !$pid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Idée et projet requis']);
            return;
        }

        $projectModel = new \Project($this->f3->get('DB'));
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $systemPrompt = $defaults['synopsis_system'] ?? "Tu es un expert en dramaturgie. Réponds UNIQUEMENT en JSON valide.";
        $taskPrompt   = $defaults['synopsis_from_idea'] ?? '';

        $details = [];
        if ($genre)    $details[] = "Genre : $genre";
        if ($audience) $details[] = "Public cible : $audience";
        if ($method && $method !== 'libre') $details[] = "Structure narrative : $method";

        $userPrompt = $taskPrompt . "\n\n[IDÉE]\n" . $idea;
        if ($details) $userPrompt .= "\n\n" . implode("\n", $details);

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $t0     = microtime(true);
        $result = $service->generate($systemPrompt, $userPrompt, 0.8, 1800);
        $elapsed = microtime(true) - $t0;

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA']);
            return;
        }

        $text  = preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/'], '', trim($result['text'] ?? ''));
        $beats = json_decode($text, true);

        if (!is_array($beats)) {
            echo json_encode(['success' => false, 'error' => 'Réponse IA invalide', 'raw' => $text]);
            return;
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'synopsis_from_idea');
        $this->notifyAiCompletionIfNeeded($elapsed, 'synopsis_from_idea');

        echo json_encode(['success' => true, 'beats' => $beats]);
    }

    /**
     * Génère un synopsis depuis le contenu existant du projet.
     * POST /ai/synopsis/generate-from-project
     */
    public function synopsisFromProject()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Attendez quelques secondes.']);
            return;
        }

        $json   = json_decode($this->f3->get('BODY'), true);
        $pid    = (int) ($json['project_id'] ?? 0);
        $method = trim($json['structure_method'] ?? 'libre');

        if (!$pid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Projet requis']);
            return;
        }

        $db           = $this->f3->get('DB');
        $projectModel = new \Project($db);
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        $project = $db->exec('SELECT title, description FROM projects WHERE id=?', [$pid]);
        $project = $project[0] ?? [];

        $chars = $db->exec('SELECT name, description FROM characters WHERE project_id=? ORDER BY id ASC LIMIT 5', [$pid]);
        $charsText = '';
        foreach ($chars as $c) {
            $raw     = $c['description'] ?? '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $parts = [];
                if (!empty($decoded['statut_social'])) $parts[] = $decoded['statut_social'];
                if (!empty($decoded['description']))   $parts[] = mb_substr(strip_tags($decoded['description']), 0, 150);
                if (!empty($decoded['role']))          $parts[] = $decoded['role'];
                $desc = implode(' — ', $parts);
            } else {
                $desc = mb_substr(strip_tags($raw), 0, 200);
            }
            $charsText .= "- " . $c['name'] . ($desc ? " : $desc" : '') . "\n";
        }

        $chapters     = $db->exec('SELECT title, resume, content FROM chapters WHERE project_id=? AND parent_id IS NULL ORDER BY order_index ASC, id ASC', [$pid]);
        $chaptersText = '';
        $budget       = 5000;
        foreach ($chapters as $ch) {
            if ($budget <= 0) break;
            $excerpt = !empty($ch['resume'])
                ? mb_substr(strip_tags($ch['resume']), 0, 600)
                : mb_substr(strip_tags(html_entity_decode($ch['content'] ?? '')), 0, 300);
            if (!$excerpt) continue;
            $line          = "- " . $ch['title'] . " : " . $excerpt . "\n";
            $chaptersText .= $line;
            $budget       -= mb_strlen($line);
        }

        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service = new AiService($provider, $apiKey, $model);

        $systemPrompt = "Tu es un expert en dramaturgie. Tu analyses le contenu d'un roman et tu rédiges un synopsis structuré complet. "
            . "Tu réponds UNIQUEMENT avec un objet JSON valide contenant exactement ces 16 clés :\n"
            . "{\"genre\",\"subgenre\",\"tone\",\"themes\",\"comps\",\"audience\","
            . "\"logline\",\"pitch\",\"situation\",\"trigger_evt\",\"plot_point1\","
            . "\"development\",\"midpoint\",\"crisis\",\"climax\",\"resolution\"}\n"
            . "Aucun autre texte, aucun markdown, aucune autre clé JSON.";

        $userPrompt = "Voici les données du projet. Génère le synopsis :\n\nTitre : " . ($project['title'] ?? '');
        if (!empty($project['description'])) $userPrompt .= "\nDescription : " . mb_substr(strip_tags($project['description']), 0, 400);
        if ($charsText)    $userPrompt .= "\n\nPersonnages principaux :\n" . $charsText;
        if ($chaptersText) $userPrompt .= "\n\nChapitres :\n" . $chaptersText;
        if ($method && $method !== 'libre') $userPrompt .= "\n\nStructure narrative : $method";

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $t0     = microtime(true);
        $result = $service->generate($systemPrompt, $userPrompt, 0.6, 2000);
        $elapsed = microtime(true) - $t0;

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA']);
            return;
        }

        $text  = preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/'], '', trim($result['text'] ?? ''));
        $beats = json_decode($text, true);

        if (!is_array($beats)) {
            echo json_encode(['success' => false, 'error' => 'Réponse IA invalide', 'raw' => $text]);
            return;
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'synopsis_from_project');
        $this->notifyAiCompletionIfNeeded($elapsed, 'synopsis_from_project');

        echo json_encode(['success' => true, 'beats' => $beats]);
    }

    /**
     * Génère un beat individuel du synopsis.
     * POST /ai/synopsis/generate-beat
     */
    public function synopsisGenerateBeat()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Attendez quelques secondes.']);
            return;
        }

        $json    = json_decode($this->f3->get('BODY'), true);
        $pid     = (int) ($json['project_id'] ?? 0);
        $beat    = trim($json['beat'] ?? '');
        $context = $json['synopsis_context'] ?? [];

        $validBeats = ['logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
                       'development', 'midpoint', 'crisis', 'climax', 'resolution'];

        if (!$pid || !in_array($beat, $validBeats, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            return;
        }

        $projectModel = new \Project($this->f3->get('DB'));
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        $beatLabels = [
            'logline'     => 'Logline (1 phrase : personnage + objectif + obstacle + enjeu)',
            'pitch'       => 'Accroche / pitch (1-2 paragraphes séduisants)',
            'situation'   => 'Situation initiale',
            'trigger_evt' => 'Élément déclencheur',
            'plot_point1' => 'Premier tournant (plot point 1)',
            'development' => 'Développement (acte II)',
            'midpoint'    => 'Point médian',
            'crisis'      => 'Crise / nœud dramatique',
            'climax'      => 'Climax',
            'resolution'  => 'Résolution / situation finale',
        ];

        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $systemPrompt = "Tu es un expert en écriture de synopsis de romans. Tu génères un passage précis et cohérent avec le contexte fourni. Réponds uniquement avec le texte demandé, sans titre ni JSON.";
        $taskPrompt   = $defaults['synopsis_generate_beat'] ?? '';

        $contextText = '';
        foreach ($context as $key => $val) {
            if (!empty($val) && $key !== $beat && in_array($key, $validBeats, true)) {
                $contextText .= ($beatLabels[$key] ?? $key) . " : " . mb_substr(strip_tags($val), 0, 300) . "\n";
            }
        }

        $userPrompt = $taskPrompt . "\n\nSection à générer : " . $beatLabels[$beat];
        if ($contextText) $userPrompt .= "\n\n[CONTEXTE DU SYNOPSIS]\n" . $contextText;

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $result       = $service->generate($systemPrompt, $userPrompt, 0.75, 600);

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA']);
            return;
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'synopsis_generate_beat');
        echo json_encode(['success' => true, 'beat' => $beat, 'content' => trim($result['text'] ?? '')]);
    }

    /**
     * Propose 3 loglines alternatives.
     * POST /ai/synopsis/suggest-logline
     */
    public function synopsisSuggestLogline()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Attendez quelques secondes.']);
            return;
        }

        $json    = json_decode($this->f3->get('BODY'), true);
        $pid     = (int) ($json['project_id'] ?? 0);
        $context = trim($json['synopsis_context'] ?? '');

        if (!$pid || !$context) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Contexte requis']);
            return;
        }

        $projectModel = new \Project($this->f3->get('DB'));
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $systemPrompt = "Tu es spécialiste de la logline cinématographique et romanesque. Une logline = 1 phrase : personnage + objectif + obstacle + enjeu. Réponds UNIQUEMENT par un tableau JSON de 3 chaînes, sans autre texte.";
        $taskPrompt   = ($defaults['synopsis_suggest_logline'] ?? '') . "\n\n[SYNOPSIS]\n" . mb_substr($context, 0, 3000);

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $result       = $service->generate($systemPrompt, $taskPrompt, 0.9, 300);

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA']);
            return;
        }

        $text     = preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/'], '', trim($result['text'] ?? ''));
        $loglines = json_decode($text, true);

        if (!is_array($loglines)) {
            echo json_encode(['success' => false, 'error' => 'Réponse IA invalide', 'raw' => $text]);
            return;
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'synopsis_suggest_logline');
        echo json_encode(['success' => true, 'loglines' => array_slice($loglines, 0, 3)]);
    }

    /**
     * Évalue la cohérence narrative du synopsis.
     * POST /ai/synopsis/evaluate
     */
    public function synopsisEvaluate()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('ai_eval', 3, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => "Trop de requêtes d'évaluation. Attendez une minute."]);
            return;
        }

        $json     = json_decode($this->f3->get('BODY'), true);
        $pid      = (int) ($json['project_id'] ?? 0);
        $synopsis = $json['synopsis'] ?? [];

        if (!$pid || empty($synopsis)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Données manquantes']);
            return;
        }

        $projectModel = new \Project($this->f3->get('DB'));
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        $beatLabels = [
            'logline' => 'Logline', 'pitch' => 'Accroche',
            'situation' => 'Situation initiale', 'trigger_evt' => 'Élément déclencheur',
            'plot_point1' => 'Premier tournant', 'development' => 'Développement',
            'midpoint' => 'Point médian', 'crisis' => 'Crise',
            'climax' => 'Climax', 'resolution' => 'Résolution',
        ];

        $synopsisText = '';
        foreach ($beatLabels as $key => $label) {
            if (!empty($synopsis[$key])) {
                $synopsisText .= "[$label]\n" . mb_substr(strip_tags($synopsis[$key]), 0, 600) . "\n\n";
            }
        }

        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $systemPrompt = "Tu es un éditeur littéraire expérimenté et bienveillant. Tu évalues des synopsis avec rigueur et professionnalisme. Réponds UNIQUEMENT en JSON valide avec les clés : score_global, points_forts, points_faibles, incoherences, suggestions, logline_evaluation, verdict.";
        $taskPrompt   = ($defaults['synopsis_evaluate'] ?? '') . "\n\n[SYNOPSIS À ÉVALUER]\n" . $synopsisText;

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $t0     = microtime(true);
        $result = $service->generate($systemPrompt, $taskPrompt, 0.4, 1000);
        $elapsed = microtime(true) - $t0;

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA']);
            return;
        }

        $text       = preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/'], '', trim($result['text'] ?? ''));
        $evaluation = json_decode($text, true);

        if (!is_array($evaluation)) {
            echo json_encode(['success' => false, 'error' => 'Réponse IA invalide', 'raw' => $text]);
            return;
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'synopsis_evaluate');
        $this->notifyAiCompletionIfNeeded($elapsed, 'synopsis_evaluate');

        echo json_encode(['success' => true, 'evaluation' => $evaluation]);
    }

    /**
     * Enrichit / développe un beat existant du synopsis.
     * POST /ai/synopsis/enrich-beat
     */
    public function synopsisEnrichBeat()
    {
        header('Content-Type: application/json');

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Attendez quelques secondes.']);
            return;
        }

        $json    = json_decode($this->f3->get('BODY'), true);
        $pid     = (int) ($json['project_id'] ?? 0);
        $beat    = trim($json['beat'] ?? '');
        $current = trim($json['current_content'] ?? '');
        $context = trim($json['synopsis_context'] ?? '');

        $validBeats = ['logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
                       'development', 'midpoint', 'crisis', 'climax', 'resolution'];

        if (!$pid || !in_array($beat, $validBeats, true) || !$current) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            return;
        }

        $projectModel = new \Project($this->f3->get('DB'));
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        $defaults     = $this->getDefaultPrompts();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $service      = new AiService($provider, $apiKey, $model);

        $systemPrompt = "Tu es un expert en écriture de synopsis. Tu enrichis des passages de synopsis en les rendant plus précis, plus vivants et plus tendus, sans dénaturer le contenu existant. Réponds uniquement avec le texte enrichi.";
        $taskPrompt   = ($defaults['synopsis_enrich_beat'] ?? '')
            . "\n\n[CONTENU ACTUEL]\n" . mb_substr(strip_tags($current), 0, 1000);
        if ($context) $taskPrompt .= "\n\n[CONTEXTE DU ROMAN]\n" . mb_substr(strip_tags($context), 0, 1000);

        $systemPrompt = $this->compressPrompt($systemPrompt);
        $result       = $service->generate($systemPrompt, $taskPrompt, 0.7, 500);

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA']);
            return;
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'synopsis_enrich_beat');
        echo json_encode(['success' => true, 'beat' => $beat, 'content' => trim($result['text'] ?? '')]);
    }
}
