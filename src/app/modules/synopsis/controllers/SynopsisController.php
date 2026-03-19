<?php

class SynopsisController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Vérifie que le template actif du projet contient un élément synopsis activé.
     */
    private function templateHasSynopsis(int $pid): bool
    {
        $rows = $this->db->exec(
            'SELECT COUNT(*) AS cnt
               FROM template_elements te
               JOIN projects p ON p.template_id = te.template_id
              WHERE p.id = ? AND te.element_type = ? AND te.is_enabled = 1',
            [$pid, 'synopsis']
        );
        return ($rows[0]['cnt'] ?? 0) > 0;
    }

    /**
     * GET /project/@pid/synopsis
     * Affiche le formulaire du synopsis (création auto si inexistant).
     */
    public function show()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');

        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(403);
            return;
        }

        if (!$this->templateHasSynopsis($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) {
            $this->f3->error(404);
            return;
        }
        $project = $project[0];

        $synopsisModel = new Synopsis();
        $synopsis = $synopsisModel->getByProject($pid);

        if (!$synopsis) {
            $synopsisModel->createForProject($pid);
            $synopsis = $synopsisModel->getByProject($pid);
        }

        $this->render('synopsis/edit.html', [
            'title'   => 'Synopsis — ' . htmlspecialchars($project['title']),
            'project' => $project,
            'synopsis' => $synopsis,
            'isOwner' => $this->isOwner($pid),
        ]);
    }

    /**
     * POST /project/@pid/synopsis/save
     * Enregistre tous les champs du synopsis.
     */
    public function save()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');

        if (!$this->isOwner($pid)) {
            $this->f3->error(403);
            return;
        }

        if (!$this->templateHasSynopsis($pid)) {
            $this->f3->error(403);
            return;
        }

        $synopsisModel = new Synopsis();
        $synopsis = $synopsisModel->getByProject($pid);
        if (!$synopsis) {
            $synopsisModel->createForProject($pid);
        }

        $textFields = ['logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
                       'development', 'midpoint', 'crisis', 'climax', 'resolution'];

        $fields = [
            'genre'            => trim($_POST['genre'] ?? ''),
            'subgenre'         => trim($_POST['subgenre'] ?? ''),
            'audience'         => trim($_POST['audience'] ?? ''),
            'tone'             => trim($_POST['tone'] ?? ''),
            'themes'           => trim($_POST['themes'] ?? ''),
            'comps'            => trim($_POST['comps'] ?? ''),
            'status'           => trim($_POST['status'] ?? 'en_cours'),
            'structure_method' => trim($_POST['structure_method'] ?? 'libre'),
        ];

        foreach ($textFields as $field) {
            $raw = $_POST[$field] ?? '';
            $fields[$field] = in_array($field, ['pitch', 'development', 'climax', 'resolution'])
                ? $this->cleanQuillHtml($raw)
                : trim(strip_tags($raw));
        }

        // Valider status et structure_method
        $validStatus = ['en_cours', 'premier_jet', 'revise', 'pret_soumission'];
        if (!in_array($fields['status'], $validStatus, true)) {
            $fields['status'] = 'en_cours';
        }
        $validMethods = ['libre', 'freytag', 'voyage_heros', 'save_the_cat', 'sept_points', 'snowflake', 'actanciel'];
        if (!in_array($fields['structure_method'], $validMethods, true)) {
            $fields['structure_method'] = 'libre';
        }

        $synopsisModel->updateFields($pid, $fields);
        $this->logActivity($pid, 'update', 'synopsis', $synopsis['id'] ?? null, 'Synopsis mis à jour');

        $this->f3->set('SESSION.success', 'Synopsis enregistré.');
        $this->f3->reroute('/project/' . $pid . '/synopsis');
    }

    /**
     * POST /project/@pid/synopsis/toggle-export
     * Bascule le flag is_exported du synopsis.
     */
    public function toggleExport()
    {
        header('Content-Type: application/json');

        $pid  = (int) $this->f3->get('PARAMS.pid');

        if (!$this->isOwner($pid)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        if (!$this->templateHasSynopsis($pid)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }
        $body = json_decode($this->f3->get('BODY'), true);
        $val  = isset($body['is_exported']) ? (int) $body['is_exported'] : null;

        if ($val === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètre manquant']);
            return;
        }

        $synopsisModel = new Synopsis();
        $synopsisModel->updateFields($pid, ['is_exported' => $val ? 1 : 0]);

        echo json_encode(['is_exported' => $val ? 1 : 0]);
    }

    /**
     * GET /project/@pid/synopsis/export
     * Vue d'export du synopsis avec boutons de téléchargement.
     */
    public function export()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');

        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(403);
            return;
        }

        if (!$this->templateHasSynopsis($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) {
            $this->f3->error(404);
            return;
        }

        $synopsisModel = new Synopsis();
        $synopsis = $synopsisModel->getByProject($pid);

        $this->render('synopsis/export.html', [
            'title'    => 'Synopsis — ' . htmlspecialchars($project[0]['title']),
            'project'  => $project[0],
            'synopsis' => $synopsis ?? [],
        ]);
    }

    /**
     * GET /project/@pid/synopsis/export/txt
     */
    public function exportTxt()
    {
        [$project, $synopsis] = $this->loadForExport();
        if (!$project) return;

        $lines = [];
        $lines[] = strtoupper($project['title']);
        $lines[] = 'SYNOPSIS';
        $lines[] = str_repeat('─', 60);

        $meta = [];
        if (!empty($synopsis['genre']))    $meta[] = 'Genre : ' . $synopsis['genre'] . (!empty($synopsis['subgenre']) ? ' — ' . $synopsis['subgenre'] : '');
        if (!empty($synopsis['audience'])) $meta[] = 'Public : ' . $synopsis['audience'];
        if (!empty($synopsis['tone']))     $meta[] = 'Ton : ' . $synopsis['tone'];
        if (!empty($synopsis['themes']))   $meta[] = 'Thèmes : ' . $synopsis['themes'];
        if (!empty($synopsis['comps']))    $meta[] = 'Comparables : ' . $synopsis['comps'];
        if ($meta) { $lines[] = implode("\n", $meta); $lines[] = ''; }

        if (!empty($synopsis['logline'])) { $lines[] = $synopsis['logline']; $lines[] = ''; }
        if (!empty($synopsis['pitch']))   { $lines[] = strip_tags($synopsis['pitch']); $lines[] = ''; }

        $beats = [
            'situation'   => 'SITUATION INITIALE',
            'trigger_evt' => 'ÉLÉMENT DÉCLENCHEUR',
            'plot_point1' => 'PREMIER TOURNANT',
            'development' => 'DÉVELOPPEMENT',
            'midpoint'    => 'POINT MÉDIAN',
            'crisis'      => 'CRISE',
            'climax'      => 'CLIMAX',
            'resolution'  => 'RÉSOLUTION',
        ];
        foreach ($beats as $key => $label) {
            if (!empty($synopsis[$key])) {
                $lines[] = $label;
                $lines[] = strip_tags($synopsis[$key]);
                $lines[] = '';
            }
        }

        $filename = 'synopsis-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($project['title'])) . '.txt';
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo implode("\n", $lines);
    }

    /**
     * GET /project/@pid/synopsis/export/markdown
     */
    public function exportMarkdown()
    {
        [$project, $synopsis] = $this->loadForExport();
        if (!$project) return;

        $md = '# ' . $project['title'] . "\n\n## Synopsis\n\n";

        $meta = [];
        if (!empty($synopsis['genre']))    $meta[] = '**Genre :** ' . $synopsis['genre'] . (!empty($synopsis['subgenre']) ? ' — ' . $synopsis['subgenre'] : '');
        if (!empty($synopsis['audience'])) $meta[] = '**Public :** ' . $synopsis['audience'];
        if (!empty($synopsis['tone']))     $meta[] = '**Ton :** ' . $synopsis['tone'];
        if (!empty($synopsis['themes']))   $meta[] = '**Thèmes :** ' . $synopsis['themes'];
        if (!empty($synopsis['comps']))    $meta[] = '**Comparables :** ' . $synopsis['comps'];
        if ($meta) $md .= implode('  ' . "\n", $meta) . "\n\n---\n\n";

        if (!empty($synopsis['logline'])) $md .= '_' . $synopsis['logline'] . "_\n\n";
        if (!empty($synopsis['pitch']))   $md .= strip_tags($synopsis['pitch']) . "\n\n";

        $beats = [
            'situation'   => 'Situation initiale',
            'trigger_evt' => 'Élément déclencheur',
            'plot_point1' => 'Premier tournant',
            'development' => 'Développement',
            'midpoint'    => 'Point médian',
            'crisis'      => 'Crise',
            'climax'      => 'Climax',
            'resolution'  => 'Résolution',
        ];
        foreach ($beats as $key => $label) {
            if (!empty($synopsis[$key])) {
                $md .= "### $label\n\n" . strip_tags($synopsis[$key]) . "\n\n";
            }
        }

        $filename = 'synopsis-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($project['title'])) . '.md';
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $md;
    }

    /**
     * GET /project/@pid/synopsis/export/html
     */
    public function exportHtml()
    {
        [$project, $synopsis] = $this->loadForExport();
        if (!$project) return;

        $esc = fn($s) => htmlspecialchars($s ?? '', ENT_QUOTES);
        $title = $esc($project['title']);

        $html  = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">';
        $html .= '<title>Synopsis — ' . $title . '</title>';
        $html .= '<style>body{font-family:Georgia,serif;max-width:800px;margin:2rem auto;padding:2rem;line-height:1.7;color:#222}';
        $html .= 'h1{text-align:center}h2{border-bottom:1px solid #ccc;padding-bottom:.3rem}';
        $html .= '.logline{font-style:italic;border-left:3px solid #3f51b5;padding-left:1rem;margin:1.5rem 0}';
        $html .= '.meta{color:#555;font-size:.9rem;margin-bottom:1.5rem}.beat-title{font-weight:700;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;color:#555;margin-bottom:.25rem}</style>';
        $html .= '</head><body>';
        $html .= '<h1>' . $title . '</h1><h2>Synopsis</h2>';

        $metaParts = [];
        if (!empty($synopsis['genre']))    $metaParts[] = '<strong>Genre :</strong> ' . $esc($synopsis['genre']) . (!empty($synopsis['subgenre']) ? ' — ' . $esc($synopsis['subgenre']) : '');
        if (!empty($synopsis['audience'])) $metaParts[] = '<strong>Public :</strong> ' . $esc($synopsis['audience']);
        if (!empty($synopsis['tone']))     $metaParts[] = '<strong>Ton :</strong> ' . $esc($synopsis['tone']);
        if (!empty($synopsis['themes']))   $metaParts[] = '<strong>Thèmes :</strong> ' . $esc($synopsis['themes']);
        if (!empty($synopsis['comps']))    $metaParts[] = '<strong>Comparables :</strong> ' . $esc($synopsis['comps']);
        if ($metaParts) $html .= '<p class="meta">' . implode(' &nbsp;·&nbsp; ', $metaParts) . '</p>';

        if (!empty($synopsis['logline'])) $html .= '<p class="logline">' . $esc($synopsis['logline']) . '</p>';
        if (!empty($synopsis['pitch']))   $html .= $synopsis['pitch'];

        $beats = [
            'situation'   => 'Situation initiale',
            'trigger_evt' => 'Élément déclencheur',
            'plot_point1' => 'Premier tournant',
            'development' => 'Développement',
            'midpoint'    => 'Point médian',
            'crisis'      => 'Crise',
            'climax'      => 'Climax',
            'resolution'  => 'Résolution',
        ];
        $hasBeats = array_filter($beats, fn($k) => !empty($synopsis[$k]), ARRAY_FILTER_USE_KEY);
        if ($hasBeats) {
            $html .= '<h2>Structure narrative</h2>';
            foreach ($beats as $key => $label) {
                if (!empty($synopsis[$key])) {
                    $html .= '<p class="beat-title">' . $esc($label) . '</p>';
                    $html .= strpos($synopsis[$key], '<') !== false ? $synopsis[$key] : '<p>' . $esc($synopsis[$key]) . '</p>';
                }
            }
        }

        $html .= '</body></html>';

        $filename = 'synopsis-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($project['title'])) . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $html;
    }

    /**
     * Charge projet + synopsis et vérifie les accès. Retourne [project, synopsis] ou [null, null].
     */
    private function loadForExport(): array
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(403);
            return [null, null];
        }
        if (!$this->templateHasSynopsis($pid)) {
            $this->f3->error(403);
            return [null, null];
        }
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) {
            $this->f3->error(404);
            return [null, null];
        }
        $synopsisModel = new Synopsis();
        $synopsis = $synopsisModel->getByProject($pid) ?? [];
        return [$project[0], $synopsis];
    }
}
