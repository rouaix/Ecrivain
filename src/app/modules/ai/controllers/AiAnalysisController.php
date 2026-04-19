<?php

/**
 * AiAnalysisController — analyse narrative (continuité, incohérences, relations, enrichissement).
 */
class AiAnalysisController extends AiBaseController
{
    /**
     * Propose 3 phrases d'ouverture pour enchaîner avec le chapitre précédent.
     * POST /ai/suggest-continuity
     */
    public function suggestContinuity()
    {
        $json      = json_decode($this->f3->get('BODY'), true);
        $chapterId = (int) ($json['chapter_id'] ?? 0);

        if (!$chapterId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de chapitre requis']);
            return;
        }

        $db      = $this->f3->get('DB');
        $chapter = new \Chapter($db);
        $chapter->load(['id=?', $chapterId]);

        if ($chapter->dry()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Chapitre introuvable']);
            return;
        }

        $projectModel = new \Project($db);
        $projectModel->load(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']]);
        if ($projectModel->dry()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes IA. Attendez quelques secondes.']);
            return;
        }

        $allChapters = $db->exec(
            'SELECT c.id, c.title, c.content, c.order_index, c.act_id, c.parent_id,
                    a.order_index as act_order,
                    COALESCE(p.order_index, c.order_index) as parent_order
             FROM chapters c
             LEFT JOIN acts a ON c.act_id = a.id
             LEFT JOIN chapters p ON c.parent_id = p.id
             WHERE c.project_id = ?
             ORDER BY
                (a.order_index IS NULL) ASC, a.order_index ASC,
                COALESCE(p.order_index, c.order_index) ASC,
                (c.parent_id IS NOT NULL) ASC, c.order_index ASC, c.id ASC',
            [$chapter->project_id]
        ) ?: [];

        $currentIndex = null;
        foreach ($allChapters as $i => $ch) {
            if ((int)$ch['id'] === $chapterId) { $currentIndex = $i; break; }
        }

        $prevEndText = '';
        if ($currentIndex !== null && $currentIndex > 0) {
            $prev      = $allChapters[$currentIndex - 1];
            $plainText = trim(strip_tags($prev['content'] ?? ''));
            if (mb_strlen($plainText) > 600) $plainText = '…' . mb_substr($plainText, -600);
            $prevEndText = $plainText;
        }

        $currentTitle  = $chapter->title;
        $currentResume = trim(strip_tags($chapter->resume ?? ''));
        $contextParts  = [];
        if ($prevEndText !== '') $contextParts[] = "Fin du chapitre précédent :\n«\n{$prevEndText}\n»";
        $contextParts[] = "Titre du chapitre à ouvrir : « {$currentTitle} »";
        if ($currentResume !== '') $contextParts[] = "Résumé du chapitre à ouvrir : {$currentResume}";

        $context    = implode("\n\n", $contextParts);
        $taskPrompt = "En t'appuyant sur le contexte ci-dessous, propose exactement 3 phrases d'ouverture distinctes pour débuter ce nouveau chapitre. "
                    . "Chaque suggestion doit être sur une ligne séparée, précédée d'un numéro (1. 2. 3.). "
                    . "Les phrases doivent être en français, fluides, et créer une continuité naturelle avec la fin du chapitre précédent.\n\n"
                    . $context;

        $userConfig   = $this->getUserConfig();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $systemPrompt = $this->compressPrompt($userConfig['prompts']['system'] ?? "Tu es un assistant d'écriture créative expert en narration française.");

        $service = new AiService($provider, $apiKey, $model);
        $t0      = microtime(true);
        $result  = $service->generate($systemPrompt, $taskPrompt, 0.85, 350);
        $elapsed = microtime(true) - $t0;

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
            return;
        }

        $raw         = trim($result['text']);
        $suggestions = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $clean = preg_replace('/^\d+[\.\)]\s*/', '', $line);
            if ($clean !== '') $suggestions[] = $clean;
        }
        if (count($suggestions) < 2) {
            $suggestions = array_values(array_filter(array_map('trim', preg_split('/\n{2,}/', $raw))));
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'suggest_continuity');
        $this->notifyAiCompletionIfNeeded($elapsed, 'suggest_continuity');

        echo json_encode([
            'success'      => true,
            'suggestions'  => array_slice($suggestions, 0, 3),
            'has_previous' => $prevEndText !== '',
        ]);
    }

    /**
     * Détecte les incohérences narratives dans un chapitre par rapport aux fiches personnages.
     * POST /ai/detect-inconsistencies
     */
    public function detectInconsistencies()
    {
        $json      = json_decode($this->f3->get('BODY'), true);
        $chapterId = (int) ($json['chapter_id'] ?? 0);

        if (!$chapterId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de chapitre requis']);
            return;
        }

        $db      = $this->f3->get('DB');
        $chapter = new \Chapter($db);
        $chapter->load(['id=?', $chapterId]);

        if ($chapter->dry()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Chapitre introuvable']);
            return;
        }

        $projectModel = new \Project($db);
        $projectModel->load(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']]);
        if ($projectModel->dry()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            return;
        }

        if (!$this->checkRateLimit('ai_gen', 10, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes IA. Attendez quelques secondes.']);
            return;
        }

        $characters = $db->exec(
            'SELECT name, description, age, traits, motivations, arc, flaws, group_name FROM characters WHERE project_id = ? ORDER BY name ASC',
            [$chapter->project_id]
        ) ?: [];

        if (empty($characters)) {
            echo json_encode(['success' => false, 'error' => "Aucune fiche personnage dans ce projet. Créez des personnages d'abord."]);
            return;
        }

        $charContext = '';
        foreach ($characters as $char) {
            $desc         = mb_substr(trim(strip_tags(html_entity_decode($char['description'] ?? ''))), 0, 600);
            $charContext .= "— {$char['name']}";
            if (!empty($char['group_name'])) $charContext .= " ({$char['group_name']})";
            $charContext .= " : " . ($desc ?: '(pas de description)') . "\n";
            if (!empty($char['age']))         $charContext .= "  Âge : {$char['age']}\n";
            if (!empty($char['traits']))      $charContext .= "  Traits : " . mb_substr(trim($char['traits']), 0, 150) . "\n";
            if (!empty($char['flaws']))       $charContext .= "  Défauts : " . mb_substr(trim($char['flaws']), 0, 150) . "\n";
            if (!empty($char['motivations'])) $charContext .= "  Motivations : " . mb_substr(trim($char['motivations']), 0, 150) . "\n";
            if (!empty($char['arc']))         $charContext .= "  Arc : " . mb_substr(trim($char['arc']), 0, 150) . "\n";
        }

        $subChapters = $db->exec('SELECT title, content FROM chapters WHERE parent_id = ? ORDER BY order_index ASC, id ASC', [$chapterId]) ?: [];
        if ($subChapters) {
            $chapterText = '';
            foreach ($subChapters as $sub) {
                $chapterText .= "\n[" . $sub['title'] . "]\n" . mb_substr(trim(strip_tags(html_entity_decode($sub['content'] ?? ''))), 0, 1500);
            }
        } else {
            $chapterText = mb_substr(trim(strip_tags(html_entity_decode($chapter->content ?? ''))), 0, 4000);
        }

        if (trim($chapterText) === '') {
            echo json_encode(['success' => false, 'error' => 'Le chapitre est vide.']);
            return;
        }

        $taskPrompt =
            "Tu es un assistant éditorial. Analyse le chapitre ci-dessous en le comparant aux fiches personnages fournies.\n"
          . "Signale uniquement les vraies contradictions logiques qui sont impossibles ou incompatibles :\n"
          . "- Attributs physiques décrits différemment\n"
          . "- Connaissances ou compétences que le personnage ne peut pas avoir selon sa fiche\n"
          . "- Événements qui contredisent directement un fait établi\n"
          . "- Traits de personnalité radicalement opposés à ceux décrits\n"
          . "NE SIGNALE PAS les comportements situationnels normaux.\n"
          . "Si tu ne trouves aucune incohérence réelle, réponds exactement : \"Aucune incohérence détectée.\"\n"
          . "Sinon, liste chaque problème sous forme de puces courtes (une par ligne, commençant par « - »).\n\n"
          . "### Fiches personnages\n" . $charContext . "\n"
          . "### Chapitre : « " . $chapter->title . " »\n" . $chapterText;

        $userConfig   = $this->getUserConfig();
        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        $systemPrompt = $this->compressPrompt($userConfig['prompts']['system'] ?? "Tu es un assistant d'écriture créative expert en narration française.");

        $service = new AiService($provider, $apiKey, $model);
        $t0      = microtime(true);
        $result  = $service->generate($systemPrompt, $taskPrompt, 0.3, 600);
        $elapsed = microtime(true) - $t0;

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
            return;
        }

        $raw   = trim($result['text']);
        $clean = (stripos($raw, 'Aucune incohérence') !== false);
        $issues = [];
        if (!$clean) {
            foreach (preg_split('/\r?\n/', $raw) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $line = preg_replace('/^[-•*]\s*/', '', $line);
                if ($line !== '') $issues[] = $line;
            }
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'detect_inconsistencies');
        $this->notifyAiCompletionIfNeeded($elapsed, 'detect_inconsistencies');

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'clean' => $clean, 'issues' => $issues, 'raw' => $raw]);
    }

    /**
     * Suggère des relations entre personnages en analysant les chapitres.
     * POST /ai/suggest-relations
     */
    public function suggestRelations()
    {
        header('Content-Type: application/json');
        $user = $this->currentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Non authentifié']);
            return;
        }

        $json = json_decode($this->f3->get('BODY'), true);
        $pid  = (int) ($json['project_id'] ?? 0);

        if (!$pid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'project_id manquant']);
            return;
        }

        $db      = $this->f3->get('DB');
        $project = $db->exec('SELECT id, title FROM projects WHERE id = ? AND user_id = ?', [$pid, $user['id']]);
        if (empty($project)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            return;
        }

        $characters = $db->exec('SELECT id, name, description, group_name FROM characters WHERE project_id = ? ORDER BY name ASC', [$pid]) ?: [];
        if (count($characters) < 2) {
            echo json_encode(['success' => false, 'error' => 'Il faut au moins 2 personnages pour suggérer des relations.']);
            return;
        }

        $nameToId    = [];
        $charContext = '';
        foreach ($characters as $c) {
            $nameToId[mb_strtolower($c['name'])] = (int) $c['id'];
            $desc         = mb_substr(trim(strip_tags(html_entity_decode($c['description'] ?? ''))), 0, 200);
            $charContext .= "- {$c['name']}";
            if (!empty($c['group_name'])) $charContext .= " ({$c['group_name']})";
            $charContext .= ($desc ? " : $desc" : '') . "\n";
        }

        $chapters = $db->exec('SELECT title, content FROM chapters WHERE project_id = ? ORDER BY order_index ASC LIMIT 30', [$pid]) ?: [];
        if (empty($chapters)) {
            echo json_encode(['success' => false, 'error' => 'Aucun chapitre dans ce projet.']);
            return;
        }

        $chapContext = '';
        foreach ($chapters as $ch) {
            $text = mb_substr(trim(strip_tags(html_entity_decode($ch['content'] ?? ''))), 0, 800);
            if ($text) $chapContext .= "### " . $ch['title'] . "\n" . $text . "\n\n";
        }

        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        if (!$apiKey) {
            echo json_encode(['success' => false, 'error' => 'Aucune clé API configurée.']);
            return;
        }

        $charNames = implode(', ', array_column($characters, 'name'));
        $prompt    =
            "Tu es un assistant littéraire. Analyse les chapitres ci-dessous et identifie les relations "
          . "significatives entre les personnages suivants : {$charNames}.\n\n"
          . "Règles :\n"
          . "- Ne retiens que les relations claires et récurrentes.\n"
          . "- Chaque relation doit être entre exactement deux personnages de la liste.\n"
          . "- Utilise un label court en français (2-4 mots max).\n"
          . "- Attribue une couleur hex parmi : #ef4444 (conflit), #f97316 (tension), #eab308 (alliance), "
          . "#22c55e (amitié), #ec4899 (amour), #3b82f6 (famille), #8b5cf6 (lien mystique), #94a3b8 (neutre).\n"
          . "- Réponds UNIQUEMENT en JSON valide : [{\"from\":\"NomA\",\"to\":\"NomB\",\"label\":\"label\",\"color\":\"#xxxxxx\"},...]\n"
          . "- Si aucune relation claire n'est détectable, réponds : []\n\n"
          . "### Personnages\n{$charContext}\n"
          . "### Chapitres\n{$chapContext}";

        $service = new AiService($provider, $apiKey, $model);
        $t0      = microtime(true);

        try {
            $result = $service->generate("Tu es un assistant littéraire.", $prompt, 0.5, 800);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur IA : ' . $e->getMessage()]);
            return;
        }

        $raw  = trim($result['text'] ?? '');
        $raw  = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw  = preg_replace('/\s*```$/', '', $raw);
        $suggestions = json_decode($raw, true);

        if (!is_array($suggestions)) {
            echo json_encode(['success' => false, 'error' => 'Réponse IA invalide.', 'raw' => $raw]);
            return;
        }

        $resolved      = [];
        $allowedColors = ['#ef4444','#f97316','#eab308','#22c55e','#ec4899','#3b82f6','#8b5cf6','#94a3b8'];
        foreach ($suggestions as $s) {
            $fromId = $nameToId[mb_strtolower($s['from'] ?? '')] ?? null;
            $toId   = $nameToId[mb_strtolower($s['to']   ?? '')] ?? null;
            if (!$fromId || !$toId || $fromId === $toId) continue;

            $color     = in_array($s['color'] ?? '', $allowedColors) ? $s['color'] : '#94a3b8';
            $resolved[] = [
                'from_id'   => $fromId,  'to_id'   => $toId,
                'from_name' => $s['from'], 'to_name' => $s['to'],
                'label'     => mb_substr(trim($s['label'] ?? ''), 0, 100),
                'color'     => $color,
            ];
        }

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'suggest_relations');
        echo json_encode(['success' => true, 'suggestions' => $resolved]);
    }

    /**
     * Enrichit la fiche d'un personnage avec l'IA.
     * POST /ai/enrich-character
     */
    public function enrichCharacter()
    {
        header('Content-Type: application/json');
        $user = $this->currentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Non authentifié']);
            return;
        }

        $json        = json_decode($this->f3->get('BODY'), true);
        $characterId = (int) ($json['character_id'] ?? 0);
        $rawDesc     = trim($json['description'] ?? '');

        if (!$characterId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'character_id manquant']);
            return;
        }

        $db       = $this->f3->get('DB');
        $charRows = $db->exec(
            'SELECT c.id, c.name, c.description, c.project_id, c.age, c.traits, c.motivations, c.arc, c.flaws, c.group_name
             FROM characters c JOIN projects p ON p.id = c.project_id
             WHERE c.id = ? AND p.user_id = ?',
            [$characterId, $user['id']]
        );

        if (empty($charRows)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Personnage introuvable ou accès refusé']);
            return;
        }

        $character = $charRows[0];
        $pid       = (int) $character['project_id'];

        if ($rawDesc === '') $rawDesc = trim(strip_tags(html_entity_decode($character['description'] ?? '')));

        $projectRows  = $db->exec('SELECT title FROM projects WHERE id = ?', [$pid]);
        $projectTitle = $projectRows[0]['title'] ?? 'Sans titre';

        $otherChars = $db->exec('SELECT name, description FROM characters WHERE project_id = ? AND id != ? ORDER BY name ASC LIMIT 20', [$pid, $characterId]) ?: [];
        $otherCharContext = '';
        foreach ($otherChars as $oc) {
            $d = mb_substr(trim(strip_tags(html_entity_decode($oc['description'] ?? ''))), 0, 150);
            $otherCharContext .= '- ' . $oc['name'] . ($d ? ' : ' . $d : '') . "\n";
        }

        $chapters   = $db->exec('SELECT title, content FROM chapters WHERE project_id = ? ORDER BY order_index ASC LIMIT 20', [$pid]) ?: [];
        $chapContext = '';
        foreach ($chapters as $ch) {
            $text = mb_substr(trim(strip_tags(html_entity_decode($ch['content'] ?? ''))), 0, 600);
            if ($text) $chapContext .= '### ' . $ch['title'] . "\n" . $text . "\n\n";
        }

        $templatePath = __DIR__ . '/../../characters/models/character_template.md';
        $template     = file_exists($templatePath) ? file_get_contents($templatePath) : '';

        [$provider, $apiKey, $model] = $this->resolveAiProvider();
        if (!$apiKey) {
            echo json_encode(['success' => false, 'error' => 'Aucune clé API configurée.']);
            return;
        }

        $charName        = $character['name'];
        $structuredParts = [];
        if (!empty($character['age']))         $structuredParts[] = "Âge : " . $character['age'];
        if (!empty($character['group_name']))  $structuredParts[] = "Groupe/Faction : " . $character['group_name'];
        if (!empty($character['traits']))      $structuredParts[] = "Traits : " . $character['traits'];
        if (!empty($character['flaws']))       $structuredParts[] = "Défauts : " . $character['flaws'];
        if (!empty($character['motivations'])) $structuredParts[] = "Motivations : " . $character['motivations'];
        if (!empty($character['arc']))         $structuredParts[] = "Arc narratif : " . $character['arc'];
        $structuredContext = $structuredParts ? implode("\n", $structuredParts) : '';

        $systemPrompt =
            "Tu es un assistant littéraire expert en création de personnages. "
          . "Tu produis des fiches de personnages riches, cohérentes avec l'univers du livre. "
          . "Tu réponds UNIQUEMENT en HTML valide (balises <h2> et <p> uniquement, sans <html>/<body>/<head>). "
          . "Tu respectes scrupuleusement la structure imposée par le modèle fourni. "
          . "Si une information est inconnue, tu écris « Non défini. » sans inventer.";

        $userPrompt =
            "## Projet : {$projectTitle}\n\n"
          . "## Personnage à enrichir : {$charName}\n\n"
          . "## Description brute saisie par l'auteur :\n" . ($rawDesc ?: '(aucune description saisie)') . "\n\n"
          . ($structuredContext ? "## Champs structurés déjà renseignés :\n{$structuredContext}\n\n" : '')
          . ($otherCharContext  ? "## Autres personnages du projet :\n{$otherCharContext}\n\n" : '')
          . ($chapContext       ? "## Extraits des chapitres :\n{$chapContext}\n\n" : '')
          . "## Modèle de structure à respecter :\n{$template}\n\n---\n\n"
          . "Produis la fiche complète du personnage **{$charName}** en HTML. "
          . "Chaque section du modèle doit apparaître comme un `<h2>` suivi de `<p>`. "
          . "Enrichis les informations manquantes si le contexte le permet, sinon indique « Non défini. ». "
          . "Ne produis que le HTML des sections, sans wrapper <div> ni doctype.";

        $service = new AiService($provider, $apiKey, $model);
        try {
            $result = $service->generate($systemPrompt, $userPrompt, 0.7, 2000);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur IA : ' . $e->getMessage()]);
            return;
        }

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur inconnue']);
            return;
        }

        $html = trim($result['text'] ?? '');
        $html = preg_replace('/^```(?:html)?\s*/i', '', $html);
        $html = preg_replace('/\s*```$/', '', $html);

        $this->logAiUsage($model, $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, 'enrich_character');
        echo json_encode(['success' => true, 'html' => $html]);
    }
}
