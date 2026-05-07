<?php

/**
 * ApiController — REST API for MCP access.
 *
 * Authentication : Bearer token in Authorization header (or ?token= fallback).
 * All responses    : JSON (content converted to plain text for content_text fields).
 * No session       : user context resolved from token on every request.
 * No CSRF          : token-based authentication replaces CSRF protection.
 */
require_once __DIR__ . '/../../../services/api/ApiFetchService.php';
require_once __DIR__ . '/../../../services/api/ProjectApiService.php';
require_once __DIR__ . '/../../../services/api/ActApiService.php';
require_once __DIR__ . '/../../../services/api/ChapterApiService.php';
require_once __DIR__ . '/../../../services/api/SectionApiService.php';
require_once __DIR__ . '/../../../services/api/NoteApiService.php';
require_once __DIR__ . '/../../../services/api/CharacterApiService.php';
require_once __DIR__ . '/../../../services/api/ElementApiService.php';
require_once __DIR__ . '/../../../services/api/ImageApiService.php';
require_once __DIR__ . '/../../../services/api/ExportApiService.php';
require_once __DIR__ . '/../../../services/api/SearchApiService.php';

class ApiController extends ApiBaseController
{
    private ?ApiFetchService $fetchService = null;
    private ?ProjectApiService $projectApiService = null;
    private ?ActApiService $actApiService = null;
    private ?ChapterApiService $chapterApiService = null;
    private ?SectionApiService $sectionApiService = null;
    private ?NoteApiService $noteApiService = null;
    private ?CharacterApiService $characterApiService = null;
    private ?ElementApiService $elementApiService = null;
    private ?ImageApiService $imageApiService = null;
    private ?ExportApiService $exportApiService = null;
    private ?SearchApiService $searchApiService = null;

    private function getFetchService(): ApiFetchService
    {
        if ($this->fetchService === null) {
            $this->fetchService = new ApiFetchService($this->f3->get('DB'), $this->f3);
        }
        return $this->fetchService;
    }

    private function getProjectApiService(): ProjectApiService
    {
        if ($this->projectApiService === null) {
            $this->projectApiService = new ProjectApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->projectApiService;
    }

    private function getActApiService(): ActApiService
    {
        if ($this->actApiService === null) {
            $this->actApiService = new ActApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->actApiService;
    }

    private function getChapterApiService(): ChapterApiService
    {
        if ($this->chapterApiService === null) {
            $this->chapterApiService = new ChapterApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->chapterApiService;
    }

    private function getSectionApiService(): SectionApiService
    {
        if ($this->sectionApiService === null) {
            $this->sectionApiService = new SectionApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->sectionApiService;
    }

    private function getNoteApiService(): NoteApiService
    {
        if ($this->noteApiService === null) {
            $this->noteApiService = new NoteApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->noteApiService;
    }

    private function getCharacterApiService(): CharacterApiService
    {
        if ($this->characterApiService === null) {
            $this->characterApiService = new CharacterApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->characterApiService;
    }

    private function getElementApiService(): ElementApiService
    {
        if ($this->elementApiService === null) {
            $this->elementApiService = new ElementApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->elementApiService;
    }

    private function getImageApiService(): ImageApiService
    {
        if ($this->imageApiService === null) {
            $this->imageApiService = new ImageApiService($this->f3->get('DB'), $this->getFetchService(), $this->f3);
        }
        return $this->imageApiService;
    }

    private function getExportApiService(): ExportApiService
    {
        if ($this->exportApiService === null) {
            $this->exportApiService = new ExportApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->exportApiService;
    }

    private function getSearchApiService(): SearchApiService
    {
        if ($this->searchApiService === null) {
            $this->searchApiService = new SearchApiService($this->f3->get('DB'), $this->getFetchService());
        }
        return $this->searchApiService;
    }

    // ──────────────────────────────────────────────────────────────
    // PROJECTS
    // ──────────────────────────────────────────────────────────────

    public function listProjects()
    {
        $user = $this->currentUser();
        [$offset, $limit] = $this->getPaginationParams();
        [$rows, $total] = $this->getProjectApiService()->listProjects($user['id'], $offset, $limit);
        $this->paginatedOut($rows, $total, $offset, $limit);
    }

    public function createProject()
    {
        $user = $this->currentUser();
        $body = $this->getBody();
        try {
            $project = $this->getProjectApiService()->createProject($user['id'], $body);
            $this->jsonOut($project, 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function getProject()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $project = $this->getProjectApiService()->getProject($id, $user['id']);
        if (!$project) {
            $this->jsonError('Projet introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut($project);
    }

    public function updateProject()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $body = $this->getBody();
        try {
            $project = $this->getProjectApiService()->updateProject($id, $user['id'], $body);
            if (!$project) {
                $this->jsonError('Projet introuvable ou accès refusé.', 404, 'NOT_FOUND');
            }
            $this->jsonOut($project);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function deleteProject()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getProjectApiService()->deleteProject($id, $user['id'])) {
            $this->jsonError('Projet introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // ACTS
    // ──────────────────────────────────────────────────────────────

    public function listActs()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getActApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        [$offset, $limit] = $this->getPaginationParams();
        [$rows, $total] = $this->getActApiService()->listActs($pid, $offset, $limit);
        $this->paginatedOut($rows, $total, $offset, $limit);
    }

    public function createAct()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        $body = $this->getBody();
        try {
            $act = $this->getActApiService()->createAct($pid, $user['id'], $body);
            $this->jsonOut($act, 201);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function getAct()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $act = $this->getActApiService()->getAct($id, $user['id']);
        if (!$act) {
            $this->jsonError('Acte introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut($act);
    }

    public function updateAct()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $body = $this->getBody();
        try {
            $act = $this->getActApiService()->updateAct($id, $user['id'], $body);
            if (!$act) {
                $this->jsonError('Acte introuvable ou accès refusé.', 404, 'NOT_FOUND');
            }
            $this->jsonOut($act);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function deleteAct()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getActApiService()->deleteAct($id, $user['id'])) {
            $this->jsonError('Acte introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // CHAPTERS
    // ──────────────────────────────────────────────────────────────

    public function getChapter()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $chapter = $this->getChapterApiService()->getChapter($id, $user['id']);
        if (!$chapter) {
            $this->jsonError('Chapitre introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $subChapters = $this->db->exec(
            'SELECT id, title, content, resume, word_count, order_index, updated_at FROM chapters WHERE parent_id=? ORDER BY order_index ASC, id ASC',
            [(int)$chapter['id']]
        );
        $subs = array_map(function ($s) {
            return [
                'id'           => (int)$s['id'],
                'title'        => $s['title'],
                'content_html' => $s['content'] ?? '',
                'content_text' => $this->getFetchService()->htmlToText($s['content'] ?? ''),
                'resume'       => $s['resume'] ?? '',
                'word_count'   => (int)$s['word_count'],
                'order_index'  => (int)$s['order_index'],
                'updated_at'   => $s['updated_at'],
            ];
        }, $subChapters ?: []);

        $chapter['sub_chapters'] = $subs;
        $this->jsonOut($chapter);
    }

    public function createChapter()
    {
        $user = $this->currentUser();
        $body = $this->getBody();
        try {
            $chapter = $this->getChapterApiService()->createChapter($user['id'], $body);
            $this->jsonOut($chapter, 201);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function updateChapter()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $body = $this->getBody();
        try {
            $chapter = $this->getChapterApiService()->updateChapter($id, $user['id'], $body);
            if (!$chapter) {
                $this->jsonError('Chapitre introuvable ou accès refusé.', 404, 'NOT_FOUND');
            }
            $this->jsonOut($chapter);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function deleteChapter()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getChapterApiService()->deleteChapter($id, $user['id'])) {
            $this->jsonError('Chapitre introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // SECTIONS
    // ──────────────────────────────────────────────────────────────

    public function listSections()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getSectionApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        [$offset, $limit] = $this->getPaginationParams();
        [$rows, $total] = $this->getSectionApiService()->listSections($pid, $offset, $limit);
        $typeLabels = [
            'cover'        => 'Couverture',
            'preface'      => 'Préface',
            'introduction' => 'Introduction',
            'prologue'     => 'Prologue',
            'postface'     => 'Postface',
            'appendices'   => 'Annexes',
            'back_cover'   => 'Quatrième de couverture',
        ];
        foreach ($rows as &$r) {
            $r['type_label'] = $typeLabels[$r['type']] ?? $r['type'];
            $r['has_image']  = !empty($r['image_path']);
            unset($r['image_path']);
        }
        $this->paginatedOut($rows, $total, $offset, $limit);
    }

    public function createSection()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        $body = $this->getBody();
        try {
            $section = $this->getSectionApiService()->createSection($user['id'], $body);
            $this->jsonOut($section, 201);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function getSection()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $section = $this->getSectionApiService()->getSection($id, $user['id']);
        if (!$section) {
            $this->jsonError('Section introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut($section);
    }

    public function updateSection()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $body = $this->getBody();
        try {
            $section = $this->getSectionApiService()->updateSection($id, $user['id'], $body);
            if (!$section) {
                $this->jsonError('Section introuvable ou accès refusé.', 404, 'NOT_FOUND');
            }
            $this->jsonOut($section);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function deleteSection()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getSectionApiService()->deleteSection($id, $user['id'])) {
            $this->jsonError('Section introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // NOTES
    // ──────────────────────────────────────────────────────────────

    public function listNotes()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getNoteApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        [$offset, $limit] = $this->getPaginationParams();
        [$rows, $total] = $this->getNoteApiService()->listNotes($pid, $offset, $limit);
        $this->paginatedOut($rows, $total, $offset, $limit);
    }

    public function createNote()
    {
        $user = $this->currentUser();
        $body = $this->getBody();
        try {
            $note = $this->getNoteApiService()->createNote($user['id'], $body);
            $this->jsonOut($note, 201);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function getNote()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $note = $this->getNoteApiService()->getNote($id, $user['id']);
        if (!$note) {
            $this->jsonError('Note introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut($note);
    }

    public function updateNote()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $body = $this->getBody();
        try {
            $note = $this->getNoteApiService()->updateNote($id, $user['id'], $body);
            if (!$note) {
                $this->jsonError('Note introuvable ou accès refusé.', 404, 'NOT_FOUND');
            }
            $this->jsonOut($note);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function deleteNote()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getNoteApiService()->deleteNote($id, $user['id'])) {
            $this->jsonError('Note introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // CHARACTERS
    // ──────────────────────────────────────────────────────────────

    public function listCharacters()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getCharacterApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        [$offset, $limit] = $this->getPaginationParams();
        [$rows, $total] = $this->getCharacterApiService()->listCharacters($pid, $offset, $limit);
        $this->paginatedOut($rows, $total, $offset, $limit);
    }

    public function createCharacter()
    {
        $user = $this->currentUser();
        $body = $this->getBody();
        try {
            $character = $this->getCharacterApiService()->createCharacter($user['id'], $body);
            $this->jsonOut($character, 201);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function getCharacter()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $character = $this->getCharacterApiService()->getCharacter($id, $user['id']);
        if (!$character) {
            $this->jsonError('Personnage introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut($character);
    }

    public function updateCharacter()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $body = $this->getBody();
        try {
            $character = $this->getCharacterApiService()->updateCharacter($id, $user['id'], $body);
            if (!$character) {
                $this->jsonError('Personnage introuvable ou accès refusé.', 404, 'NOT_FOUND');
            }
            $this->jsonOut($character);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function deleteCharacter()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getCharacterApiService()->deleteCharacter($id, $user['id'])) {
            $this->jsonError('Personnage introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // ELEMENTS
    // ──────────────────────────────────────────────────────────────

    public function listElementTypes()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getElementApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        $result = $this->getElementApiService()->listElementTypes($pid);
        $this->jsonOut($result);
    }

    public function listElements()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getElementApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        [$offset, $limit] = $this->getPaginationParams();
        [$rows, $total] = $this->getElementApiService()->listElements($pid, $offset, $limit);
        $this->paginatedOut($rows, $total, $offset, $limit);
    }

    public function createElement()
    {
        $user = $this->currentUser();
        $body = $this->getBody();
        try {
            $element = $this->getElementApiService()->createElement($user['id'], $body);
            $this->jsonOut($element, 201);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function getElement()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $element = $this->getElementApiService()->getElement($id, $user['id']);
        if (!$element) {
            $this->jsonError('Élément introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut($element);
    }

    public function updateElement()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        $body = $this->getBody();
        try {
            $element = $this->getElementApiService()->updateElement($id, $user['id'], $body);
            if (!$element) {
                $this->jsonError('Élément introuvable ou accès refusé.', 404, 'NOT_FOUND');
            }
            $this->jsonOut($element);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

    public function deleteElement()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getElementApiService()->deleteElement($id, $user['id'])) {
            $this->jsonError('Élément introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // IMAGES (project files)
    // ──────────────────────────────────────────────────────────────

    public function listImages()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getImageApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        $base = $this->f3->get('BASE');
        $result = $this->getImageApiService()->listImages($pid, $base);
        $this->jsonOut($result);
    }

    public function uploadImage()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->getImageApiService()->hasProjectAccess($pid, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        if (empty($_FILES['file'])) $this->jsonError('Aucun fichier reçu (champ: file).', 422, 'INVALID_INPUT');

        $validation = $this->getImageApiService()->validateImageUpload($_FILES['file'], 5);
        if (!$validation['success']) $this->jsonError($validation['error'], 422, 'INVALID_INPUT');

        $ownerEmail = $this->getImageApiService()->getProjectOwnerEmail($pid);
        $uploadDir  = $this->f3->get('BASEPATH') . 'public/uploads/' . $pid . '/';
        $safeName   = bin2hex(random_bytes(8));
        $dest       = (new ImageUploadService())->move($_FILES['file'], $validation['extension'], $uploadDir, $safeName);

        if (!$dest) {
            $this->jsonError('Erreur lors de la sauvegarde du fichier.', 500, 'SERVER_ERROR');
        }

        $relPath = 'public/uploads/' . $pid . '/' . basename($dest);
        $image = $this->getImageApiService()->createImage(
            $pid, $_FILES['file']['name'], $relPath, $_FILES['file']['type'], (int)$_FILES['file']['size']
        );
        $base = $this->f3->get('BASE');
        $this->jsonOut([
            'id'       => $image['id'],
            'filename' => $image['filename'],
            'url'      => $base . '/' . $relPath,
            'size_kb'  => round($_FILES['file']['size'] / 1024, 1),
        ], 201);
    }

    public function deleteImage()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        $fid = (int)$this->f3->get('PARAMS.fid');
        if (!$this->getImageApiService()->deleteImage($pid, $fid, $user['id'])) {
            $this->jsonError('Fichier introuvable ou accès refusé.', 404, 'NOT_FOUND');
        }
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $fid]);
    }

    // ──────────────────────────────────────────────────────────────
    // EXPORT MARKDOWN
    // ──────────────────────────────────────────────────────────────

    public function exportMarkdown()
    {
        $user = $this->currentUser();
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->getExportApiService()->hasProjectAccess($id, $user['id'])) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }

        $content = $this->getExportApiService()->generateMarkdownExport($id);
        if ($content === null) {
            $this->jsonError('Projet introuvable.', 404, 'NOT_FOUND');
        }

        header('Content-Type: text/markdown; charset=utf-8');
        echo $content;
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // SEARCH
    // ──────────────────────────────────────────────────────────────

    public function search()
    {
        $user = $this->currentUser();
        $q = trim($this->f3->get('GET.q') ?? '');
        $pid = $this->f3->get('GET.pid') ? (int)$this->f3->get('GET.pid') : null;

        try {
            $result = $this->getSearchApiService()->search($user['id'], $q, $pid);
            $this->jsonOut($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }



    // ──────────────────────────────────────────────────────────────
    // SYNOPSIS
    // ──────────────────────────────────────────────────────────────

    public function getSynopsis()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        try {
            $synopsis = $this->getChapterApiService()->fetchOrCreateSynopsis($pid, $user['id']);
            $this->jsonOut($synopsis);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        }
    }

    public function updateSynopsis()
    {
        $user = $this->currentUser();
        $pid = (int)$this->f3->get('PARAMS.pid');
        $body = $this->getBody();
        try {
            $synopsis = $this->getChapterApiService()->updateSynopsis($pid, $user['id'], $body);
            $this->jsonOut($synopsis);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403, 'FORBIDDEN');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422, 'INVALID_INPUT');
        }
    }

}
