<?php

class SectionController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Edit or create a section
     * GET /project/@pid/section/@type
     */
    public function edit()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $type = $this->f3->get('PARAMS.type');
        $sectionId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        // Verify project ownership
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }
        $project = $project[0];

        // Load section if editing
        $section = null;
        if ($sectionId) {
            $sectionModel = new Section();
            $sections = $sectionModel->findAndCast(['id=? AND project_id=?', $sectionId, $pid]);
            if ($sections) {
                $section = $sections[0];
            }
        }

        $this->render('section/edit.html', [
            'title' => ($section ? 'Modifier' : 'Créer') . ' - ' . Section::getTypeName($type),
            'project' => $project,
            'section' => $section ?? ['title' => '', 'content' => '', 'comment' => '', 'image_path' => '', 'id' => null],
            'sectionType' => $type,
            'sectionTypeName' => Section::getTypeName($type),
            'errors' => []
        ]);
    }

    /**
     * Save section (create or update)
     * POST /project/@pid/section/@type
     */
    public function save()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $type = $this->f3->get('PARAMS.type');
        $sectionId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        // Verify project ownership
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }
        $project = $project[0];

        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $content = $this->cleanQuillHtml($content);
        $comment = $_POST['comment'] ?? '';
        $comment = $this->cleanQuillHtml($comment);
        $errors = [];

        // Handle image upload for cover/back_cover
        $imagePath = null;
        if (($type === 'cover' || $type === 'back_cover') && isset($_FILES['image'])) {
            // SECURITY: Multi-level validation (fixes vulnerability #14)
            $validation = $this->validateImageUpload($_FILES['image']);

            if (!$validation['success']) {
                $errors[] = $validation['error'];
            } else {
                $uploadDir = 'public/uploads/covers/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Use validated extension (not from filename)
                $extension = $validation['extension'];
                $filename = uniqid('cover_') . '.' . $extension;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = '/' . $targetPath;
                } else {
                    $errors[] = 'Erreur lors du téléchargement de l\'image.';
                }
            }
        }

        if (empty($errors)) {
            $sectionModel = new Section();

            // If editing existing section, preserve image if no new upload
            if ($sectionId) {
                $existing = $sectionModel->findAndCast(['id=? AND project_id=?', $sectionId, $pid]);
                if ($existing && !$imagePath) {
                    $imagePath = $existing[0]['image_path'];
                }
            }

            $result = $sectionModel->createOrUpdate($pid, $type, $title, $content, $comment, $imagePath, $sectionId);

            if ($result) {
                $this->f3->reroute('/project/' . $pid);
                return;
            } else {
                $errors[] = 'Erreur lors de l\'enregistrement de la section.';
            }
        }

        // Re-render form with errors
        $section = null;
        if ($sectionId) {
            $sectionModel = new Section();
            $sections = $sectionModel->findAndCast(['id=?', $sectionId]);
            if ($sections) {
                $section = $sections[0];
            }
        }

        $this->render('section/edit.html', [
            'title' => ($section ? 'Modifier' : 'Créer') . ' - ' . Section::getTypeName($type),
            'project' => $project,
            'section' => $section ?: ['title' => $title, 'content' => $content, 'comment' => $comment],
            'sectionType' => $type,
            'sectionTypeName' => Section::getTypeName($type),
            'errors' => $errors
        ]);
    }

    /**
     * Delete a section
     * GET /section/@id/delete
     */
    public function delete()
    {
        $sid = (int) $this->f3->get('PARAMS.id');

        $sectionModel = new Section();
        $sectionModel->load(['id=?', $sid]);

        if (!$sectionModel->dry()) {
            // Verify project ownership
            $projectModel = new Project();
            if ($projectModel->count(['id=? AND user_id=?', $sectionModel->project_id, $this->currentUser()['id']])) {
                $pid = $sectionModel->project_id;
                $sectionModel->erase();
                $this->f3->reroute('/project/' . $pid);
                return;
            }
        }

        $this->f3->reroute('/dashboard');
    }
}
