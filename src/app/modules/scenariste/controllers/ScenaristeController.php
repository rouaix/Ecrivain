<?php

class ScenaristeController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * GET /project/@pid/scenarios
     */
    public function list()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        $projects = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $user['id']]);
        if (!$projects) {
            $this->f3->error(404);
            return;
        }
        $project = $projects[0];

        $scenarioModel = new Scenario();
        $scenarios = $scenarioModel->getAllByProject($pid);

        foreach ($scenarios as &$sc) {
            $sc['meta_saison']  = $sc['saison']  ?? '';
            $sc['meta_episode'] = $sc['episode'] ?? '';
            $sc['meta_genre']   = $sc['genre']   ?? '';
        }
        unset($sc);

        $this->render('scenariste/list.html', [
            'title'     => 'Scénarios — ' . $project['title'],
            'project'   => $project,
            'scenarios' => $scenarios,
        ]);
    }

    /**
     * GET /project/@pid/scenariste/new
     */
    public function newEpisode()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        $projects = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $user['id']]);
        if (!$projects) {
            $this->f3->error(404);
            return;
        }
        $project = $projects[0];

        // Chapters ordered by act then position
        $chapters = $this->db->exec(
            'SELECT c.id, c.title, c.act_id, a.title AS act_title, c.order_index
             FROM chapters c
             LEFT JOIN acts a ON c.act_id = a.id
             WHERE c.project_id = ? AND c.parent_id IS NULL
             ORDER BY (c.act_id IS NULL) ASC,
                      COALESCE(a.order_index, 9999) ASC,
                      c.order_index ASC, c.id ASC',
            [$pid]
        ) ?: [];

        // Characters
        $characters = $this->db->exec(
            'SELECT id, name FROM characters WHERE project_id = ? ORDER BY name ASC',
            [$pid]
        ) ?: [];

        // Existing scenario episodes (for "épisodes précédents")
        $previousEpisodes = $this->db->exec(
            'SELECT id, title FROM scenarios WHERE project_id = ? ORDER BY id ASC',
            [$pid]
        ) ?: [];

        $this->render('scenariste/new.html', [
            'title'            => 'Nouveau scénario',
            'project'          => $project,
            'chapters'         => $chapters,
            'characters'       => $characters,
            'previousEpisodes' => $previousEpisodes,
        ]);
    }

    /**
     * POST /project/@pid/scenariste/generate  (AJAX)
     */
    public function generate()
    {
        header('Content-Type: application/json');
        set_time_limit(300);

        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            return;
        }

        if (!$this->checkRateLimit('ai_scenariste', 3, 120)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Attendez quelques secondes.']);
            return;
        }

        // --- Form data ---
        $saison         = preg_replace('/\D/', '', $_POST['saison'] ?? '01') ?: '01';
        $episode        = preg_replace('/\D/', '', $_POST['episode'] ?? '01') ?: '01';
        $title          = trim($_POST['title'] ?? '');
        $genre          = trim($_POST['genre'] ?? '');
        $chapterIds     = array_values(array_filter(array_map('intval', (array)($_POST['chapter_ids'] ?? []))));
        $prevIds        = array_values(array_filter(array_map('intval', (array)($_POST['previous_episode_ids'] ?? []))));
        $freeContext    = trim($_POST['free_context'] ?? '');
        $uploadedText   = trim($_POST['uploaded_content'] ?? '');

        // --- Load skill system prompt ---
        $skillFile = dirname(__DIR__, 3) . '/scenariste_skill.md';
        $systemPrompt = '';
        if (file_exists($skillFile)) {
            $raw = file_get_contents($skillFile);
            // Strip YAML front matter
            $systemPrompt = trim(preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $raw));
        }
        if (empty($systemPrompt)) {
            echo json_encode(['success' => false, 'error' => 'Fichier skill scénariste introuvable.']);
            return;
        }

        // --- Build chapter content (chapter + all its sub-chapters) ---
        $chapterContent = '';
        if (!empty($chapterIds)) {
            $placeholders = implode(',', array_fill(0, count($chapterIds), '?'));
            // Fetch selected top-level chapters
            $chaps = $this->db->exec(
                "SELECT id, title, content FROM chapters
                 WHERE id IN ($placeholders) AND project_id = ?
                 ORDER BY order_index ASC, id ASC",
                array_merge($chapterIds, [$pid])
            ) ?: [];

            // Fetch all sub-chapters of selected chapters in one query
            $subChaps = $this->db->exec(
                "SELECT id, parent_id, title, content FROM chapters
                 WHERE parent_id IN ($placeholders) AND project_id = ?
                 ORDER BY order_index ASC, id ASC",
                array_merge($chapterIds, [$pid])
            ) ?: [];

            // Index sub-chapters by parent_id
            $subByParent = [];
            foreach ($subChaps as $sub) {
                $subByParent[(int)$sub['parent_id']][] = $sub;
            }

            foreach ($chaps as $ch) {
                $text = $this->htmlToPlainText($ch['content'] ?? '');
                $chapterContent .= "\n\n### Chapitre : " . $ch['title'] . "\n\n" . $text;
                // Append sub-chapters
                foreach ($subByParent[(int)$ch['id']] ?? [] as $sub) {
                    $subText = $this->htmlToPlainText($sub['content'] ?? '');
                    $chapterContent .= "\n\n#### " . $sub['title'] . "\n\n" . $subText;
                }
            }
        }
        if ($uploadedText) {
            $chapterContent .= "\n\n### Source uploadée\n\n" . $uploadedText;
        }

        if (empty($chapterContent) && empty($freeContext)) {
            echo json_encode(['success' => false, 'error' => 'Aucun contenu source (chapitres, fichier ou contexte libre).']);
            return;
        }

        // --- Characters (with description) ---
        $chars = $this->db->exec(
            'SELECT name, description FROM characters WHERE project_id = ? ORDER BY name ASC',
            [$pid]
        ) ?: [];
        $charLines = '';
        foreach ($chars as $c) {
            $desc = mb_substr($this->htmlToPlainText($c['description'] ?? ''), 0, 400);
            $charLines .= '- **' . $c['name'] . '**';
            if ($desc) {
                $charLines .= ' : ' . $desc;
            }
            $charLines .= "\n";
        }

        // --- Project title + description ---
        $projectRow   = $this->db->exec('SELECT title, description FROM projects WHERE id=?', [$pid])[0] ?? [];
        $projectTitle = $projectRow['title'] ?? '';
        $projectDesc  = mb_substr($this->htmlToPlainText($projectRow['description'] ?? ''), 0, 3000);

        // --- All chapter titles (story arc overview) ---
        $allChapters = $this->db->exec(
            'SELECT c.title, a.title AS act_title, c.order_index
             FROM chapters c
             LEFT JOIN acts a ON c.act_id = a.id
             WHERE c.project_id = ? AND c.parent_id IS NULL
             ORDER BY (c.act_id IS NULL) ASC, COALESCE(a.order_index, 9999) ASC, c.order_index ASC, c.id ASC',
            [$pid]
        ) ?: [];
        $arcLines = '';
        $lastAct = null;
        foreach ($allChapters as $ch) {
            if ($ch['act_title'] && $ch['act_title'] !== $lastAct) {
                $arcLines .= "\n**" . $ch['act_title'] . "**\n";
                $lastAct = $ch['act_title'];
            }
            $arcLines .= '- ' . $ch['title'] . "\n";
        }

        // --- Previous episodes ---
        $prevText = '';
        if (!empty($prevIds)) {
            $placeholders = implode(',', array_fill(0, count($prevIds), '?'));
            $prevEps = $this->db->exec(
                "SELECT title, content FROM scenarios
                 WHERE id IN ($placeholders) AND project_id = ?
                 ORDER BY id ASC",
                array_merge($prevIds, [$pid])
            ) ?: [];
            foreach ($prevEps as $ep) {
                $snippet = mb_substr($ep['content'], 0, 2000);
                $prevText .= "\n\n### Épisode précédent : " . $ep['title'] . "\n" . $snippet . "\n[...suite tronquée]";
            }
        }

        // --- Build user prompt ---
        $userPrompt  = "## DEMANDE D'ADAPTATION SCÉNARISTIQUE\n\n";
        $userPrompt .= "**Série** : " . $projectTitle . "\n";
        $userPrompt .= "**Auteur** : " . ($user['username'] ?? 'Inconnu') . "\n";
        $userPrompt .= "**Genre** : " . ($genre ?: 'Non spécifié') . "\n";
        $userPrompt .= "**Saison** : " . $saison . " | **Épisode** : " . $episode . "\n";
        if ($title) {
            $userPrompt .= "**Titre pressenti** : " . $title . "\n";
        }

        // Contraintes de fidélité — placées en tête pour que l'IA les prioritise
        $userPrompt .= "\n## ⚠️ CONTRAINTES ABSOLUES DE FIDÉLITÉ\n";
        $userPrompt .= "1. **Personnages** : N'utilise QUE les personnages listés ci-dessous. Ne crée aucun nouveau personnage, ne fusionne pas deux personnages, ne change pas leur rôle. Si un personnage n'est pas dans la liste, il n'existe pas dans cette série.\n";
        $userPrompt .= "2. **Événements** : Le scénario doit être une adaptation directe du/des chapitre(s) source fourni(s). Tu peux développer les ellipses et les scènes implicites, mais chaque événement inventé doit être cohérent avec la psychologie des personnages et l'intrigue du livre telle qu'elle est décrite dans le synopsis.\n";
        $userPrompt .= "3. **Fidélité au livre** : Ne trahis pas la trame du livre. Si le synopsis indique la fin ou des révélations importantes, ne les anticipe pas prématurément et ne les contredis pas.\n";
        $userPrompt .= "4. **Voix des personnages** : Chaque personnage doit parler et se comporter conformément à sa description. Un personnage timide ne fait pas de grandes tirades. Un antagoniste ne se comporte pas comme un allié.\n";

        if ($projectDesc) {
            $userPrompt .= "\n## SYNOPSIS DU LIVRE (contexte global — à respecter impérativement)\n" . mb_substr($projectDesc, 0, 2000) . "\n";
        }

        if ($charLines) {
            $charCount = count($chars);
            $userPrompt .= "\n## PERSONNAGES (liste exhaustive — {$charCount} personnage(s) autorisé(s) UNIQUEMENT)\n";
            $userPrompt .= $charLines;
            $userPrompt .= "\n> Ces personnages sont les SEULS qui existent dans cette série. Ne pas en inventer d'autres.\n";
        }

        if ($arcLines) {
            $userPrompt .= "\n## PLAN DU LIVRE (arc narratif global — pour situer l'épisode dans l'ensemble)\n" . $arcLines . "\n";
        }

        if ($prevText) {
            $userPrompt .= "\n## ÉPISODES PRÉCÉDENTS (continuité stricte à respecter)\n" . $prevText . "\n";
        }

        if ($chapterContent) {
            $userPrompt .= "\n## CHAPITRE(S) SOURCE À ADAPTER (base principale du scénario)\n" . $chapterContent . "\n";
        }

        if ($freeContext) {
            $userPrompt .= "\n## CONSIGNES SPÉCIFIQUES DE L'AUTEUR\n" . $freeContext . "\n";
        }

        $userPrompt .= "\nRédige maintenant le scénario complet de cet épisode en respectant scrupuleusement les contraintes ci-dessus.";

        // --- AI call ---
        $aiConfig = $this->loadAiConfig();
        $provider = $aiConfig['active_provider'] ?? 'openai';
        $apiKey   = $aiConfig['providers'][$provider]['api_key'] ?? '';
        $model    = $aiConfig['providers'][$provider]['model'] ?? 'gpt-4o';

        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Aucune clé API configurée. Rendez-vous dans Configuration IA.']);
            return;
        }

        $service = new AiService($provider, $apiKey, $model);
        $result  = $service->generate($systemPrompt, $userPrompt, 0.45, 12000);

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erreur IA inconnue.']);
            return;
        }

        // --- Convert Markdown → HTML for Quill storage ---
        $markdownText = $result['text'];
        // Strip any code fence wrapper the AI may have added around the entire screenplay
        $markdownText = preg_replace('/^```\w*\r?\n([\s\S]*)\r?\n```\s*$/m', '$1', trim($markdownText));
        $parsedown     = new Parsedown();
        $parsedown->setSafeMode(false);
        $generatedText = $parsedown->text($markdownText);
        // Strip outer <div> wrapper that Parsedown sometimes adds around block content
        $generatedText = preg_replace('/^\s*<div[^>]*>([\s\S]*)<\/div>\s*$/i', '$1', trim($generatedText));

        // --- Episode title ---
        $sNum = str_pad($saison, 2, '0', STR_PAD_LEFT);
        $eNum = str_pad($episode, 2, '0', STR_PAD_LEFT);
        $episodeTitle = "S{$sNum}E{$eNum}";
        if ($title) {
            $episodeTitle .= ' — ' . $title;
        }

        // --- Save to scenarios table ---
        $sm = new Scenario();
        $sm->project_id           = $pid;
        $sm->title                = $episodeTitle;
        $sm->content              = $generatedText;
        $sm->saison               = $saison;
        $sm->episode              = $episode;
        $sm->genre                = $genre;
        $sm->source_chapter_ids   = json_encode($chapterIds, JSON_UNESCAPED_UNICODE);
        $sm->previous_episode_ids = json_encode($prevIds, JSON_UNESCAPED_UNICODE);
        $sm->markdown             = $markdownText;
        $sm->order_index          = 0;
        $sm->save();
        $scenarioId = $sm->id;

        // --- Log AI usage ---
        if (!empty($result['prompt_tokens'])) {
            $this->db->exec(
                'INSERT INTO ai_usage (user_id, model_name, prompt_tokens, completion_tokens, total_tokens, feature_name)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $user['id'], $model,
                    (int)$result['prompt_tokens'],
                    (int)$result['completion_tokens'],
                    (int)($result['prompt_tokens'] + $result['completion_tokens']),
                    'scenariste',
                ]
            );
        }

        echo json_encode([
            'success'     => true,
            'scenario_id' => $scenarioId,
            'title'       => $episodeTitle,
            'redirect'    => '/scenariste/' . $scenarioId . '/edit',
        ]);
    }

    /**
     * GET /scenariste/@id/edit
     */
    public function edit()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $scenarioModel = new Scenario();
        $scenarios = $scenarioModel->findAndCast(['id=?', $id]);
        if (!$scenarios) {
            $this->f3->error(404);
            return;
        }
        $scenario = $scenarios[0];

        $projectModel = new Project();
        $projects = $projectModel->findAndCast(['id=? AND user_id=?', $scenario['project_id'], $user['id']]);
        if (!$projects) {
            $this->f3->error(403);
            return;
        }
        $project = $projects[0];

        // Build meta array for template backward-compat (@meta.saison, @meta.episode, @meta.genre)
        $meta = [
            'saison'  => $scenario['saison']  ?? '',
            'episode' => $scenario['episode'] ?? '',
            'genre'   => $scenario['genre']   ?? '',
        ];

        $this->render('scenariste/edit.html', [
            'title'   => 'Scénario — ' . $scenario['title'],
            'note'    => $scenario,
            'project' => $project,
            'meta'    => $meta,
            'saved'   => isset($_GET['saved']),
        ]);
    }

    /**
     * POST /scenariste/@id/save
     */
    public function save()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $scenarioModel = new Scenario();
        $scenarioModel->load(['id=?', $id]);
        if ($scenarioModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $scenarioModel->project_id, $user['id']])) {
            $this->f3->error(403);
            return;
        }

        $scenarioModel->title   = trim($_POST['title'] ?? '') ?: $scenarioModel->title;
        $scenarioModel->content = $_POST['content'] ?? '';
        $scenarioModel->save();

        if ($this->f3->get('AJAX')) {
            echo json_encode(['success' => true]);
            exit;
        }

        $this->f3->reroute('/scenariste/' . $id . '/edit?saved=1');
    }

    /**
     * GET /scenariste/@id/delete
     */
    public function delete()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $scenarioModel = new Scenario();
        $scenarioModel->load(['id=?', $id]);
        if ($scenarioModel->dry()) {
            $this->f3->reroute('/dashboard');
            return;
        }

        $pid = (int) $scenarioModel->project_id;
        $projectModel = new Project();
        if ($projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            $scenarioModel->erase();
        }

        $this->f3->reroute('/project/' . $pid);
    }

    /**
     * GET /scenariste/@id/download
     * Download the screenplay as a .md file
     */
    public function download()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $scenarioModel = new Scenario();
        $scenarios = $scenarioModel->findAndCast(['id=?', $id]);
        if (!$scenarios) {
            $this->f3->error(404);
            return;
        }
        $scenario = $scenarios[0];

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $scenario['project_id'], $user['id']])) {
            $this->f3->error(403);
            return;
        }

        $saison  = str_pad($scenario['saison']  ?? '01', 2, '0', STR_PAD_LEFT);
        $episode = str_pad($scenario['episode'] ?? '01', 2, '0', STR_PAD_LEFT);

        // Slug from title (remove SxxEyy prefix if present)
        $rawTitle = preg_replace('/^S\d+E\d+\s*[—-]?\s*/', '', $scenario['title']);
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strip_tags($rawTitle)));
        $slug = trim($slug, '-') ?: 'episode';
        $filename = "S{$saison}E{$episode}_{$slug}.md";

        // Prefer raw markdown column; fallback to stripping HTML content
        $content = $scenario['markdown'] ?? strip_tags($scenario['content'] ?? '');
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    /**
     * POST /project/@pid/scenariste/upload-source  (AJAX)
     * Upload a .md or .txt source file, save it via ProjectFile system, return content
     */
    public function uploadSource()
    {
        header('Content-Type: application/json');
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']);
            return;
        }

        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['md', 'txt'])) {
            echo json_encode(['success' => false, 'error' => 'Format non supporté. Utilisez .md ou .txt']);
            return;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 2 Mo)']);
            return;
        }

        // Save via ProjectFile system
        $userFilesDir = $this->f3->get('ROOT') . '/' . $this->getUserDataDir($user['email']) . '/files';
        if (!is_dir($userFilesDir)) {
            mkdir($userFilesDir, 0755, true);
        }

        $safeName   = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $uniqueName = $safeName . '_' . time() . '.' . $ext;
        $filepath   = $userFilesDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement']);
            return;
        }

        $relativePath = $this->getUserDataDir($user['email']) . '/files/' . $uniqueName;

        $fileModel             = new ProjectFile();
        $fileModel->project_id = $pid;
        $fileModel->filename   = $file['name'];
        $fileModel->filepath   = $relativePath;
        $fileModel->filetype   = 'text/' . $ext;
        $fileModel->filesize   = $file['size'];
        $fileModel->comment    = 'Source scénario';
        $fileModel->save();

        $content = file_get_contents($filepath);

        echo json_encode([
            'success'  => true,
            'filename' => $file['name'],
            'file_id'  => $fileModel->id,
            'content'  => $content,
        ]);
    }

    /**
     * Convert Quill HTML content to clean plain text, preserving paragraph structure.
     * <p> → double newline, <br> → newline, headings → keep text with newline.
     */
    private function htmlToPlainText(string $html): string
    {
        if (empty(trim($html))) return '';
        // Block elements → newlines before stripping tags
        $html = preg_replace('/<\/p\s*>/i',       "\n\n", $html);
        $html = preg_replace('/<br\s*\/?>/i',      "\n",   $html);
        $html = preg_replace('/<\/h[1-6]\s*>/i',   "\n\n", $html);
        $html = preg_replace('/<\/li\s*>/i',        "\n",   $html);
        $html = preg_replace('/<\/blockquote\s*>/i',"\n\n", $html);
        $html = strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Collapse more than 3 consecutive newlines
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim($html);
    }

    /**
     * Load user AI config (same format as AiController::getUserConfig)
     */
    private function loadAiConfig(): array
    {
        $user = $this->currentUser();
        $dir  = $this->getUserDataDir($user['email']);
        $file = $this->f3->get('ROOT') . '/' . $dir . '/ai_config.json';

        $defaults = ['active_provider' => 'openai', 'providers' => []];

        if (file_exists($file)) {
            $loaded = json_decode(file_get_contents($file), true);
            if (is_array($loaded)) {
                return array_merge($defaults, $loaded);
            }
        }

        return $defaults;
    }
}
