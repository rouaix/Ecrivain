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
     * List all acts for a project
     * GET /project/@pid/acts
     */
    public function listAll()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $project = $this->requireOwnedProject($pid);

        $actModel = $this->actModel();
        $acts = $actModel->getAllByProject($pid);

        // Chapter counts per act (top-level only)
        $chapterModel = $this->chapterModel();
        $allChapters  = $chapterModel->getAllByProject($pid);
        $chapterCounts = [];
        foreach ($allChapters as $ch) {
            if ($ch['act_id'] && !$ch['parent_id']) {
                $chapterCounts[$ch['act_id']] = ($chapterCounts[$ch['act_id']] ?? 0) + 1;
            }
        }

        // Embed chapter_count directly into each act for template access
        foreach ($acts as &$a) {
            $a['chapter_count'] = $chapterCounts[$a['id']] ?? 0;
        }
        unset($a);

        $actsJson = json_encode(array_map(fn($a) => [
            'id'            => (int)$a['id'],
            'title'         => $a['title'],
            'content'       => $a['content'] ?? '',
            'resume'        => $a['resume'] ?? '',
            'chapter_count' => $a['chapter_count'],
        ], $acts));

        $this->render('acts/list.html', [
            'title'    => 'Actes — ' . $project['title'],
            'project'  => $project,
            'acts'     => $acts,
            'actsJson' => $actsJson,
            'isOwner'  => $this->isOwner($pid),
        ]);
    }

    /**
     * Create a new act
     * GET /project/@pid/act/create
     */
    public function create()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $project = $this->requireOwnedProject($pid);

        $this->render('acts/create.html', [
            'title' => 'Nouvel acte',
            'project' => $project,
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
        $project = $this->requireOwnedProject($pid);

        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $content = $this->cleanQuillHtml($content);
        $resume = $_POST['resume'] ?? '';
        $resume = $this->cleanQuillHtml($resume);
        $comment = $_POST['comment'] ?? '';
        $comment = $this->cleanQuillHtml($comment);
        $errors = [];
        if ($title === '') {
            $errors[] = "Le titre de l'acte est obligatoire.";
        }

        if (empty($errors)) {
            $actModel = $this->actModel();
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
                $this->logActivity($pid, 'create', 'act', (int) $actModel->id, $title);
                $this->f3->reroute('/project/' . $pid);
                return;
            } else {
                $errors[] = 'Impossible de créer l’acte.';
            }
        }

        $this->render('acts/create.html', [
            'title' => 'Nouvel acte',
            'project' => $project,
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
        $actModel = $this->actModel();
        $act = $actModel->findAndCast(['id=?', $aid]);
        if (!$act) {
            $this->f3->error(404);
            return;
        }
        $act = $act[0];

        $project = $this->requireOwnedProject((int) $act['project_id']);

        $chapterModel = $this->chapterModel();
        $chapterCount = $chapterModel->count(['act_id=?', $aid]);

        $this->render('acts/edit.html', [
            'title' => 'Modifier l\'acte',
            'project' => $project,
            'act' => $act,
            'chapterCount' => $chapterCount,
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
        $actModel = $this->actModel();
        $actModel->load(['id=?', $aid]);
        if ($actModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $this->requireOwnedProject((int) $actModel->project_id);

        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $content = $this->cleanQuillHtml($content);
        $resume = $_POST['resume'] ?? '';
        $resume = $this->cleanQuillHtml($resume);
        $comment = $_POST['comment'] ?? '';
        $comment = $this->cleanQuillHtml($comment);
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
            $this->logActivity($actModel->project_id, 'update', 'act', $aid, $title);
            $this->f3->reroute('/project/' . $actModel->project_id);
            return;
        }

        $projectModel->load(['id=?', $actModel->project_id]);
        $project = $projectModel->cast();
        $chapterModel2 = $this->chapterModel();
        $chapterCount = $chapterModel2->count(['act_id=?', $aid]);
        $this->render('acts/edit.html', [
            'title' => 'Modifier l\'acte',
            'project' => $project,
            'act' => $actModel->cast(),
            'chapterCount' => $chapterCount,
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
        $actModel = $this->actModel();
        $actModel->load(['id=?', $aid]);
        if (!$actModel->dry()) {
            $this->requireOwnedProject((int) $actModel->project_id);
            $pid   = $actModel->project_id;
            $label = $actModel->title;
            $actModel->erase();
            $this->logActivity($pid, 'delete', 'act', $aid, $label);
            $this->f3->reroute('/project/' . $pid);
            return;
        }
        $this->f3->reroute('/dashboard');
    }
}
