<?php

class LectureController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function read()
    {
        $pid = (int) $this->f3->get('PARAMS.id');

        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }
        $project = $project[0];

        // TEMPLATE SYSTEM: Load template configuration
        $templateElements = [];
        $db = $this->f3->get('DB');
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();

            if (!$templateId) {
                $template = $templateModel->getDefault();
                $templateId = $template['id'] ?? null;
            } else {
                $template = $templateModel->findAndCast(['id=?', $templateId]);
                $template = $template ? $template[0] : $templateModel->getDefault();
            }

            // Load template elements configuration
            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        // Get project settings
        $settings = json_decode($project['settings'] ?? '{}', true);
        $authorName = $settings['author'] ?? $this->getUserFullName($this->currentUser());
        $lpp = $project['lines_per_page'] ?: 38;

        // Get cover image URL
        $coverImage = null;
        if (!empty($project['cover_image'])) {
            $coverImage = $this->f3->get('BASE') . '/project/' . $pid . '/cover';
        }

        // Fetch all content
        $chapterModel = new Chapter();
        $allChapters = $chapterModel->getAllByProject($pid);

        $actModel = new Act();
        $acts = $actModel->getAllByProject($pid);

        $sectionModel = new Section();
        $sectionsBeforeChapters = $sectionModel->getBeforeChapters($pid);
        $sectionsAfterChapters = $sectionModel->getAfterChapters($pid);

        $noteModel = new Note();
        $notes = $noteModel->getAllByProject($pid);

        // Build reading content in order
        $readingContent = [];
        $tocItems = [];
        $currentPage = 1;

        // Helper to prepare content for display
        $prepareContent = function ($content) {
            $decoded = html_entity_decode($content ?? '');
            return $this->cleanQuillHtml($decoded);
        };
        // Helper to calculate pages
        $calculatePages = function ($content) use ($lpp) {
            $cleanContent = strip_tags(html_entity_decode($content ?? ''));
            $lines = 0;
            if ($cleanContent !== '') {
                $lines = ceil(strlen($cleanContent) / 80);
            }
            return max(1, ceil($lines / $lpp));
        };

        // TEMPLATE SYSTEM: Organize chapters by hierarchy
        $chaptersByAct = [];
        $chaptersWithoutAct = [];
        $subChaptersByParent = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) {
                $subChaptersByParent[$ch['parent_id']][] = $ch;
            }
        }

        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                if ($ch['act_id']) {
                    $chaptersByAct[$ch['act_id']][] = $ch;
                } else {
                    $chaptersWithoutAct[] = $ch;
                }
            }
        }

        // Load custom elements (only if table exists)
        $customElementsByType = [];
        $customSubElementsByParent = [];
        if ($db->exists('elements')) {
            $elementModel = new Element();
            $customElements = $elementModel->getAllByProject($pid);
        } else {
            $customElements = [];
        }

        foreach ($customElements as $elem) {
            $tid = $elem['template_element_id'];
            if (!isset($customElementsByType[$tid])) {
                $customElementsByType[$tid] = [];
            }

            if ($elem['parent_id']) {
                $customSubElementsByParent[$elem['parent_id']][] = $elem;
            } else {
                $customElementsByType[$tid][] = $elem;
            }
        }

        // LOOP THROUGH TEMPLATE ELEMENTS
        foreach ($templateElements as $elem) {
            if (!$elem['is_enabled']) continue;

            switch ($elem['element_type']) {
                case 'section':
                    // Process sections (before or after)
                    $sections = ($elem['section_placement'] === 'before') ? $sectionsBeforeChapters : $sectionsAfterChapters;
                    foreach ($sections as $sec) {
                        if ($sec['type'] !== $elem['element_subtype']) continue;
                        if (!($sec['is_exported'] ?? 1)) continue;

                        $pages = $calculatePages($sec['content']);
                        $typeName = Section::getTypeName($sec['type']);

                        $readingContent[] = [
                            'type' => 'section',
                            'id' => $sec['id'],
                            'title' => $sec['title'] ?: $typeName,
                            'content' => $prepareContent($sec['content']),
                            'page_start' => $currentPage,
                            'page_end' => $currentPage + $pages - 1
                        ];

                        $tocItems[] = [
                            'title' => $sec['title'] ?: $typeName,
                            'page' => $currentPage,
                            'level' => 0
                        ];

                        $currentPage += $pages;
                    }
                    break;

                case 'act':
                    // Process acts with their chapters
                    foreach ($acts as $act) {
                        $actChapters = $chaptersByAct[$act['id']] ?? [];
                        $hasExportedChapters = false;
                        foreach ($actChapters as $ch) {
                            if ($ch['is_exported'] ?? 1) {
                                $hasExportedChapters = true;
                                break;
                            }
                        }

                        $actHasContent = !empty($act['content']) && ($act['is_exported'] ?? 1);
                        if (!$actHasContent && !$hasExportedChapters) {
                            continue;
                        }

                        $tocItems[] = [
                            'title' => $act['title'],
                            'page' => $currentPage,
                            'level' => 0
                        ];

                        if ($actHasContent) {
                            $pages = $calculatePages($act['content']);
                            $readingContent[] = [
                                'type' => 'act',
                                'id' => $act['id'],
                                'title' => $act['title'],
                                'content' => $prepareContent($act['content']),
                                'page_start' => $currentPage,
                                'page_end' => $currentPage + $pages - 1
                            ];
                            $currentPage += $pages;
                        }

                        foreach ($actChapters as $ch) {
                            if (!($ch['is_exported'] ?? 1)) continue;

                            $pages = $calculatePages($ch['content']);
                            $readingContent[] = [
                                'type' => 'chapter',
                                'id' => $ch['id'],
                                'title' => $ch['title'],
                                'content' => $prepareContent($ch['content']),
                                'page_start' => $currentPage,
                                'page_end' => $currentPage + $pages - 1
                            ];

                            $tocItems[] = [
                                'title' => $ch['title'],
                                'page' => $currentPage,
                                'level' => 1
                            ];

                            $currentPage += $pages;

                            // Sub-chapters
                            $subs = $subChaptersByParent[$ch['id']] ?? [];
                            foreach ($subs as $sub) {
                                if (!($sub['is_exported'] ?? 1)) continue;

                                $subPages = $calculatePages($sub['content']);
                                $readingContent[] = [
                                    'type' => 'subchapter',
                                    'id' => $sub['id'],
                                    'title' => $sub['title'],
                                    'content' => $prepareContent($sub['content']),
                                    'page_start' => $currentPage,
                                    'page_end' => $currentPage + $subPages - 1
                                ];

                                $tocItems[] = [
                                    'title' => $sub['title'],
                                    'page' => $currentPage,
                                    'level' => 2
                                ];

                                $currentPage += $subPages;
                            }
                        }
                    }
                    break;

                case 'chapter':
                    // Process orphan chapters (without act)
                    foreach ($chaptersWithoutAct as $ch) {
                        if (!($ch['is_exported'] ?? 1)) continue;

                        $pages = $calculatePages($ch['content']);
                        $readingContent[] = [
                            'type' => 'chapter',
                            'title' => $ch['title'],
                            'content' => $prepareContent($ch['content']),
                            'page_start' => $currentPage,
                            'page_end' => $currentPage + $pages - 1
                        ];

                        $tocItems[] = [
                            'title' => $ch['title'],
                            'page' => $currentPage,
                            'level' => 0
                        ];

                        $currentPage += $pages;

                        // Sub-chapters
                        $subs = $subChaptersByParent[$ch['id']] ?? [];
                        foreach ($subs as $sub) {
                            if (!($sub['is_exported'] ?? 1)) continue;

                            $subPages = $calculatePages($sub['content']);
                            $readingContent[] = [
                                'type' => 'subchapter',
                                'title' => $sub['title'],
                                'content' => $prepareContent($sub['content']),
                                'page_start' => $currentPage,
                                'page_end' => $currentPage + $subPages - 1
                            ];

                            $tocItems[] = [
                                'title' => $sub['title'],
                                'page' => $currentPage,
                                'level' => 1
                            ];

                            $currentPage += $subPages;
                        }
                    }
                    break;

                case 'note':
                    // Process notes
                    foreach ($notes as $note) {
                        if (!($note['is_exported'] ?? 1)) continue;

                        $pages = $calculatePages($note['content']);
                        $readingContent[] = [
                            'type' => 'note',
                            'id' => $note['id'],
                            'title' => $note['title'],
                            'content' => $prepareContent($note['content']),
                            'page_start' => $currentPage,
                            'page_end' => $currentPage + $pages - 1
                        ];

                        $tocItems[] = [
                            'title' => $note['title'],
                            'page' => $currentPage,
                            'level' => 0
                        ];

                        $currentPage += $pages;
                    }
                    break;

                case 'element':
                    // Process custom elements
                    $elements = $customElementsByType[$elem['id']] ?? [];
                    foreach ($elements as $e) {
                        if (!($e['is_exported'] ?? 1)) continue;

                        $pages = $calculatePages($e['content']);
                        $readingContent[] = [
                            'type' => 'element',
                            'id' => $e['id'],
                            'title' => $e['title'],
                            'content' => $prepareContent($e['content']),
                            'page_start' => $currentPage,
                            'page_end' => $currentPage + $pages - 1
                        ];

                        $tocItems[] = [
                            'title' => $e['title'],
                            'page' => $currentPage,
                            'level' => $e['parent_id'] ? 1 : 0
                        ];

                        $currentPage += $pages;

                        // Sub-elements
                        $subs = $customSubElementsByParent[$e['id']] ?? [];
                        foreach ($subs as $sub) {
                            if (!($sub['is_exported'] ?? 1)) continue;

                            $subPages = $calculatePages($sub['content']);
                            $readingContent[] = [
                                'type' => 'subelement',
                                'id' => $sub['id'],
                                'title' => $sub['title'],
                                'content' => $prepareContent($sub['content']),
                                'page_start' => $currentPage,
                                'page_end' => $currentPage + $subPages - 1
                            ];

                            $tocItems[] = [
                                'title' => $sub['title'],
                                'page' => $currentPage,
                                'level' => 2
                            ];

                            $currentPage += $subPages;
                        }
                    }
                    break;

                case 'character':
                case 'file':
                    // Characters and files are not typically displayed in reading mode
                    break;
            }
        }

        $totalPages = $currentPage - 1;

        // Render without layout for immersive reading mode
        $this->f3->mset([
            'title' => 'Lecture: ' . htmlspecialchars($project['title']),
            'project' => $project,
            'authorName' => $authorName,
            'coverImage' => $coverImage,
            'readingContent' => $readingContent,
            'tocItems' => $tocItems,
            'totalPages' => $totalPages
        ]);
        $this->f3->set('csrfToken', $this->csrfToken());
        $this->f3->set('currentUser', $this->currentUser());
        $this->f3->set('base', $this->f3->get('BASE'));
        $this->f3->set('nonce', $this->f3->get('nonce'));
        echo \Template::instance()->render('lecture/read.html');
    }

    public function addComment()
    {
        $body = json_decode($this->f3->get('BODY'), true);
        $type = $body['type'] ?? '';
        $id = (int) ($body['id'] ?? 0);
        $comment = $body['comment'] ?? '';

        if (!$id || !$type || !$comment) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
            return;
        }

        // Determine the model based on type
        if ($type === 'chapter' || $type === 'subchapter') {
            $model = new Chapter();
        } elseif ($type === 'act') {
            $model = new Act();
        } elseif ($type === 'section') {
            $model = new Section();
        } elseif ($type === 'note') {
            $model = new Note();
        } elseif ($type === 'element' || $type === 'subelement') {
            $db = $this->f3->get('DB');
            if (!$db->exists('elements')) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Elements module not available']);
                return;
            }
            $model = new Element();
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
            return;
        }

        // Load the content item
        $model->load(['id=?', $id]);
        if ($model->dry()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Content not found']);
            return;
        }

        // Verify access (owner or collaborator)
        if (!$this->hasProjectAccess($model->project_id)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        // Append comment to existing comment field
        $existingComment = $model->comment ?? '';
        $model->comment = $existingComment . $comment;
        $model->save();

        echo json_encode(['status' => 'ok']);
    }

    public function saveBookmark()
    {
        $body = json_decode($this->f3->get('BODY'), true);
        $projectId = (int) ($body['project_id'] ?? 0);
        $scrollPosition = (int) ($body['scroll_position'] ?? 0);
        $currentPage = (int) ($body['current_page'] ?? 1);

        if (!$projectId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing project_id']);
            return;
        }

        // Verify project access (owner or accepted collaborator)
        if (!$this->hasProjectAccess($projectId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        // Save bookmark to JSON file in data/user@mail/bookmarks.json
        $userEmail = $this->currentUser()['email'];
        $bookmarkFile = 'data/' . $userEmail . '/bookmarks.json';

        // Load existing bookmarks
        $bookmarks = [];
        if (file_exists($bookmarkFile)) {
            $content = file_get_contents($bookmarkFile);
            $bookmarks = json_decode($content, true) ?? [];
        }

        // Update bookmark for this project
        $bookmarks[$projectId] = [
            'scroll_position' => $scrollPosition,
            'current_page' => $currentPage,
            'content_id' => $body['content_id'] ?? null,
            'content_type' => $body['content_type'] ?? null,
            'section_offset' => $body['section_offset'] ?? 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Ensure directory exists
        $dir = dirname($bookmarkFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save to file
        file_put_contents($bookmarkFile, json_encode($bookmarks, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'ok']);
    }

    public function getBookmark()
    {
        $projectId = (int) $this->f3->get('GET.project_id');

        if (!$projectId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing project_id']);
            return;
        }

        // Verify project access (owner or accepted collaborator)
        if (!$this->hasProjectAccess($projectId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        // Load bookmark from JSON file
        $userEmail = $this->currentUser()['email'];
        $bookmarkFile = 'data/' . $userEmail . '/bookmarks.json';

        if (!file_exists($bookmarkFile)) {
            echo json_encode(['status' => 'not_found']);
            return;
        }

        $content = file_get_contents($bookmarkFile);
        $bookmarks = json_decode($content, true) ?? [];

        if (isset($bookmarks[$projectId])) {
            echo json_encode($bookmarks[$projectId]);
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    }

    private function getUserFullName($user)
    {
        if (!$user)
            return '';
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        return trim($firstName . ' ' . $lastName);
    }
}
