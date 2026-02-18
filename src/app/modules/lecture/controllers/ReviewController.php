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
        $user = $this->currentUser();
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $user['id']]);
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
        $notes     = $noteModel->getAllByProject($pid);

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

        $annotations = $this->db->exec(
            'SELECT * FROM annotations WHERE project_id = ? AND user_id = ? ORDER BY created_at DESC',
            [$pid, $user['id']]
        ) ?: [];

        $this->render('relecture/review.html', [
            'title'           => 'Relecture : ' . $data['project']['title'],
            'project'         => $data['project'],
            'items'           => $data['items'],
            'annotations'     => $annotations,
            'annotationsJson' => json_encode(array_values($annotations)),
            'projectJson'     => json_encode(['id' => $pid]),
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

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
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
            'SELECT id FROM annotations WHERE id = ? AND user_id = ?',
            [$aid, $user['id']]
        );

        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Annotation introuvable.']);
            return;
        }

        $this->db->exec('DELETE FROM annotations WHERE id = ?', [$aid]);
        echo json_encode(['status' => 'ok']);
    }

    /**
     * Annotation report for a project.
     */
    public function report()
    {
        $pid  = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $user['id']]);
        if (!$project) {
            $this->f3->error(404);
            return;
        }
        $project = $project[0];

        $rows = $this->db->exec(
            'SELECT * FROM annotations WHERE project_id = ? AND user_id = ? ORDER BY content_type, content_id, created_at ASC',
            [$pid, $user['id']]
        ) ?: [];

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
        ]);
    }
}
