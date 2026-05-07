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

        // Get project settings
        $settings = json_decode($project['settings'] ?? '{}', true);
        $authorName = $settings['author'] ?? $this->getUserFullName($this->currentUser());
        $lpp = $project['lines_per_page'] ?: 38;

        // Get cover image URL
        $coverImage = null;
        if (!empty($project['cover_image'])) {
            $coverImage = $this->f3->get('BASE') . '/project/' . $pid . '/cover';
        }

        // Use ReadingContentService to build reading content
        $readingService = new ReadingContentService($this->f3->get('DB'), $this);
        
        // Load template elements
        $templateElements = $readingService->loadTemplateElements($project);
        
        // Load and organize reading data
        $readingData = $readingService->loadReadingData($pid, $lpp);
        
        // Build reading content and TOC
        $buildResult = $readingService->buildReadingContent($readingData, $templateElements);
        
        $readingContent = $buildResult['readingContent'];
        $tocItems = $buildResult['tocItems'];
        $totalPages = $buildResult['totalPages'];

        // FALLBACK: If template produced no chapters/acts but the project has chapters, render them directly
        if (!$readingService->hasRenderedChapters($readingContent) && !empty($readingData['allChapters'])) {
            $fallbackCurrentPage = $totalPages + 1;
            $readingService->buildFallbackContent(
                $readingData,
                $readingContent,
                $tocItems,
                $fallbackCurrentPage
            );
            $totalPages = $fallbackCurrentPage - 1;
        }

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
        header('Content-Type: application/json');
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
        header('Content-Type: application/json');
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
        header('Content-Type: application/json');
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
