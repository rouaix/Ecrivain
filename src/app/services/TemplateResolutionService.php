<?php

/**
 * TemplateResolutionService — Gère la résolution des modules de sidebar basés sur le template du projet.
 */
class TemplateResolutionService
{
    private \DB\SQL $db;

    public function __construct(\DB\SQL $db)
    {
        $this->db = $db;
    }

    /**
     * Charge et résout les modules de sidebar pour un projet.
     *
     * @param array $project Projet courant
     * @return array Liste des modules de sidebar
     */
    public function loadSidebarModules(array $project): array
    {
        $pid = (int) $project['id'];
        $hasNoteModule = false;

        // Compter les éléments par type
        $cnt = $this->countProjectElements($pid);

        // Compter les sections par sous-type
        $sectionCounts = $this->countSectionsByType($pid);

        // Modules par défaut
        $defaultModules = [
            ['label' => 'Structure',   'path' => '/project/'.$pid.'#chapters',    'icon' => 'list-ol',    'nb' => $cnt['chapter']   ?? 0],
            ['label' => 'Personnages', 'path' => '/project/'.$pid.'/characters',  'icon' => 'users',      'nb' => $cnt['character'] ?? 0],
            ['label' => 'Notes',       'path' => '/project/'.$pid.'/notes',       'icon' => 'sticky-note','nb' => $cnt['note']      ?? 0],
            ['label' => 'Glossaire',   'path' => '/project/'.$pid.'/glossary',    'icon' => 'book-open',  'nb' => $cnt['glossary']  ?? 0],
            ['label' => 'Fichiers',    'path' => '/project/'.$pid.'/files',       'icon' => 'paperclip',  'nb' => $cnt['file']      ?? 0],
        ];

        // Résoudre le template
        $templateId = $this->resolveTemplateId($project);

        if (!$templateId) {
            return $this->addActivePaths($defaultModules);
        }

        // Charger les éléments de template
        $teRows = $this->loadTemplateElements($templateId);

        if (empty($teRows)) {
            return $this->addActivePaths($defaultModules);
        }

        // Construire les modules basés sur les éléments de template
        $modules = $this->buildModulesFromTemplateElements($teRows, $pid, $cnt, $sectionCounts, $hasNoteModule);

        // Fallback: si les notes existent mais que le template n'a pas de module 'note', toujours les afficher
        if (!$hasNoteModule && ($cnt['note'] ?? 0) > 0) {
            $modules[] = [
                'label' => 'Notes',
                'path'  => '/project/'.$pid.'/notes',
                'icon'  => 'sticky-note',
                'nb'    => (int)($cnt['note'] ?? 0),
            ];
        }

        return $this->addActivePaths($modules ?: $defaultModules);
    }

    /**
     * Compte les éléments par type pour un projet.
     */
    private function countProjectElements(int $projectId): array
    {
        $cnt = [];
        $countQueries = [
            'chapter'   => 'SELECT COUNT(*) AS n FROM chapters WHERE project_id = ? AND parent_id IS NULL',
            'act'       => 'SELECT COUNT(*) AS n FROM acts             WHERE project_id = ?',
            'character' => 'SELECT COUNT(*) AS n FROM characters       WHERE project_id = ?',
            'note'      => 'SELECT COUNT(*) AS n FROM notes            WHERE project_id = ?',
            'glossary'  => 'SELECT COUNT(*) AS n FROM glossary_entries WHERE project_id = ?',
            'file'      => 'SELECT COUNT(*) AS n FROM project_files    WHERE project_id = ?',
            'scenario'  => 'SELECT COUNT(*) AS n FROM scenarios        WHERE project_id = ?',
        ];

        foreach ($countQueries as $key => $sql) {
            try {
                $r = $this->db->exec($sql, [$projectId]);
                $cnt[$key] = (int)($r[0]['n'] ?? 0);
            } catch (\Exception $e) {
                $cnt[$key] = 0;
            }
        }

        return $cnt;
    }

    /**
     * Compte les sections par sous-type pour un projet.
     */
    private function countSectionsByType(int $projectId): array
    {
        try {
            $sRows = $this->db->exec(
                'SELECT type, COUNT(*) AS cnt FROM sections WHERE project_id = ? GROUP BY type',
                [$projectId]
            );
            $sectionCounts = [];
            foreach ($sRows as $sr) {
                $sectionCounts[$sr['type']] = (int) $sr['cnt'];
            }
            return $sectionCounts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Résout l'ID du template pour un projet.
     */
    private function resolveTemplateId(array $project): ?int
    {
        $templateId = isset($project['template_id']) && $project['template_id']
            ? (int) $project['template_id']
            : null;

        if (!$templateId) {
            try {
                // Même ordre de fallback que ProjectController: is_default first, puis first available
                $tRows = $this->db->exec('SELECT id FROM templates WHERE is_default = 1 LIMIT 1');
                if (!$tRows) {
                    $tRows = $this->db->exec('SELECT id FROM templates ORDER BY id ASC LIMIT 1');
                }
                $templateId = $tRows ? (int) $tRows[0]['id'] : null;
            } catch (\Exception $e) {
                $templateId = null;
            }
        }

        return $templateId;
    }

    /**
     * Charge les éléments de template activés pour un template donné.
     */
    private function loadTemplateElements(int $templateId): array
    {
        try {
            return $this->db->exec(
                'SELECT id, element_type, element_subtype, section_placement, config_json
                 FROM template_elements
                 WHERE template_id = ? AND is_enabled = 1
                 ORDER BY display_order ASC',
                [$templateId]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Construit les modules à partir des éléments de template.
     */
    private function buildModulesFromTemplateElements(
        array $teRows,
        int $projectId,
        array $cnt,
        array $sectionCounts,
        bool &$hasNoteModule
    ): array {
        $iconMap = [
            'chapter'   => 'list-ol',
            'act'       => 'layer-group',
            'section'   => 'bookmark',
            'character' => 'users',
            'note'      => 'sticky-note',
            'file'      => 'paperclip',
            'scenario'  => 'film',
            'synopsis'  => 'file-alt',
            'element'   => 'puzzle-piece',
            'glossary'  => 'book-open',
        ];

        $pathMap = [
            'character' => '/project/'.$projectId.'/characters',
            'note'      => '/project/'.$projectId.'/notes',
            'file'      => '/project/'.$projectId.'/files',
            'scenario'  => '/project/'.$projectId.'/scenarios',
            'synopsis'  => '/project/'.$projectId.'/synopsis',
            'glossary'  => '/project/'.$projectId.'/glossary',
        ];

        $defaultLabels = [
            'character' => 'Personnages',
            'note'      => 'Notes',
            'file'      => 'Fichiers',
            'scenario'  => 'Scénario',
            'synopsis'  => 'Synopsis',
            'glossary'  => 'Glossaire',
        ];

        $countMap = [
            'character' => (int)($cnt['character'] ?? 0),
            'note'      => (int)($cnt['note']      ?? 0),
            'file'      => (int)($cnt['file']      ?? 0),
            'scenario'  => (int)($cnt['scenario']  ?? 0),
            'glossary'  => (int)($cnt['glossary']  ?? 0),
            'synopsis'  => 0,
        ];

        $modules = [];

        foreach ($teRows as $te) {
            $type = $te['element_type'];
            $cfg  = json_decode($te['config_json'] ?? '{}', true);

            if ($type === 'chapter') {
                $modules[] = [
                    'label' => $cfg['label_plural'] ?? 'Chapitres',
                    'path'  => '/project/'.$projectId.'/chapters',
                    'icon'  => 'list-ol',
                    'nb'    => (int)($cnt['chapter'] ?? 0),
                ];
                continue;
            }

            if ($type === 'act') {
                $modules[] = [
                    'label' => $cfg['label_plural'] ?? 'Actes',
                    'path'  => '/project/'.$projectId.'/acts',
                    'icon'  => 'layer-group',
                    'nb'    => (int)($cnt['act'] ?? 0),
                ];
                continue;
            }

            if ($type === 'section') {
                $subtype = $te['element_subtype'] ?? '';
                $label   = $cfg['label'] ?? $cfg['label_plural'] ?? 'Sections';
                $nb      = $subtype ? ($sectionCounts[$subtype] ?? 0) : array_sum($sectionCounts);
                $path    = $subtype
                    ? '/project/'.$projectId.'/section/'.$subtype
                    : '/project/'.$projectId;
                $modules[] = [
                    'label' => $label,
                    'path'  => $path,
                    'icon'  => 'bookmark',
                    'nb'    => $nb,
                ];
                continue;
            }

            if ($type === 'element') {
                try {
                    $eRows  = $this->db->exec(
                        'SELECT COUNT(*) AS cnt FROM elements WHERE project_id = ? AND template_element_id = ? AND parent_id IS NULL',
                        [$projectId, (int) $te['id']]
                    );
                    $eCount = (int)($eRows[0]['cnt'] ?? 0);
                } catch (\Exception $e) {
                    $eCount = 0;
                }
                $modules[] = [
                    'label' => $cfg['label_plural'] ?? 'Éléments',
                    'path'  => '/project/'.$projectId.'/elements/'.(int)$te['id'],
                    'icon'  => 'puzzle-piece',
                    'nb'    => $eCount,
                ];
                continue;
            }

            if (!isset($pathMap[$type])) {
                continue;
            }

            if ($type === 'note') {
                $hasNoteModule = true;
            }

            $modules[] = [
                'label' => $cfg['label_plural'] ?? ($defaultLabels[$type] ?? ucfirst($type)),
                'path'  => $pathMap[$type],
                'icon'  => $iconMap[$type] ?? 'circle',
                'nb'    => $countMap[$type] ?? 0,
            ];
        }

        return $modules;
    }

    /**
     * Ajoute les chemins actifs aux modules (sans le fragment après #).
     */
    private function addActivePaths(array $modules): array
    {
        foreach ($modules as &$m) {
            $m['active_path'] = strtok($m['path'], '#');
        }
        unset($m);
        return $modules;
    }
}
