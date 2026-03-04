<?php

class GlossaryController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getProject(int $pid): ?array
    {
        $pm = new Project();
        $rows = $pm->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);
        return $rows ? $rows[0] : null;
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $project = $this->getProject($pid);
        if (!$project) { $this->f3->error(404); return; }

        $model   = new GlossaryEntry();
        $entries = $model->getAllByProject($pid);

        // Group by category
        $grouped = [];
        foreach ($entries as $e) {
            $grouped[$e['category']][] = $e;
        }

        $this->render('glossary/index.html', [
            'title'      => 'Lexique — ' . $project['title'],
            'project'    => $project,
            'grouped'    => $grouped,
            'categories' => GlossaryEntry::$categories,
            'count'      => count($entries),
        ]);
    }

    // ── Create form ───────────────────────────────────────────────────────────

    public function create()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $project = $this->getProject($pid);
        if (!$project) { $this->f3->error(404); return; }

        $this->render('glossary/edit.html', [
            'title'      => 'Nouveau terme — ' . $project['title'],
            'project'    => $project,
            'entry'      => ['id' => null, 'term' => '', 'category' => 'terme', 'definition' => ''],
            'categories' => GlossaryEntry::$categories,
            'errors'     => [],
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $project = $this->getProject($pid);
        if (!$project) { $this->f3->error(404); return; }

        $term       = mb_substr(trim($_POST['term'] ?? ''), 0, 150);
        $category   = $_POST['category'] ?? 'terme';
        $definition = trim($_POST['definition'] ?? '');

        if (!array_key_exists($category, GlossaryEntry::$categories)) {
            $category = 'terme';
        }

        $errors = [];
        if ($term === '') $errors[] = 'Le terme est obligatoire.';

        if (empty($errors)) {
            $model             = new GlossaryEntry();
            $model->project_id = $pid;
            $model->term       = $term;
            $model->category   = $category;
            $model->definition = $definition;
            $model->save();
            $this->f3->reroute('/project/' . $pid . '/glossary');
        }

        $this->render('glossary/edit.html', [
            'title'      => 'Nouveau terme',
            'project'    => $project,
            'entry'      => ['id' => null, 'term' => $term, 'category' => $category, 'definition' => $definition],
            'categories' => GlossaryEntry::$categories,
            'errors'     => $errors,
        ]);
    }

    // ── Edit form ─────────────────────────────────────────────────────────────

    public function edit()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $eid     = (int) $this->f3->get('PARAMS.eid');
        $project = $this->getProject($pid);
        if (!$project) { $this->f3->error(404); return; }

        $model = new GlossaryEntry();
        $model->load(['id=? AND project_id=?', $eid, $pid]);
        if ($model->dry()) { $this->f3->error(404); return; }

        $this->render('glossary/edit.html', [
            'title'      => 'Modifier — ' . $model->term,
            'project'    => $project,
            'entry'      => $model->cast(),
            'categories' => GlossaryEntry::$categories,
            'errors'     => [],
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $eid     = (int) $this->f3->get('PARAMS.eid');
        $project = $this->getProject($pid);
        if (!$project) { $this->f3->error(404); return; }

        $model = new GlossaryEntry();
        $model->load(['id=? AND project_id=?', $eid, $pid]);
        if ($model->dry()) { $this->f3->error(404); return; }

        $term       = mb_substr(trim($_POST['term'] ?? ''), 0, 150);
        $category   = $_POST['category'] ?? 'terme';
        $definition = trim($_POST['definition'] ?? '');

        if (!array_key_exists($category, GlossaryEntry::$categories)) {
            $category = 'terme';
        }

        $errors = [];
        if ($term === '') $errors[] = 'Le terme est obligatoire.';

        if (empty($errors)) {
            $model->term       = $term;
            $model->category   = $category;
            $model->definition = $definition;
            $model->save();
            $this->f3->reroute('/project/' . $pid . '/glossary');
        }

        $this->render('glossary/edit.html', [
            'title'      => 'Modifier terme',
            'project'    => $project,
            'entry'      => ['id' => $eid, 'term' => $term, 'category' => $category, 'definition' => $definition],
            'categories' => GlossaryEntry::$categories,
            'errors'     => $errors,
        ]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete()
    {
        $pid   = (int) $this->f3->get('PARAMS.pid');
        $eid   = (int) $this->f3->get('PARAMS.eid');
        if (!$this->getProject($pid)) { $this->f3->error(404); return; }

        $model = new GlossaryEntry();
        $model->load(['id=? AND project_id=?', $eid, $pid]);
        if (!$model->dry()) $model->erase();

        $this->f3->reroute('/project/' . $pid . '/glossary');
    }

    // ── JSON endpoint for editor ──────────────────────────────────────────────

    public function terms()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) {
            http_response_code(403);
            echo json_encode([]);
            return;
        }
        $model = new GlossaryEntry();
        header('Content-Type: application/json');
        echo json_encode($model->getTermsJson($pid));
    }
}
