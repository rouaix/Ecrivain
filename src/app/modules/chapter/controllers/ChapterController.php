<?php

class ChapterController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function listAll()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);
        if (!$project) { $this->f3->error(403); return; }
        $project = $project[0];

        $chapterModel = new Chapter();
        $chapters = $chapterModel->getAllByProject($pid);

        $actModel = new Act();
        $acts = $actModel->getAllByProject($pid);

        // Build sub-chapter map keyed by parent_id
        $subMap   = [];
        $topLevel = [];
        foreach ($chapters as $ch) {
            if ($ch['parent_id']) {
                $subMap[$ch['parent_id']][] = $ch;
            } else {
                $topLevel[] = $ch;
            }
        }

        // Embed sub_chapters array directly in each top-level chapter
        foreach ($topLevel as &$ch) {
            $ch['sub_chapters'] = $subMap[$ch['id']] ?? [];
        }
        unset($ch);

        $chaptersJson = json_encode(array_map(fn($c) => [
            'id'        => (int)$c['id'],
            'title'     => $c['title'],
            'content'   => $c['content'] ?? '',
            'resume'    => $c['resume'] ?? '',
            'act_title' => $c['act_title'] ?? '',
            'parent_id' => $c['parent_id'],
        ], $chapters));

        $this->render('chapter/list.html', [
            'title'        => 'Chapitres — ' . $project['title'],
            'project'      => $project,
            'topLevel'     => $topLevel,
            'acts'         => $acts,
            'chaptersJson' => $chaptersJson,
            'isOwner'      => $this->isOwner($pid),
        ]);
    }

    public function create()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        $actModel = new Act();
        $acts = $actModel->getAllByProject($pid);

        $chapterModel = new Chapter();
        $chapters = $chapterModel->getTopLevelByProject($pid);

        $parentId = !empty($_GET['parent_id']) ? (int) $_GET['parent_id'] : null;
        $actId = !empty($_GET['act_id']) ? (int) $_GET['act_id'] : null;

        $this->render('chapter/create.html', [
            'title'     => 'Nouveau chapitre',
            'project'   => $project,
            'acts'      => $acts,
            'chapters'  => $chapters,
            'old'       => ['parent_id' => $parentId, 'act_id' => $actId, 'title' => ''],
            'errors'    => [],
            'activeTab' => 'create',
        ]);
    }

    public function store()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $actId = !empty($_POST['act_id']) ? (int) $_POST['act_id'] : null;
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre du chapitre est obligatoire.';
        }

        if (empty($errors)) {
            $chapterModel = new Chapter();
            // Inherit act from parent if not set
            if ($parentId && $actId === null) {
                // We need to load parent to check its act_id
                $parent = $chapterModel->findAndCast(['id=?', $parentId]);
                if ($parent) {
                    $actId = (int) $parent[0]['act_id'] ?: null;
                }
            }

            $cid = $chapterModel->create($pid, $title, $actId, $parentId);
            if ($cid) {
                $this->logActivity($pid, 'create', 'chapter', $cid, $title);
                $this->f3->set('SESSION.success', 'Chapitre créé avec succès.');
                $this->f3->reroute('/chapter/' . $cid);
            } else {
                $errors[] = 'Impossible de créer le chapitre.';
            }
        }

        // Need to reload data for view
        $project = $projectModel->findAndCast(['id=?', $pid])[0];
        $actModel = new Act();
        $acts = $actModel->getAllByProject($pid);
        $chapterModel = new Chapter();
        $chapters = $chapterModel->getTopLevelByProject($pid);

        $this->render('chapter/create.html', [
            'title'     => 'Nouveau chapitre',
            'project'   => $project,
            'acts'      => $acts,
            'chapters'  => $chapters,
            'errors'    => $errors,
            'old'       => ['parent_id' => $parentId, 'act_id' => $actId, 'title' => $title],
            'activeTab' => 'create',
        ]);
    }

    public function show()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);
        if ($chapterModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $user = $this->currentUser();
        // Ownership check & Get Project
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $chapterModel->project_id, $user['id']]);
        if (!$project) {
            $this->f3->error(403);
            return;
        }
        $project = $project[0];

        // Additional data for the view
        $actModel = new Act();
        $acts = $actModel->getAllByProject($project['id']);

        // Context: Current Act
        $currentAct = null;
        if ($chapterModel->act_id) {
            foreach ($acts as $a) {
                if ($a['id'] == $chapterModel->act_id) {
                    $currentAct = $a;
                    break;
                }
            }
        }

        // Context: Parent Chapter (for subchapters)
        $parentChapter = null;
        if ($chapterModel->parent_id) {
            $parent = new Chapter();
            $parent->load(['id=?', $chapterModel->parent_id]);
            if (!$parent->dry()) {
                $parentChapter = $parent->cast();
            }
        }

        // Top level chapters for "Parent" dropdown
        $topChapters = $chapterModel->getTopLevelByProject($project['id']);

        // Comments
        $commentModel = new Comment();
        $comments = $commentModel->getByChapter($cid);

        // Check for session success msg
        $success = $this->f3->get('SESSION.success');
        $this->f3->clear('SESSION.success');

        $chapterData = $chapterModel->cast();
        $chapterData['comment'] = $this->getChapterComment($cid);

        // Build ignored-words list: project dictionary + character names
        $ignoredWords = [];
        $projRow = $this->db->exec('SELECT ignored_words FROM projects WHERE id=?', [$project['id']]);
        if ($projRow && !empty($projRow[0]['ignored_words'])) {
            $ignoredWords = json_decode($projRow[0]['ignored_words'], true) ?: [];
        }
        $charRows = $this->db->exec(
            'SELECT name FROM characters WHERE project_id=? AND name IS NOT NULL AND name != \'\'',
            [$project['id']]
        ) ?: [];
        foreach ($charRows as $cr) {
            $ignoredWords[] = $cr['name'];
        }
        $ignoredWords = array_values(array_unique($ignoredWords));

        $subChapterCount = $chapterModel->count(['parent_id=?', $cid]);

        $this->render('editor/edit.html', [
            'title' => $chapterModel->title,
            'chapter' => $chapterData,
            'project' => $project,
            'acts' => $acts,
            'currentAct' => $currentAct,
            'parentChapter' => $parentChapter,
            'topChapters' => $topChapters,
            'comments' => $comments,
            'errors' => [],
            'success' => $success,
            'ignoredWords' => json_encode($ignoredWords),
            'subChapterCount' => $subChapterCount,
            'bodyClass' => 'editor-mode',
        ]);
    }

    public function update()
    {
        $cid = (int) $this->f3->get('PARAMS.id');

        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);

        if ($chapterModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $content = $this->cleanQuillHtml($content);
        // Security: Removed html_entity_decode to prevent XSS (vulnerability #10)
        // Content is already encoded by client editor (TinyMCE/Quill)
        $actId = !empty($_POST['act_id']) ? (int) $_POST['act_id'] : null;
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

        $resume = $_POST['resume'] ?? '';
        $resume = $this->cleanQuillHtml($resume);

        // Security: Sanitize comment to prevent XSS (vulnerability #11)
        $comment = $_POST['comment'] ?? '';
        $comment = $this->cleanQuillHtml($comment);
        $comment = strip_tags($comment); // Remove all HTML tags
        $comment = trim($comment);
        $comment = mb_substr($comment, 0, 5000); // Limit to 5000 characters

        $chapterModel->title = $title;
        $chapterModel->content = $content;
        $chapterModel->resume = $resume;
        $chapterModel->comment = $comment;

        // Check if context (parent or act) changed
        if ($chapterModel->act_id != $actId || $chapterModel->parent_id != $parentId) {
            $oldParentVal = (int) ($chapterModel->parent_id ?: 0);
            $newParentVal = (int) ($parentId ?: 0);

            // Inherit act from new parent if parent is changed and act is not explicitly correct/set?
            // Actually, we should force the act_id to be consistent with the parent chapter.
            if ($parentId) {
                // Determine act_id of the new parent
                $params = ['id=?', $parentId];
                // Since specific mapper not available here easily without new query, let's query.
                // Or simplified: Just don't rely on POST act_id if parent_id is set. Use parent's act.
                $parentCheck = $chapterModel->findAndCast($params);
                if ($parentCheck) {
                    $actId = (int) ($parentCheck[0]['act_id'] ?? null) ?: null;
                }
            }

            // If new parent ID > old parent ID (moving "forward"), prepend to start.
            // Otherwise append to end.
            if ($newParentVal > $oldParentVal) {
                $chapterModel->shiftOrderDown($chapterModel->project_id, $actId, $parentId);
                $chapterModel->order_index = 1;
            } else {
                $chapterModel->order_index = $chapterModel->getNextOrder(
                    $chapterModel->project_id,
                    $actId,
                    $parentId
                );
            }
        }

        $chapterModel->act_id = $actId;
        $chapterModel->parent_id = $parentId;

        // Compute word count from plain text
        $wordCount = $this->countWords($content);
        $chapterModel->word_count = $wordCount;

        $chapterModel->save();
        $this->logActivity($chapterModel->project_id, 'update', 'chapter', $cid, $title);

        // Comment already sanitized above and saved via ORM
        // This direct SQL update appears redundant but kept for compatibility
        $this->db->exec('UPDATE chapters SET `comment`=? WHERE id=?', [$comment, $cid]);

        // Record daily writing snapshot (non-blocking: table may not exist yet)
        $user = $this->currentUser();
        try {
            $this->db->exec(
                'INSERT INTO writing_stats (user_id, chapter_id, project_id, stat_date, word_count)
                 VALUES (?, ?, ?, CURDATE(), ?)
                 ON DUPLICATE KEY UPDATE word_count = VALUES(word_count)',
                [$user['id'], $cid, $chapterModel->project_id, $wordCount]
            );
        } catch (Exception $e) {
            error_log('writing_stats insert failed: ' . $e->getMessage());
        }

        // Save version snapshot, keep last 10 (non-blocking)
        try {
            $this->db->exec(
                'INSERT INTO chapter_versions (chapter_id, content, word_count) VALUES (?, ?, ?)',
                [$cid, $content, $wordCount]
            );
            $this->db->exec(
                'DELETE FROM chapter_versions WHERE chapter_id = ? AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM chapter_versions WHERE chapter_id = ? ORDER BY created_at DESC LIMIT 10
                    ) AS keep
                )',
                [$cid, $cid]
            );
        } catch (Exception $e) {
            error_log('chapter_versions insert failed: ' . $e->getMessage());
        }

        // Bust AI context cache so the next ask() rebuilds fresh project context
        unset($_SESSION['_ai_ctx_' . $chapterModel->project_id]);

        if ($this->f3->get('AJAX')) {
            echo json_encode(['status' => 'ok']);
            exit;
        }

        // Ne plus afficher le message de succès pour éviter la duplication avec l'indicateur d'auto-sauvegarde
        // $this->f3->set('SESSION.success', 'Chapitre enregistré.');
        $this->f3->reroute('/chapter/' . $cid);
    }

    public function comment()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        // Handle JSON input
        $json = json_decode($this->f3->get('BODY'), true);

        $content = $json['content'] ?? '';
        $start = (int) ($json['start'] ?? 0);
        $end = (int) ($json['end'] ?? 0);

        if (!$content) {
            http_response_code(400);
            return;
        }

        // SECURITY: Verify ownership before adding comment (fixes IDOR vulnerability #15)
        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);

        if ($chapterModel->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Chapitre introuvable']);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $commentModel = new Comment();
        $commentModel->chapter_id = $cid;
        $commentModel->content = $content;
        $commentModel->start_pos = $start;
        $commentModel->end_pos = $end;
        $commentModel->created_at = date('Y-m-d H:i:s');
        $commentModel->save();

        echo json_encode(['status' => 'ok']);
    }

    public function deleteComment()
    {
        header('Content-Type: application/json');
        $cid = (int) $this->f3->get('PARAMS.cid');

        $commentModel = new Comment();
        $commentModel->load(['id=?', $cid]);

        if ($commentModel->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Annotation introuvable']);
            return;
        }

        // Verify ownership via chapter
        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $commentModel->chapter_id]);
        if ($chapterModel->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Chapitre introuvable']);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $chapterId = $commentModel->chapter_id;
        $commentModel->erase();

        // Return updated list
        $comments = (new Comment())->getByChapter($chapterId);
        echo json_encode($comments);
    }

    public function getComments()
    {
        $cid = (int) $this->f3->get('PARAMS.id');

        // SECURITY: Verify ownership before returning comments (fixes IDOR vulnerability #15)
        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);

        if ($chapterModel->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Chapitre introuvable']);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $commentModel = new Comment();
        $comments = $commentModel->getByChapter($cid);
        echo json_encode($comments);
    }

    public function synonyms()
    {
        $word = $this->f3->get('PARAMS.word');
        $results = \Synonyms::get($word);
        echo json_encode($results);
    }

    public function delete()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);

        if ($chapterModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $pid   = $chapterModel->project_id;
        $label = $chapterModel->title;
        $chapterModel->erase();
        $this->logActivity($pid, 'delete', 'chapter', $cid, $label);
        $this->f3->reroute('/project/' . $pid);
    }

    // versions(), preview(), deleteVersion(), restore() → ChapterVersionController

    public function import()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }

        $actId    = !empty($_POST['act_id'])    ? (int) $_POST['act_id']    : null;
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $manualTitle = trim($_POST['title'] ?? '');

        $errors = [];
        $html   = '';
        $extractedTitle = '';

        $hasFile    = isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK;
        $pastedText = trim($_POST['import_text'] ?? '');

        if (!$hasFile && $pastedText === '') {
            $errors[] = 'Veuillez fournir un fichier ou coller du texte.';
        }

        if (empty($errors)) {
            if ($hasFile) {
                $file = $_FILES['import_file'];
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['md', 'txt', 'docx', 'odt'])) {
                    $errors[] = 'Format non supporté. Utilisez .md, .txt, .docx ou .odt.';
                } elseif ($file['size'] > 10 * 1024 * 1024) {
                    $errors[] = 'Fichier trop volumineux (max 10 Mo).';
                } else {
                    $raw = file_get_contents($file['tmp_name']);
                    [$extractedTitle, $html] = $this->parseImportContent($raw, $ext);
                }
            } else {
                $format = in_array($_POST['import_format'] ?? '', ['md', 'txt']) ? $_POST['import_format'] : 'md';
                [$extractedTitle, $html] = $this->parseImportContent($pastedText, $format);
            }
        }

        if (empty($errors) && trim(strip_tags($html)) === '') {
            $errors[] = 'Le contenu importé est vide ou n\'a pas pu être lu.';
        }

        $title = $manualTitle !== '' ? $manualTitle : $extractedTitle;
        if (empty($errors) && $title === '') {
            $errors[] = 'Impossible de détecter un titre. Veuillez le saisir dans le champ "Titre".';
        }

        if (empty($errors)) {
            $chapterModel = new Chapter();
            if ($parentId && $actId === null) {
                $parent = $chapterModel->findAndCast(['id=?', $parentId]);
                if ($parent) {
                    $actId = (int) $parent[0]['act_id'] ?: null;
                }
            }
            $cid = $chapterModel->create($pid, $title, $actId, $parentId);
            if ($cid) {
                $cleanHtml = $this->cleanQuillHtml($html);
                $wc = $this->countWords($cleanHtml);
                $this->db->exec(
                    'UPDATE chapters SET content=?, word_count=? WHERE id=?',
                    [$cleanHtml, $wc, $cid]
                );
                $this->logActivity($pid, 'create', 'chapter', $cid, $title);
                $this->f3->set('SESSION.success', 'Chapitre importé avec succès.');
                $this->f3->reroute('/chapter/' . $cid);
            } else {
                $errors[] = 'Impossible de créer le chapitre.';
            }
        }

        $project = $projectModel->findAndCast(['id=?', $pid])[0];
        $actModel = new Act();
        $acts = $actModel->getAllByProject($pid);
        $chapterModel = new Chapter();
        $chapters = $chapterModel->getTopLevelByProject($pid);

        $this->render('chapter/create.html', [
            'title'     => 'Nouveau chapitre',
            'project'   => $project,
            'acts'      => $acts,
            'chapters'  => $chapters,
            'errors'    => $errors,
            'old'       => ['parent_id' => $parentId, 'act_id' => $actId, 'title' => $manualTitle],
            'activeTab' => 'import',
        ]);
    }

    private function parseImportContent(string $raw, string $format): array
    {
        $title = '';
        $html  = '';

        switch ($format) {
            case 'md':
                if (preg_match('/^#\s+(.+)/m', $raw, $m)) {
                    $title = trim($m[1]);
                    $raw   = preg_replace('/^#\s+.+\r?\n?/m', '', $raw, 1);
                }
                $pd = new Parsedown();
                $pd->setSafeMode(true);
                $html = $pd->text(trim($raw));
                break;

            case 'txt':
                $lines = preg_split('/\r\n|\n|\r/', $raw);
                foreach ($lines as $i => $line) {
                    if (trim($line) !== '') {
                        $title = trim($line);
                        array_splice($lines, $i, 1);
                        break;
                    }
                }
                $paragraphs = preg_split('/\n{2,}/', trim(implode("\n", $lines)));
                foreach ($paragraphs as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $html .= '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>';
                    }
                }
                break;

            case 'docx':
                $html = $this->parseDocx($raw, $title);
                break;

            case 'odt':
                $html = $this->parseOdt($raw, $title);
                break;
        }

        return [$title, $html];
    }

    private function parseDocx(string $raw, string &$title): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tmp, $raw);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            unlink($tmp);
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tmp);
        return $xml !== false ? $this->docxXmlToHtml($xml, $title) : '';
    }

    private function docxXmlToHtml(string $xml, string &$title): string
    {
        $prev = libxml_use_internal_errors(true);
        $dom  = new DOMDocument();
        $dom->loadXML($xml);
        libxml_use_internal_errors($prev);

        $wNS   = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $html  = '';
        $titleFound = false;

        $body = $dom->getElementsByTagNameNS($wNS, 'body');
        if (!$body->length) {
            return '';
        }

        foreach ($body->item(0)->getElementsByTagNameNS($wNS, 'p') as $para) {
            $styleEl = $para->getElementsByTagNameNS($wNS, 'pStyle');
            $style   = $styleEl->length ? strtolower($styleEl->item(0)->getAttributeNS($wNS, 'val')) : '';

            $text = '';
            foreach ($para->getElementsByTagNameNS($wNS, 'r') as $run) {
                foreach ($run->getElementsByTagNameNS($wNS, 't') as $t) {
                    $text .= $t->nodeValue;
                }
            }
            if (trim($text) === '') {
                continue;
            }

            $escaped  = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            $isBold   = $para->getElementsByTagNameNS($wNS, 'b')->length > 0;
            $isItalic = $para->getElementsByTagNameNS($wNS, 'i')->length > 0;
            if ($isItalic) $escaped = "<em>$escaped</em>";
            if ($isBold)   $escaped = "<strong>$escaped</strong>";

            if (str_starts_with($style, 'heading') || $style === 'title') {
                $level = max(1, min(6, (int) substr($style, -1) ?: 1));
                if (!$titleFound && $level === 1) {
                    $title = $text;
                    $titleFound = true;
                    continue;
                }
                $html .= "<h$level>$escaped</h$level>";
            } else {
                $html .= "<p>$escaped</p>";
            }
        }

        return $html;
    }

    private function parseOdt(string $raw, string &$title): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'odt_');
        file_put_contents($tmp, $raw);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            unlink($tmp);
            return '';
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        unlink($tmp);
        return $xml !== false ? $this->odtXmlToHtml($xml, $title) : '';
    }

    private function odtXmlToHtml(string $xml, string &$title): string
    {
        $prev = libxml_use_internal_errors(true);
        $dom  = new DOMDocument();
        $dom->loadXML($xml);
        libxml_use_internal_errors($prev);

        $textNS   = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
        $officeNS = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
        $html     = '';
        $titleFound = false;

        $bodyList = $dom->getElementsByTagNameNS($officeNS, 'text');
        if (!$bodyList->length) {
            return '';
        }

        foreach ($bodyList->item(0)->childNodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            $text = $node->textContent;
            if (trim($text) === '') {
                continue;
            }
            $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            if ($node->localName === 'h') {
                $level = max(1, min(6, (int) ($node->getAttributeNS($textNS, 'outline-level') ?: 1)));
                if (!$titleFound && $level === 1) {
                    $title = $text;
                    $titleFound = true;
                    continue;
                }
                $html .= "<h$level>$escaped</h$level>";
            } else {
                $html .= "<p>$escaped</p>";
            }
        }

        return $html;
    }

    private function getChapterComment(int $cid): string
    {
        $rows = $this->db->exec('SELECT `comment` FROM chapters WHERE id=?', [$cid]);
        if (!$rows || !isset($rows[0]['comment'])) {
            return '';
        }
        return (string) $rows[0]['comment'];
    }

    private function countWords(string $html): int
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') return 0;
        return count(explode(' ', $text));
    }
}
