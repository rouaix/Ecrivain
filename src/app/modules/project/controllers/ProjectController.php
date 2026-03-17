<?php

class ProjectController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        $pattern = $f3->get('PATTERN');
        Logger::debug('project', 'beforeRoute', ['pattern' => $pattern, 'verb' => $f3->get('VERB')]);

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
        $theme = $this->f3->get('POST.theme');
        Logger::debug('project', 'setTheme called', ['theme' => $theme]);

        if (in_array($theme, ['default', 'sepia', 'dark', 'modern', 'paper', 'midnight', 'deep', 'studio', 'writer', 'rouge', 'blue', 'forest', 'moderne'])) {

            if (headers_sent($file, $line)) {
                Logger::warn('project', 'setTheme: headers already sent', ['file' => $file, 'line' => $line]);
                return;
            }

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            $domain = $this->f3->get('SESSION_DOMAIN');
            Logger::debug('project', 'setTheme: setting cookie', ['domain' => $domain, 'https' => $isHttps]);

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

            Logger::debug('project', 'setTheme: cookie result', ['success' => $res]);

            $_COOKIE['theme'] = $theme;
            $this->f3->sync('COOKIE');
        } else {
            Logger::warn('project', 'setTheme: invalid theme', ['theme' => $theme]);
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
        $user = $this->currentUser();
        $svc  = new ProjectService($this->db);

        $projects = $svc->getOwnedProjects($user['id']);

        // Attach tags to each owned project
        $projectIds = array_map('intval', array_column($projects, 'id'));
        $tagsMap    = $svc->getTagsForProjects($projectIds);
        foreach ($projects as &$proj) {
            $proj['tags'] = $tagsMap[(int) $proj['id']] ?? [];
        }
        unset($proj);

        // Daily goal widget
        $profileFile = $this->getUserDataDir($user['email']) . 'profile.json';
        $profileData = file_exists($profileFile)
            ? (json_decode(file_get_contents($profileFile), true) ?: [])
            : [];
        $dailyGoal   = max(0, (int) ($profileData['daily_goal'] ?? 0));
        $daily       = $svc->getDailyProgress($user['id'], $dailyGoal);

        $this->render('project/dashboard', [
            'title'              => 'Tableau de bord',
            'projects'           => $projects,
            'user'               => $user,
            'sharedProjects'     => $svc->getSharedProjects($user['id']),
            'pendingInvitations' => $svc->getPendingInvitations($user['id']),
            'allTags'            => $svc->getAllTags($user['id']),
            'dailyGoal'          => $dailyGoal,
            'wordsToday'         => $daily['wordsToday'],
            'dailyPct'           => $daily['dailyPct'],
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
                'tags'           => '',
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
        $tagsRaw     = trim($_POST['tags'] ?? '');

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
                    $this->saveProjectTags((int)$projectModel->id, $this->currentUser()['id'], $tagsRaw);
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

        // Strip HTML tags (including <style>/<script> content) and normalize whitespace for word counting
        $cleanForWordCount = function (string $html): string {
            $s = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
            $s = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $s);
            $s = strip_tags($s);
            $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $s = str_replace("\xC2\xA0", ' ', $s); // non-breaking space → regular space
            return trim(preg_replace('/\s+/', ' ', $s));
        };

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
            'scenario'       => true,
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
                    'scenario'       => false,
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

                        $cleanContent       = $cleanForWordCount($elem['content'] ?? '');
                        $elem['wc']         = $cleanContent !== '' ? str_word_count($cleanContent) : 0;
                        $elem['is_exported_attr'] = ($elem['is_exported'] ?? 1) ? 'checked' : '';

                        $elem['subs'] = [];
                        $subsWc = 0;
                        if (isset($subElementsByParent[$elem['id']])) {
                            foreach ($subElementsByParent[$elem['id']] as $sub) {
                                $cleanSubContent        = $cleanForWordCount($sub['content'] ?? '');
                                $sub['wc']              = $cleanSubContent !== '' ? str_word_count($cleanSubContent) : 0;
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
                    } elseif ($type === 'scenario') {
                        $key = 'scenario';
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
                  'section_before' => true, 'content'  => true,
                  'section_after'  => true, 'note'     => true,
                  'character'      => true, 'file'     => true,
                  'scenario'       => true,
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
        $notes     = array_values(array_filter(
            $this->supHtml($noteModel->getAllByProject($pid)),
            fn($n) => ($n['type'] ?? 'note') !== 'scenario'
        ));

        $scenarioModel = new Scenario();
        $scenarios     = $this->supHtml($scenarioModel->getAllByProject($pid));

        $glossaryModel  = new GlossaryEntry();
        $glossaryEntries = $glossaryModel->getAllByProject($pid);

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

        $calculateStats = function ($content, $accumulate = true) use (&$totalWords, &$totalLines, $lpp, $wpp, $cleanForWordCount) {
            $cleanContent = $cleanForWordCount($content ?? '');
            $wc    = $cleanContent !== '' ? str_word_count($cleanContent) : 0;
            $lines = $cleanContent !== '' ? ceil(mb_strlen($cleanContent) / 80) : 0;

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
            'glossary_count'   => count($glossaryEntries),
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
            'scenarios'           => $scenarios,
            'files'               => $files,
            'glossaryEntries'     => $glossaryEntries,
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

        // Load existing tags for this project
        $existingTags = $this->getProjectTags($pid);
        $projectData['tags_string'] = implode(', ', array_column($existingTags, 'name'));

        $fileModel   = new ProjectFile();
        $rawImgFiles = $fileModel->find(['project_id=? AND filetype LIKE ?', $pid, 'image/%']);
        $imageFiles  = [];
        if ($rawImgFiles) {
            foreach ($rawImgFiles as $f) {
                $imageFiles[] = $f->cast();
            }
        }

        $this->render('project/edit.html', [
            'title'      => 'Modifier le projet',
            'project'    => $projectData,
            'templates'  => $templates,
            'imageFiles' => $imageFiles,
            'errors'     => []
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
        $tagsRaw      = trim($_POST['tags'] ?? '');

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (empty($errors)) {
            // Lazy Migration: Ensure columns exist
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN lines_per_page INT DEFAULT 38"); } catch (\Exception $e) {}
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN settings TEXT"); } catch (\Exception $e) {}
            try { $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN cover_image TEXT"); } catch (\Exception $e) {}

            $coverFromFile   = intval($_POST['cover_from_file'] ?? 0);
            $newFileUploaded = isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE;

            if ($newFileUploaded) {
                $validation = $this->validateImageUpload($_FILES['cover_image']);

                if (!$validation['success']) {
                    $errors[] = $validation['error'];
                    error_log("Cover upload validation failed: " . $validation['error']);
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
            } elseif ($coverFromFile > 0) {
                $srcFileModel = new ProjectFile();
                $srcFileModel->load(['id=? AND project_id=?', $coverFromFile, $pid]);

                if (!$srcFileModel->dry()) {
                    $absoluteSource = $this->f3->get('ROOT') . '/' . $srcFileModel->filepath;

                    if (file_exists($absoluteSource)) {
                        $ext       = strtolower(pathinfo($srcFileModel->filepath, PATHINFO_EXTENSION));
                        $uploadDir = 'data/' . $user['email'] . '/projects/' . $pid . '/';

                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $filename   = 'cover.' . $ext;
                        $targetPath = $uploadDir . $filename;

                        if (copy($absoluteSource, $targetPath)) {
                            if (!empty($projectModel->cover_image) && $projectModel->cover_image !== $filename) {
                                $oldFile = $uploadDir . $projectModel->cover_image;
                                if (file_exists($oldFile)) {
                                    unlink($oldFile);
                                }
                            }
                            $projectModel->cover_image = $filename;
                        } else {
                            $errors[] = 'Erreur lors de la copie de l\'image existante.';
                        }
                    }
                }
            }

            $oldTemplateId = (int)($projectModel->template_id ?? 0);

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
            $this->saveProjectTags($pid, $user['id'], $tagsRaw);

            // Migrate elements to new template if template changed
            if ($templateId && $templateId !== $oldTemplateId && $oldTemplateId > 0) {
                $this->migrateElementsOnTemplateChange($pid, $oldTemplateId, $templateId);
            }

            $this->f3->reroute('/project/' . $pid);
        }

        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user          = $this->currentUser();
            $templates     = $templateModel->getAllAvailable($user['id']);
        }

        $rawImgFilesErr = (new ProjectFile())->find(['project_id=? AND filetype LIKE ?', $pid, 'image/%']);
        $imageFilesErr  = [];
        if ($rawImgFilesErr) {
            foreach ($rawImgFilesErr as $f) {
                $imageFilesErr[] = $f->cast();
            }
        }

        $this->render('project/edit.html', [
            'title'      => 'Modifier le projet',
            'project'    => $projectModel->cast(),
            'templates'  => $templates,
            'imageFiles' => $imageFilesErr,
            'errors'     => $errors,
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

    // ─── Kanban status ────────────────────────────────────────────────────────

    public function updateStatus()
    {
        $pid    = (int) $this->f3->get('PARAMS.pid');
        $user   = $this->currentUser();
        $json   = json_decode($this->f3->get('BODY'), true);
        $status = $json['status'] ?? '';

        $allowed = ['active', 'review', 'done'];
        if (!in_array($status, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Statut invalide']);
            return;
        }

        $rows = $this->db->exec(
            'UPDATE projects SET status=? WHERE id=? AND user_id=?',
            [$status, $pid, $user['id']]
        );

        echo json_encode(['success' => true]);
    }

    // ─── Tag helpers ──────────────────────────────────────────────────────────

    private function saveProjectTags(int $projectId, int $userId, string $rawTags): void
    {
        // Parse comma-separated tag names, normalize
        $names = array_unique(array_filter(array_map(
            fn($t) => mb_substr(trim($t), 0, 64),
            explode(',', $rawTags)
        )));

        // Remove existing links for this project
        $this->db->exec('DELETE FROM project_tag_links WHERE project_id = ?', [$projectId]);

        foreach ($names as $name) {
            if ($name === '') continue;

            // Upsert tag for this user
            $this->db->exec(
                'INSERT INTO project_tags (user_id, name) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                [$userId, $name]
            );
            $tagId = (int) $this->db->exec('SELECT LAST_INSERT_ID() AS id')[0]['id'];

            if ($tagId > 0) {
                $this->db->exec(
                    'INSERT IGNORE INTO project_tag_links (project_id, tag_id) VALUES (?, ?)',
                    [$projectId, $tagId]
                );
            }
        }
    }

    private function getProjectTags(int $projectId): array
    {
        return $this->db->exec(
            'SELECT pt.name FROM project_tags pt
             JOIN project_tag_links ptl ON ptl.tag_id = pt.id
             WHERE ptl.project_id = ?
             ORDER BY pt.name ASC',
            [$projectId]
        ) ?: [];
    }

}
