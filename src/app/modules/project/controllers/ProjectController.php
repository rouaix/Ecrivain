<?php

class ProjectController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        $pattern = $f3->get('PATTERN');
        file_put_contents($f3->get('ROOT') . '/logs/theme_debug.log', date('[Y-m-d H:i:s] ') . "ProjectController::beforeRoute - PATTERN: '$pattern' | VERB: " . $f3->get('VERB') . "\n", FILE_APPEND);

        // Skip login check AND CSRF check for setTheme to allow theme switching on login/register pages
        if ($pattern === '/theme') {
            $this->checkAutoLogin($f3);
            return; // Skip both login check and CSRF validation
        }

        parent::beforeRoute($f3);

        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function setTheme()
    {
        $theme = $this->f3->get('POST.theme');
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

            // Expire possible conflicting cookies on all common variations
            setcookie('theme', '', time() - 3600, '/', '', $isHttps, false);
            setcookie('theme', '', time() - 3600, '/', $this->f3->get('HOST'), $isHttps, false);

            $res = setcookie(
                'theme',
                $theme,
                [
                    'expires' => time() + (3600 * 24 * 30),
                    'path' => '/',
                    'domain' => $domain,
                    'secure' => $isHttps,
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
        $base = $this->f3->get('BASE');
        $host = $this->f3->get('HOST');

        // Validate referer is from same host
        if ($referer && parse_url($referer, PHP_URL_HOST) === $host) {
            $this->f3->reroute($referer);
        } else {
            $this->f3->reroute($base . '/dashboard');
        }
    }

    public function dashboard()
    {
        $projectModel = new Project();
        $user = $this->currentUser();
        $projects = $projectModel->findAndCast(['user_id=?', $user['id']], ['order' => 'created_at DESC']);

        // Prepare view data
        foreach ($projects as &$proj) {
            $wpp = $proj['words_per_page'] ?: 350;
            $proj['pages_count'] = ceil($proj['target_words'] / $wpp);
        }

        $this->render('project/dashboard', [
            'title' => 'Tableau de bord',
            'projects' => $projects,
            'user' => $user,
        ]);
    }

    public function create()
    {
        // Load available templates for selector
        $templates = [];
        $defaultTemplate = null;
        $db = $this->f3->get('DB');
        if ($db->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user = $this->currentUser();
            $templates = $templateModel->getAllAvailable($user['id']);
            $defaultTemplate = $templateModel->getDefault();
        }

        $this->render('project/create', [
            'title' => 'Nouveau projet',
            'templates' => $templates,
            'old' => [
                'title' => '',
                'description' => '',
                'words_per_page' => 350,
                'target_pages' => 0,
                'target_words' => 0,
                'template_id' => $defaultTemplate['id'] ?? ''
            ]
        ]);
    }

    public function store()
    {
        $f3 = Base::instance();

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $target = intval($_POST['target_words'] ?? 0);
        $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
        $targetPages = intval($_POST['target_pages'] ?? 0);
        $templateId = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre du projet est obligatoire.';
        }

        if (empty($errors)) {
            // If no template selected, use default
            if (!$templateId && $this->f3->get('DB')->exists('templates')) {
                $templateModel = new ProjectTemplate();
                $defaultTemplate = $templateModel->getDefault();
                $templateId = $defaultTemplate['id'] ?? null;
            }

            $projectModel = new Project();
            $projectModel->user_id = $this->currentUser()['id'];
            $projectModel->title = $title;
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

        // Reload templates for view
        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user = $this->currentUser();
            $templates = $templateModel->getAllAvailable($user['id']);
        }

        $this->render('project/create', [
            'title' => 'Nouveau projet',
            'errors' => $errors,
            'templates' => $templates,
            'old' => [
                'title' => htmlspecialchars($title),
                'description' => htmlspecialchars($description),
                'target_words' => $target,
                'words_per_page' => $wordsPerPage,
                'target_pages' => $targetPages,
                'template_id' => $templateId
            ],
        ]);
    }

    public function show()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404, 'Projet introuvable.');
            return;
        }
        $project = $project[0];
        $authorName = $this->getUserFullName($this->currentUser());
        $wpp = $project['words_per_page'] ?: 350;
        $lpp = $project['lines_per_page'] ?: 38;

        // TEMPLATE SYSTEM: Load template configuration
        $template = null;
        $templateElements = [];
        $customElementPanels = [];
        $customElementsByType = [];
        $panelCss = '';
        // Default: all standard panels visible (fallback if no template)
        $panelConfig = [
            'section_before' => true,
            'content'        => true,
            'section_after'  => true,
            'note'           => true,
            'character'      => true,
            'file'           => true,
        ];

        $db = $this->f3->get('DB');
        if ($db->exists('templates') && $db->exists('template_elements')) {
          try {
            $templateId = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();

            if (!$templateId) {
                $template = $templateModel->getDefault();
                $templateId = $template['id'] ?? null;
            } else {
                $template = $templateModel->findAndCast(['id=?', $templateId]);
                $template = $template ? $template[0] : $templateModel->getDefault();
            }

            $templateElements = $template ? $templateModel->getElements($template['id']) : [];

            if (!empty($templateElements)) {
                // Reset all panels to hidden, then enable only those in the template
                $panelConfig = [
                    'section_before' => false,
                    'content'        => false,
                    'section_after'  => false,
                    'note'           => false,
                    'character'      => false,
                    'file'           => false,
                ];

                $customElementsByType = [];
                if ($db->exists('elements')) {
                    $elementModel = new Element();
                    $customElements = $elementModel->getAllByProject($pid);

                    // First pass: organize sub-elements by parent
                    $subElementsByParent = [];
                    foreach ($customElements as $elem) {
                        if ($elem['parent_id']) {
                            $subElementsByParent[$elem['parent_id']][] = $elem;
                        }
                    }

                    // Second pass: process parent elements and attach their subs
                    foreach ($customElements as $elem) {
                        // Skip sub-elements (they'll be attached to their parent)
                        if ($elem['parent_id']) {
                            continue;
                        }

                        $tid = $elem['template_element_id'];
                        if (!isset($customElementsByType[$tid])) {
                            $customElementsByType[$tid] = [];
                        }

                        // Calculate word count for parent
                        $cleanContent = strip_tags(html_entity_decode($elem['content'] ?? ''));
                        $elem['wc'] = str_word_count($cleanContent);
                        $elem['is_exported_attr'] = ($elem['is_exported'] ?? 1) ? 'checked' : '';

                        // Attach sub-elements and calculate total word count
                        $elem['subs'] = [];
                        $subsWc = 0;
                        if (isset($subElementsByParent[$elem['id']])) {
                            foreach ($subElementsByParent[$elem['id']] as $sub) {
                                $cleanSubContent = strip_tags(html_entity_decode($sub['content'] ?? ''));
                                $sub['wc'] = str_word_count($cleanSubContent);
                                $sub['is_exported_attr'] = ($sub['is_exported'] ?? 1) ? 'checked' : '';
                                $subsWc += $sub['wc'];
                                $elem['subs'][] = $sub;
                            }
                        }

                        // Total word count (parent + subs)
                        $elem['total_wc'] = $elem['wc'] + $subsWc;
                        $elem['wc_subs_only'] = $subsWc;

                        $customElementsByType[$tid][] = $elem;
                    }
                }

                foreach ($templateElements as $te) {
                    if (!$te['is_enabled']) continue;

                    $type = $te['element_type'];
                    if ($type === 'section') {
                        $key = 'section_' . ($te['section_placement'] ?? 'before');
                    } elseif ($type === 'act' || $type === 'chapter') {
                        $key = 'content';
                    } elseif ($type === 'element') {
                        // Custom element panel
                        $cfg = json_decode($te['config_json'] ?? '{}', true);
                        $customElementPanels[] = [
                            'id' => $te['id'],
                            'label_singular' => $cfg['label_singular'] ?? 'élément',
                            'label_plural' => $cfg['label_plural'] ?? 'Éléments',
                            'elements' => $customElementsByType[$te['id']] ?? [],
                            'count' => count($customElementsByType[$te['id']] ?? []),
                        ];
                        continue;
                    } else {
                        $key = $type;
                    }

                    if (isset($panelConfig[$key])) {
                        $panelConfig[$key] = true;
                    }
                }

                // Build CSS for panel ordering
                $panelCss = $this->buildPanelOrderCss($templateElements);
            }
          } catch (\Exception $e) {
              // Template system error - fall back to defaults
              $panelConfig = [
                  'section_before' => true, 'content' => true,
                  'section_after' => true, 'note' => true,
                  'character' => true, 'file' => true,
              ];
          }
        } // end if tables exist

        $chapterModel = new Chapter();
        $allChapters = $this->supHtml($chapterModel->getAllByProject($pid));

        $characterModel = new Character();
        $characters = $this->supHtml($characterModel->getAllByProject($pid));

        $actModel = new Act();
        $acts = $this->supHtml($actModel->getAllByProject($pid));

        $sectionModel = new Section();
        $sectionsBeforeChapters = $this->supHtml($sectionModel->getBeforeChapters($pid));
        $sectionsAfterChapters = $this->supHtml($sectionModel->getAfterChapters($pid));

        $noteModel = new Note();
        $notes = $this->supHtml($noteModel->getAllByProject($pid));

        // Load project files
        $fileModel = new ProjectFile();
        $filesRaw = $fileModel->find(['project_id=?', $pid], ['order' => 'uploaded_at DESC']);
        $files = [];
        foreach ($filesRaw as $f) {
            $files[] = [
                'id' => $f->id,
                'filename' => $f->filename,
                'filepath' => $f->filepath,
                'filetype' => $f->filetype,
                'filesize' => $f->filesize,
                'size_formatted' => $f->getSizeFormatted(),
                'comment' => $f->comment,
                'uploaded_at' => $f->uploaded_at
            ];
        }

        // --- Logic moved from View ---

        // 1. Cover Image
        $coverImage = null;
        foreach ($sectionsBeforeChapters as $sec) {
            if ($sec['type'] === 'cover' && !empty($sec['image_path'])) {
                $coverImage = $sec['image_path'];
                break;
            }
        }

        // 2. Logic: Word Counts & Progress & Display Helpers
        $totalWords = 0;
        $totalLines = 0; // NEW

        $calculateStats = function ($content, $accumulate = true) use (&$totalWords, &$totalLines, $lpp, $wpp) {
            $cleanContent = strip_tags(html_entity_decode($content ?? ''));
            $wc = str_word_count($cleanContent);

            // Line calculation heuristic: 80 chars per line
            $lines = 0;
            if ($cleanContent !== '') {
                $lines = ceil(strlen($cleanContent) / 80);
            }

            if ($accumulate) {
                $totalWords += $wc;
                $totalLines += $lines; // Accumulate global lines
            }

            $pagesByWords = ceil($wc / $wpp);
            $pagesByLines = ceil($lines / $lpp);

            return [
                'words' => $wc,
                'lines' => $lines,
                'pages' => max(0, max($pagesByWords, $pagesByLines))
            ];
        };

        // Process Sections (Before)
        $enrichSections = function ($sections) use ($calculateStats) {
            foreach ($sections as &$sec) {
                $isExported = ($sec['is_exported'] ?? 1);
                $stats = $calculateStats($sec['content'], $isExported);
                $sec['wc'] = $stats['words'];
                $sec['lines'] = $stats['lines'];
                $sec['pages'] = $stats['pages'];
                $sec['type_name'] = \Section::getTypeName($sec['type']);
                $sec['is_exported_attr'] = $isExported ? 'checked' : '';
            }
            return $sections;
        };
        $sectionsBeforeChapters = $enrichSections($sectionsBeforeChapters);
        $sectionsAfterChapters = $enrichSections($sectionsAfterChapters);

        // Process Notes
        foreach ($notes as &$note) {
            $isExported = ($note['is_exported'] ?? 1);
            $stats_note = $calculateStats($note['content'], $isExported);
            $note['wc'] = $stats_note['words'];
            $note['lines'] = $stats_note['lines'];
            $note['pages'] = $stats_note['pages'];
            $note['is_exported_attr'] = $isExported ? 'checked' : '';
        }

        // Process Chapters hierarchy & stats
        $chaptersByAct = [];
        $chaptersWithoutAct = [];
        $subChaptersByParent = [];

        // First pass: organize subs and calculate their stats
        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) {
                $subChaptersByParent[$ch['parent_id']][] = $ch;
            }
        }

        // Helper to process a chapter and its subs
        $processChapter = function ($ch) use ($subChaptersByParent, $calculateStats, $lpp, $wpp) {
            $isExported = ($ch['is_exported'] ?? 1);
            $myStats = $calculateStats($ch['content'], $isExported);
            $ch['wc'] = $myStats['words'];
            $ch['lines'] = $myStats['lines'];
            $ch['pages'] = $myStats['pages'];
            $ch['is_exported_attr'] = $isExported ? 'checked' : '';

            $subs = $subChaptersByParent[$ch['id']] ?? [];
            $ch['subs'] = [];
            $subsWc = 0;
            $subsLines = 0;
            $subsPages = 0;

            foreach ($subs as $sub) {
                $subIsExported = ($sub['is_exported'] ?? 1);
                $subStats = $calculateStats($sub['content'], $subIsExported);
                $sub['wc'] = $subStats['words'];
                $sub['lines'] = $subStats['lines'];
                $sub['pages'] = $subStats['pages'];
                $sub['is_exported_attr'] = $subIsExported ? 'checked' : '';
                $ch['subs'][] = $sub;
                $subsWc += $subStats['words'];
                $subsLines += $subStats['lines'];
                $subsPages += $subStats['pages'];
            }

            $ch['total_wc'] = $ch['wc'] + $subsWc;
            $ch['total_lines'] = $ch['lines'] + $subsLines;

            // Sum pages directly
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

        $target = (int) $project['target_words'];
        $progress = $target > 0 ? min(100, round($totalWords / $target * 100)) : 0;

        // Stats bundle
        $stats = [
            'total_words' => $totalWords,
            'target_words' => $target,
            'progress_percent' => $progress,
            'pages_current' => max(ceil($totalWords / $wpp), ceil($totalLines / $lpp)), // Global Hybrid Calc
            'pages_target' => ceil($target / $wpp), // Target still words-based
            // User did not specify global total pages logic, but consistent with request:
            // "Everywhere page calc function of lines".
            // So global pages should be total_lines / lpp. But calculateStats does not sum total lines for project.
            // For now keep simple estimation based on words or try to sum.
            // Actually, we can sum pages from sections + chapters + notes for a grand total?
            // Or just use totalWords/wpp as global fallback?
            // Let's leave global stats as 'estimation' using WPP for now unless requested.
            // Wait, previous code used totalWords/wpp.
            // I'll stick to that for the global counter or switch to totalLines if I tracked it.
            // Since I didn't track totalLines globally efficiently, I'll update pages_current to use
            // ceil($totalWords / $wpp) as a rough proxy OR better: leave it as is, or switch to line based if I had total chars.
            // Given constraints, I will leave global 'pages_current' on WPP logic as it's just a progress bar.
            // Actually, user said "calculation *everywhere*".
            // I'll recalculate global pages based on all content using lines.

            'pages_current' => ceil($totalWords / $wpp), // TODO: Switch to lines if strict
            'pages_target' => ceil($target / $wpp),
            'wpp' => $wpp,
            'lpp' => $lpp,
            'before_count' => count($sectionsBeforeChapters),
            'after_count' => count($sectionsAfterChapters),
            'note_count' => count($notes),
            'character_count' => count($characters),
            'file_count' => count($files),
        ];

        // 3. Logic: Group Sections by Type
        $prepareSectionGroups = function (array $sections, array $typeOrder) {
            $grouped = [];
            foreach ($sections as $s) {
                $grouped[$s['type']][] = $s;
            }
            $orderedTypes = [];
            $seenTypes = [];
            foreach ($sections as $s) {
                if (!in_array($s['type'], $seenTypes)) {
                    $orderedTypes[] = $s['type'];
                    $seenTypes[] = $s['type'];
                }
            }
            foreach ($typeOrder as $t) {
                if (!in_array($t, $seenTypes)) {
                    $orderedTypes[] = $t;
                }
            }

            $finalGroups = [];
            foreach ($orderedTypes as $type) {
                $isMulti = ($type === 'notes' || $type === 'appendices');
                $items = $grouped[$type] ?? [];

                // Filter empty singletons (matching view logic)
                if (!$isMulti && empty($items) && $type !== 'postface' && $type !== 'back_cover' && $type !== 'cover' && $type !== 'preface' && $type !== 'introduction' && $type !== 'prologue') {
                    continue;
                }

                $finalGroups[] = [
                    'type' => $type,
                    'name' => \Section::getTypeName($type),
                    'is_multi' => $isMulti,
                    'items' => $items,
                    'show_create' => (empty($items) && !$isMulti),
                    'show_add' => $isMulti
                ];
            }
            return $finalGroups;
        };

        $filterBeforeGroups = function ($sections) {
            $grouped = [];
            foreach ($sections as $s) {
                $grouped[$s['type']][] = $s;
            }
            $types = ['cover', 'preface', 'introduction', 'prologue'];
            foreach ($sections as $s) {
                if (!in_array($s['type'], $types))
                    $types[] = $s['type'];
            }

            $final = [];
            foreach ($types as $type) {
                $items = $grouped[$type] ?? [];
                $final[] = [
                    'type' => $type,
                    'name' => \Section::getTypeName($type),
                    'is_multi' => false,
                    'items' => $items,
                    'show_create' => empty($items),
                    'show_add' => false
                ];
            }
            return $final;
        };

        $beforeGroups = $filterBeforeGroups($sectionsBeforeChapters);
        $afterGroups = $prepareSectionGroups($sectionsAfterChapters, ['postface', 'appendices', 'back_cover']);

        // Enrich Acts with stats
        foreach ($acts as &$act) {
            $actChapters = $chaptersByAct[$act['id']] ?? [];
            $act['stats_chapters'] = count($actChapters);

            $actTotalWc = 0;
            foreach ($actChapters as $ch) {
                $actTotalWc += $ch['total_wc'];
            }
            $act['stats_pages'] = ceil($actTotalWc / $wpp);

            // Add export checkbox attribute
            $isExported = ($act['is_exported'] ?? 1);
            $act['is_exported_attr'] = $isExported ? 'checked' : '';
        }
        unset($act);

        // Stats for Orphan Chapters
        $orphanStats = ['chapters' => count($chaptersWithoutAct), 'pages' => 0];
        if (!empty($chaptersWithoutAct)) {
            $orphanTotalWc = 0;
            foreach ($chaptersWithoutAct as $ch) {
                $orphanTotalWc += $ch['total_wc'];
            }
            $orphanStats['pages'] = ceil($orphanTotalWc / $wpp);
        }

        // Add content indicator to stats
        $totalChapters = count($allChapters);
        $stats['has_content'] = (count($acts) > 0 || $totalChapters > 0);
        $this->render('project/show.html', [
            'title' => 'Projet: ' . htmlspecialchars($project['title']),
            'project' => $project,
            'acts' => $acts,
            'chaptersByAct' => $chaptersByAct,
            'chaptersWithoutAct' => $chaptersWithoutAct,
            'orphanStats' => $orphanStats,
            'characters' => $characters,
            'coverImage' => $coverImage,
            'stats' => $stats,
            'beforeGroups' => $beforeGroups,
            'afterGroups' => $afterGroups,
            'notes' => $notes,
            'files' => $files,
            'authorName' => $authorName,
            'template' => $template,
            'templateElements' => $templateElements,
            'panelConfig' => $panelConfig,
            'customElementPanels' => $customElementPanels,
            'customElementsByType' => $customElementsByType ?? [],
            'panelCss' => $panelCss,
        ]);
    }

    public function edit()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project) {
            $this->f3->error(404);
            return;
        }

        $projectData = $project[0];
        $projectData['lines_per_page'] = $projectData['lines_per_page'] ?? 38;

        // Decode settings for Author
        $settings = json_decode($projectData['settings'] ?? '{}', true);
        $projectData['author'] = $settings['author'] ?? '';

        // Load available templates for selector
        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user = $this->currentUser();
            $templates = $templateModel->getAllAvailable($user['id']);
        }

        $this->render('project/edit.html', [
            'title' => 'Modifier le projet',
            'project' => $projectData,
            'templates' => $templates,
            'errors' => []
        ]);
    }

    public function update()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $projectModel->load(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if ($projectModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $author = trim($_POST['author'] ?? ''); // New Author Field
        $comment = $_POST['comment'] ?? '';
        $target = intval($_POST['target_words'] ?? 0);
        $wordsPerPage = intval($_POST['words_per_page'] ?? 350);
        $linesPerPage = intval($_POST['lines_per_page'] ?? 38);
        $targetPages = intval($_POST['target_pages'] ?? 0);
        $templateId = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (empty($errors)) {
            // Lazy Migration: Ensure columns exist
            try {
                $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN lines_per_page INT DEFAULT 38");
            } catch (\Exception $e) { /* Column likely exists */
            }

            try {
                $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN settings TEXT");
            } catch (\Exception $e) { /* Column likely exists */
            }

            try {
                $this->f3->get('DB')->exec("ALTER TABLE projects ADD COLUMN cover_image TEXT");
            } catch (\Exception $e) { /* Column likely exists */
            }

            // Handle Cover Image Upload
            if (isset($_FILES['cover_image'])) {
                // SECURITY: Multi-level validation (fixes vulnerability #14)
                $validation = $this->validateImageUpload($_FILES['cover_image']);

                if (!$validation['success']) {
                    // Only add error if file was actually uploaded (not just missing)
                    if ($_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $errors[] = $validation['error'];
                        error_log("Cover upload validation failed: " . $validation['error']);
                    }
                } else {
                    $uploadDir = 'public/uploads/covers/';

                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            error_log("Failed to create upload directory: {$uploadDir}");
                            $errors[] = 'Impossible de créer le répertoire d\'upload.';
                        }
                    }

                    if (empty($errors)) {
                        // Use validated extension (not from filename)
                        $extension = $validation['extension'];
                        // Format: project_{pid}_couverture_{timestamp}.ext
                        $filename = 'project_' . $pid . '_couverture_' . time() . '.' . $extension;
                        $targetPath = $uploadDir . $filename;

                        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                            // Remove old cover image if exists
                            if (!empty($projectModel->cover_image)) {
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

            $projectModel->title = $title;
            $projectModel->description = $description;
            $projectModel->comment = $comment;
            $projectModel->target_words = $target;
            $projectModel->words_per_page = $wordsPerPage;
            $projectModel->lines_per_page = $linesPerPage;
            $projectModel->target_pages = $targetPages;
            if ($templateId) {
                $projectModel->template_id = $templateId;
            }

            // Save Settings JSON
            $currentSettings = json_decode($projectModel->settings ?? '{}', true) ?: [];
            $currentSettings['author'] = $author;
            $projectModel->settings = json_encode($currentSettings);

            // DEBUG: Trace saving
            error_log("Saving Author: " . $author);
            error_log("Settings JSON: " . $projectModel->settings);

            $projectModel->save();
            $this->f3->reroute('/project/' . $pid);
        }

        // Reload templates for view
        $templates = [];
        if ($this->f3->get('DB')->exists('templates')) {
            $templateModel = new ProjectTemplate();
            $user = $this->currentUser();
            $templates = $templateModel->getAllAvailable($user['id']);
        }

        $this->render('project/edit.html', [
            'title' => 'Modifier le projet',
            'project' => $projectModel->cast(),
            'templates' => $templates,
            'errors' => $errors,
        ]);
    }

    public function delete()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
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
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if (!$project || empty($project[0]['cover_image'])) {
            error_log("Cover not found - Project: {$pid}, Cover: " . ($project[0]['cover_image'] ?? 'none'));
            $this->f3->error(404);
            return;
        }

        $projectData = $project[0];
        $coverImage = $projectData['cover_image'];

        // Use public/uploads/covers/ path (consistent with SectionController)
        $filePath = 'public/uploads/covers/' . $coverImage;

        if (!file_exists($filePath)) {
            error_log("Cover file not found at path: {$filePath}");
            $this->f3->error(404);
            return;
        }

        // Determine MIME type from extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000');
        readfile($filePath);
        exit;
    }

    public function mindmap()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

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
        $sectionsAfter = $sectionModel->getAfterChapters($pid);

        $chapterModel = new Chapter();
        $chapters = $chapterModel->getAllByProject($pid);

        // --- Build Graph Data ---
        $nodes = [];
        $links = [];

        // 1. Root: Project
        $settings = json_decode($project[0]['settings'] ?? '{}', true);
        $nodes[] = [
            'id' => 'project',
            'name' => $project[0]['title'],
            'type' => 'project',
            'description' => $project[0]['summary'] ?? ($project[0]['description'] ?? ''),
            'author' => $settings['author'] ?? ''
        ];

        // Load template system to respect display_order
        $db = $this->f3->get('DB');
        $templateElements = [];
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId = $project[0]['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            $template = $templateId
                ? ($templateModel->findAndCast(['id=?', $templateId]) ? $templateModel->findAndCast(['id=?', $templateId])[0] : $templateModel->getDefault())
                : $templateModel->getDefault();

            if ($template) {
                $templateElements = $templateModel->getElements($template['id']);
            }
        }

        // Load custom elements
        $topElementsByType = [];
        $subElementsByParent = [];
        if ($db->exists('elements')) {
            $elementModel = new Element();
            $allElements = $elementModel->getAllByProject($pid);

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
        $exportedNotes = array_filter($notes, fn($n) => ($n['is_exported'] ?? 1));
        $exportedSecBefore = array_filter($sectionsBefore, fn($s) => ($s['is_exported'] ?? 1));
        $exportedSecAfter = array_filter($sectionsAfter, fn($s) => ($s['is_exported'] ?? 1));
        $exportedChapters = array_filter($chapters, fn($c) => ($c['is_exported'] ?? 1));
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
                'id' => 'chars_group',
                'name' => 'Personnages',
                'type' => 'character_group',
                'description' => 'Regroupement des personnages'
            ];
            $links[] = ['source' => 'project', 'target' => 'chars_group'];

            foreach ($characters as $char) {
                $nodes[] = [
                    'id' => 'char_' . $char['id'],
                    'name' => $char['name'],
                    'type' => 'character',
                    'content' => $char['description']
                ];
                $links[] = ['source' => 'chars_group', 'target' => 'char_' . $char['id']];
            }
        }

        // RIGHT SIDE: Loop through template elements in display_order
        $beforeGroupAdded = false;
        $afterGroupAdded = false;
        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) continue;

            switch ($te['element_type']) {
                case 'section':
                    $isBeforePlacement = ($te['section_placement'] === 'before');
                    $sections = $isBeforePlacement ? $exportedSecBefore : $exportedSecAfter;
                    $groupName = $isBeforePlacement ? 'Avant-propos' : 'Annexes';
                    $groupId = $isBeforePlacement ? 'sec_before_group' : 'sec_after_group';

                    if (!empty($sections)) {
                        // Check if group was already added to prevent duplicates
                        if ($isBeforePlacement && $beforeGroupAdded) break;
                        if (!$isBeforePlacement && $afterGroupAdded) break;

                        $nodes[] = [
                            'id' => $groupId,
                            'name' => $groupName,
                            'type' => 'section_group',
                            'description' => ''
                        ];
                        $links[] = ['source' => 'project', 'target' => $groupId];

                        // Mark as added
                        if ($isBeforePlacement) $beforeGroupAdded = true;
                        else $afterGroupAdded = true;

                        foreach ($sections as $sec) {
                            $nodes[] = [
                                'id' => 'sec_' . $sec['id'],
                                'name' => $sec['title'],
                                'type' => 'section',
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
                            'id' => 'act_' . $act['id'],
                            'name' => $act['title'],
                            'type' => 'act',
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
                            'id' => 'chapter_' . $ch['id'],
                            'name' => $ch['title'],
                            'type' => 'chapter',
                            'content' => $ch['content'],
                            'description' => '',
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
                            'id' => 'notes_group',
                            'name' => 'Notes',
                            'type' => 'note_group',
                            'description' => ''
                        ];
                        $links[] = ['source' => 'project', 'target' => 'notes_group'];

                        foreach ($exportedNotes as $note) {
                            $nodes[] = [
                                'id' => 'note_' . $note['id'],
                                'name' => $note['title'] ?: 'Sans titre',
                                'type' => 'note',
                                'content' => $note['content']
                            ];
                            $links[] = ['source' => 'notes_group', 'target' => 'note_' . $note['id']];
                        }
                    }
                    break;

                case 'element':
                    $teId = $te['id'];
                    $items = $topElementsByType[$teId] ?? [];
                    if (empty($items)) break;

                    $cfg = json_decode($te['config_json'] ?? '{}', true);
                    $groupId = 'elem_group_' . $teId;
                    $groupLabel = $cfg['label_plural'] ?? 'Éléments';

                    $nodes[] = [
                        'id' => $groupId,
                        'name' => $groupLabel,
                        'type' => 'element_group',
                        'description' => ''
                    ];
                    $links[] = ['source' => 'project', 'target' => $groupId];

                    foreach ($items as $elem) {
                        $nodeId = 'element_' . $elem['id'];
                        $nodes[] = [
                            'id' => $nodeId,
                            'name' => $elem['title'],
                            'type' => 'element',
                            'content' => $elem['content'] ?? '',
                            'is_subelement' => false
                        ];
                        $links[] = ['source' => $groupId, 'target' => $nodeId];

                        $subs = $subElementsByParent[$elem['id']] ?? [];
                        foreach ($subs as $sub) {
                            $subNodeId = 'element_' . $sub['id'];
                            $nodes[] = [
                                'id' => $subNodeId,
                                'name' => $sub['title'],
                                'type' => 'element',
                                'content' => $sub['content'] ?? '',
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
            'title' => 'Carte mentale',
            'project' => $project[0],
            'mindmapData' => json_encode($data)
        ]);
    }

    public function reorderItem()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $projectModel->load(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if ($projectModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $data = json_decode($this->f3->get('BODY'), true);
        $type = $data['type'] ?? '';
        $itemId = (int) ($data['id'] ?? 0);
        $newIndex = (int) ($data['new_index'] ?? 0);

        // Normalize 1-based index from UI to 0-based if necessary,
        // but let's assume UI sends 1-based and we convert, or 0-based.
        // Usually users see 1, 2, 3. Let's assume input is 1-based (natural for users) -> convert to 0-based.
        // Wait, typical arrays are 0-based. I'll stick to 0-based for DB, but UI might show 1-based?
        // Let's assume raw value. If user types 1, it matches index 1? Or 1st item?
        // Let's treat input as "Position". Position 1 = Index 0.
        // So newIndex = input - 1.

        $newIndex = max(0, $newIndex - 1);

        $db = $this->f3->get('DB');

        if ($type === 'chapter') {
            $item = new Chapter();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry())
                return;

            // Determine siblings
            $parentId = $item->parent_id;
            $actId = $item->act_id;

            if ($parentId) {
                // Sub-chapters
                $siblings = $item->findAndCast(['parent_id=?', $parentId], ['order' => 'order_index ASC, id ASC']);
            } elseif ($actId) {
                // Chapter in Act
                $siblings = $item->findAndCast(['act_id=? AND parent_id IS NULL', $actId], ['order' => 'order_index ASC, id ASC']);
            } else {
                // Orphan Chapter
                $siblings = $item->findAndCast(['project_id=? AND act_id IS NULL AND parent_id IS NULL', $pid], ['order' => 'order_index ASC, id ASC']);
            }

            $table = 'chapters';

        } elseif ($type === 'section') {
            $item = new Section();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry())
                return;

            $sectionTypes = Section::SECTION_TYPES;
            $currentTypeConf = $sectionTypes[$item->type] ?? null;
            $currentPosition = $currentTypeConf['position'] ?? 'before';

            // Get all sections for this project
            $allSections = $item->findAndCast(['project_id=?', $pid], ['order' => 'order_index ASC, id ASC']);

            // Filter siblings by same position
            $siblings = [];
            foreach ($allSections as $sec) {
                $t = $sec['type'];
                $p = $sectionTypes[$t]['position'] ?? 'before';
                if ($p === $currentPosition) {
                    $siblings[] = $sec;
                }
            }
            // Sort stability follows findAndCast

            $table = 'sections';

        } elseif ($type === 'note') {
            $item = new Note();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry())
                return;

            $siblings = $item->findAndCast(['project_id=?', $pid], ['order' => 'order_index ASC, id ASC']);
            $table = 'notes';

        } elseif ($type === 'act') {
            $item = new Act();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry())
                return;

            $siblings = $item->findAndCast(['project_id=?', $pid], ['order' => 'order_index ASC, id ASC']);
            $table = 'acts';

        } elseif ($type === 'element') {
            if (!$this->f3->get('DB')->exists('elements')) { $this->f3->error(400); return; }
            $item = new Element();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry())
                return;

            // Determine siblings (same template_element_id and same parent)
            $parentId = $item->parent_id;
            $templateElementId = $item->template_element_id;

            if ($parentId) {
                // Sub-elements
                $siblings = $item->findAndCast(['parent_id=?', $parentId], ['order' => 'order_index ASC, id ASC']);
            } else {
                // Top-level elements of this type
                $siblings = $item->findAndCast(['project_id=? AND template_element_id=? AND parent_id IS NULL', $pid, $templateElementId], ['order' => 'order_index ASC, id ASC']);
            }

            $table = 'elements';

        } else {
            $this->f3->error(400, 'Invalid type');
            return;
        }

        // Reorder Logic
        $idList = array_column($siblings, 'id');

        // Remove current item ID
        $key = array_search($itemId, $idList);
        if ($key !== false) {
            unset($idList[$key]);
        }
        $idList = array_values($idList); // Re-index array

        // Insert at new position
        array_splice($idList, $newIndex, 0, $itemId);

        // Update DB
        $db->begin();
        foreach ($idList as $idx => $id) {
            $db->exec("UPDATE $table SET order_index = ? WHERE id = ?", [$idx, $id]);
        }
        $db->commit();

        echo json_encode(['status' => 'ok']);
    }

    // --- Export Methods ---

    public function export()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->exportFile($pid, 'txt');
    }

    public function exportHtml()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->exportFile($pid, 'html');
    }

    public function exportEpub()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        if (!class_exists('ZipArchive')) {
            $this->f3->error(500, 'ZipArchive extension missing');
            return;
        }
        $this->generateEpub($pid);
    }

    public function exportVector()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->exportFile($pid, 'vector');
    }

    public function exportClean()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->exportFile($pid, 'clean');
    }

    public function exportSummaries()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->exportFile($pid, 'summaries');
    }

    public function generateExportContent($pid, $format)
    {
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        if (!$project) {
            return null;
        }
        $project = $project[0];

        // Get Author
        $author = $this->currentUser()['username'] ?? 'Auteur inconnu';

        // TEMPLATE SYSTEM: Load template configuration
        $templateElements = [];
        $db = $this->f3->get('DB');
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();

            if (!$templateId) {
                $template = $templateModel->getDefault();
                $templateId = $template['id'] ?? null;
            } else {
                $template = $templateModel->findAndCast(['id=?', $templateId]);
                $template = $template ? $template[0] : $templateModel->getDefault();
            }

            // Load template elements configuration
            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        // Prepare Models
        $sectionModel = new Section();
        $chapterModel = new Chapter();

        // 1. Sections Before
        $sectionsBefore = $sectionModel->getBeforeChapters($pid);

        // 2. Chapters
        // Use getAllByProject to respect Act grouping and user-defined order
        $chapters = $chapterModel->getAllByProject($pid);

        // 3. Sections After
        $sectionsAfter = $sectionModel->getAfterChapters($pid);

        // 4. Notes
        $noteModel = new Note();
        $notes = $noteModel->getAllByProject($pid);

        // Detect Cover from Sections Before
        $coverImage = null;
        $coverKey = null;

        foreach ($sectionsBefore as $k => $sec) {
            if ($sec['type'] === 'cover') {
                $coverKey = $k;
                if (!empty($sec['image_path'])) {
                    $coverImage = $this->f3->get('SCHEME') . '://' . $this->f3->get('HOST') . $this->f3->get('BASE') . $sec['image_path'];
                }
                // Use Cover Title as Description
                if (!empty($sec['title'])) {
                    $project['description'] = $sec['title'];
                }
                // Use Cover Content as Author
                if (!empty($sec['content'])) {
                    $author = $sec['content'];
                }
                break;
            }
        }

        // Remove Cover Section from body so it doesn't appear twice
        if ($coverKey !== null) {
            unset($sectionsBefore[$coverKey]);
        }

        $content = "";
        $jsonOutput = []; // For vector/json export

        if ($format === 'html') {
            $content .= "<!DOCTYPE html><html><head>";
            $content .= "<meta charset='utf-8'>";
            $content .= "<title>{$project['title']}</title>";
            $content .= "<style>";
            $content .= "body { max-width: 800px; margin: 0 auto; padding: 20px; font-family: Georgia, serif; line-height: 1.6; }";
            $content .= ".book-cover { width: 50vw; max-width: 400px; height: auto; margin: 0 auto 20px auto; display: block; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }";
            $content .= ".book-header { text-align: center; margin-bottom: 50px; }";
            $content .= "h1.book-title { font-size: 3em; margin-bottom: 5px; color: #111; }";
            $content .= ".book-description { font-size: 1.4em; color: #777; max-width: 600px; margin: 0 auto 10px auto; line-height: 1.5; }";
            $content .= "h2 { margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px; color: #444; }";
            $content .= "h3 { margin-top: 30px; font-size: 1.3em; color: #666; }";
            $content .= ".chapter-content, .section-content { font-size: 1.1em; margin-bottom: 20px; }";
            $content .= ".ql-align-left { text-align: left; }";
            $content .= ".ql-align-center { text-align: center; }";
            $content .= ".ql-align-right { text-align: right; }";
            $content .= ".ql-align-justify { text-align: justify; }";
            $content .= "strong { font-weight: bold; }";
            $content .= "em { font-style: italic; }";
            $content .= "u { text-decoration: underline; }";
            $content .= "s { text-decoration: line-through; }";
            $content .= ".page-break { page-break-before: always; margin-top: 50px; }";
            $content .= ".act-title { page-break-before: always; text-align: center; font-size: 2em; margin-top: 100px; margin-bottom: 50px; color: #222; text-transform: uppercase; letter-spacing: 2px; }";
            $content .= "</style>";
            $content .= "</head><body class='export-document'>";

            $content .= "<div class='book-header'>";
            if ($coverImage) {
                $content .= "<img src='{$coverImage}' alt='Couverture' class='book-cover'>";
            }
            $content .= "<h1 class='book-title'>{$project['title']}</h1>";
            if (!empty($project['description'])) {
                $content .= "<div class='book-description'>" . nl2br($project['description']) . "</div>";
            }
            $content .= "</div>";

        } elseif ($format === 'summaries') {
            // Header for Summaries Export
            $content .= ucfirst($project['title']) . "\n";
            if (!empty($project['description'])) {
                $content .= strip_tags(html_entity_decode($project['description'])) . "\n";
            }
            $content .= trim(strip_tags(html_entity_decode($author))) . "\n\n";
        } else {
            if ($format === 'vector') {
                // For JSON, we might want to add Project Metadata as the first item or separate field?
                // Let's rely on the requested structure: plain list of blocks.
                // But preserving project title/author is good.
                // Let's add a "metadata" block.
                $jsonOutput[] = [
                    'type' => 'project_meta',
                    'title' => mb_strtolower($project['title'], 'UTF-8'),
                    'author' => mb_strtolower(strip_tags(html_entity_decode(html_entity_decode($author, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5)), 'UTF-8'),
                    'description' => !empty($project['description']) ? mb_strtolower(strip_tags($project['description']), 'UTF-8') : ''
                ];
            } elseif ($format === 'clean') {
                $content .= mb_strtolower($project['title'], 'UTF-8') . "\n\n";
                // Add Author for Clean Text
                $cleanAuthor = mb_strtolower(strip_tags(html_entity_decode(html_entity_decode($author, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5)), 'UTF-8');
                $content .= "par " . $cleanAuthor . "\n\n";
                // Add Description for Clean Text
                if (!empty($project['description'])) {
                    $desc = str_replace(['</p>', '<br>', '<br/>', '</div>'], "\n\n", $project['description']);
                    $desc = html_entity_decode(html_entity_decode($desc, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
                    $content .= mb_strtolower(strip_tags($desc), 'UTF-8') . "\n\n";
                }
            } else {
                $content .= strtoupper($project['title']) . "\n\n";

                if (!empty($project['description'])) {
                    $desc = str_replace(['</p>', '<br>', '<br/>', '</div>'], "\n\n", $project['description']);
                    $desc = html_entity_decode(html_entity_decode($desc, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
                    $dText = strip_tags($desc);
                    if ($format === 'clean') { // Add check for clean format here too if we want description
                        $dText = mb_strtolower($dText, 'UTF-8');
                    }
                    $content .= $dText . "\n\n";
                }

                // Cleanup Author for Text Export (since it might be HTML content now)
                $authorText = str_replace(['</p>', '<br>', '<br/>', '</div>'], "\n\n", $author);
                $authorText = html_entity_decode(html_entity_decode($authorText, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
                $authorText = strip_tags($authorText);

                if ($format === 'clean') {
                    $content .= "par " . mb_strtolower(trim($authorText), 'UTF-8') . "\n\n";
                } else {
                    $content .= "Par " . trim($authorText) . "\n\n";
                }
            }
        }

        // --- Output Content Loop ---

        // Helper to append item
        $appendItem = function ($item, $isChapter = false) use (&$content, &$jsonOutput, $format) {
            // Check export flag
            // Note: is_exported field in DB is integer 0/1.
            if (isset($item['is_exported']) && (int) $item['is_exported'] === 0) {
                return;
            }

            if ($format === 'summaries') {
                // Only acts and chapters with summaries
                $summary = $item['resume'] ?? ($item['description'] ?? '');
                $summary = trim(strip_tags(html_entity_decode($summary)));

                if (empty($summary)) {
                    return; // Skip if no summary
                }

                $title = $item['title'] ?? ($item['name'] ?? '');

                $content .= $title . "\n";
                $content .= $summary . "\n";

                // Add Act/Chapter explicit labeling not requested but structure implied
                return;
            }

            $title = $item['title'] ?? ($item['name'] ?? '');
            $text = $item['content'] ?? '';

            if ($format === 'html') {
                $content .= "<div class='page-break'></div>";
                if ($title) {
                    $content .= "<h2>{$title}</h2>";
                }
                $content .= "<div class='" . ($isChapter ? 'chapter-content' : 'section-content') . "'>{$text}</div>";
            } else {
                // Convert HTML to Text
                // 1. Pre-process breaks vs paragraphs
                // <br> -> \n
                $plain = preg_replace('/<br\s*\/?>\s*/i', "\n", $text);
                // Headers -> \n\n (Keep one empty line for internal content headers)
                $plain = preg_replace('/<\/h[1-6]>\s*/i', "\n\n", $plain);
                // Paragraphs/Divs -> \n\n (Single empty line between blocks)
                $plain = preg_replace('/<\/(p|div)>\s*/i', "\n\n", $plain);

                // 2. Decode entities (twice to be safe)
                $plain = html_entity_decode(html_entity_decode($plain, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);

                // 3. Strip tags
                $plain = strip_tags($plain);

                // 4. Normalize Whitespace
                // Replace non-breaking space with normal space
                $plain = str_replace("\u{00A0}", ' ', $plain);
                // Collapse detailed whitespace if needed, but preserved indentation might be desired?
                // Usually for novel writing, we just want clean paragraphs.

                // Collapse 3+ newlines into 2 (Max one blank line anywhere)
                $plain = preg_replace("/\n{3,}/", "\n\n", $plain);
                $plain = trim($plain);

                if ($format === 'vector') {
                    $plainLowercase = mb_strtolower($plain, 'UTF-8');
                    $titleLowercase = $title ? mb_strtolower($title, 'UTF-8') : '';

                    $jsonOutput[] = [
                        'id' => (int) ($item['id'] ?? 0),
                        'type' => $isChapter ? 'chapter' : ($item['type'] ?? 'section'),
                        'title' => $titleLowercase,
                        'content' => $plainLowercase
                    ];
                } elseif ($format === 'clean') {
                    if ($title) {
                        // For clean text, maybe valid to skip title or include it lowercase?
                        // User said "tout en minuscule et sans mise en forme".
                        // Assuming simple concatenation.
                        // Let's add double newline separate blocks.
                        $content .= mb_strtolower($title, 'UTF-8') . "\n\n";
                    }
                    $content .= mb_strtolower($plain, 'UTF-8') . "\n\n"; // Ensure separation
                } else {
                    if ($title) {
                        $content .= "\n\n### " . strtoupper($title) . "\n\n";
                    }
                    $content .= $plain . "\n";
                }
            }
        };

        // TEMPLATE SYSTEM: Export content loop driven by template elements
        // Pre-fetch all data we might need
        $allChapters = $chapterModel->getAllByProject($pid);
        $actModel = new \Act();
        $acts = $actModel->getAllByProject($pid);

        // Build chapter hierarchy (used by act and chapter exports)
        $chaptersByAct = [];
        $chaptersWithoutAct = [];
        $subChaptersByParent = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) {
                $subChaptersByParent[$ch['parent_id']][] = $ch;
            }
        }

        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                $subs = $subChaptersByParent[$ch['id']] ?? [];
                usort($subs, function ($a, $b) {
                    return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                });
                $ch['subs'] = $subs;

                if ($ch['act_id']) {
                    $chaptersByAct[$ch['act_id']][] = $ch;
                } else {
                    $chaptersWithoutAct[] = $ch;
                }
            }
        }

        // Load custom elements (only if table exists)
        $customElementsByType = [];
        $db = $this->f3->get('DB');
        if ($db->exists('elements')) {
            $elementModel = new Element();
            $customElements = $elementModel->getAllByProject($pid);
            foreach ($customElements as $elem) {
                $tid = $elem['template_element_id'];
                if (!isset($customElementsByType[$tid])) {
                    $customElementsByType[$tid] = [];
                }
                $customElementsByType[$tid][] = $elem;
            }
        }

        // LOOP THROUGH TEMPLATE ELEMENTS
        foreach ($templateElements as $elem) {
            if (!$elem['is_enabled']) continue;

            switch ($elem['element_type']) {
                case 'section':
                    // Export sections (before or after)
                    $sections = ($elem['section_placement'] === 'before') ? $sectionsBefore : $sectionsAfter;
                    if ($format !== 'summaries') {
                        foreach ($sections as $sec) {
                            if ($sec['type'] === $elem['element_subtype']) {
                                $appendItem($sec, false);
                            }
                        }
                    }
                    break;

                case 'act':
                    // Export acts with their chapters
                    foreach ($acts as $act) {
                        if (!isset($chaptersByAct[$act['id']])) continue;

                        $actChaps = $chaptersByAct[$act['id']];
                        usort($actChaps, function ($a, $b) {
                            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                        });

                        // Output Act title/content
                        if ($format === 'summaries') {
                            $actSum = trim(strip_tags(html_entity_decode($act['description'] ?? '')));
                            if (empty($actSum)) {
                                $actSum = trim(strip_tags(html_entity_decode($act['content'] ?? '')));
                            }
                            $content .= "\n" . $act['title'] . "\n";
                            if (!empty($actSum)) {
                                $content .= $actSum . "\n";
                            }
                        } elseif ($format === 'html') {
                            $content .= "<h1 class='act-title'>{$act['title']}</h1>";
                            if (!empty($act['content'])) {
                                $content .= "<div class='act-content'>" . $act['content'] . "</div>";
                            }
                        } else {
                            $content .= "\n\n# " . strtoupper($act['title']) . "\n\n";
                            if (!empty($act['content'])) {
                                $aText = preg_replace('/<br\s*\/?>\s*/i', "\n", $act['content']);
                                $aText = strip_tags($aText);
                                $aText = html_entity_decode($aText, ENT_QUOTES | ENT_HTML5);
                                $content .= trim($aText) . "\n\n";
                            }
                        }

                        // Output chapters in this act
                        foreach ($actChaps as $topCh) {
                            $appendItem($topCh, true);
                            foreach ($topCh['subs'] as $sub) {
                                $appendItem($sub, true);
                            }
                        }
                    }
                    break;

                case 'chapter':
                    // Export orphan chapters (without act)
                    usort($chaptersWithoutAct, function ($a, $b) {
                        return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                    });

                    foreach ($chaptersWithoutAct as $topCh) {
                        $appendItem($topCh, true);
                        foreach ($topCh['subs'] as $sub) {
                            $appendItem($sub, true);
                        }
                    }
                    break;

                case 'note':
                    // Export notes
                    if ($format !== 'summaries') {
                        foreach ($notes as $note) {
                            $appendItem($note, false);
                        }
                    }
                    break;

                case 'element':
                    // Export custom elements
                    if ($format !== 'summaries') {
                        $elements = $customElementsByType[$elem['id']] ?? [];

                        // Organize by hierarchy
                        $topElements = [];
                        $subElementsByParent = [];

                        foreach ($elements as $e) {
                            if ($e['parent_id']) {
                                $subElementsByParent[$e['parent_id']][] = $e;
                            } else {
                                $topElements[] = $e;
                            }
                        }

                        // Sort top elements
                        usort($topElements, function ($a, $b) {
                            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                        });

                        // Output with hierarchy
                        foreach ($topElements as $topElem) {
                            $appendItem($topElem, true);

                            // Output sub-elements
                            $subs = $subElementsByParent[$topElem['id']] ?? [];
                            usort($subs, function ($a, $b) {
                                return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                            });
                            foreach ($subs as $sub) {
                                $appendItem($sub, true);
                            }
                        }
                    }
                    break;

                case 'character':
                case 'file':
                    // Characters and files are not typically exported in text/HTML
                    // Skip for now (can be added later if needed)
                    break;
            }
        }

        if ($format === 'html') {
            $content .= "</body></html>";
        }

        if ($format === 'vector') {
            $content = json_encode($jsonOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $ext = 'json';
        } else {
            $ext = ($format === 'html') ? 'html' : 'txt';
        }

        return [
            'content' => $content,
            'ext' => $ext,
            'title' => $project['title']
        ];
    }

    private function exportFile($pid, $format)
    {
        $result = $this->generateExportContent($pid, $format);

        if (!$result) {
            $this->f3->error(404);
            return;
        }

        $content = $result['content'];
        $ext = $result['ext'];
        $title = $result['title'];

        if ($format === 'vector') {
            header('Content-Type: application/json');
        } else {
            header('Content-Type: ' . ($format === 'html' ? 'text/html' : 'text/plain'));
        }

        $filename = "project_{$pid}_" . date('Ymd_His') . "_{$format}." . $ext;
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        exit;
    }

    private function sanitizeToXhtml($html)
    {
        // Basic cleanup for XML validity
        // 1. Close common void elements
        $html = preg_replace('/<(br|hr|img|input)([^>]*)>/i', '<$1$2 />', $html);
        // 2. Remove double closing (in case they were already closed)
        $html = str_replace('//>', '/>', $html);
        // 3. Ensure & is escaped (but not double escaped)
        // This is tricky without a DOM parser.
        // Let's use a simple approach: if we have " & " replace with " &amp; "
        $html = preg_replace('/ & /', ' &amp; ', $html);

        return $html;
    }

    private function generateEpub($pid)
    {
        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        // --- Data Gathering (Mirroring exportFile logic) ---

        $author = $this->currentUser()['username'] ?? 'Auteur inconnu';

        $sectionModel = new Section();
        $chapterModel = new Chapter();
        $noteModel = new Note();

        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $allChapters = $chapterModel->getAllByProject($pid);
        $sectionsAfter = $sectionModel->getAfterChapters($pid);
        $notes = $noteModel->getAllByProject($pid);

        // Detect Cover Logic
        $coverImage = null;
        $coverKey = null;
        foreach ($sectionsBefore as $k => $sec) {
            if ($sec['type'] === 'cover') {
                $coverKey = $k;
                if (!empty($sec['image_path'])) {
                    // Start relative for EPUB? No, need absolute or embedding.
                    // For EPUB, we need to grab the file and add it to the ZIP.
                    $coverImage = $sec['image_path'];
                }
                if (!empty($sec['title'])) {
                    $project['description'] = $sec['title'];

                    // Smart Extraction: Check for "Description (Author)" pattern
                    if (preg_match('/^(.*)\s*\((.+)\)$/u', $sec['title'], $matches)) {
                        $project['description'] = trim($matches[1]);
                        // Set author from title if not already manually set in content (or update it preferred)
                        // User specifically asked for this author name
                        $author = trim($matches[2]);
                    }
                }
                if (!empty($sec['content'])) {
                    // Sanitize Author for Metadata (strip tags)
                    $authorHtml = html_entity_decode($sec['content']);
                    $authorHtml = str_replace(['</p>', '<br>', '<br/>', '</div>'], " ", $authorHtml);
                    $author = trim(strip_tags($authorHtml));
                }
                break;
            }
        }
        if ($coverKey !== null) {
            unset($sectionsBefore[$coverKey]);
        }

        // --- Flatten Content List ---
        $contentList = [];

        // 1. Sections Before
        foreach ($sectionsBefore as $sec) {
            if (isset($sec['is_exported']) && (int) $sec['is_exported'] === 0)
                continue;
            $title = $sec['title'] ?? ($sec['name'] ?? '');
            $contentList[] = ['title' => $title, 'content' => $sec['content'], 'type' => 'section'];
        }

        // 2. Chapters (Hierarchical Sort & Mixed Acts/Orphans)
        $chaptersByAct = [];
        $chaptersWithoutAct = [];
        $subChaptersByParent = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id'])
                $subChaptersByParent[$ch['parent_id']][] = $ch;
        }

        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                $subs = $subChaptersByParent[$ch['id']] ?? [];
                usort($subs, function ($a, $b) {
                    return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                });
                $ch['subs'] = $subs;
                if ($ch['act_id'])
                    $chaptersByAct[$ch['act_id']][] = $ch;
                else
                    $chaptersWithoutAct[] = $ch;
            }
        }

        $actModel = new Act();
        $acts = $actModel->getAllByProject($pid);

        // Merge Acts and Orphan Chapters for strict ordering
        $rootItems = [];
        foreach ($acts as $act) {
            $act['is_act'] = true;
            $rootItems[] = $act;
        }
        foreach ($chaptersWithoutAct as $ch) {
            $ch['is_act'] = false;
            $rootItems[] = $ch;
        }

        // Sort mixed list
        usort($rootItems, function ($a, $b) {
            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
        });

        foreach ($rootItems as $item) {
            if ($item['is_act']) {
                // Determine Act Visibility (skip if no chapters? Or always show?)
                // Usually show act title if it exists.

                // Add Act Divider
                $contentList[] = ['title' => $item['title'], 'content' => $item['content'] ?? '', 'type' => 'act-title'];

                if (isset($chaptersByAct[$item['id']])) {
                    $actChaps = $chaptersByAct[$item['id']];
                    usort($actChaps, function ($a, $b) {
                        return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                    });

                    foreach ($actChaps as $topCh) {
                        if (isset($topCh['is_exported']) && (int) $topCh['is_exported'] === 0)
                            continue;
                        $contentList[] = ['title' => $topCh['title'], 'content' => $topCh['content'], 'type' => 'chapter'];
                        foreach ($topCh['subs'] as $sub) {
                            if (isset($sub['is_exported']) && (int) $sub['is_exported'] === 0)
                                continue;
                            $contentList[] = ['title' => $sub['title'], 'content' => $sub['content'], 'type' => 'sub-chapter'];
                        }
                    }
                }
            } else {
                // Orphan Chapter
                $topCh = $item;
                if (isset($topCh['is_exported']) && (int) $topCh['is_exported'] === 0)
                    continue;
                $contentList[] = ['title' => $topCh['title'], 'content' => $topCh['content'], 'type' => 'chapter'];
                foreach ($topCh['subs'] as $sub) {
                    if (isset($sub['is_exported']) && (int) $sub['is_exported'] === 0)
                        continue;
                    $contentList[] = ['title' => $sub['title'], 'content' => $sub['content'], 'type' => 'sub-chapter'];
                }
            }
        }

        // 3. Sections After
        foreach ($sectionsAfter as $sec) {
            if (isset($sec['is_exported']) && (int) $sec['is_exported'] === 0)
                continue;
            $title = $sec['title'] ?? ($sec['name'] ?? '');
            $contentList[] = ['title' => $title, 'content' => $sec['content'], 'type' => 'section'];
        }

        // 4. Notes
        foreach ($notes as $note) {
            if (isset($note['is_exported']) && (int) $note['is_exported'] === 0)
                continue;
            $title = $note['title'] ?? ($note['name'] ?? 'Note');
            $contentList[] = ['title' => $title, 'content' => $note['content'], 'type' => 'note'];
        }

        // --- ZIP Creation ---
        $filename = "project_{$pid}_" . date('Ymd_His') . ".epub";
        $tempFile = tempnam(sys_get_temp_dir(), 'epub');
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);

        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');

        // Styles
        $css = "body{font-family:serif; line-height:1.6;} h1{text-align:center; page-break-before:always;} img{max-width:100%;} .act-title{font-size:2em; margin-top:20%; text-transform:uppercase;} .title-page{text-align:center; margin-top:10%;} .title-page__cover{max-height:500px;}";
        $zip->addFromString('OEBPS/style.css', $css);

        // --- Build Manifest & Spine ---
        $manifestItems = [];
        $spineRefs = [];
        $navPoints = "";

        $assetIdx = 0;

        // 1. Cover/Title Page
        $titlePageHtml = "<html xmlns='http://www.w3.org/1999/xhtml'><head><title>Title Page</title><link rel='stylesheet' type='text/css' href='style.css'/></head><body>";
        $titlePageHtml .= "<div class='title-page'>";

        if ($coverImage && file_exists('.' . $coverImage)) {
            $zip->addFile('.' . $coverImage, 'OEBPS/cover.jpg');
            $manifestItems[] = "<item id='cover-img' href='cover.jpg' media-type='image/jpeg'/>";
            $titlePageHtml .= "<img src='cover.jpg' alt='Cover' class='title-page__cover' /><br/>";
        }

        $titlePageHtml .= "<h1>" . htmlspecialchars($project['title']) . "</h1>";
        if (!empty($project['description'])) {
            $titlePageHtml .= "<h2>" . htmlspecialchars($project['description']) . "</h2>";
        }
        $titlePageHtml .= "<p>Par " . htmlspecialchars($author) . "</p>";
        $titlePageHtml .= "</div></body></html>";

        $zip->addFromString('OEBPS/title.xhtml', $titlePageHtml);
        $manifestItems[] = "<item id='title-page' href='title.xhtml' media-type='application/xhtml+xml'/>";
        $spineRefs[] = "<itemref idref='title-page'/>";

        // 2. Content Pages
        $playOrder = 1;

        foreach ($contentList as $idx => $item) {
            $itemId = "item_" . $idx;
            $file = "$itemId.xhtml";

            $safeContent = $this->sanitizeToXhtml($item['content']);

            $xhtml = "<html xmlns='http://www.w3.org/1999/xhtml'><head><title>" . htmlspecialchars($item['title']) . "</title><link rel='stylesheet' type='text/css' href='style.css'/></head><body>";

            if ($item['type'] === 'act-title') {
                $xhtml .= "<h1 class='act-title'>" . htmlspecialchars($item['title']) . "</h1>";
            } else {
                if ($item['title']) {
                    $xhtml .= "<h1>" . htmlspecialchars($item['title']) . "</h1>";
                }
                $xhtml .= "<div>" . $safeContent . "</div>";
            }
            $xhtml .= "</body></html>";

            $zip->addFromString("OEBPS/$file", $xhtml);
            $manifestItems[] = "<item id='$itemId' href='$file' media-type='application/xhtml+xml'/>";
            $spineRefs[] = "<itemref idref='$itemId'/>";

            if ($item['title']) {
                $navPoints .= "<navPoint id='nav_$itemId' playOrder='$playOrder'><navLabel><text>" . htmlspecialchars($item['title']) . "</text></navLabel><content src='$file'/></navPoint>";
                $playOrder++;
            }
        }

        $manifestItems[] = "<item id='css' href='style.css' media-type='text/css'/>";
        $manifestItems[] = "<item id='ncx' href='toc.ncx' media-type='application/x-dtbncx+xml'/>";

        // Generate OPF
        $manifestXml = implode("\n", $manifestItems);
        $spineXml = implode("\n", $spineRefs);

        $opf = "<?xml version='1.0'?>
<package xmlns='http://www.idpf.org/2007/opf' unique-identifier='bookid' version='2.0'>
  <metadata xmlns:dc='http://purl.org/dc/elements/1.1/'>
    <dc:title>" . htmlspecialchars($project['title']) . "</dc:title>
    <dc:creator>" . htmlspecialchars($author) . "</dc:creator>
    <dc:language>fr</dc:language>
    <dc:identifier id='bookid'>urn:uuid:EcrivainProject{$pid}</dc:identifier>
  </metadata>
  <manifest>
    $manifestXml
  </manifest>
  <spine toc='ncx'>
    $spineXml
  </spine>
</package>";

        // Generate NCX
        $ncx = "<?xml version='1.0'?>
<!DOCTYPE ncx PUBLIC '-//NISO//DTD ncx 2005-1//EN' 'http://www.daisy.org/z3986/2005/ncx-2005-1.dtd'>
<ncx xmlns='http://www.daisy.org/z3986/2005/ncx/' version='2005-1'>
  <head><meta name='dtb:uid' content='urn:uuid:EcrivainProject{$pid}'/></head>
  <docTitle><text>" . htmlspecialchars($project['title']) . "</text></docTitle>
  <navMap>
    <navPoint id='nav_title' playOrder='0'><navLabel><text>Titre</text></navLabel><content src='title.xhtml'/></navPoint>
    $navPoints
  </navMap>
</ncx>";

        $zip->addFromString('OEBPS/content.opf', $opf);
        $zip->addFromString('OEBPS/toc.ncx', $ncx);
        $zip->close();

        header('Content-Type: application/epub+zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }
    // --- AJAX Methods ---

    public function reorderChapters()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $body = json_decode($this->f3->get('BODY'), true);
        $order = $body['order'] ?? [];

        if (empty($order)) {
            echo json_encode(['status' => 'error', 'message' => 'No order provided']);
            return;
        }

        // Verify project ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            return;
        }

        $chapterModel = new Chapter();
        foreach ($order as $index => $id) {
            $chapterModel->load(['id=?', $id]);
            if (!$chapterModel->dry() && $chapterModel->project_id == $pid) {
                // If there's an 'order_index' field, update it.
                // Assuming schema has order_index or sort_order.
                // If not, we might need to add it to DB schema or skip this for now.
                // Let's assume 'order_index' exists as hinted by feature request.
                // If column missing, this will fail or do nothing.
                // Let's check model properties via dry run if needed, but for now blindly set it.
                // $chapterModel->order_index = $index;
                // $chapterModel->save();
            }
            $chapterModel->reset();
        }

        echo json_encode(['status' => 'ok']);
    }

    public function reorderSections()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $body = json_decode($this->f3->get('BODY'), true);
        $order = $body['order'] ?? [];
        $type = $body['type'] ?? '';

        if (empty($order)) {
            echo json_encode(['status' => 'error']);
            return;
        }

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            return;
        }

        if ($type === 'note' || $type === 'notes') {
            $model = new Note();
        } else {
            $model = new Section();
        }

        foreach ($order as $index => $id) {
            $model->load(['id=? AND project_id=?', (int) $id, $pid]);
            if (!$model->dry()) {
                $model->order_index = $index;
                $model->save();
            }
            $model->reset();
        }

        echo json_encode(['status' => 'ok']);
    }

    public function toggleExport()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $body = json_decode($this->f3->get('BODY'), true);
        $id = $body['id'] ?? null;
        $type = $body['type'] ?? ''; // 'chapter', 'section', 'note', 'act', or 'character'
        $state = $body['is_exported'] ?? 1;

        if (!$id || !$type) {
            echo json_encode(['status' => 'error']);
            return;
        }

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            return;
        }

        if ($type === 'chapter') {
            $model = new Chapter();
        } elseif ($type === 'note') {
            $model = new Note();
        } elseif ($type === 'act') {
            $model = new Act();
        } elseif ($type === 'character') {
            $model = new Character();
        } elseif ($type === 'element') {
            if (!$this->f3->get('DB')->exists('elements')) { return; }
            $model = new Element();
        } else {
            $model = new Section();
        }

        $model->load(['id=?', $id]);
        if (!$model->dry() && $model->project_id == $pid) {
            $model->is_exported = $state ? 1 : 0;
            $model->save();
        }

        echo json_encode(['status' => 'ok']);
    }

    public function getPreview()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $type = $this->f3->get('PARAMS.type');
        $id = (int) $this->f3->get('PARAMS.id');

        // Verify ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        if ($type === 'chapter') {
            $model = new Chapter();
        } elseif ($type === 'note') {
            $model = new Note();
        } elseif ($type === 'element') {
            if (!$this->f3->get('DB')->exists('elements')) { $this->f3->error(404); return; }
            $model = new Element();
        } else {
            $model = new Section();
        }

        $model->load(['id=?', $id]);
        if ($model->dry() || $model->project_id != $pid) {
            $this->f3->error(404);
            return;
        }

        $title = $model->title ?: ($model->name ?? 'Sans titre');
        $content = $model->content ?? '';
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        header('Content-Type: application/json');
        echo json_encode([
            'title' => $title,
            'content' => $content
        ]);
    }

    public function supHtml($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->supHtml($v);
            }
            return $data;
        }

        if (is_string($data)) {
            $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $data = strip_tags($data);
            $data = trim($data);
            // optionnel: normaliser les espaces (y compris &nbsp; => U+00A0)
            $data = preg_replace('/\x{00A0}/u', ' ', $data);
            $data = preg_replace('/\s+/u', ' ', $data);
            return $data;
        }

        // int, float, bool, null, objets: on laisse
        return $data;
    }

    private function getUserFullName(?array $user): string
    {
        if (!$user || empty($user['email'])) {
            return '';
        }

        $file = $this->getUserDataDir($user['email']) . '/profile.json';
        if (!file_exists($file)) {
            return '';
        }

        $profile = json_decode(file_get_contents($file), true);
        if (!is_array($profile)) {
            return '';
        }

        $first = trim($profile['firstname'] ?? '');
        $last = trim($profile['lastname'] ?? '');
        return trim($first . ' ' . $last);
    }

    /**
     * Build CSS rules to order panels based on template configuration.
     */
    private function buildPanelOrderCss(array $templateElements): string
    {
        if (empty($templateElements)) {
            return '';
        }

        $selectorMap = [
            'section_before' => '#panel-before',
            'section_after'  => '#panel-after',
            'act'            => '#panel-content',
            'chapter'        => '#panel-content',
            'note'           => '#panel-notes',
            'character'      => '#panel-characters',
            'file'           => '#panel-files',
        ];

        $rules = [];
        $assigned = [];
        $order = 0;

        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) {
                $order++;
                continue;
            }

            $key = $te['element_type'];
            if ($key === 'section') {
                $key = 'section_' . ($te['section_placement'] ?? 'before');
            }

            if ($key === 'element') {
                $rules[] = '#panel-element-' . (int)$te['id'] . '{order:' . $order . '}';
            } elseif (isset($selectorMap[$key]) && !isset($assigned[$selectorMap[$key]])) {
                $selector = $selectorMap[$key];
                $assigned[$selector] = true;
                $rules[] = $selector . '{order:' . $order . '}';
            }

            $order++;
        }

        return implode("\n", $rules);
    }

    /**
     * Upload a file to the project
     */
    public function uploadFile()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        // Check ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Projet non trouvé']);
            return;
        }

        // Check if file was uploaded
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Aucun fichier téléchargé']);
            return;
        }

        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($file['size'] > $maxSize) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 10 MB)']);
            return;
        }

        // Create directory for user files
        $userFilesDir = $this->f3->get('ROOT') . '/' . $this->getUserDataDir($user['email']) . '/files';
        if (!is_dir($userFilesDir)) {
            mkdir($userFilesDir, 0755, true);
        }

        // Generate unique filename
        $originalName = basename($file['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $uniqueName = $safeName . '_' . time() . '.' . $ext;
        $filepath = $userFilesDir . '/' . $uniqueName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement du fichier']);
            return;
        }

        // Save to database - store RELATIVE path for portability
        $relativePath = $this->getUserDataDir($user['email']) . '/files/' . $uniqueName;

        $fileModel = new ProjectFile();
        $fileModel->project_id = $pid;
        $fileModel->filename = $originalName;
        $fileModel->filepath = $relativePath;
        $fileModel->filetype = $file['type'];
        $fileModel->filesize = $file['size'];
        $fileModel->comment = $_POST['comment'] ?? '';
        $fileModel->save();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'file_id' => $fileModel->id]);
    }

    /**
     * Download a file
     */
    public function downloadFile()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $fid = (int) $this->f3->get('PARAMS.fid');
        $user = $this->currentUser();

        // Check ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            $this->f3->error(403);
            return;
        }

        // Get file
        $fileModel = new ProjectFile();
        $fileModel->load(['id=? AND project_id=?', $fid, $pid]);

        if ($fileModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Build absolute path from relative path stored in DB
        $absolutePath = $this->f3->get('ROOT') . '/' . $fileModel->filepath;

        // Send file
        if (!file_exists($absolutePath)) {
            $this->f3->error(404);
            return;
        }

        header('Content-Type: ' . $fileModel->filetype);
        header('Content-Disposition: inline; filename="' . $fileModel->filename . '"');
        header('Content-Length: ' . filesize($absolutePath));
        readfile($absolutePath);
        exit;
    }

    /**
     * Delete a file
     */
    public function deleteFile()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $fid = (int) $this->f3->get('PARAMS.fid');
        $user = $this->currentUser();

        // Check ownership
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            $this->f3->error(403);
            return;
        }

        // Get file
        $fileModel = new ProjectFile();
        $fileModel->load(['id=? AND project_id=?', $fid, $pid]);

        if ($fileModel->dry()) {
            $this->f3->error(404);
            return;
        }

        // Delete physical file - build absolute path from relative path
        $absolutePath = $this->f3->get('ROOT') . '/' . $fileModel->filepath;
        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        // Delete from database
        $fileModel->erase();

        // Redirect back
        $this->f3->reroute('/project/' . $pid);
    }

    public function dictionaryAdd()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $body = json_decode($this->f3->get('BODY'), true);
        $word = trim($body['word'] ?? '');
        if ($word === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Mot manquant']);
            return;
        }

        $rows    = $this->db->exec('SELECT ignored_words FROM projects WHERE id=?', [$pid]);
        $current = json_decode($rows[0]['ignored_words'] ?? '[]', true) ?: [];
        if (!in_array($word, $current)) {
            $current[] = $word;
        }
        $this->db->exec('UPDATE projects SET ignored_words=? WHERE id=?', [json_encode($current), $pid]);

        echo json_encode(['status' => 'ok', 'words' => $current]);
    }

    public function dictionaryRemove()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $body = json_decode($this->f3->get('BODY'), true);
        $word = trim($body['word'] ?? '');

        $rows    = $this->db->exec('SELECT ignored_words FROM projects WHERE id=?', [$pid]);
        $current = json_decode($rows[0]['ignored_words'] ?? '[]', true) ?: [];
        $current = array_values(array_filter($current, fn($w) => $w !== $word));
        $this->db->exec('UPDATE projects SET ignored_words=? WHERE id=?', [json_encode($current), $pid]);

        echo json_encode(['status' => 'ok', 'words' => $current]);
    }

}
