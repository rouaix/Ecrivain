<?php

/**
 * Public share controller — no authentication required.
 * All routes are read-only. Validates the share token on every action.
 */
class SharePublicController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        // Public routes: no auth check, no auto-login, no CSRF (no POST)
    }

    // -------------------------------------------------------------------------
    // Public routes
    // -------------------------------------------------------------------------

    /**
     * List all projects accessible via a share token.
     */
    public function index()
    {
        $token = $this->f3->get('PARAMS.token');
        $shareLink = new ShareLink();
        $link = $shareLink->findByToken($token);

        if (!$link || !$link['is_active']) {
            $this->f3->error(403, 'Ce lien de partage est invalide ou désactivé.');
            return;
        }

        $projects = $shareLink->getProjects((int)$link['id']);

        $this->renderPublic('share/public/index.html', [
            'title'    => $link['label'] ?: 'Projets partagés',
            'link'     => $link,
            'projects' => $projects,
            'token'    => $token,
        ]);
    }

    /**
     * Project overview page (cover, description, navigation).
     */
    public function project()
    {
        $token = $this->f3->get('PARAMS.token');
        $pid   = (int)$this->f3->get('PARAMS.pid');
        $link  = $this->resolveLink($token, $pid);

        if (!$link) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) {
            $this->f3->error(404);
            return;
        }
        $project = $project[0];

        $coverImage = null;
        if (!empty($project['cover_image'])) {
            $coverImage = $this->f3->get('BASE') . '/project/' . $pid . '/cover';
        }

        $this->renderPublic('share/public/project.html', [
            'title'      => $project['title'],
            'link'       => $link,
            'project'    => $project,
            'coverImage' => $coverImage,
            'token'      => $token,
        ]);
    }

    /**
     * Read-only mindmap.
     */
    public function mindmap()
    {
        $token = $this->f3->get('PARAMS.token');
        $pid   = (int)$this->f3->get('PARAMS.pid');
        $link  = $this->resolveLink($token, $pid);

        if (!$link) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) {
            $this->f3->error(404);
            return;
        }
        $project = $project[0];

        $mindmapData = $this->buildMindmapData($pid, $project);

        $this->renderPublic('share/public/mindmap.html', [
            'title'       => 'Carte mentale : ' . $project['title'],
            'link'        => $link,
            'project'     => $project,
            'mindmapData' => json_encode($mindmapData),
            'token'       => $token,
        ]);
    }

    /**
     * Read-only lecture mode (respects is_exported).
     */
    public function lecture()
    {
        $token = $this->f3->get('PARAMS.token');
        $pid   = (int)$this->f3->get('PARAMS.pid');
        $link  = $this->resolveLink($token, $pid);

        if (!$link) {
            $this->f3->error(403);
            return;
        }

        $data = $this->buildReadingContent($pid);
        if (!$data) {
            $this->f3->error(404);
            return;
        }

        $coverImage = null;
        if (!empty($data['project']['cover_image'])) {
            $coverImage = $this->f3->get('BASE') . '/project/' . $pid . '/cover';
        }

        $this->renderPublic('share/public/lecture.html', [
            'title'          => 'Lecture : ' . $data['project']['title'],
            'link'           => $link,
            'project'        => $data['project'],
            'coverImage'     => $coverImage,
            'readingContent' => $data['readingContent'],
            'tocItems'       => $data['tocItems'],
            'totalPages'     => $data['totalPages'],
            'token'          => $token,
        ]);
    }

    /**
     * Read-only relecture mode — shows owner's annotations, no modifications.
     */
    public function relecture()
    {
        $token = $this->f3->get('PARAMS.token');
        $pid   = (int)$this->f3->get('PARAMS.pid');
        $link  = $this->resolveLink($token, $pid);

        if (!$link) {
            $this->f3->error(403);
            return;
        }

        $data = $this->buildReviewItems($pid);
        if (!$data) {
            $this->f3->error(404);
            return;
        }

        // Load owner annotations (read-only — identified by link's user_id)
        $annotations = $this->db->exec(
            'SELECT * FROM annotations WHERE project_id = ? AND user_id = ? ORDER BY created_at DESC',
            [$pid, (int)$link['user_id']]
        ) ?: [];

        $this->renderPublic('share/public/relecture.html', [
            'title'           => 'Relecture : ' . $data['project']['title'],
            'link'            => $link,
            'project'         => $data['project'],
            'items'           => $data['items'],
            'annotations'     => $annotations,
            'annotationsJson' => json_encode(array_values($annotations)),
            'token'           => $token,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Validate token and project membership. Returns link array or null.
     */
    private function resolveLink(string $token, int $pid): ?array
    {
        $shareLink = new ShareLink();
        $link = $shareLink->findByToken($token);

        if (!$link || !$link['is_active']) {
            return null;
        }

        $projectIds = $shareLink->getProjectIds((int)$link['id']);
        if (!in_array($pid, array_map('intval', $projectIds))) {
            return null;
        }

        return $link;
    }

    /**
     * Render a standalone public view (no main layout).
     */
    private function renderPublic(string $view, array $data = []): void
    {
        $this->f3->mset($data);
        $this->f3->set('base', $this->f3->get('BASE'));
        $this->f3->set('nonce', $this->f3->get('nonce') ?? '');
        echo \Template::instance()->render($view);
    }

    /**
     * Build reading content (same logic as LectureController, no user_id check).
     */
    private function buildReadingContent(int $pid): ?array
    {
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) return null;
        $project = $project[0];

        $db = $this->f3->get('DB');

        $templateElements = [];
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId    = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            $template = $templateId
                ? ($templateModel->findAndCast(['id=?', $templateId])[0] ?? $templateModel->getDefault())
                : $templateModel->getDefault();
            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        $lpp = $project['lines_per_page'] ?: 38;

        $chapterModel = new Chapter();
        $allChapters  = $chapterModel->getAllByProject($pid);

        $actModel = new Act();
        $acts     = $actModel->getAllByProject($pid);

        $sectionModel = new Section();
        $sectionsBeforeChapters = $sectionModel->getBeforeChapters($pid);
        $sectionsAfterChapters  = $sectionModel->getAfterChapters($pid);

        $noteModel = new Note();
        $notes     = $noteModel->getAllByProject($pid);

        $chaptersByAct       = [];
        $chaptersWithoutAct  = [];
        $subChaptersByParent = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) $subChaptersByParent[$ch['parent_id']][] = $ch;
        }
        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                if ($ch['act_id']) $chaptersByAct[$ch['act_id']][] = $ch;
                else $chaptersWithoutAct[] = $ch;
            }
        }

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

        $readingContent = [];
        $tocItems       = [];
        $currentPage    = 1;

        $prepare = function ($content) {
            return $this->cleanQuillHtml(html_entity_decode($content ?? ''));
        };

        $calcPages = function ($content) use ($lpp) {
            $clean = strip_tags(html_entity_decode($content ?? ''));
            $lines = $clean !== '' ? ceil(strlen($clean) / 80) : 0;
            return max(1, ceil($lines / $lpp));
        };

        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) continue;

            switch ($te['element_type']) {
                case 'section':
                    $sections = ($te['section_placement'] === 'before')
                        ? $sectionsBeforeChapters
                        : $sectionsAfterChapters;
                    foreach ($sections as $sec) {
                        if ($sec['type'] !== $te['element_subtype']) continue;
                        if (!($sec['is_exported'] ?? 1)) continue;
                        $pages = $calcPages($sec['content']);
                        $typeName = Section::getTypeName($sec['type']);
                        $readingContent[] = [
                            'type'       => 'section',
                            'id'         => $sec['id'],
                            'title'      => $sec['title'] ?: $typeName,
                            'content'    => $prepare($sec['content']),
                            'page_start' => $currentPage,
                            'page_end'   => $currentPage + $pages - 1,
                        ];
                        $tocItems[] = ['title' => $sec['title'] ?: $typeName, 'page' => $currentPage, 'level' => 0];
                        $currentPage += $pages;
                    }
                    break;

                case 'act':
                    foreach ($acts as $act) {
                        $actChapters = $chaptersByAct[$act['id']] ?? [];
                        $hasExported = false;
                        foreach ($actChapters as $ch) {
                            if ($ch['is_exported'] ?? 1) { $hasExported = true; break; }
                        }
                        $actHasContent = !empty($act['content']) && ($act['is_exported'] ?? 1);
                        if (!$actHasContent && !$hasExported) continue;

                        $tocItems[] = ['title' => $act['title'], 'page' => $currentPage, 'level' => 0];

                        if ($actHasContent) {
                            $pages = $calcPages($act['content']);
                            $readingContent[] = [
                                'type'       => 'act',
                                'id'         => $act['id'],
                                'title'      => $act['title'],
                                'content'    => $prepare($act['content']),
                                'page_start' => $currentPage,
                                'page_end'   => $currentPage + $pages - 1,
                            ];
                            $currentPage += $pages;
                        }

                        foreach ($actChapters as $ch) {
                            if (!($ch['is_exported'] ?? 1)) continue;
                            $pages = $calcPages($ch['content']);
                            $readingContent[] = [
                                'type'       => 'chapter',
                                'id'         => $ch['id'],
                                'title'      => $ch['title'],
                                'content'    => $prepare($ch['content']),
                                'page_start' => $currentPage,
                                'page_end'   => $currentPage + $pages - 1,
                            ];
                            $tocItems[] = ['title' => $ch['title'], 'page' => $currentPage, 'level' => 1];
                            $currentPage += $pages;
                            foreach ($subChaptersByParent[$ch['id']] ?? [] as $sub) {
                                if (!($sub['is_exported'] ?? 1)) continue;
                                $sp = $calcPages($sub['content']);
                                $readingContent[] = [
                                    'type'       => 'subchapter',
                                    'id'         => $sub['id'],
                                    'title'      => $sub['title'],
                                    'content'    => $prepare($sub['content']),
                                    'page_start' => $currentPage,
                                    'page_end'   => $currentPage + $sp - 1,
                                ];
                                $tocItems[] = ['title' => $sub['title'], 'page' => $currentPage, 'level' => 2];
                                $currentPage += $sp;
                            }
                        }
                    }
                    break;

                case 'chapter':
                    foreach ($chaptersWithoutAct as $ch) {
                        if (!($ch['is_exported'] ?? 1)) continue;
                        $pages = $calcPages($ch['content']);
                        $readingContent[] = [
                            'type'       => 'chapter',
                            'id'         => $ch['id'],
                            'title'      => $ch['title'],
                            'content'    => $prepare($ch['content']),
                            'page_start' => $currentPage,
                            'page_end'   => $currentPage + $pages - 1,
                        ];
                        $tocItems[] = ['title' => $ch['title'], 'page' => $currentPage, 'level' => 0];
                        $currentPage += $pages;
                        foreach ($subChaptersByParent[$ch['id']] ?? [] as $sub) {
                            if (!($sub['is_exported'] ?? 1)) continue;
                            $sp = $calcPages($sub['content']);
                            $readingContent[] = [
                                'type'       => 'subchapter',
                                'id'         => $sub['id'],
                                'title'      => $sub['title'],
                                'content'    => $prepare($sub['content']),
                                'page_start' => $currentPage,
                                'page_end'   => $currentPage + $sp - 1,
                            ];
                            $tocItems[] = ['title' => $sub['title'], 'page' => $currentPage, 'level' => 1];
                            $currentPage += $sp;
                        }
                    }
                    break;

                case 'note':
                    foreach ($notes as $note) {
                        if (!($note['is_exported'] ?? 1)) continue;
                        $pages = $calcPages($note['content']);
                        $readingContent[] = [
                            'type'       => 'note',
                            'id'         => $note['id'],
                            'title'      => $note['title'],
                            'content'    => $prepare($note['content']),
                            'page_start' => $currentPage,
                            'page_end'   => $currentPage + $pages - 1,
                        ];
                        $tocItems[] = ['title' => $note['title'], 'page' => $currentPage, 'level' => 0];
                        $currentPage += $pages;
                    }
                    break;

                case 'element':
                    foreach ($customElementsByType[$te['id']] ?? [] as $e) {
                        if (!($e['is_exported'] ?? 1)) continue;
                        $pages = $calcPages($e['content']);
                        $readingContent[] = [
                            'type'       => 'element',
                            'id'         => $e['id'],
                            'title'      => $e['title'],
                            'content'    => $prepare($e['content']),
                            'page_start' => $currentPage,
                            'page_end'   => $currentPage + $pages - 1,
                        ];
                        $tocItems[] = ['title' => $e['title'], 'page' => $currentPage, 'level' => $e['parent_id'] ? 1 : 0];
                        $currentPage += $pages;
                        foreach ($customSubElementsByParent[$e['id']] ?? [] as $sub) {
                            if (!($sub['is_exported'] ?? 1)) continue;
                            $sp = $calcPages($sub['content']);
                            $readingContent[] = [
                                'type'       => 'subelement',
                                'id'         => $sub['id'],
                                'title'      => $sub['title'],
                                'content'    => $prepare($sub['content']),
                                'page_start' => $currentPage,
                                'page_end'   => $currentPage + $sp - 1,
                            ];
                            $tocItems[] = ['title' => $sub['title'], 'page' => $currentPage, 'level' => 2];
                            $currentPage += $sp;
                        }
                    }
                    break;
            }
        }

        return [
            'project'        => $project,
            'readingContent' => $readingContent,
            'tocItems'       => $tocItems,
            'totalPages'     => $currentPage - 1,
        ];
    }

    /**
     * Build ordered content items for relecture (same logic as ReviewController, no user_id check).
     */
    private function buildReviewItems(int $pid): ?array
    {
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) return null;
        $project = $project[0];

        $db = $this->f3->get('DB');

        $templateElements = [];
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId    = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            $template = $templateId
                ? ($templateModel->findAndCast(['id=?', $templateId])[0] ?? $templateModel->getDefault())
                : $templateModel->getDefault();
            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        $chapterModel = new Chapter();
        $allChapters  = $chapterModel->getAllByProject($pid);

        $actModel = new Act();
        $acts     = $actModel->getAllByProject($pid);

        $sectionModel = new Section();
        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $sectionsAfter  = $sectionModel->getAfterChapters($pid);

        $noteModel = new Note();
        $notes     = $noteModel->getAllByProject($pid);

        $chaptersByAct       = [];
        $chaptersWithoutAct  = [];
        $subChaptersByParent = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) $subChaptersByParent[$ch['parent_id']][] = $ch;
        }
        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                if ($ch['act_id']) $chaptersByAct[$ch['act_id']][] = $ch;
                else $chaptersWithoutAct[] = $ch;
            }
        }

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
            $items[] = ['type' => 'chapter', 'id' => $ch['id'], 'title' => $ch['title'], 'content' => $prepare($ch['content'])];
            foreach ($subChaptersByParent[$ch['id']] ?? [] as $sub) {
                if (!($sub['is_exported'] ?? 1)) continue;
                $items[] = ['type' => 'chapter', 'id' => $sub['id'], 'title' => $sub['title'], 'content' => $prepare($sub['content'])];
            }
        };

        $items = [];

        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) continue;

            switch ($te['element_type']) {
                case 'section':
                    $sections = ($te['section_placement'] === 'before') ? $sectionsBefore : $sectionsAfter;
                    foreach ($sections as $sec) {
                        if ($sec['type'] !== $te['element_subtype']) continue;
                        if (!($sec['is_exported'] ?? 1)) continue;
                        $items[] = ['type' => 'section', 'id' => $sec['id'], 'title' => $sec['title'] ?: Section::getTypeName($sec['type']), 'content' => $prepare($sec['content'])];
                    }
                    break;

                case 'act':
                    foreach ($acts as $act) {
                        if (!empty($act['content']) && ($act['is_exported'] ?? 1)) {
                            $items[] = ['type' => 'act', 'id' => $act['id'], 'title' => $act['title'], 'content' => $prepare($act['content'])];
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
                        $items[] = ['type' => 'note', 'id' => $note['id'], 'title' => $note['title'], 'content' => $prepare($note['content'])];
                    }
                    break;

                case 'element':
                    foreach ($customElementsByType[$te['id']] ?? [] as $e) {
                        if (!($e['is_exported'] ?? 1)) continue;
                        $items[] = ['type' => 'element', 'id' => $e['id'], 'title' => $e['title'], 'content' => $prepare($e['content'])];
                        foreach ($customSubElementsByParent[$e['id']] ?? [] as $sub) {
                            if (!($sub['is_exported'] ?? 1)) continue;
                            $items[] = ['type' => 'element', 'id' => $sub['id'], 'title' => $sub['title'], 'content' => $prepare($sub['content'])];
                        }
                    }
                    break;
            }
        }

        return ['project' => $project, 'items' => $items];
    }

    /**
     * Build mindmap nodes/links (same logic as ProjectController->mindmap, no user_id check).
     */
    private function buildMindmapData(int $pid, array $project): array
    {
        $db = $this->f3->get('DB');

        $characterModel = new Character();
        $characters = $characterModel->getAllByProject($pid);

        $noteModel  = new Note();
        $notes      = $noteModel->getAllByProject($pid);

        $actModel = new Act();
        $acts     = $actModel->getAllByProject($pid);

        $sectionModel   = new Section();
        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $sectionsAfter  = $sectionModel->getAfterChapters($pid);

        $chapterModel = new Chapter();
        $chapters     = $chapterModel->getAllByProject($pid);

        $templateElements = [];
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId    = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            $template = $templateId
                ? ($templateModel->findAndCast(['id=?', $templateId])[0] ?? $templateModel->getDefault())
                : $templateModel->getDefault();
            if ($template) $templateElements = $templateModel->getElements($template['id']);
        }

        $topElementsByType   = [];
        $subElementsByParent = [];
        if ($db->exists('elements')) {
            $elementModel = new Element();
            foreach ($elementModel->getAllByProject($pid) as $elem) {
                if (!($elem['is_exported'] ?? 1)) continue;
                if (!empty($elem['parent_id'])) {
                    $subElementsByParent[$elem['parent_id']][] = $elem;
                } else {
                    $topElementsByType[$elem['template_element_id']][] = $elem;
                }
            }
        }

        $settings = json_decode($project['settings'] ?? '{}', true);

        $nodes = [];
        $links = [];

        $nodes[] = [
            'id'          => 'project',
            'name'        => $project['title'],
            'type'        => 'project',
            'description' => $project['summary'] ?? ($project['description'] ?? ''),
            'author'      => $settings['author'] ?? '',
        ];

        $exportedNotes     = array_filter($notes,    fn($n) => ($n['is_exported'] ?? 1));
        $exportedSecBefore = array_filter($sectionsBefore, fn($s) => ($s['is_exported'] ?? 1));
        $exportedSecAfter  = array_filter($sectionsAfter,  fn($s) => ($s['is_exported'] ?? 1));
        $exportedChapters  = array_filter($chapters,  fn($c) => ($c['is_exported'] ?? 1));
        $exportedChapterIds = array_column($exportedChapters, 'id');

        $hasOrphans = false;
        foreach ($exportedChapters as $ch) {
            if (empty($ch['act_id']) && empty($ch['parent_id'])) { $hasOrphans = true; break; }
        }

        if (!empty($characters)) {
            $nodes[] = ['id' => 'chars_group', 'name' => 'Personnages', 'type' => 'character_group', 'description' => ''];
            $links[] = ['source' => 'project', 'target' => 'chars_group'];
            foreach ($characters as $char) {
                $nodes[] = ['id' => 'char_' . $char['id'], 'name' => $char['name'], 'type' => 'character', 'content' => $char['description']];
                $links[] = ['source' => 'chars_group', 'target' => 'char_' . $char['id']];
            }
        }

        $beforeGroupAdded = false;
        $afterGroupAdded  = false;

        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) continue;

            switch ($te['element_type']) {
                case 'section':
                    $isBefore  = ($te['section_placement'] === 'before');
                    $sections  = $isBefore ? $exportedSecBefore : $exportedSecAfter;
                    $groupId   = $isBefore ? 'sec_before_group' : 'sec_after_group';
                    $groupName = $isBefore ? 'Avant-propos' : 'Annexes';

                    if (!empty($sections)) {
                        if ($isBefore && $beforeGroupAdded) break;
                        if (!$isBefore && $afterGroupAdded) break;
                        $nodes[] = ['id' => $groupId, 'name' => $groupName, 'type' => 'section_group', 'description' => ''];
                        $links[] = ['source' => 'project', 'target' => $groupId];
                        if ($isBefore) $beforeGroupAdded = true; else $afterGroupAdded = true;
                        foreach ($sections as $sec) {
                            $nodes[] = ['id' => 'sec_' . $sec['id'], 'name' => $sec['title'], 'type' => 'section', 'content' => $sec['content']];
                            $links[] = ['source' => $groupId, 'target' => 'sec_' . $sec['id']];
                        }
                    }
                    break;

                case 'act':
                    foreach ($acts as $act) {
                        if (!($act['is_exported'] ?? 1)) continue;
                        $nodes[] = ['id' => 'act_' . $act['id'], 'name' => $act['title'], 'type' => 'act', 'content' => $act['content'] ?? ''];
                        $links[] = ['source' => 'project', 'target' => 'act_' . $act['id']];
                    }
                    break;

                case 'chapter':
                    if ($hasOrphans) {
                        $nodes[] = ['id' => 'act_xxx', 'name' => 'Acte XXX', 'type' => 'act'];
                        $links[] = ['source' => 'project', 'target' => 'act_xxx'];
                    }
                    foreach ($exportedChapters as $ch) {
                        $nodes[] = [
                            'id'            => 'chapter_' . $ch['id'],
                            'name'          => $ch['title'],
                            'type'          => 'chapter',
                            'content'       => $ch['content'],
                            'description'   => '',
                            'is_subchapter' => !empty($ch['parent_id']),
                        ];
                        if (!empty($ch['parent_id']) && in_array($ch['parent_id'], $exportedChapterIds)) {
                            $links[] = ['source' => 'chapter_' . $ch['parent_id'], 'target' => 'chapter_' . $ch['id']];
                        } elseif (!empty($ch['act_id'])) {
                            $links[] = ['source' => 'act_' . $ch['act_id'], 'target' => 'chapter_' . $ch['id']];
                        } else {
                            $links[] = ['source' => 'act_xxx', 'target' => 'chapter_' . $ch['id']];
                        }
                    }
                    break;

                case 'note':
                    if (!empty($exportedNotes)) {
                        $nodes[] = ['id' => 'notes_group', 'name' => 'Notes', 'type' => 'note_group', 'description' => ''];
                        $links[] = ['source' => 'project', 'target' => 'notes_group'];
                        foreach ($exportedNotes as $note) {
                            $nodes[] = ['id' => 'note_' . $note['id'], 'name' => $note['title'] ?: 'Sans titre', 'type' => 'note', 'content' => $note['content']];
                            $links[] = ['source' => 'notes_group', 'target' => 'note_' . $note['id']];
                        }
                    }
                    break;

                case 'element':
                    $teId  = $te['id'];
                    $items = $topElementsByType[$teId] ?? [];
                    if (empty($items)) break;
                    $cfg     = json_decode($te['config_json'] ?? '{}', true);
                    $groupId = 'elem_group_' . $teId;
                    $nodes[] = ['id' => $groupId, 'name' => $cfg['label_plural'] ?? 'Éléments', 'type' => 'element_group', 'description' => ''];
                    $links[] = ['source' => 'project', 'target' => $groupId];
                    foreach ($items as $elem) {
                        $nid = 'element_' . $elem['id'];
                        $nodes[] = ['id' => $nid, 'name' => $elem['title'], 'type' => 'element', 'content' => $elem['content'] ?? '', 'is_subelement' => false];
                        $links[] = ['source' => $groupId, 'target' => $nid];
                        foreach ($subElementsByParent[$elem['id']] ?? [] as $sub) {
                            $snid = 'element_' . $sub['id'];
                            $nodes[] = ['id' => $snid, 'name' => $sub['title'], 'type' => 'element', 'content' => $sub['content'] ?? '', 'is_subelement' => true];
                            $links[] = ['source' => $nid, 'target' => $snid];
                        }
                    }
                    break;
            }
        }

        return ['nodes' => $nodes, 'links' => $links];
    }
}
