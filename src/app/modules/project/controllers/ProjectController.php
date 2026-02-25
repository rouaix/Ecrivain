<?php

class ProjectController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        $pattern = $f3->get('PATTERN');
        file_put_contents($f3->get('ROOT') . '/logs/theme_debug.log', date('[Y-m-d H:i:s] ') . "ProjectController::beforeRoute - PATTERN: '$pattern' | VERB: " . $f3->get('VERB') . "\n", FILE_APPEND);

        // Skip login check AND CSRF check for setTheme to allow theme switching on login/register pages
        if ($pattern === '/theme') {
            $this->checkAutoLogin($f3);
            return;
        }

        parent::beforeRoute($f3);

        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function setTheme()
    {
        $theme   = $this->f3->get('POST.theme');
        $logFile = $this->f3->get('ROOT') . '/logs/theme_debug.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "setTheme called. Theme: $theme\n", FILE_APPEND);

        if (in_array($theme, ['default', 'sepia', 'dark', 'modern', 'paper', 'midnight', 'deep', 'studio', 'writer', 'rouge', 'blue', 'forest', 'moderne'])) {

            if (headers_sent($file, $line)) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "ERROR: Headers already sent in $file on line $line\n", FILE_APPEND);
                return;
            }

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            $domain = $this->f3->get('SESSION_DOMAIN');
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Setting cookie - Domain: '$domain' | HTTPS: " . ($isHttps ? 'Yes' : 'No') . "\n", FILE_APPEND);

            setcookie('theme', '', time() - 3600, '/', '', $isHttps, false);
            setcookie('theme', '', time() - 3600, '/', $this->f3->get('HOST'), $isHttps, false);

            $res = setcookie(
                'theme',
                $theme,
                [
                    'expires'  => time() + (3600 * 24 * 30),
                    'path'     => '/',
                    'domain'   => $domain,
                    'secure'   => $isHttps,
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]
            );

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "setcookie result: " . ($res ? 'Success' : 'Failure') . "\n", FILE_APPEND);

            $_COOKIE['theme'] = $theme;
            $this->f3->sync('COOKIE');
        } else {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Invalid theme: $theme\n", FILE_APPEND);
        }

        // Prevent open redirect - only allow internal redirects
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $base    = $this->f3->get('BASE');
        $host    = $this->f3->get('HOST');

        if ($referer && parse_url($referer, PHP_URL_HOST) === $host) {
            $this->f3->reroute($referer);
        } else {
            $this->f3->reroute($base . '/dashboard');
        }
    }

    public function dashboard()
    {
        $projectModel = new Project();
        $user         = $this->currentUser();
        $projects     = $projectModel->findAndCast(['user_id=?', $user['id']], ['order' => 'created_at DESC']);

        foreach ($projects as &$proj) {
            $wpp = $proj['words_per_page'] ?: 350;
            $proj['pages_count'] = ceil($proj['target_words'] / $wpp);
        }

        $sharedProjects = $this->db->exec(
            'SELECT p.*, pc.accepted_at, u.username AS owner_username
             FROM projects p
             JOIN project_collaborators pc ON pc.project_id = p.id
             JOIN users u ON u.id = p.user_id
             WHERE pc.user_id = ? AND pc.status = "accepted"
             ORDER BY p.updated_at DESC',
            [$user['id']]
        ) ?: [];

        $pendingInvitations = $this->db->exec(
            'SELECT pc.id, p.title AS project_title, u.username AS owner_username
             FROM project_collaborators pc
             JOIN projects p ON p.id = pc.project_id
             JOIN users u ON u.id = pc.owner_id
             WHERE pc.user_id = ? AND pc.status = "pending"',
            [$user['id']]
        ) ?: [];

        $this->render('project/dashboard', [
            'title'              => 'Tableau de bord',
            'projects'           => $projects,
            'user'               => $user,
            'sharedProjects'     => $sharedProjects,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    public function create()
    {
        $templates       = [];
        $defaultTemplate = null;
        $db              = $this->f3->get('DB');
        if ($db->exists('templates')) {
            $templateModel   = new ProjectTemplate();
            $user            = $this->currentUser();
            $templates       = $templateModel->getAllAvailable($user['id']);
            $defaultTemplate = $templateModel->getDefault();
        }

        $this->render('project/create', [
            'title'     => 'Nouveau projet',
            'templates' => $templates,
            'old'       => [
                'title'          => '',
                'description'    => '',
                'words_per_page' => 350,
                'target_pages'   => 0,
                'target_words'   => 0,
                'template_id'    => $defaultTemplate['id'] ?? ''
            ]
        ]);
    }

    public function store()
    {
        $f3          = Base::instance();
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $target      = intval($_POST['target_words'] ?? 0);
        $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
        $targetPages = intval($_POST['target_pages'] ?? 0);
        $templateId  = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre du projet est obligatoire.';
        }

        if (empty($errors)) {
            if (!$templateId && $this->f3->get('DB')->exists('templates')) {
                $templateModel = new ProjectTemplate();
                $defaultTemplate = $templateModel->getDefault();
                $templateId = $defaultTemplate['id'] ?? null;
            }

            $projectModel              = new Project();
            $projectModel->user_id     = $this->currentUser()['id'];
            $projectModel->title       = $title;
            $projectModel->description = $description;
            $projectModel->target_words = $target;
            $projectModel->words_per_page = $wordsPerPage;
            $projectModel->target_pages = $targetPages;
            $projectModel->template_id = $templateId;

            try {
                if ($projectModel->save()) {
                    $this->f3->reroute('/project/' . $projectModel->id);
                } else {
                    $errors[] = 'Impossible de créer le projet.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }

        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user          = $this->currentUser();
            $templates     = $templateModel->getAllAvailable($user['id']);
        }

        $this->render('project/create', [
            'title'     => 'Nouveau projet',
            'errors'    => $errors,
            'templates' => $templates,
            'old'       => [
                'title'          => htmlspecialchars($title),
                'description'    => htmlspecialchars($description),
                'target_words'   => $target,
                'words_per_page' => $wordsPerPage,
                'target_pages'   => $targetPages,
                'template_id'    => $templateId
            ],
        ]);
    }

    public function show()
    {
        $pid = (int) $this->f3->get('PARAMS.id');

        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }

        $isOwner        = $this->isOwner($pid);
        $isCollaborator = !$isOwner;

        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=?', $pid]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }
        $project    = $project[0];
        $authorName = $this->getUserFullName($this->currentUser());
        $wpp        = $project['words_per_page'] ?: 350;
        $lpp        = $project['lines_per_page'] ?: 38;

        // TEMPLATE SYSTEM: Load template configuration
        $template            = null;
        $templateElements    = [];
        $customElementPanels = [];
        $customElementsByType = [];
        $panelCss            = '';
        $panelLabels = [
            'section_before' => 'Sections avant les chapitres',
            'section_after'  => 'Sections après les chapitres',
            'act'            => ['singular' => 'Acte',       'plural' => 'Actes'],
            'chapter'        => ['singular' => 'Chapitre',   'plural' => 'Chapitres'],
            'note'           => ['singular' => 'Note',        'plural' => 'Notes'],
            'character'      => ['singular' => 'Personnage', 'plural' => 'Personnages'],
            'file'           => ['singular' => 'Fichier',    'plural' => 'Fichiers'],
        ];
        $sectionBeforeConfig = null;
        $sectionAfterConfig  = null;
        $panelConfig = [
            'section_before' => true,
            'content'        => true,
            'section_after'  => true,
            'note'           => true,
            'character'      => true,
            'file'           => true,
            'has_acts'       => true,
        ];

        $db = $this->f3->get('DB');
        if ($db->exists('templates') && $db->exists('template_elements')) {
          try {
            $templateId    = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();

            if (!$templateId) {
                $template   = $templateModel->getDefault();
                $templateId = $template['id'] ?? null;
            } else {
                $template = $templateModel->findAndCast(['id=?', $templateId]);
                $template = $template ? $template[0] : $templateModel->getDefault();
            }

            $templateElements = $template ? $templateModel->getElements($template['id']) : [];

            if (!empty($templateElements)) {
                $panelConfig = [
                    'section_before' => false,
                    'content'        => false,
                    'section_after'  => false,
                    'note'           => false,
                    'character'      => false,
                    'file'           => false,
                    'has_acts'       => false,
                ];

                $customElementsByType = [];
                if ($db->exists('elements')) {
                    $elementModel   = new Element();
                    $customElements = $elementModel->getAllByProject($pid);

                    $subElementsByParent = [];
                    foreach ($customElements as $elem) {
                        if ($elem['parent_id']) {
                            $subElementsByParent[$elem['parent_id']][] = $elem;
                        }
                    }

                    foreach ($customElements as $elem) {
                        if ($elem['parent_id']) continue;

                        $tid = $elem['template_element_id'];
                        if (!isset($customElementsByType[$tid])) {
                            $customElementsByType[$tid] = [];
                        }

                        $cleanContent       = strip_tags(html_entity_decode($elem['content'] ?? ''));
                        $elem['wc']         = str_word_count($cleanContent);
                        $elem['is_exported_attr'] = ($elem['is_exported'] ?? 1) ? 'checked' : '';

                        $elem['subs'] = [];
                        $subsWc = 0;
                        if (isset($subElementsByParent[$elem['id']])) {
                            foreach ($subElementsByParent[$elem['id']] as $sub) {
                                $cleanSubContent        = strip_tags(html_entity_decode($sub['content'] ?? ''));
                                $sub['wc']              = str_word_count($cleanSubContent);
                                $sub['is_exported_attr'] = ($sub['is_exported'] ?? 1) ? 'checked' : '';
                                $subsWc                += $sub['wc'];
                                $elem['subs'][]        = $sub;
                            }
                        }

                        $elem['total_wc']      = $elem['wc'] + $subsWc;
                        $elem['wc_subs_only']  = $subsWc;

                        $customElementsByType[$tid][] = $elem;
                    }
                }

                foreach ($templateElements as $te) {
                    if (!$te['is_enabled']) continue;

                    $type = $te['element_type'];
                    $cfg  = json_decode($te['config_json'] ?? '{}', true);

                    if ($type === 'section') {
                        $placement = $te['section_placement'] ?? 'before';
                        $subtype   = $te['element_subtype']   ?? '';
                        $label     = $cfg['label'] ?? \Section::getTypeName($subtype);
                        if ($placement === 'before') {
                            if ($sectionBeforeConfig === null) $sectionBeforeConfig = [];
                            $sectionBeforeConfig[$subtype] = $label;
                        } else {
                            if ($sectionAfterConfig === null) $sectionAfterConfig = [];
                            $sectionAfterConfig[$subtype] = $label;
                        }
                        $key = 'section_' . $placement;
                    } elseif ($type === 'act') {
                        $panelLabels['act'] = [
                            'singular' => $cfg['label_singular'] ?? 'Acte',
                            'plural'   => $cfg['label_plural']   ?? 'Actes',
                        ];
                        $panelConfig['has_acts'] = true;
                        $key = 'content';
                    } elseif ($type === 'chapter') {
                        $panelLabels['chapter'] = [
                            'singular' => $cfg['label_singular'] ?? 'Chapitre',
                            'plural'   => $cfg['label_plural']   ?? 'Chapitres',
                        ];
                        $key = 'content';
                    } elseif ($type === 'element') {
                        $customElementPanels[] = [
                            'id'             => $te['id'],
                            'label_singular' => $cfg['label_singular'] ?? 'élément',
                            'label_plural'   => $cfg['label_plural']   ?? 'Éléments',
                            'elements'       => $customElementsByType[$te['id']] ?? [],
                            'count'          => count($customElementsByType[$te['id']] ?? []),
                        ];
                        continue;
                    } elseif ($type === 'note') {
                        $panelLabels['note'] = [
                            'singular' => $cfg['label_singular'] ?? 'Note',
                            'plural'   => $cfg['label_plural']   ?? 'Notes',
                        ];
                        $key = 'note';
                    } elseif ($type === 'character') {
                        $panelLabels['character'] = [
                            'singular' => $cfg['label_singular'] ?? 'Personnage',
                            'plural'   => $cfg['label_plural']   ?? 'Personnages',
                        ];
                        $key = 'character';
                    } elseif ($type === 'file') {
                        $panelLabels['file'] = [
                            'singular' => $cfg['label_singular'] ?? 'Fichier',
                            'plural'   => $cfg['label_plural']   ?? 'Fichiers',
                        ];
                        $key = 'file';
                    } else {
                        $key = $type;
                    }

                    if (isset($panelConfig[$key])) {
                        $panelConfig[$key] = true;
                    }
                }

                $panelCss = $this->buildPanelOrderCss($templateElements);
            }
          } catch (\Exception $e) {
              $panelConfig = [
                  'section_before' => true, 'content' => true,
                  'section_after'  => true, 'note'    => true,
                  'character'      => true, 'file'    => true,
              ];
          }
        }

        foreach (['act', 'chapter', 'note', 'character', 'file'] as $_pk) {
            $panelLabels[$_pk]['singular_lc'] = strtolower($panelLabels[$_pk]['singular']);
            $panelLabels[$_pk]['plural_lc']   = strtolower($panelLabels[$_pk]['plural']);
        }

        $chapterModel  = new Chapter();
        $allChapters   = $this->supHtml($chapterModel->getAllByProject($pid));

        $characterModel = new Character();
        $characters     = $this->supHtml($characterModel->getAllByProject($pid));

        $actModel = new Act();
        $acts     = $this->supHtml($actModel->getAllByProject($pid));

        $sectionModel           = new Section();
        $sectionsBeforeChapters = $this->supHtml($sectionModel->getBeforeChapters($pid));
        $sectionsAfterChapters  = $this->supHtml($sectionModel->getAfterChapters($pid));

        $noteModel = new Note();
        $notes     = $this->supHtml($noteModel->getAllByProject($pid));

        $fileModel = new ProjectFile();
        $filesRaw  = $fileModel->find(['project_id=?', $pid], ['order' => 'uploaded_at DESC']);
        $files     = [];
        foreach ($filesRaw as $f) {
            $files[] = [
                'id'             => $f->id,
                'filename'       => $f->filename,
                'filepath'       => $f->filepath,
                'filetype'       => $f->filetype,
                'filesize'       => $f->filesize,
                'size_formatted' => $f->getSizeFormatted(),
                'comment'        => $f->comment,
                'uploaded_at'    => $f->uploaded_at
            ];
        }

        // 1. Cover Image
        $coverImage = null;
        foreach ($sectionsBeforeChapters as $sec) {
            if ($sec['type'] === 'cover' && !empty($sec['image_path'])) {
                $coverImage = $sec['image_path'];
                break;
            }
        }

        // 2. Word Counts & Progress
        $totalWords = 0;
        $totalLines = 0;

        $calculateStats = function ($content, $accumulate = true) use (&$totalWords, &$totalLines, $lpp, $wpp) {
            $cleanContent = strip_tags(html_entity_decode($content ?? ''));
            $wc    = str_word_count($cleanContent);
            $lines = $cleanContent !== '' ? ceil(strlen($cleanContent) / 80) : 0;

            if ($accumulate) {
                $totalWords += $wc;
                $totalLines += $lines;
            }

            return [
                'words' => $wc,
                'lines' => $lines,
                'pages' => max(0, max(ceil($wc / $wpp), ceil($lines / $lpp)))
            ];
        };

        $enrichSections = function ($sections) use ($calculateStats) {
            foreach ($sections as &$sec) {
                $isExported   = ($sec['is_exported'] ?? 1);
                $stats        = $calculateStats($sec['content'], $isExported);
                $sec['wc']    = $stats['words'];
                $sec['lines'] = $stats['lines'];
                $sec['pages'] = $stats['pages'];
                $sec['type_name']       = \Section::getTypeName($sec['type']);
                $sec['is_exported_attr'] = $isExported ? 'checked' : '';
            }
            return $sections;
        };
        $sectionsBeforeChapters = $enrichSections($sectionsBeforeChapters);
        $sectionsAfterChapters  = $enrichSections($sectionsAfterChapters);

        foreach ($notes as &$note) {
            $isExported         = ($note['is_exported'] ?? 1);
            $stats_note         = $calculateStats($note['content'], $isExported);
            $note['wc']         = $stats_note['words'];
            $note['lines']      = $stats_note['lines'];
            $note['pages']      = $stats_note['pages'];
            $note['is_exported_attr'] = $isExported ? 'checked' : '';
        }

        $chaptersByAct       = [];
        $chaptersWithoutAct  = [];
        $subChaptersByParent = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) {
                $subChaptersByParent[$ch['parent_id']][] = $ch;
            }
        }

        $processChapter = function ($ch) use ($subChaptersByParent, $calculateStats, $lpp, $wpp) {
            $isExported        = ($ch['is_exported'] ?? 1);
            $myStats           = $calculateStats($ch['content'], $isExported);
            $ch['wc']          = $myStats['words'];
            $ch['lines']       = $myStats['lines'];
            $ch['pages']       = $myStats['pages'];
            $ch['is_exported_attr'] = $isExported ? 'checked' : '';

            $subs      = $subChaptersByParent[$ch['id']] ?? [];
            $ch['subs'] = [];
            $subsWc    = 0;
            $subsLines = 0;
            $subsPages = 0;

            foreach ($subs as $sub) {
                $subIsExported = ($sub['is_exported'] ?? 1);
                $subStats      = $calculateStats($sub['content'], $subIsExported);
                $sub['wc']     = $subStats['words'];
                $sub['lines']  = $subStats['lines'];
                $sub['pages']  = $subStats['pages'];
                $sub['is_exported_attr'] = $subIsExported ? 'checked' : '';
                $ch['subs'][]  = $sub;
                $subsWc       += $subStats['words'];
                $subsLines    += $subStats['lines'];
                $subsPages    += $subStats['pages'];
            }

            $ch['total_wc']    = $ch['wc'] + $subsWc;
            $ch['total_lines'] = $ch['lines'] + $subsLines;
            $ch['total_pages'] = $ch['pages'] + $subsPages;
            $ch['wc_subs_only'] = $subsWc;

            return $ch;
        };

        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                $processed = $processChapter($ch);
                if ($processed['act_id']) {
                    $chaptersByAct[$processed['act_id']][] = $processed;
                } else {
                    $chaptersWithoutAct[] = $processed;
                }
            }
        }

        $target   = (int) $project['target_words'];
        $progress = $target > 0 ? min(100, round($totalWords / $target * 100)) : 0;

        $stats = [
            'total_words'      => $totalWords,
            'target_words'     => $target,
            'progress_percent' => $progress,
            'pages_current'    => ceil($totalWords / $wpp),
            'pages_target'     => ceil($target / $wpp),
            'wpp'              => $wpp,
            'lpp'              => $lpp,
            'before_count'     => count($sectionsBeforeChapters),
            'after_count'      => count($sectionsAfterChapters),
            'note_count'       => count($notes),
            'character_count'  => count($characters),
            'file_count'       => count($files),
        ];

        // 3. Group Sections by Type
        $prepareSectionGroups = function (array $sections, array $typeOrder, ?array $sectionConfig = null) {
            $grouped = [];
            foreach ($sections as $s) {
                $grouped[$s['type']][] = $s;
            }

            if ($sectionConfig !== null) {
                $orderedTypes = array_keys($sectionConfig);
                foreach ($sections as $s) {
                    if (!in_array($s['type'], $orderedTypes))
                        $orderedTypes[] = $s['type'];
                }
            } else {
                $orderedTypes = [];
                $seenTypes    = [];
                foreach ($sections as $s) {
                    if (!in_array($s['type'], $seenTypes)) {
                        $orderedTypes[] = $s['type'];
                        $seenTypes[]    = $s['type'];
                    }
                }
                foreach ($typeOrder as $t) {
                    if (!in_array($t, $seenTypes)) {
                        $orderedTypes[] = $t;
                    }
                }
            }

            $finalGroups = [];
            foreach ($orderedTypes as $type) {
                $isMulti = ($type === 'notes' || $type === 'appendices');
                $items   = $grouped[$type] ?? [];

                if (!$isMulti && empty($items) && $sectionConfig === null &&
                    $type !== 'postface' && $type !== 'back_cover' && $type !== 'cover' &&
                    $type !== 'preface' && $type !== 'introduction' && $type !== 'prologue') {
                    continue;
                }

                $name = ($sectionConfig !== null && isset($sectionConfig[$type]))
                    ? $sectionConfig[$type]
                    : \Section::getTypeName($type);

                $finalGroups[] = [
                    'type'        => $type,
                    'name'        => $name,
                    'is_multi'    => $isMulti,
                    'items'       => $items,
                    'show_create' => (empty($items) && !$isMulti),
                    'show_add'    => $isMulti
                ];
            }
            return $finalGroups;
        };

        $filterBeforeGroups = function ($sections, ?array $sectionConfig = null) {
            $grouped = [];
            foreach ($sections as $s) {
                $grouped[$s['type']][] = $s;
            }

            if ($sectionConfig !== null) {
                $types = array_keys($sectionConfig);
                foreach ($sections as $s) {
                    if (!in_array($s['type'], $types))
                        $types[] = $s['type'];
                }
            } else {
                $types = ['cover', 'preface', 'introduction', 'prologue'];
                foreach ($sections as $s) {
                    if (!in_array($s['type'], $types))
                        $types[] = $s['type'];
                }
            }

            $final = [];
            foreach ($types as $type) {
                $items = $grouped[$type] ?? [];
                $name  = ($sectionConfig !== null && isset($sectionConfig[$type]))
                    ? $sectionConfig[$type]
                    : \Section::getTypeName($type);
                $final[] = [
                    'type'        => $type,
                    'name'        => $name,
                    'is_multi'    => false,
                    'items'       => $items,
                    'show_create' => empty($items),
                    'show_add'    => false
                ];
            }
            return $final;
        };

        $beforeGroups = $filterBeforeGroups($sectionsBeforeChapters, $sectionBeforeConfig);
        $afterGroups  = $prepareSectionGroups($sectionsAfterChapters, ['postface', 'appendices', 'back_cover'], $sectionAfterConfig);

        foreach ($acts as &$act) {
            $actChapters         = $chaptersByAct[$act['id']] ?? [];
            $act['stats_chapters'] = count($actChapters);
            $actTotalWc          = 0;
            foreach ($actChapters as $ch) {
                $actTotalWc += $ch['total_wc'];
            }
            $act['stats_pages']       = ceil($actTotalWc / $wpp);
            $isExported               = ($act['is_exported'] ?? 1);
            $act['is_exported_attr']  = $isExported ? 'checked' : '';
        }
        unset($act);

        $orphanStats = ['chapters' => count($chaptersWithoutAct), 'pages' => 0];
        if (!empty($chaptersWithoutAct)) {
            $orphanTotalWc = 0;
            foreach ($chaptersWithoutAct as $ch) {
                $orphanTotalWc += $ch['total_wc'];
            }
            $orphanStats['pages'] = ceil($orphanTotalWc / $wpp);
        }

        $stats['has_content'] = (count($acts) > 0 || count($allChapters) > 0);

        $this->render('project/show.html', [
            'title'               => 'Projet: ' . htmlspecialchars($project['title']),
            'project'             => $project,
            'acts'                => $acts,
            'chaptersByAct'       => $chaptersByAct,
            'chaptersWithoutAct'  => $chaptersWithoutAct,
            'orphanStats'         => $orphanStats,
            'characters'          => $characters,
            'coverImage'          => $coverImage,
            'stats'               => $stats,
            'beforeGroups'        => $beforeGroups,
            'afterGroups'         => $afterGroups,
            'notes'               => $notes,
            'files'               => $files,
            'authorName'          => $authorName,
            'template'            => $template,
            'templateElements'    => $templateElements,
            'panelConfig'         => $panelConfig,
            'panelLabels'         => $panelLabels,
            'customElementPanels' => $customElementPanels,
            'customElementsByType' => $customElementsByType ?? [],
            'panelCss'            => $panelCss,
            'isOwner'             => $isOwner,
            'isCollaborator'      => $isCollaborator,
        ]);
    }

    public function edit()
    {
        $pid          = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404);
            return;
        }

        $projectData                    = $project[0];
        $projectData['lines_per_page']  = $projectData['lines_per_page'] ?? 38;

        $settings                = json_decode($projectData['settings'] ?? '{}', true);
        $projectData['author']   = $settings['author'] ?? '';

        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user          = $this->currentUser();
            $templates     = $templateModel->getAllAvailable($user['id']);
        }

        $this->render('project/edit.html', [
            'title'     => 'Modifier le projet',
            'project'   => $projectData,
            'templates' => $templates,
            'errors'    => []
        ]);
    }

    public function update()
    {
        $pid          = (int) $this->f3->get('PARAMS.id');
        $user         = $this->currentUser();
        $projectModel = new Project();
        $projectModel->load(['id=? AND user_id=?', $pid, $user['id']]);

        if ($projectModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $author       = trim($_POST['author'] ?? '');
        $comment      = $_POST['comment'] ?? '';
        $target       = intval($_POST['target_words'] ?? 0);
        $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
        $linesPerPage = intval($_POST['lines_per_page'] ?? 38);
        $targetPages  = intval($_POST['target_pages'] ?? 0);
        $templateId   = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (empty($errors)) {
            // Lazy Migration: Ensure columns exist
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN lines_per_page INT DEFAULT 38"); } catch (\Exception $e) {}
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN settings TEXT"); } catch (\Exception $e) {}
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN cover_image TEXT"); } catch (\Exception $e) {}

            if (isset($_FILES['cover_image'])) {
                $validation = $this->validateImageUpload($_FILES['cover_image']);

                if (!$validation['success']) {
                    if ($_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $errors[] = $validation['error'];
                        error_log("Cover upload validation failed: " . $validation['error']);
                    }
                } else {
                    $uploadDir = 'data/' . $user['email'] . '/projects/' . $pid . '/';

                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            error_log("Failed to create upload directory: {$uploadDir}");
                            $errors[] = 'Impossible de créer le répertoire d\'upload.';
                        }
                    }

                    if (empty($errors)) {
                        $extension  = $validation['extension'];
                        $filename   = 'cover.' . $extension;
                        $targetPath = $uploadDir . $filename;

                        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                            if (!empty($projectModel->cover_image) && $projectModel->cover_image !== $filename) {
                                $oldFile = $uploadDir . $projectModel->cover_image;
                                if (file_exists($oldFile)) {
                                    unlink($oldFile);
                                    error_log("Deleted old cover: {$oldFile}");
                                }
                            }
                            $projectModel->cover_image = $filename;
                            error_log("Cover uploaded successfully: {$targetPath}");
                        } else {
                            error_log("Failed to move uploaded file to: {$targetPath}");
                            $errors[] = 'Erreur lors de l\'upload de l\'image.';
                        }
                    }
                }
            }

            $projectModel->title          = $title;
            $projectModel->description    = $description;
            $projectModel->comment        = $comment;
            $projectModel->target_words   = $target;
            $projectModel->words_per_page = $wordsPerPage;
            $projectModel->lines_per_page = $linesPerPage;
            $projectModel->target_pages   = $targetPages;
            if ($templateId) {
                $projectModel->template_id = $templateId;
            }

            $currentSettings           = json_decode($projectModel->settings ?? '{}', true) ?: [];
            $currentSettings['author'] = $author;
            $projectModel->settings    = json_encode($currentSettings);

            error_log("Saving Author: " . $author);
            error_log("Settings JSON: " . $projectModel->settings);

            $projectModel->save();
            $this->f3->reroute('/project/' . $pid);
        }

        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user          = $this->currentUser();
            $templates     = $templateModel->getAllAvailable($user['id']);
        }

        $this->render('project/edit.html', [
            'title'     => 'Modifier le projet',
            'project'   => $projectModel->cast(),
            'templates' => $templates,
            'errors'    => $errors,
        ]);
    }

    public function delete()
    {
        $pid          = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $projectModel->load(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);
        if (!$projectModel->dry()) {
            $projectModel->erase();
        }
        $this->f3->reroute('/dashboard');
    }

    public function cover()
    {
        $pid = (int) $this->f3->get('PARAMS.id');

        if (!$this->canAccessProject($pid)) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=?', $pid]);

        if (!$project || empty($project[0]['cover_image'])) {
            error_log("Cover not found - Project: {$pid}, Cover: " . ($project[0]['cover_image'] ?? 'none'));
            $this->f3->error(404);
            return;
        }

        $projectData    = $project[0];
        $coverImage     = $projectData['cover_image'];
        $projectDataDir = $this->getProjectDataDir($pid);
        $filePath       = $projectDataDir . '/projects/' . $pid . '/' . $coverImage;

        if (!file_exists($filePath)) {
            error_log("Cover file not found at path: {$filePath}");
            $this->f3->error(404);
            return;
        }

        $ext       = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000');
        readfile($filePath);
        exit;
    }
}
