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
        $user = $this->currentUser();
        if (($type === 'cover' || $type === 'back_cover') && isset($_FILES['image'])) {
            // SECURITY: Multi-level validation (fixes vulnerability #14)
            $validation = $this->validateImageUpload($_FILES['image']);

            if (!$validation['success']) {
                $errors[] = $validation['error'];
            } else {
                $uploadDir = 'data/' . $user['email'] . '/projects/' . $pid . '/sections/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Use validated extension (not from filename)
                $extension = $validation['extension'];
                $filename = $type . '.' . $extension;
                $targetPath = $uploadDir . $filename;

                // Delete any existing image for this type (may have different extension)
                foreach (glob($uploadDir . $type . '.*') as $old) {
                    if ($old !== $targetPath) {
                        unlink($old);
                    }
                }

                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = '/project/' . $pid . '/section/' . $type . '/image';
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
     * Delete section image
     * POST /project/@pid/section/@type/image/delete
     */
    public function deleteImage()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $type = $this->f3->get('PARAMS.type');
        $user = $this->currentUser();

        // Verify project ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            $this->f3->error(403);
            return;
        }

        // Delete physical file
        $dir = 'data/' . $user['email'] . '/projects/' . $pid . '/sections/';
        foreach (glob($dir . $type . '.*') ?: [] as $f) {
            unlink($f);
        }

        // Clear image_path in DB
        $sectionModel = new Section();
        $sectionModel->load(['project_id=? AND type=?', $pid, $type]);
        if (!$sectionModel->dry()) {
            $sid = $sectionModel->id;
            $sectionModel->image_path = null;
            $sectionModel->save();
            $this->f3->reroute('/project/' . $pid . '/section/' . $type . '?id=' . $sid);
        }

        $this->f3->reroute('/project/' . $pid);
    }

    /**
     * Serve section image
     * GET /project/@pid/section/@type/image
     */
    public function image()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $type = $this->f3->get('PARAMS.type');

        // Allow access for owner OR collaborator
        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(403);
            return;
        }

        // Use project OWNER's email to build the correct path
        $ownerEmail = $this->getProjectOwnerEmail($pid);
        if (!$ownerEmail) {
            $this->f3->error(404);
            return;
        }

        $dir = 'data/' . $this->sanitizeEmailForPath($ownerEmail) . '/projects/' . $pid . '/sections/';
        $filePath = null;
        foreach (glob($dir . $type . '.*') ?: [] as $f) {
            $filePath = $f;
            break;
        }

        if (!$filePath || !file_exists($filePath)) {
            $this->f3->error(404);
            return;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000');
        readfile($filePath);
        exit;
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
            $user = $this->currentUser();
            if ($projectModel->count(['id=? AND user_id=?', $sectionModel->project_id, $user['id']])) {
                $pid = $sectionModel->project_id;
                $type = $sectionModel->type;

                // Delete associated image file if any
                if (!empty($sectionModel->image_path)) {
                    $dir = 'data/' . $user['email'] . '/projects/' . $pid . '/sections/';
                    foreach (glob($dir . $type . '.*') as $f) {
                        unlink($f);
                    }
                }

                $sectionModel->erase();
                $this->f3->reroute('/project/' . $pid);
                return;
            }
        }

        $this->f3->reroute('/dashboard');
    }
}
