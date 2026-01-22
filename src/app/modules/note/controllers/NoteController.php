<?php

class NoteController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Edit or create a note
     * GET /project/@pid/note/edit
     */
    public function edit()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $noteId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        // Verify project ownership
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }
        $project = $project[0];

        // Load note if editing
        $note = null;
        if ($noteId) {
            $noteModel = new Note();
            $notes = $noteModel->findAndCast(['id=? AND project_id=?', $noteId, $pid]);
            if ($notes) {
                $note = $notes[0];
            }
        }

        $this->render('note/edit.html', [
            'title' => ($note ? 'Modifier' : 'Créer') . ' - Note',
            'project' => $project,
            'note' => $note ?? ['title' => '', 'content' => '', 'image_path' => '', 'id' => null],
            'errors' => []
        ]);
    }

    /**
     * Save note (create or update)
     * POST /project/@pid/note/save
     */
    public function save()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $noteId = isset($_GET['id']) ? (int) $_GET['id'] : null;

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
        $errors = [];

        if (empty($errors)) {
            $noteModel = new Note();
            if ($noteId) {
                $noteModel->load(['id=? AND project_id=?', $noteId, $pid]);
                if (!$noteModel->dry()) {
                    $noteModel->title = $title;
                    $noteModel->content = $content;
                    $noteModel->save();
                }
            } else {
                $noteModel->create($pid, $title, $content);
            }

            $this->f3->reroute('/project/' . $pid);
            return;
        }

        // Re-render form with errors
        $this->render('note/edit.html', [
            'title' => ($noteId ? 'Modifier' : 'Créer') . ' - Note',
            'project' => $project,
            'note' => ['title' => $title, 'content' => $content, 'id' => $noteId],
            'errors' => $errors
        ]);
    }

    /**
     * Delete a note
     * GET /note/@id/delete
     */
    public function delete()
    {
        $nid = (int) $this->f3->get('PARAMS.id');

        $noteModel = new Note();
        $noteModel->load(['id=?', $nid]);

        if (!$noteModel->dry()) {
            // Verify project ownership
            $projectModel = new Project();
            if ($projectModel->count(['id=? AND user_id=?', $noteModel->project_id, $this->currentUser()['id']])) {
                $pid = $noteModel->project_id;
                $noteModel->erase();
                $this->f3->reroute('/project/' . $pid);
                return;
            }
        }

        $this->f3->reroute('/dashboard');
    }
}
