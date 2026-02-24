<?php

class ElementController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
        // Elements table may not exist yet (migration pending)
        if (!$f3->get('DB')->exists('elements')) {
            $f3->error(503, 'Module elements not yet available.');
            return;
        }
    }

    public function create()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $templateElementId = (int) ($_GET['type'] ?? 0);

        if (!$templateElementId) {
            $this->f3->error(400);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        // Load template element config
        $templateElementModel = new TemplateElement();
        $templateElementModel->load(['id=?', $templateElementId]);
        if ($templateElementModel->dry()) {
            $this->f3->error(404);
            return;
        }
        $templateElement = $templateElementModel->cast();
        $config = json_decode($templateElement['config_json'] ?? '{}', true);

        $elementModel = new Element();
        $topElements = $elementModel->getByTemplateElement($pid, $templateElementId);

        // Filter to only top-level for parent dropdown
        $topLevelElements = array_filter($topElements, function($e) {
            return empty($e['parent_id']);
        });

        $parentId = !empty($_GET['parent_id']) ? (int) $_GET['parent_id'] : null;

        $this->render('element/create.html', [
            'title' => 'Nouvel élément : ' . ($config['label_singular'] ?? 'Élément'),
            'project' => $project,
            'templateElement' => $templateElement,
            'config' => $config,
            'topElements' => $topLevelElements,
            'old' => ['parent_id' => $parentId, 'title' => ''],
            'errors' => []
        ]);
    }

    public function store()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $templateElementId = (int) ($_POST['template_element_id'] ?? 0);

        if (!$templateElementId) {
            $this->f3->error(400);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (empty($errors)) {
            $elementModel = new Element();
            $eid = $elementModel->create($pid, $templateElementId, $title, $parentId);
            if ($eid) {
                $this->f3->set('SESSION.success', 'Élément créé avec succès.');
                $this->f3->reroute('/element/' . $eid);
            } else {
                $errors[] = 'Impossible de créer l\'élément.';
            }
        }

        // Reload data for view
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        $templateElementModel = new TemplateElement();
        $templateElementModel->load(['id=?', $templateElementId]);
        $templateElement = $templateElementModel->cast();
        $config = json_decode($templateElement['config_json'] ?? '{}', true);

        $elementModel = new Element();
        $topElements = $elementModel->getByTemplateElement($pid, $templateElementId);
        $topLevelElements = array_filter($topElements, function($e) {
            return empty($e['parent_id']);
        });

        $this->render('element/create.html', [
            'title' => 'Nouvel élément : ' . ($config['label_singular'] ?? 'Élément'),
            'project' => $project,
            'templateElement' => $templateElement,
            'config' => $config,
            'topElements' => $topLevelElements,
            'errors' => $errors,
            'old' => ['parent_id' => $parentId, 'title' => $title],
        ]);
    }

    public function show()
    {
        $eid = (int) $this->f3->get('PARAMS.id');
        $elementModel = new Element();
        $elementModel->load(['id=?', $eid]);
        if ($elementModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $user = $this->currentUser();
        // Ownership check & Get Project
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $elementModel->project_id, $user['id']]);
        if (!$project) {
            $this->f3->error(403);
            return;
        }
        $project = $project[0];

        // Load template element config
        $templateElementModel = new TemplateElement();
        $templateElementModel->load(['id=?', $elementModel->template_element_id]);
        $templateElement = $templateElementModel->cast();
        $config = json_decode($templateElement['config_json'] ?? '{}', true);

        // Context: Parent Element (for sub-elements)
        $parentElement = null;
        if ($elementModel->parent_id) {
            $parent = new Element();
            $parent->load(['id=?', $elementModel->parent_id]);
            if (!$parent->dry()) {
                $parentElement = $parent->cast();
            }
        }

        // Top level elements for "Parent" dropdown
        $topElements = $elementModel->getByTemplateElement($project['id'], $elementModel->template_element_id);
        $topLevelElements = array_filter($topElements, function($e) {
            return empty($e['parent_id']);
        });

        // Load all element types of the same kind for type-switching dropdown
        $elementTypes = $templateElementModel->findAndCast(
            ['template_id=? AND element_type=? AND is_enabled=?', $templateElement['template_id'], 'element', 1],
            ['order' => 'display_order ASC']
        );
        foreach ($elementTypes as &$et) {
            $etConfig = json_decode($et['config_json'] ?? '{}', true);
            $et['label'] = $etConfig['label_singular'] ?? ucfirst($et['element_type']);
        }
        unset($et);

        // Check for session success msg
        $success = $this->f3->get('SESSION.success');
        $this->f3->clear('SESSION.success');

        $elementData = $elementModel->cast();

        $this->render('element/edit.html', [
            'title' => $elementModel->title,
            'element' => $elementData,
            'project' => $project,
            'templateElement' => $templateElement,
            'config' => $config,
            'parentElement' => $parentElement,
            'topElements' => $topLevelElements,
            'elementTypes' => $elementTypes,
            'errors' => [],
            'success' => $success
        ]);
    }

    public function update()
    {
        $eid = (int) $this->f3->get('PARAMS.id');

        $elementModel = new Element();
        $elementModel->load(['id=?', $eid]);

        if ($elementModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $elementModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $content = $this->cleanQuillHtml($content);
        $resume = $_POST['resume'] ?? '';
        $resume = $this->cleanQuillHtml($resume);
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $newTemplateElementId = !empty($_POST['template_element_id']) ? (int) $_POST['template_element_id'] : null;

        $elementModel->title = $title;
        $elementModel->content = $content;
        $elementModel->resume = $resume;

        // Handle type change: update element and all its sub-elements
        if ($newTemplateElementId && $newTemplateElementId !== (int) $elementModel->template_element_id) {
            $elementModel->changeType($eid, $newTemplateElementId, $elementModel->project_id);
            $elementModel->load(['id=?', $eid]);
            // Re-apply fields set before the reload
            $elementModel->title = $title;
            $elementModel->content = $content;
            $elementModel->resume = $resume;
        }

        // Check if parent changed
        if ($elementModel->parent_id != $parentId) {
            $oldParentVal = (int) ($elementModel->parent_id ?: 0);
            $newParentVal = (int) ($parentId ?: 0);

            // If moving to a new parent, recalculate order
            if ($newParentVal > $oldParentVal) {
                $elementModel->shiftOrderDown($elementModel->project_id, $elementModel->template_element_id, $parentId);
                $elementModel->order_index = 1;
            } else {
                $elementModel->order_index = $elementModel->getNextOrder(
                    $elementModel->project_id,
                    $elementModel->template_element_id,
                    $parentId
                );
            }
        }

        $elementModel->parent_id = $parentId;
        $elementModel->save();

        if ($this->f3->get('AJAX')) {
            echo json_encode(['status' => 'ok']);
            exit;
        }

        $this->f3->set('SESSION.success', 'Élément enregistré.');
        $this->f3->reroute('/element/' . $eid);
    }

    public function delete()
    {
        $eid = (int) $this->f3->get('PARAMS.id');
        $elementModel = new Element();
        $elementModel->load(['id=?', $eid]);

        if ($elementModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $elementModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $pid = $elementModel->project_id;
        $elementModel->erase();
        $this->f3->reroute('/project/' . $pid);
    }
}
