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
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }
        $project = $project[0];

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

        // Helper to calculate pages
        $calculatePages = function ($content) use ($lpp) {
            $cleanContent = strip_tags(html_entity_decode($content ?? ''));
            $lines = 0;
            if ($cleanContent !== '') {
                $lines = ceil(strlen($cleanContent) / 80);
            }
            return max(1, ceil($lines / $lpp));
        };

        // 1. Sections Before Chapters
        foreach ($sectionsBeforeChapters as $sec) {
            if (!($sec['is_exported'] ?? 1))
                continue;

            $pages = $calculatePages($sec['content']);
            $typeName = Section::getTypeName($sec['type']);

            $readingContent[] = [
                'type' => 'section',
                'id' => $sec['id'],
                'title' => $sec['title'] ?: $typeName,
                'content' => $sec['content'],
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

        // 2. Organize chapters by act
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

        // 3. Process Acts and Chapters
        foreach ($acts as $act) {
            // Check if act has any exported chapters
            $actChapters = $chaptersByAct[$act['id']] ?? [];
            $hasExportedChapters = false;
            foreach ($actChapters as $ch) {
                if ($ch['is_exported'] ?? 1) {
                    $hasExportedChapters = true;
                    break;
                }
            }

            // Skip act if it has no content and no exported chapters
            $actHasContent = !empty($act['content']) && ($act['is_exported'] ?? 1);
            if (!$actHasContent && !$hasExportedChapters) {
                continue;
            }

            // Add act title to TOC only if it will be displayed
            $tocItems[] = [
                'title' => $act['title'],
                'page' => $currentPage,
                'level' => 0
            ];

            // Act content (if any and exported)
            if ($actHasContent) {
                $pages = $calculatePages($act['content']);
                $readingContent[] = [
                    'type' => 'act',
                    'id' => $act['id'],
                    'title' => $act['title'],
                    'content' => $act['content'],
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $pages - 1
                ];
                $currentPage += $pages;
            }

            // Chapters in this act
            foreach ($actChapters as $ch) {
                if (!($ch['is_exported'] ?? 1))
                    continue;

                $pages = $calculatePages($ch['content']);

                $readingContent[] = [
                    'type' => 'chapter',
                    'id' => $ch['id'],
                    'title' => $ch['title'],
                    'content' => $ch['content'],
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
                    if (!($sub['is_exported'] ?? 1))
                        continue;

                    $subPages = $calculatePages($sub['content']);

                    $readingContent[] = [
                        'type' => 'subchapter',
                        'id' => $sub['id'],
                        'title' => $sub['title'],
                        'content' => $sub['content'],
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

        // Orphan chapters
        foreach ($chaptersWithoutAct as $ch) {
            if (!($ch['is_exported'] ?? 1))
                continue;

            $pages = $calculatePages($ch['content']);

            $readingContent[] = [
                'type' => 'chapter',
                'title' => $ch['title'],
                'content' => $ch['content'],
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
                if (!($sub['is_exported'] ?? 1))
                    continue;

                $subPages = $calculatePages($sub['content']);

                $readingContent[] = [
                    'type' => 'subchapter',
                    'title' => $sub['title'],
                    'content' => $sub['content'],
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

        // 4. Sections After Chapters
        foreach ($sectionsAfterChapters as $sec) {
            if (!($sec['is_exported'] ?? 1))
                continue;

            $pages = $calculatePages($sec['content']);
            $typeName = Section::getTypeName($sec['type']);

            $readingContent[] = [
                'type' => 'section',
                'id' => $sec['id'],
                'title' => $sec['title'] ?: $typeName,
                'content' => $sec['content'],
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

        // 5. Notes (at the end)
        foreach ($notes as $note) {
            if (!($note['is_exported'] ?? 1))
                continue;

            $pages = $calculatePages($note['content']);

            $readingContent[] = [
                'type' => 'note',
                'id' => $note['id'],
                'title' => $note['title'],
                'content' => $note['content'],
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

        $totalPages = $currentPage - 1;

        $this->render('lecture/read.html', [
            'title' => 'Lecture: ' . htmlspecialchars($project['title']),
            'project' => $project,
            'authorName' => $authorName,
            'coverImage' => $coverImage,
            'readingContent' => $readingContent,
            'tocItems' => $tocItems,
            'totalPages' => $totalPages
        ]);
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

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $model->project_id, $this->currentUser()['id']])) {
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

    private function getUserFullName($user)
    {
        if (!$user)
            return '';
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        return trim($firstName . ' ' . $lastName);
    }
}
