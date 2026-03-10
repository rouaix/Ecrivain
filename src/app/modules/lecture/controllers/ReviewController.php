<?php

class ReviewController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Build the ordered content list for a project (same logic as LectureController).
     * Returns null if project not found or not owned by current user.
     */
    private function loadContent(int $pid): ?array
    {
        if (!$this->hasProjectAccess($pid)) return null;

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) return null;
        $project = $project[0];

        $db = $this->f3->get('DB');

        // Template system
        $templateElements = [];
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId   = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            $template = $templateId
                ? ($templateModel->findAndCast(['id=?', $templateId])[0] ?? $templateModel->getDefault())
                : $templateModel->getDefault();
            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        // Fetch content
        $chapterModel = new Chapter();
        $allChapters  = $chapterModel->getAllByProject($pid);

        $actModel = new Act();
        $acts     = $actModel->getAllByProject($pid);

        $sectionModel   = new Section();
        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $sectionsAfter  = $sectionModel->getAfterChapters($pid);

        $noteModel = new Note();
        $notes     = array_values(array_filter(
            $noteModel->getAllByProject($pid),
            fn($n) => ($n['type'] ?? 'note') !== 'scenario'
        ));

        $scenarioModel = new Scenario();
        $scenarios     = $scenarioModel->getAllByProject($pid);

        // Chapter hierarchy
        $chaptersByAct        = [];
        $chaptersWithoutAct   = [];
        $subChaptersByParent  = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) $subChaptersByParent[$ch['parent_id']][] = $ch;
        }
        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                if ($ch['act_id']) $chaptersByAct[$ch['act_id']][] = $ch;
                else $chaptersWithoutAct[] = $ch;
            }
        }

        // Custom elements
        $customElementsByType      = [];
        $customSubElementsByParent = [];
        if ($db->exists('elements')) {
            $elementModel = new Element();
            foreach ($elementModel->getAllByProject($pid) as $elem) {
                if ($elem['parent_id']) {
                    $customSubElementsByParent[$elem['parent_id']][] = $elem;
                } else {
                    $customElementsByType[$elem['template_element_id']][] = $elem;
                }
            }
        }

        $prepare = function ($content) {
            return $this->cleanQuillHtml(html_entity_decode($content ?? ''));
        };

        $prepareScenario = function ($content) {
            $html = html_entity_decode($content ?? '');
            $html = preg_replace_callback(
                '/<pre[^>]*>(?:<code[^>]*>)?([\s\S]*?)(?:<\/code>)?<\/pre>/i',
                function ($m) {
                    $text = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                    $lines = preg_split('/\r?\n/', trim($text));
                    $result = '';
                    foreach ($lines as $line) {
                        if (trim($line) !== '') {
                            $result .= '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
                        }
                    }
                    return $result ?: '';
                },
                $html
            );
            return $this->cleanQuillHtml($html);
        };

        $addChapter = function ($ch, &$items) use ($prepare, &$subChaptersByParent) {
            if (!($ch['is_exported'] ?? 1)) return;
            $items[] = [
                'type'    => 'chapter',
                'id'      => $ch['id'],
                'title'   => $ch['title'],
                'content' => $prepare($ch['content']),
            ];
            foreach ($subChaptersByParent[$ch['id']] ?? [] as $sub) {
                if (!($sub['is_exported'] ?? 1)) continue;
                $items[] = [
                    'type'    => 'chapter',
                    'id'      => $sub['id'],
                    'title'   => $sub['title'],
                    'content' => $prepare($sub['content']),
                ];
            }
        };

        $items = [];

        foreach ($templateElements as $elem) {
            if (!$elem['is_enabled']) continue;

            switch ($elem['element_type']) {
                case 'section':
                    $sections = ($elem['section_placement'] === 'before') ? $sectionsBefore : $sectionsAfter;
                    foreach ($sections as $sec) {
                        if ($sec['type'] !== $elem['element_subtype']) continue;
                        if (!($sec['is_exported'] ?? 1)) continue;
                        $items[] = [
                            'type'    => 'section',
                            'id'      => $sec['id'],
                            'title'   => $sec['title'] ?: Section::getTypeName($sec['type']),
                            'content' => $prepare($sec['content']),
                        ];
                    }
                    break;

                case 'act':
                    foreach ($acts as $act) {
                        if (!empty($act['content']) && ($act['is_exported'] ?? 1)) {
                            $items[] = [
                                'type'    => 'act',
                                'id'      => $act['id'],
                                'title'   => $act['title'],
                                'content' => $prepare($act['content']),
                            ];
                        }
                        foreach ($chaptersByAct[$act['id']] ?? [] as $ch) {
                            $addChapter($ch, $items);
                        }
                    }
                    break;

                case 'chapter':
                    foreach ($chaptersWithoutAct as $ch) {
                        $addChapter($ch, $items);
                    }
                    break;

                case 'note':
                    foreach ($notes as $note) {
                        if (!($note['is_exported'] ?? 1)) continue;
                        $items[] = [
                            'type'    => 'note',
                            'id'      => $note['id'],
                            'title'   => $note['title'],
                            'content' => $prepare($note['content']),
                        ];
                    }
                    break;

                case 'scenario':
                    foreach ($scenarios as $sc) {
                        if (!($sc['is_exported'] ?? 1)) continue;
                        $items[] = [
                            'type'    => 'scenario',
                            'id'      => $sc['id'],
                            'title'   => $sc['title'],
                            'content' => $prepareScenario($sc['content']),
                        ];
                    }
                    break;

                case 'element':
                    foreach ($customElementsByType[$elem['id']] ?? [] as $e) {
                        if (!($e['is_exported'] ?? 1)) continue;
                        $items[] = [
                            'type'    => 'element',
                            'id'      => $e['id'],
                            'title'   => $e['title'],
                            'content' => $prepare($e['content']),
                        ];
                        foreach ($customSubElementsByParent[$e['id']] ?? [] as $sub) {
                            if (!($sub['is_exported'] ?? 1)) continue;
                            $items[] = [
                                'type'    => 'element',
                                'id'      => $sub['id'],
                                'title'   => $sub['title'],
                                'content' => $prepare($sub['content']),
                            ];
                        }
                    }
                    break;
            }
        }

        return ['project' => $project, 'items' => $items];
    }

    /**
     * Main review mode page.
     */
    public function review()
    {
        $pid  = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $data = $this->loadContent($pid);
        if (!$data) {
            $this->f3->error(404);
            return;
        }

        $isOwner = $this->isOwner($pid);

        if ($isOwner) {
            $annotations = $this->db->exec(
                'SELECT a.*, u.username AS author_name, u.email AS author_email
                 FROM annotations a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.project_id = ?
                 ORDER BY a.created_at DESC',
                [$pid]
            ) ?: [];
        } else {
            $annotations = $this->db->exec(
                'SELECT * FROM annotations WHERE project_id = ? AND user_id = ? ORDER BY created_at DESC',
                [$pid, $user['id']]
            ) ?: [];
        }

        if ($isOwner) {
            $suggestions = $this->db->exec(
                'SELECT s.*, u.username AS author_name, u.email AS author_email
                 FROM inline_suggestions s
                 LEFT JOIN users u ON u.id = s.user_id
                 WHERE s.project_id = ?
                 ORDER BY s.created_at DESC',
                [$pid]
            ) ?: [];
        } else {
            $suggestions = $this->db->exec(
                'SELECT * FROM inline_suggestions WHERE project_id = ? AND user_id = ? ORDER BY created_at DESC',
                [$pid, $user['id']]
            ) ?: [];
        }

        $this->render('relecture/review.html', [
            'title'           => 'Relecture : ' . $data['project']['title'],
            'project'         => $data['project'],
            'items'           => $data['items'],
            'annotations'     => $annotations,
            'annotationsJson' => json_encode(array_values($annotations)),
            'suggestions'     => $suggestions,
            'suggestionsJson' => json_encode(array_values($suggestions)),
            'projectJson'     => json_encode(['id' => $pid, 'currentUserId' => $user['id'], 'isOwner' => $isOwner]),
            'isOwner'         => $isOwner,
            'isCollaborator'  => $this->isCollaborator($pid),
        ]);
    }

    /**
     * AJAX: add an annotation.
     */
    public function addAnnotation()
    {
        header('Content-Type: application/json');
        $user = $this->currentUser();

        $pid          = (int) ($_POST['project_id'] ?? 0);
        $contentType  = trim($_POST['content_type'] ?? '');
        $contentId    = (int) ($_POST['content_id'] ?? 0);
        $selectedText = trim($_POST['selected_text'] ?? '');
        $comment      = trim($_POST['comment'] ?? '');
        $category     = trim($_POST['category'] ?? 'to_check');

        $validCategories = ['to_rewrite', 'inconsistency', 'to_check', 'good'];
        $validTypes      = ['chapter', 'act', 'section', 'note', 'element'];

        if (!$pid || !$contentType || !$contentId || $selectedText === ''
            || !in_array($category, $validCategories)
            || !in_array($contentType, $validTypes)) {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides.']);
            return;
        }

        if (!$this->hasProjectAccess($pid)) {
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
            return;
        }

        $this->db->exec(
            'INSERT INTO annotations (project_id, user_id, content_type, content_id, selected_text, comment, category)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$pid, $user['id'], $contentType, $contentId, $selectedText, $comment, $category]
        );

        $rows = $this->db->exec('SELECT LAST_INSERT_ID() AS id');
        $id   = (int) ($rows[0]['id'] ?? 0);

        echo json_encode(['status' => 'ok', 'id' => $id]);
    }

    /**
     * AJAX: delete an annotation.
     */
    public function deleteAnnotation()
    {
        header('Content-Type: application/json');
        $user = $this->currentUser();
        $aid  = (int) $this->f3->get('PARAMS.aid');

        $rows = $this->db->exec(
            'SELECT id, project_id FROM annotations WHERE id = ?',
            [$aid]
        );

        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Annotation introuvable.']);
            return;
        }

        $annotation = $rows[0];

        // Autoriser : auteur de l'annotation OU propriétaire du projet
        if ((int)$annotation['project_id'] !== 0 && $this->isOwner((int)$annotation['project_id'])) {
            // Propriétaire du projet : peut supprimer toute annotation
        } elseif (!$this->db->exec('SELECT id FROM annotations WHERE id = ? AND user_id = ?', [$aid, $user['id']])) {
            echo json_encode(['status' => 'error', 'message' => 'Non autorisé.']);
            return;
        }

        $this->db->exec('DELETE FROM annotations WHERE id = ?', [$aid]);
        echo json_encode(['status' => 'ok']);
    }

    /**
     * AJAX: add an inline suggestion.
     */
    public function addSuggestion()
    {
        header('Content-Type: application/json');
        $user = $this->currentUser();

        $pid           = (int) ($_POST['project_id'] ?? 0);
        $contentType   = trim($_POST['content_type'] ?? '');
        $contentId     = (int) ($_POST['content_id'] ?? 0);
        $originalText  = trim($_POST['original_text'] ?? '');
        $suggestedText = trim($_POST['suggested_text'] ?? '');
        $comment       = trim($_POST['comment'] ?? '');

        $validTypes = ['chapter', 'act', 'section', 'note', 'element'];

        if (!$pid || !$contentType || !$contentId || $originalText === '' || $suggestedText === ''
            || !in_array($contentType, $validTypes)) {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides.']);
            return;
        }

        if (!$this->hasProjectAccess($pid)) {
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
            return;
        }

        $this->db->exec(
            'INSERT INTO inline_suggestions (project_id, user_id, content_type, content_id, original_text, suggested_text, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$pid, $user['id'], $contentType, $contentId, $originalText, $suggestedText, $comment ?: null]
        );

        $rows = $this->db->exec('SELECT LAST_INSERT_ID() AS id');
        $id   = (int) ($rows[0]['id'] ?? 0);

        echo json_encode(['status' => 'ok', 'id' => $id]);
    }

    /**
     * AJAX: accept or reject an inline suggestion (owner only).
     */
    public function updateSuggestion()
    {
        header('Content-Type: application/json');
        $sid    = (int) $this->f3->get('PARAMS.sid');
        $action = trim($this->f3->get('PARAMS.action') ?? '');

        if (!in_array($action, ['accept', 'reject'])) {
            echo json_encode(['status' => 'error', 'message' => 'Action invalide.']);
            return;
        }

        $rows = $this->db->exec('SELECT * FROM inline_suggestions WHERE id = ?', [$sid]);
        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Suggestion introuvable.']);
            return;
        }

        $sug = $rows[0];

        if (!$this->isOwner((int) $sug['project_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Réservé au propriétaire du projet.']);
            return;
        }

        $status = $action === 'accept' ? 'accepted' : 'rejected';
        $this->db->exec('UPDATE inline_suggestions SET status = ? WHERE id = ?', [$status, $sid]);

        echo json_encode(['status' => 'ok', 'newStatus' => $status]);
    }

    /**
     * AJAX: delete an inline suggestion.
     */
    public function deleteSuggestion()
    {
        header('Content-Type: application/json');
        $user = $this->currentUser();
        $sid  = (int) $this->f3->get('PARAMS.sid');

        $rows = $this->db->exec('SELECT * FROM inline_suggestions WHERE id = ?', [$sid]);
        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Suggestion introuvable.']);
            return;
        }

        $sug = $rows[0];

        if ((int) $sug['user_id'] !== (int) $user['id'] && !$this->isOwner((int) $sug['project_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Non autorisé.']);
            return;
        }

        $this->db->exec('DELETE FROM inline_suggestions WHERE id = ?', [$sid]);
        echo json_encode(['status' => 'ok']);
    }

    /**
     * Annotation report for a project.
     */
    public function report()
    {
        $pid  = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) {
            $this->f3->error(404);
            return;
        }
        $project = $project[0];

        $isOwner = $this->isOwner($pid);

        if ($isOwner) {
            $rows = $this->db->exec(
                'SELECT a.*, u.username AS author_name, u.email AS author_email
                 FROM annotations a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.project_id = ?
                 ORDER BY a.content_type, a.content_id, a.created_at ASC',
                [$pid]
            ) ?: [];
        } else {
            $rows = $this->db->exec(
                'SELECT * FROM annotations WHERE project_id = ? AND user_id = ? ORDER BY content_type, content_id, created_at ASC',
                [$pid, $user['id']]
            ) ?: [];
        }

        // Group by content block and enrich with title
        $grouped      = [];
        $chapterModel = new Chapter();
        $noteModel    = new Note();

        foreach ($rows as $row) {
            $key = $row['content_type'] . ':' . $row['content_id'];
            if (!isset($grouped[$key])) {
                // Resolve content title
                $title = '';
                if ($row['content_type'] === 'chapter') {
                    $ch    = $chapterModel->findAndCast(['id=?', $row['content_id']]);
                    $title = $ch ? $ch[0]['title'] : '';
                } elseif ($row['content_type'] === 'note') {
                    $nt    = $noteModel->findAndCast(['id=?', $row['content_id']]);
                    $title = $nt ? $nt[0]['title'] : '';
                }
                if ($title === '') {
                    $title = ucfirst($row['content_type']) . ' #' . $row['content_id'];
                }
                $grouped[$key] = [
                    'type'        => $row['content_type'],
                    'id'          => $row['content_id'],
                    'title'       => $title,
                    'annotations' => [],
                ];
            }
            $grouped[$key]['annotations'][] = $row;
        }

        $this->render('relecture/report.html', [
            'title'           => 'Rapport de relecture : ' . $project['title'],
            'project'         => $project,
            'grouped'         => array_values($grouped),
            'total'           => count($rows),
            'annotationsJson' => json_encode(array_values($rows)),
            'isOwner'         => $isOwner,
            'currentUserId'   => $user['id'],
        ]);
    }
}
