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
            'title' => 'Nouveau chapitre',
            'project' => $project,
            'acts' => $acts,
            'chapters' => $chapters,
            'old' => ['parent_id' => $parentId, 'act_id' => $actId, 'title' => ''],
            'errors' => []
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
            'title' => 'Nouveau chapitre',
            'project' => $project,
            'acts' => $acts,
            'chapters' => $chapters,
            'errors' => $errors,
            'old' => ['parent_id' => $parentId, 'act_id' => $actId, 'title' => $title],
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

        $pid = $chapterModel->project_id;
        $chapterModel->erase();
        $this->f3->reroute('/project/' . $pid);
    }

    public function versions()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);
        if ($chapterModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $versions = $this->db->exec(
            'SELECT id, word_count, created_at FROM chapter_versions WHERE chapter_id = ? ORDER BY created_at DESC',
            [$cid]
        ) ?: [];

        $this->render('editor/versions.html', [
            'title'    => 'Historique — ' . $chapterModel->title,
            'chapter'  => $chapterModel->cast(),
            'versions' => $versions,
        ]);
    }

    public function previewVersion()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        $vid = (int) $this->f3->get('PARAMS.vid');

        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);
        if ($chapterModel->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Chapitre introuvable']);
            exit;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            exit;
        }

        $rows = $this->db->exec(
            'SELECT content, word_count, created_at FROM chapter_versions WHERE id = ? AND chapter_id = ?',
            [$vid, $cid]
        );
        if (!$rows) {
            http_response_code(404);
            echo json_encode(['error' => 'Version introuvable']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'content'    => $rows[0]['content'],
            'word_count' => (int) $rows[0]['word_count'],
            'created_at' => $rows[0]['created_at'],
        ]);
        exit;
    }

    public function deleteVersion()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        $vid = (int) $this->f3->get('PARAMS.vid');

        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);
        if ($chapterModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $this->db->exec(
            'DELETE FROM chapter_versions WHERE id = ? AND chapter_id = ?',
            [$vid, $cid]
        );

        $this->f3->reroute('/chapter/' . $cid . '/versions');
    }

    public function restore()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        $vid = (int) $this->f3->get('PARAMS.vid');

        $chapterModel = new Chapter();
        $chapterModel->load(['id=?', $cid]);
        if ($chapterModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapterModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $rows = $this->db->exec(
            'SELECT content, word_count FROM chapter_versions WHERE id = ? AND chapter_id = ?',
            [$vid, $cid]
        );
        if (!$rows) {
            $this->f3->error(404);
            return;
        }

        $chapterModel->content   = $rows[0]['content'];
        $chapterModel->word_count = (int) $rows[0]['word_count'];
        $chapterModel->save();

        $this->f3->set('SESSION.success', 'Version restaurée avec succès.');
        $this->f3->reroute('/chapter/' . $cid);
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
