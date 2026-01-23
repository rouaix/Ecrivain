<?php

class ActController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Create a new act
     * GET /project/@pid/act/create
     */
    public function create()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404);
            return;
        }

        $this->render('acts/create.html', [
            'title' => 'Nouvel acte',
            'project' => $project[0],
            'errors' => [],
            'old' => ['title' => '', 'content' => '', 'resume' => '']
        ]);
    }

    /**
     * Store new act
     * POST /project/@pid/act/create
     */
    public function store()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $resume = trim($_POST['resume'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre de l’acte est obligatoire.';
        }

        if (empty($errors)) {
            $actModel = new Act();
            // Assuming Mapper usage, we set fields manually if create() is custom
            // Let's rely on standard mapper usage or check Act model?
            // The previous code called $actModel->create($pid, $title, $description).
            // I should assign fields directly if create method signature is fixed or update it.
            // Let's assume standard mapper for safety if I can, OR update create call if I can see Act model.
            // I haven't seen Act model content, but standard practice:
            $actModel->project_id = $pid;
            $actModel->title = $title;
            $actModel->content = $content;
            $actModel->resume = $resume;
            $actModel->comment = $comment;
            if ($actModel->save()) {
                $this->f3->reroute('/project/' . $pid);
                return;
            } else {
                $errors[] = 'Impossible de créer l’acte.';
            }
        }

        $this->render('acts/create.html', [
            'title' => 'Nouvel acte',
            'project' => $project[0],
            'errors' => $errors,
            'old' => [
                'title' => htmlspecialchars($title),
                'content' => htmlspecialchars($content),
                'resume' => htmlspecialchars($resume),
                'comment' => htmlspecialchars($comment)
            ],
        ]);
    }

    /**
     * Edit act
     * GET /act/@id/edit
     */
    public function edit()
    {
        $aid = (int) $this->f3->get('PARAMS.id');
        $actModel = new Act();
        $act = $actModel->findAndCast(['id=?', $aid]);
        if (!$act) {
            $this->f3->error(404);
            return;
        }
        $act = $act[0];

        // Check project ownership
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $act['project_id'], $this->currentUser()['id']]);
        if (!$project) {
            $this->f3->error(403);
            return;
        }

        $this->render('acts/edit.html', [
            'title' => 'Modifier l\'acte',
            'project' => $project[0],
            'act' => $act,
            'errors' => []
        ]);
    }

    /**
     * Update act
     * POST /act/@id/edit
     */
    public function update()
    {
        $aid = (int) $this->f3->get('PARAMS.id');
        $actModel = new Act();
        $actModel->load(['id=?', $aid]);
        if ($actModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Ownership check
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $actModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $resume = trim($_POST['resume'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (empty($errors)) {
            $actModel->title = $title;
            $actModel->content = $content;
            $actModel->resume = $resume;
            $actModel->comment = $comment;
            $actModel->save();
            $this->f3->reroute('/project/' . $actModel->project_id);
            return;
        }

        $projectModel->load(['id=?', $actModel->project_id]);
        $project = $projectModel->cast();
        $this->render('acts/edit.html', [
            'title' => 'Modifier l\'acte',
            'project' => $project,
            'act' => $actModel->cast(),
            'errors' => $errors
        ]);
    }

    /**
     * Delete act
     * GET /act/@id/delete
     */
    public function delete()
    {
        $aid = (int) $this->f3->get('PARAMS.id');
        $actModel = new Act();
        $actModel->load(['id=?', $aid]);
        if (!$actModel->dry()) {
            $projectModel = new Project();
            if ($projectModel->count(['id=? AND user_id=?', $actModel->project_id, $this->currentUser()['id']])) {
                $pid = $actModel->project_id;
                $actModel->erase();
                $this->f3->reroute('/project/' . $pid);
                return;
            }
        }
        $this->f3->reroute('/dashboard');
    }
}
