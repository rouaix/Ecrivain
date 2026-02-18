<?php

class TemplateController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * User id=1 has full admin rights over all templates.
     */
    private function isAdmin(array $user): bool
    {
        return (int)$user['id'] === 1;
    }

    /**
     * List all templates available to the user.
     */
    public function index()
    {
        $user = $this->currentUser();
        $templateModel = new ProjectTemplate();
        $templates = $templateModel->getAllAvailable($user['id']);

        // Add usage count for each template
        foreach ($templates as &$template) {
            $count = $this->db->exec('SELECT COUNT(*) as cnt FROM projects WHERE template_id = ?', [$template['id']]);
            $template['usage_count'] = $count[0]['cnt'] ?? 0;
            $template['can_edit'] = $this->isAdmin($user) || (!$template['is_system'] && $template['created_by'] == $user['id']);
            $template['can_delete'] = $template['can_edit'] && $template['usage_count'] == 0;
        }

        $this->render('template/index.html', [
            'title' => 'Templates de projet',
            'templates' => $templates
        ]);
    }

    /**
     * Create a new template (by duplicating default or another template).
     */
    public function create()
    {
        $user = $this->currentUser();
        $templateModel = new ProjectTemplate();
        $availableTemplates = $templateModel->getAllAvailable($user['id']);

        $this->render('template/create.html', [
            'title' => 'Créer un template',
            'availableTemplates' => $availableTemplates,
            'errors' => [],
            'old' => ['name' => '', 'description' => '', 'source_template_id' => '']
        ]);
    }

    /**
     * Store a new template.
     */
    public function store()
    {
        $user = $this->currentUser();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sourceTemplateId = (int) ($_POST['source_template_id'] ?? 0);

        $errors = [];
        if ($name === '') {
            $errors[] = 'Le nom du template est obligatoire.';
        }

        if (!$sourceTemplateId) {
            $errors[] = 'Vous devez sélectionner un template source à dupliquer.';
        }

        if (empty($errors)) {
            $templateModel = new ProjectTemplate();
            $newTemplateId = $templateModel->duplicate($sourceTemplateId, $name, $user['id']);

            if ($newTemplateId) {
                // Update description if provided
                $newTemplate = new ProjectTemplate();
                $newTemplate->load(['id=?', $newTemplateId]);
                $newTemplate->description = $description;
                $newTemplate->save();

                $this->f3->set('SESSION.success', 'Template créé avec succès.');
                $this->f3->reroute('/template/' . $newTemplateId . '/edit');
            } else {
                $errors[] = 'Impossible de créer le template.';
            }
        }

        // Reload data for view
        $templateModel = new ProjectTemplate();
        $availableTemplates = $templateModel->getAllAvailable($user['id']);

        $this->render('template/create.html', [
            'title' => 'Créer un template',
            'availableTemplates' => $availableTemplates,
            'errors' => $errors,
            'old' => ['name' => $name, 'description' => $description, 'source_template_id' => $sourceTemplateId]
        ]);
    }

    /**
     * Edit a template (name, description, element order, enable/disable).
     */
    public function edit()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $template = $templateModel->cast();

        // Check permissions
        if (!$this->isAdmin($user) && ($template['is_system'] || ($template['created_by'] != $user['id']))) {
            $this->f3->error(403, 'Vous ne pouvez pas modifier ce template.');
            return;
        }

        // Load template elements and enrich with display data
        $elements = $templateModel->getElements($templateId);
        $typeLabels = [
            'section' => 'Section',
            'act' => 'Acte',
            'chapter' => 'Chapitre',
            'note' => 'Note',
            'character' => 'Personnage',
            'file' => 'Fichier',
            'element' => 'Élément personnalisé'
        ];
        foreach ($elements as &$elem) {
            $cfg = json_decode($elem['config_json'] ?? '{}', true);
            $elem['type_label'] = $typeLabels[$elem['element_type']] ?? $elem['element_type'];
            $elem['main_label'] = $cfg['label'] ?? $cfg['label_singular'] ?? '-';
            $elem['plural_label'] = $cfg['label_plural'] ?? '';
            $elem['display_label'] = $elem['main_label'];
            if ($elem['plural_label']) {
                $elem['display_label'] .= ' / ' . $elem['plural_label'];
            }
        }
        unset($elem);

        // Check for session success msg
        $success = $this->f3->get('SESSION.success');
        $this->f3->clear('SESSION.success');

        $this->render('template/edit.html', [
            'title' => 'Éditer le template : ' . $template['name'],
            'template' => $template,
            'elements' => $elements,
            'errors' => [],
            'success' => $success
        ]);
    }

    /**
     * Update a template.
     */
    public function update()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Check permissions
        if (!$this->isAdmin($user) && ($templateModel->is_system || ($templateModel->created_by != $user['id']))) {
            $this->f3->error(403);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors[] = 'Le nom du template est obligatoire.';
        }

        if (empty($errors)) {
            $templateModel->name = $name;
            $templateModel->description = $description;
            $templateModel->save();

            // Update template elements (enable/disable)
            // First disable all, then enable checked ones (unchecked checkboxes are not sent)
            if (isset($_POST['element_enabled'])) {
                $this->db->exec(
                    'UPDATE template_elements SET is_enabled = 0 WHERE template_id = ?',
                    [$templateId]
                );
                foreach ($_POST['element_enabled'] as $elemId => $enabled) {
                    $this->db->exec(
                        'UPDATE template_elements SET is_enabled = 1 WHERE id = ? AND template_id = ?',
                        [(int) $elemId, $templateId]
                    );
                }
            }

            $this->f3->set('SESSION.success', 'Template mis à jour avec succès.');
            $this->f3->reroute('/template/' . $templateId . '/edit');
        }

        // Reload data for view
        $template = $templateModel->cast();
        $elements = $templateModel->getElements($templateId);

        $this->render('template/edit.html', [
            'title' => 'Éditer le template : ' . $template['name'],
            'template' => $template,
            'elements' => $elements,
            'errors' => $errors,
            'success' => null
        ]);
    }

    /**
     * Delete a template.
     */
    public function delete()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Check permissions
        if (!$this->isAdmin($user) && ($templateModel->is_system || ($templateModel->created_by != $user['id']))) {
            $this->f3->error(403, 'Vous ne pouvez pas supprimer ce template.');
            return;
        }

        // Check if in use
        if ($templateModel->isInUse($templateId)) {
            $this->f3->set('SESSION.error', 'Impossible de supprimer ce template car il est utilisé par des projets.');
            $this->f3->reroute('/templates');
            return;
        }

        $templateModel->erase();
        $this->f3->set('SESSION.success', 'Template supprimé avec succès.');
        $this->f3->reroute('/templates');
    }

    /**
     * Add an element to a template.
     */
    public function addElement()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry() || (!$this->isAdmin($user) && ($templateModel->is_system || $templateModel->created_by != $user['id']))) {
            $this->f3->error(403);
            return;
        }

        $elementType = trim($_POST['element_type'] ?? '');
        $elementSubtype = trim($_POST['element_subtype'] ?? '') ?: null;
        $sectionPlacement = trim($_POST['section_placement'] ?? '') ?: null;
        $label = trim($_POST['label'] ?? '');
        $labelPlural = trim($_POST['label_plural'] ?? '');

        if ($elementType === '' || $label === '') {
            $this->f3->set('SESSION.error', 'Le type et le label sont obligatoires.');
            $this->f3->reroute('/template/' . $templateId . '/edit');
            return;
        }

        // Build config_json
        if (in_array($elementType, ['section'])) {
            $configJson = json_encode(['label' => $label], JSON_UNESCAPED_UNICODE);
        } else {
            $configJson = json_encode([
                'label_singular' => $label,
                'label_plural' => $labelPlural ?: $label . 's'
            ], JSON_UNESCAPED_UNICODE);
        }

        // Get next display_order
        $maxOrder = $this->db->exec(
            'SELECT MAX(display_order) as max_order FROM template_elements WHERE template_id = ?',
            [$templateId]
        );
        $nextOrder = ($maxOrder[0]['max_order'] ?? -1) + 1;

        $this->db->exec(
            'INSERT INTO template_elements (template_id, element_type, element_subtype, section_placement, display_order, is_enabled, config_json)
             VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$templateId, $elementType, $elementSubtype, $sectionPlacement, $nextOrder, $configJson]
        );

        $this->f3->set('SESSION.success', 'Élément ajouté.');
        $this->f3->reroute('/template/' . $templateId . '/edit');
    }

    /**
     * Delete an element from a template.
     */
    public function deleteElement()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $elemId = (int) $this->f3->get('PARAMS.eid');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry() || (!$this->isAdmin($user) && ($templateModel->is_system || $templateModel->created_by != $user['id']))) {
            $this->f3->error(403);
            return;
        }

        $this->db->exec(
            'DELETE FROM template_elements WHERE id = ? AND template_id = ?',
            [$elemId, $templateId]
        );

        $this->f3->set('SESSION.success', 'Élément supprimé.');
        $this->f3->reroute('/template/' . $templateId . '/edit');
    }

    /**
     * Update a single element's label/config.
     */
    public function updateElement()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $elemId = (int) $this->f3->get('PARAMS.eid');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry() || (!$this->isAdmin($user) && ($templateModel->is_system || $templateModel->created_by != $user['id']))) {
            $this->f3->error(403);
            return;
        }

        $label = trim($_POST['label'] ?? '');
        $labelPlural = trim($_POST['label_plural'] ?? '');
        $elementType = trim($_POST['element_type'] ?? '');
        $elementSubtype = trim($_POST['element_subtype'] ?? '') ?: null;
        $sectionPlacement = trim($_POST['section_placement'] ?? '') ?: null;

        if ($label === '') {
            $this->f3->set('SESSION.error', 'Le label est obligatoire.');
            $this->f3->reroute('/template/' . $templateId . '/edit');
            return;
        }

        if ($elementType === 'section') {
            $configJson = json_encode(['label' => $label], JSON_UNESCAPED_UNICODE);
        } else {
            $configJson = json_encode([
                'label_singular' => $label,
                'label_plural' => $labelPlural ?: $label . 's'
            ], JSON_UNESCAPED_UNICODE);
        }

        $this->db->exec(
            'UPDATE template_elements SET element_type = ?, element_subtype = ?, section_placement = ?, config_json = ? WHERE id = ? AND template_id = ?',
            [$elementType, $elementSubtype, $sectionPlacement, $configJson, $elemId, $templateId]
        );

        $this->f3->set('SESSION.success', 'Élément mis à jour.');
        $this->f3->reroute('/template/' . $templateId . '/edit');
    }

    /**
     * Duplicate a user template as a system template.
     */
    public function promoteToSystem()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Only the owner of a non-system template can promote it (admin bypasses)
        if (!$this->isAdmin($user) && ($templateModel->is_system || $templateModel->created_by != $user['id'])) {
            $this->f3->error(403, 'Vous ne pouvez pas promouvoir ce template.');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $name = $templateModel->name . ' (Système)';
        }
        $description = trim($_POST['description'] ?? $templateModel->description ?? '');

        $newId = $templateModel->duplicate($templateId, $name, $user['id'], true);

        if ($newId) {
            $newTemplate = new ProjectTemplate();
            $newTemplate->load(['id=?', $newId]);
            $newTemplate->description = $description;
            $newTemplate->save();

            $this->f3->set('SESSION.success', 'Template copié en template système : "' . htmlspecialchars($name) . '".');
        } else {
            $this->f3->set('SESSION.error', 'Erreur lors de la copie en template système.');
        }

        $this->f3->reroute('/templates');
    }

    /**
     * Export a template as a JSON file.
     */
    public function exportJson()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Any user can export a template visible to them
        $available = array_column($templateModel->getAllAvailable($user['id']), 'id');
        if (!in_array($templateId, $available)) {
            $this->f3->error(403);
            return;
        }

        $template = $templateModel->cast();
        $elements = $templateModel->getElements($templateId);

        $export = [
            'name'        => $template['name'],
            'description' => $template['description'] ?? '',
            'elements'    => array_map(function ($elem) {
                return [
                    'element_type'      => $elem['element_type'],
                    'element_subtype'   => $elem['element_subtype'],
                    'section_placement' => $elem['section_placement'],
                    'display_order'     => (int) $elem['display_order'],
                    'is_enabled'        => (int) $elem['is_enabled'],
                    'config_json'       => json_decode($elem['config_json'] ?? '{}', true),
                ];
            }, $elements),
        ];

        $json     = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'template_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($template['name'])) . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    /**
     * Show import form (GET) or process an imported JSON template (POST).
     */
    public function importJson()
    {
        $user = $this->currentUser();

        if ($this->f3->get('VERB') === 'GET') {
            $this->render('template/import.html', [
                'title'  => 'Importer un template',
                'errors' => [],
                'old'    => ['name' => ''],
            ]);
            return;
        }

        // POST: parse uploaded file
        $errors = [];

        if (empty($_FILES['template_file']) || (int) $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Veuillez sélectionner un fichier JSON valide.';
        }

        $data = null;
        if (empty($errors)) {
            $content = file_get_contents($_FILES['template_file']['tmp_name']);
            $data    = json_decode($content, true);
            if (!$data || !isset($data['elements']) || !is_array($data['elements'])) {
                $errors[] = 'Fichier JSON invalide ou format non reconnu.';
            }
        }

        if (!empty($errors)) {
            $this->render('template/import.html', [
                'title'  => 'Importer un template',
                'errors' => $errors,
                'old'    => ['name' => trim($_POST['name'] ?? '')],
            ]);
            return;
        }

        $name = trim($_POST['name'] ?? '') ?: trim($data['name'] ?? 'Template importé');
        if ($name === '') {
            $name = 'Template importé';
        }

        // Create the new user template
        $newTemplate              = new ProjectTemplate();
        $newTemplate->name        = $name;
        $newTemplate->description = $data['description'] ?? '';
        $newTemplate->is_default  = 0;
        $newTemplate->is_system   = 0;
        $newTemplate->created_by  = $user['id'];
        $newTemplate->save();
        $newId = $newTemplate->id;

        $validTypes = ['section', 'act', 'chapter', 'note', 'character', 'file', 'element'];
        foreach ($data['elements'] as $idx => $elem) {
            $elementType = $elem['element_type'] ?? '';
            if (!in_array($elementType, $validTypes)) {
                continue;
            }
            $configJson = isset($elem['config_json'])
                ? json_encode($elem['config_json'], JSON_UNESCAPED_UNICODE)
                : '{}';

            $this->db->exec(
                'INSERT INTO template_elements (template_id, element_type, element_subtype, section_placement, display_order, is_enabled, config_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $newId,
                    $elementType,
                    $elem['element_subtype'] ?? null,
                    $elem['section_placement'] ?? null,
                    (int) ($elem['display_order'] ?? $idx),
                    (int) ($elem['is_enabled'] ?? 1),
                    $configJson,
                ]
            );
        }

        $this->f3->set('SESSION.success', 'Template "' . htmlspecialchars($name) . '" importé avec succès.');
        $this->f3->reroute('/template/' . $newId . '/edit');
    }

    /**
     * Reorder template elements via AJAX.
     */
    public function reorder()
    {
        $templateId = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $templateModel = new ProjectTemplate();
        $templateModel->load(['id=?', $templateId]);

        if ($templateModel->dry() || (!$this->isAdmin($user) && ($templateModel->is_system || $templateModel->created_by != $user['id']))) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $orderedIds = $body['order'] ?? [];

        if (empty($orderedIds)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No order data']);
            return;
        }

        $templateElementModel = new TemplateElement();
        $result = $templateElementModel->reorder($templateId, $orderedIds);

        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    }
}
