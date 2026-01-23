<?php

class CharacterController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function index()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        $charModel = new Character();
        $characters = $charModel->getAllByProject($pid);

        $this->render('characters/index.html', [
            'title' => 'Personnages - ' . $project['title'],
            'project' => $project,
            'characters' => $characters
        ]);
    }

    public function create()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        $this->render('characters/edit.html', [
            'title' => 'Nouveau personnage',
            'project' => $project,
            'character' => ['name' => '', 'description' => '', 'comment' => '', 'id' => null],
            'errors' => []
        ]);
    }

    public function store()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if (!$name) {
            $this->render('characters/edit.html', [
                'title' => 'Nouveau personnage',
                'project' => $projectModel->findAndCast(['id=?', $pid])[0],
                'character' => ['name' => $name, 'description' => $description, 'comment' => $comment, 'id' => null],
                'errors' => ['Le nom est obligatoire']
            ]);
            return;
        }

        $charModel = new Character();
        $charModel->project_id = $pid;
        $charModel->name = $name;
        $charModel->description = $description;
        $charModel->comment = $comment;
        $charModel->save();

        $this->f3->reroute('/project/' . $pid . '/characters');
    }

    public function edit()
    {
        $id = (int) $this->f3->get('PARAMS.id');
        $charModel = new Character();
        $charModel->load(['id=?', $id]);
        if ($charModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $charModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $charModel->project_id])[0];

        $this->render('characters/edit.html', [
            'title' => 'Modifier personnage',
            'project' => $project,
            'character' => $charModel->cast(),
            'errors' => []
        ]);
    }

    public function update()
    {
        $id = (int) $this->f3->get('PARAMS.id');
        $charModel = new Character();
        $charModel->load(['id=?', $id]);
        if ($charModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $charModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if (!$name) {
            $this->render('characters/edit.html', [
                'title' => 'Modifier personnage',
                'project' => $projectModel->findAndCast(['id=?', $charModel->project_id])[0],
                'character' => ['id' => $id, 'name' => $name, 'description' => $description, 'comment' => $comment],
                'errors' => ['Le nom est obligatoire']
            ]);
            return;
        }

        $charModel->name = $name;
        $charModel->description = $description;
        $charModel->comment = $comment;
        $charModel->save();

        $this->f3->reroute('/project/' . $charModel->project_id . '/characters');
    }

    public function delete()
    {
        $id = (int) $this->f3->get('PARAMS.id');
        $charModel = new Character();
        $charModel->load(['id=?', $id]);
        if (!$charModel->dry()) {
            $projectModel = new Project();
            if ($projectModel->count(['id=? AND user_id=?', $charModel->project_id, $this->currentUser()['id']])) {
                $pid = $charModel->project_id;
                $charModel->erase();
                $this->f3->reroute('/project/' . $pid . '/characters');
                return;
            }
        }
        $this->f3->error(404);
    }
}
