<?php

class ProjectMindmapController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function mindmap()
    {
        $pid = (int) $this->f3->get('PARAMS.id');

        if (!$this->canAccessProject($pid)) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);

        if (!$project) {
            $this->f3->error(404);
            return;
        }

        // --- Fetch Data ---
        $characterModel = new Character();
        $characters = $characterModel->getAllByProject($pid);

        $noteModel = new Note();
        $notes = $noteModel->getAllByProject($pid);

        $actModel = new Act();
        $acts = $actModel->getAllByProject($pid);

        $sectionModel = new Section();
        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $sectionsAfter  = $sectionModel->getAfterChapters($pid);

        $chapterModel = new Chapter();
        $chapters = $chapterModel->getAllByProject($pid);

        // --- Build Graph Data ---
        $nodes = [];
        $links = [];

        // 1. Root: Project
        $settings = json_decode($project[0]['settings'] ?? '{}', true);
        $nodes[] = [
            'id'          => 'project',
            'name'        => $project[0]['title'],
            'type'        => 'project',
            'description' => $project[0]['summary'] ?? ($project[0]['description'] ?? ''),
            'author'      => $settings['author'] ?? ''
        ];

        // Load template system to respect display_order
        $db = $this->f3->get('DB');
        $templateElements = [];
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId    = $project[0]['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            $template = $templateId
                ? ($templateModel->findAndCast(['id=?', $templateId]) ? $templateModel->findAndCast(['id=?', $templateId])[0] : $templateModel->getDefault())
                : $templateModel->getDefault();

            if ($template) {
                $templateElements = $templateModel->getElements($template['id']);
            }
        }

        // Load custom elements
        $topElementsByType   = [];
        $subElementsByParent = [];
        if ($db->exists('elements')) {
            $elementModel = new Element();
            $allElements  = $elementModel->getAllByProject($pid);

            foreach ($allElements as $elem) {
                if (!($elem['is_exported'] ?? 1)) continue;
                $tid = $elem['template_element_id'];
                if (!empty($elem['parent_id'])) {
                    $subElementsByParent[$elem['parent_id']][] = $elem;
                } else {
                    if (!isset($topElementsByType[$tid])) {
                        $topElementsByType[$tid] = [];
                    }
                    $topElementsByType[$tid][] = $elem;
                }
            }
        }

        // Filter data
        $exportedNotes     = array_filter($notes,     fn($n) => ($n['is_exported'] ?? 1));
        $exportedSecBefore = array_filter($sectionsBefore, fn($s) => ($s['is_exported'] ?? 1));
        $exportedSecAfter  = array_filter($sectionsAfter,  fn($s) => ($s['is_exported'] ?? 1));
        $exportedChapters  = array_filter($chapters,  fn($c) => ($c['is_exported'] ?? 1));
        $exportedChapterIds = array_column($exportedChapters, 'id');

        // Build orphan chapters virtual act
        $hasOrphans = false;
        foreach ($exportedChapters as $ch) {
            if (empty($ch['act_id']) && empty($ch['parent_id'])) {
                $hasOrphans = true;
                break;
            }
        }

        // LEFT SIDE: Characters (always shown) + Notes (if enabled and has data)
        if (!empty($characters)) {
            $nodes[] = [
                'id'          => 'chars_group',
                'name'        => 'Personnages',
                'type'        => 'character_group',
                'description' => 'Regroupement des personnages'
            ];
            $links[] = ['source' => 'project', 'target' => 'chars_group'];

            foreach ($characters as $char) {
                $nodes[] = [
                    'id'      => 'char_' . $char['id'],
                    'name'    => $char['name'],
                    'type'    => 'character',
                    'content' => $char['description']
                ];
                $links[] = ['source' => 'chars_group', 'target' => 'char_' . $char['id']];
            }
        }

        // RIGHT SIDE: Loop through template elements in display_order
        $beforeGroupAdded = false;
        $afterGroupAdded  = false;
        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) continue;

            switch ($te['element_type']) {
                case 'section':
                    $isBeforePlacement = ($te['section_placement'] === 'before');
                    $sections  = $isBeforePlacement ? $exportedSecBefore : $exportedSecAfter;
                    $groupName = $isBeforePlacement ? 'Avant-propos' : 'Annexes';
                    $groupId   = $isBeforePlacement ? 'sec_before_group' : 'sec_after_group';

                    if (!empty($sections)) {
                        if ($isBeforePlacement && $beforeGroupAdded) break;
                        if (!$isBeforePlacement && $afterGroupAdded) break;

                        $nodes[] = [
                            'id'          => $groupId,
                            'name'        => $groupName,
                            'type'        => 'section_group',
                            'description' => ''
                        ];
                        $links[] = ['source' => 'project', 'target' => $groupId];

                        if ($isBeforePlacement) $beforeGroupAdded = true;
                        else $afterGroupAdded = true;

                        foreach ($sections as $sec) {
                            $nodes[] = [
                                'id'      => 'sec_' . $sec['id'],
                                'name'    => $sec['title'],
                                'type'    => 'section',
                                'content' => $sec['content']
                            ];
                            $links[] = ['source' => $groupId, 'target' => 'sec_' . $sec['id']];
                        }
                    }
                    break;

                case 'act':
                    foreach ($acts as $act) {
                        if (!($act['is_exported'] ?? 1)) continue;
                        $nodes[] = [
                            'id'      => 'act_' . $act['id'],
                            'name'    => $act['title'],
                            'type'    => 'act',
                            'content' => $act['content'] ?? ''
                        ];
                        $links[] = ['source' => 'project', 'target' => 'act_' . $act['id']];
                    }
                    break;

                case 'chapter':
                    if ($hasOrphans) {
                        $nodes[] = ['id' => 'act_xxx', 'name' => 'Acte XXX', 'type' => 'act'];
                        $links[] = ['source' => 'project', 'target' => 'act_xxx'];
                    }

                    foreach ($exportedChapters as $ch) {
                        $nodes[] = [
                            'id'           => 'chapter_' . $ch['id'],
                            'name'         => $ch['title'],
                            'type'         => 'chapter',
                            'content'      => $ch['content'],
                            'description'  => '',
                            'is_subchapter' => !empty($ch['parent_id'])
                        ];

                        if (!empty($ch['parent_id']) && in_array($ch['parent_id'], $exportedChapterIds)) {
                            $links[] = ['source' => 'chapter_' . $ch['parent_id'], 'target' => 'chapter_' . $ch['id']];
                        } elseif (!empty($ch['act_id'])) {
                            $links[] = ['source' => 'act_' . $ch['act_id'], 'target' => 'chapter_' . $ch['id']];
                        } else {
                            $links[] = ['source' => 'act_xxx', 'target' => 'chapter_' . $ch['id']];
                        }
                    }
                    break;

                case 'note':
                    if (!empty($exportedNotes)) {
                        $nodes[] = [
                            'id'          => 'notes_group',
                            'name'        => 'Notes',
                            'type'        => 'note_group',
                            'description' => ''
                        ];
                        $links[] = ['source' => 'project', 'target' => 'notes_group'];

                        foreach ($exportedNotes as $note) {
                            $nodes[] = [
                                'id'      => 'note_' . $note['id'],
                                'name'    => $note['title'] ?: 'Sans titre',
                                'type'    => 'note',
                                'content' => $note['content']
                            ];
                            $links[] = ['source' => 'notes_group', 'target' => 'note_' . $note['id']];
                        }
                    }
                    break;

                case 'element':
                    $teId  = $te['id'];
                    $items = $topElementsByType[$teId] ?? [];
                    if (empty($items)) break;

                    $cfg      = json_decode($te['config_json'] ?? '{}', true);
                    $groupId  = 'elem_group_' . $teId;
                    $groupLabel = $cfg['label_plural'] ?? 'Éléments';

                    $nodes[] = [
                        'id'          => $groupId,
                        'name'        => $groupLabel,
                        'type'        => 'element_group',
                        'description' => ''
                    ];
                    $links[] = ['source' => 'project', 'target' => $groupId];

                    foreach ($items as $elem) {
                        $nodeId  = 'element_' . $elem['id'];
                        $nodes[] = [
                            'id'           => $nodeId,
                            'name'         => $elem['title'],
                            'type'         => 'element',
                            'content'      => $elem['content'] ?? '',
                            'is_subelement' => false
                        ];
                        $links[] = ['source' => $groupId, 'target' => $nodeId];

                        $subs = $subElementsByParent[$elem['id']] ?? [];
                        foreach ($subs as $sub) {
                            $subNodeId = 'element_' . $sub['id'];
                            $nodes[]   = [
                                'id'           => $subNodeId,
                                'name'         => $sub['title'],
                                'type'         => 'element',
                                'content'      => $sub['content'] ?? '',
                                'is_subelement' => true
                            ];
                            $links[] = ['source' => $nodeId, 'target' => $subNodeId];
                        }
                    }
                    break;
            }
        }

        $data = ['nodes' => $nodes, 'links' => $links];

        $this->render('project/mindmap.html', [
            'title'       => 'Carte mentale',
            'project'     => $project[0],
            'mindmapData' => json_encode($data)
        ]);
    }
}
