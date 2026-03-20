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
     * List all notes for a project
     * GET /project/@pid/notes
     */
    public function list()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');

        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $projects = $projectModel->findAndCast(['id=?', $pid]);
        if (!$projects) {
            $this->f3->error(404);
            return;
        }
        $project = $projects[0];

        $noteModel = new Note();
        $notes = $noteModel->getAllByProject($pid);

        // Assign cycling color index + compute word count from content
        $notes = array_map(function ($n, $idx) {
            $n['title']      = html_entity_decode(strip_tags($n['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $n['colorIndex'] = ($idx % 6) + 1;
            if (!isset($n['wc'])) {
                $text = strip_tags($n['content'] ?? '');
                $n['wc'] = $text ? str_word_count($text) : 0;
            }
            return $n;
        }, $notes, array_keys($notes));

        $notesJson = json_encode(array_map(function ($n) {
            return [
                'id'      => (int) $n['id'],
                'title'   => $n['title'] ?: 'Note sans titre',
                'content' => $n['content'] ?? '',
                'comment' => $n['comment'] ?? '',
                'wc'      => (int) ($n['wc'] ?? 0),
            ];
        }, $notes), JSON_HEX_TAG | JSON_HEX_AMP);

        $this->render('note/list.html', [
            'title'     => 'Notes — ' . $project['title'],
            'project'   => $project,
            'notes'     => $notes,
            'notesJson' => $notesJson,
            'isOwner'   => $this->isOwner($pid),
        ]);
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
            'note' => $note ?? ['title' => '', 'content' => '', 'comment' => '', 'image_path' => '', 'id' => null],
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

        $title = html_entity_decode(strip_tags(trim($_POST['title'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = $_POST['content'] ?? '';
        $content = $this->cleanQuillHtml($content);
        $comment = $_POST['comment'] ?? '';
        $comment = $this->cleanQuillHtml($comment);
        $errors = [];

        if (empty($errors)) {
            $noteModel = new Note();
            if ($noteId) {
                $noteModel->load(['id=? AND project_id=?', $noteId, $pid]);
                if (!$noteModel->dry()) {
                    $noteModel->title = $title;
                    $noteModel->content = $content;
                    $noteModel->comment = $comment;
                    $noteModel->save();
                    $this->logActivity($pid, 'update', 'note', $noteId, $title);
                }
            } else {
                $nid = $noteModel->create($pid, $title, $content, $comment);
                $this->logActivity($pid, 'create', 'note', $nid ?: null, $title);
            }

            $this->f3->reroute('/project/' . $pid);
            return;
        }

        // Re-render form with errors
        $this->render('note/edit.html', [
            'title' => ($noteId ? 'Modifier' : 'Créer') . ' - Note',
            'project' => $project,
            'note' => ['title' => $title, 'content' => $content, 'comment' => $comment, 'id' => $noteId],
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
                $pid   = $noteModel->project_id;
                $label = $noteModel->title;
                $noteModel->erase();
                $this->logActivity($pid, 'delete', 'note', $nid, $label);
                $this->f3->reroute('/project/' . $pid);
                return;
            }
        }

        $this->f3->reroute('/dashboard');
    }
}
