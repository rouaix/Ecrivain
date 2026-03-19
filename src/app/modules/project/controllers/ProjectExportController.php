<?php

class ProjectExportController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function export()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'txt');
    }

    public function exportHtml()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'html');
    }

    public function exportEpub()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        if (!class_exists('ZipArchive')) {
            $this->f3->error(500, 'ZipArchive extension missing');
            return;
        }
        $this->generateEpub($pid);
    }

    public function exportVector()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'vector');
    }

    public function exportClean()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'clean');
    }

    public function exportSummaries()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'summaries');
    }

    public function exportMarkdown()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'markdown');
    }

    public function generateExportContent($pid, $format)
    {
        if (!$this->hasProjectAccess((int)$pid)) {
            return null;
        }

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
        }

        // Prepare Models
        $sectionModel = new Section();
        $chapterModel = new Chapter();

        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $chapters       = $chapterModel->getAllByProject($pid);
        $sectionsAfter  = $sectionModel->getAfterChapters($pid);

        $noteModel = new Note();
        $notes     = array_values(array_filter(
            $noteModel->getAllByProject($pid),
            fn($n) => ($n['type'] ?? 'note') !== 'scenario'
        ));

        $scenarioModel = new Scenario();
        $scenarios     = $scenarioModel->getAllByProject($pid);

        // Detect Cover from Sections Before
        $coverImage = null;
        $coverKey   = null;

        foreach ($sectionsBefore as $k => $sec) {
            if ($sec['type'] === 'cover') {
                $coverKey = $k;
                if (!empty($sec['image_path'])) {
                    $coverImage = $this->f3->get('SCHEME') . '://' . $this->f3->get('HOST') . $this->f3->get('BASE') . $sec['image_path'];
                }
                if (!empty($sec['title'])) {
                    $project['description'] = $sec['title'];
                }
                if (!empty($sec['content'])) {
                    $author = $sec['content'];
                }
                break;
            }
        }

        if ($coverKey !== null) {
            unset($sectionsBefore[$coverKey]);
        }

        $content    = "";
        $jsonOutput = [];

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
            $content .= ucfirst($project['title']) . "\n";
            if (!empty($project['description'])) {
                $content .= strip_tags(html_entity_decode($project['description'])) . "\n";
            }
            $content .= trim(strip_tags(html_entity_decode($author))) . "\n\n";
        } elseif ($format === 'markdown') {
            $content .= "# " . $project['title'] . "\n\n";
            $authorMd = strip_tags(html_entity_decode(html_entity_decode($author, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5));
            $content .= "*" . trim($authorMd) . "*\n\n";
            if (!empty($project['description'])) {
                $content .= $this->htmlToMarkdown($project['description']) . "\n\n";
            }
        } else {
            if ($format === 'vector') {
                $jsonOutput[] = [
                    'type'        => 'project_meta',
                    'title'       => mb_strtolower($project['title'], 'UTF-8'),
                    'author'      => mb_strtolower(strip_tags(html_entity_decode(html_entity_decode($author, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5)), 'UTF-8'),
                    'description' => !empty($project['description']) ? mb_strtolower(strip_tags($project['description']), 'UTF-8') : ''
                ];
            } elseif ($format === 'clean') {
                $content .= mb_strtolower($project['title'], 'UTF-8') . "\n\n";
                $cleanAuthor = mb_strtolower(strip_tags(html_entity_decode(html_entity_decode($author, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5)), 'UTF-8');
                $content .= "par " . $cleanAuthor . "\n\n";
                if (!empty($project['description'])) {
                    $desc = str_replace(['</p>', '<br>', '<br/>', '</div>'], "\n\n", $project['description']);
                    $desc = html_entity_decode(html_entity_decode($desc, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
                    $content .= mb_strtolower(strip_tags($desc), 'UTF-8') . "\n\n";
                }
            } else {
                $content .= strtoupper($project['title']) . "\n\n";

                if (!empty($project['description'])) {
                    $desc  = str_replace(['</p>', '<br>', '<br/>', '</div>'], "\n\n", $project['description']);
                    $desc  = html_entity_decode(html_entity_decode($desc, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
                    $dText = strip_tags($desc);
                    $content .= $dText . "\n\n";
                }

                $authorText = str_replace(['</p>', '<br>', '<br/>', '</div>'], "\n\n", $author);
                $authorText = html_entity_decode(html_entity_decode($authorText, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
                $authorText = strip_tags($authorText);
                $content .= "Par " . trim($authorText) . "\n\n";
            }
        }

        // --- Output Content Loop ---

        $appendItem = function ($item, $isChapter = false) use (&$content, &$jsonOutput, $format) {
            if (isset($item['is_exported']) && (int) $item['is_exported'] === 0) {
                return;
            }

            if ($format === 'summaries') {
                $summary = $item['resume'] ?? ($item['description'] ?? '');
                $summary = trim(strip_tags(html_entity_decode($summary)));
                if (empty($summary)) return;
                $title = $item['title'] ?? ($item['name'] ?? '');
                $content .= $title . "\n";
                $content .= $summary . "\n";
                return;
            }

            $title = $item['title'] ?? ($item['name'] ?? '');
            $text  = $item['content'] ?? '';

            if ($format === 'html') {
                $content .= "<div class='page-break'></div>";
                if ($title) {
                    $content .= "<h2>{$title}</h2>";
                }
                $content .= "<div class='" . ($isChapter ? 'chapter-content' : 'section-content') . "'>{$text}</div>";
            } elseif ($format === 'markdown') {
                if ($title) {
                    $content .= "\n\n## " . $title . "\n\n";
                }
                $content .= $this->htmlToMarkdown($text) . "\n\n";
            } else {
                $plain = preg_replace('/<br\s*\/?>\s*/i', "\n", $text);
                $plain = preg_replace('/<\/h[1-6]>\s*/i', "\n\n", $plain);
                $plain = preg_replace('/<\/(p|div)>\s*/i', "\n\n", $plain);
                $plain = html_entity_decode(html_entity_decode($plain, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
                $plain = strip_tags($plain);
                $plain = str_replace("\u{00A0}", ' ', $plain);
                $plain = preg_replace("/\n{3,}/", "\n\n", $plain);
                $plain = trim($plain);

                if ($format === 'vector') {
                    $plainLowercase = mb_strtolower($plain, 'UTF-8');
                    $titleLowercase = $title ? mb_strtolower($title, 'UTF-8') : '';
                    $jsonOutput[] = [
                        'id'      => (int) ($item['id'] ?? 0),
                        'type'    => $isChapter ? 'chapter' : ($item['type'] ?? 'section'),
                        'title'   => $titleLowercase,
                        'content' => $plainLowercase
                    ];
                } elseif ($format === 'clean') {
                    if ($title) {
                        $content .= mb_strtolower($title, 'UTF-8') . "\n\n";
                    }
                    $content .= mb_strtolower($plain, 'UTF-8') . "\n\n";
                } else {
                    if ($title) {
                        $content .= "\n\n### " . strtoupper($title) . "\n\n";
                    }
                    $content .= $plain . "\n";
                }
            }
        };

        // TEMPLATE SYSTEM: Export content loop driven by template elements
        $allChapters = $chapterModel->getAllByProject($pid);
        $actModel    = new \Act();
        $acts        = $actModel->getAllByProject($pid);

        $chaptersByAct      = [];
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

        $customElementsByType = [];
        if ($db->exists('elements')) {
            $elementModel   = new Element();
            $customElements = $elementModel->getAllByProject($pid);
            foreach ($customElements as $elem) {
                $tid = $elem['template_element_id'];
                if (!isset($customElementsByType[$tid])) {
                    $customElementsByType[$tid] = [];
                }
                $customElementsByType[$tid][] = $elem;
            }
        }

        // Synopsis block (if exists and exported)
        if ($db->exists('synopsis')) {
            $synopsisModel = new Synopsis();
            $synopsisData  = $synopsisModel->getByProject($pid);
            if ($synopsisData && ($synopsisData['is_exported'] ?? 1)) {
                $logline = trim(strip_tags($synopsisData['logline'] ?? ''));
                $pitch   = $synopsisData['pitch'] ?? '';

                $beatLabels = [
                    'situation'   => 'Situation initiale',
                    'trigger_evt' => 'Élément déclencheur',
                    'plot_point1' => 'Premier tournant',
                    'development' => 'Développement',
                    'midpoint'    => 'Point médian',
                    'crisis'      => 'Crise',
                    'climax'      => 'Climax',
                    'resolution'  => 'Résolution',
                ];

                if ($format === 'html') {
                    $content .= "<div class='page-break'></div><h2>Synopsis</h2>";
                    if ($logline) $content .= "<p><em>" . htmlspecialchars($logline, ENT_QUOTES) . "</em></p>";
                    if ($pitch)   $content .= "<div class='section-content'>" . $pitch . "</div>";
                    foreach ($beatLabels as $field => $label) {
                        $val = trim(strip_tags(html_entity_decode($synopsisData[$field] ?? '')));
                        if ($val) {
                            $content .= "<h3>" . htmlspecialchars($label) . "</h3><p>" . nl2br(htmlspecialchars($val)) . "</p>";
                        }
                    }
                } elseif ($format === 'markdown') {
                    $content .= "\n\n## Synopsis\n\n";
                    if ($logline) $content .= "*" . $logline . "*\n\n";
                    if ($pitch)   $content .= $this->htmlToMarkdown($pitch) . "\n\n";
                    foreach ($beatLabels as $field => $label) {
                        $val = trim(strip_tags(html_entity_decode($synopsisData[$field] ?? '')));
                        if ($val) $content .= "### " . $label . "\n\n" . $val . "\n\n";
                    }
                } elseif ($format === 'vector') {
                    $beatText = '';
                    foreach ($beatLabels as $field => $label) {
                        $val = trim(strip_tags(html_entity_decode($synopsisData[$field] ?? '')));
                        if ($val) $beatText .= $label . ': ' . $val . ' ';
                    }
                    $jsonOutput[] = [
                        'type'    => 'synopsis',
                        'logline' => mb_strtolower($logline, 'UTF-8'),
                        'content' => mb_strtolower(trim($logline . ' ' . strip_tags(html_entity_decode($pitch)) . ' ' . $beatText), 'UTF-8'),
                    ];
                } elseif ($format === 'summaries') {
                    if ($logline) $content .= "Synopsis\n" . $logline . "\n";
                } elseif ($format === 'clean') {
                    $content .= "synopsis\n\n";
                    if ($logline) $content .= mb_strtolower($logline, 'UTF-8') . "\n\n";
                } else {
                    $content .= "\n\nSYNOPSIS\n\n";
                    if ($logline) $content .= $logline . "\n\n";
                    if ($pitch) {
                        $plain = strip_tags(html_entity_decode($pitch));
                        $content .= trim($plain) . "\n\n";
                    }
                    foreach ($beatLabels as $field => $label) {
                        $val = trim(strip_tags(html_entity_decode($synopsisData[$field] ?? '')));
                        if ($val) $content .= strtoupper($label) . "\n" . $val . "\n\n";
                    }
                }
            }
        }

        foreach ($templateElements as $elem) {
            if (!$elem['is_enabled']) continue;

            switch ($elem['element_type']) {
                case 'section':
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
                    foreach ($acts as $act) {
                        if (!isset($chaptersByAct[$act['id']])) continue;

                        $actChaps = $chaptersByAct[$act['id']];
                        usort($actChaps, function ($a, $b) {
                            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                        });

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
                        } elseif ($format === 'markdown') {
                            $content .= "\n\n# " . $act['title'] . "\n\n";
                            if (!empty($act['content'])) {
                                $content .= $this->htmlToMarkdown($act['content']) . "\n\n";
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

                        foreach ($actChaps as $topCh) {
                            $appendItem($topCh, true);
                            foreach ($topCh['subs'] as $sub) {
                                $appendItem($sub, true);
                            }
                        }
                    }
                    break;

                case 'chapter':
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
                    if ($format !== 'summaries') {
                        foreach ($notes as $note) {
                            $appendItem($note, false);
                        }
                    }
                    break;

                case 'element':
                    if ($format !== 'summaries') {
                        $elements        = $customElementsByType[$elem['id']] ?? [];
                        $topElements     = [];
                        $subElementsByParent = [];

                        foreach ($elements as $e) {
                            if ($e['parent_id']) {
                                $subElementsByParent[$e['parent_id']][] = $e;
                            } else {
                                $topElements[] = $e;
                            }
                        }

                        usort($topElements, function ($a, $b) {
                            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                        });

                        foreach ($topElements as $topElem) {
                            $appendItem($topElem, true);

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

                case 'scenario':
                    if ($format !== 'summaries') {
                        foreach ($scenarios as $sc) {
                            if (isset($sc['is_exported']) && (int) $sc['is_exported'] === 0) continue;
                            if ($format === 'markdown' && !empty($sc['markdown'])) {
                                $title = $sc['title'] ?? '';
                                if ($title) $content .= "\n\n## " . $title . "\n\n";
                                $content .= $sc['markdown'] . "\n\n";
                            } else {
                                $sc['content'] = preg_replace(
                                    '/<pre[^>]*>(?:<code[^>]*>)?([\s\S]*?)(?:<\/code>)?<\/pre>/i',
                                    '<p>$1</p>',
                                    $sc['content'] ?? ''
                                );
                                $appendItem($sc, false);
                            }
                        }
                    }
                    break;

                case 'character':
                case 'file':
                    break;
            }
        }

        if ($format === 'html') {
            $content .= "</body></html>";
        }

        if ($format === 'vector') {
            $content = json_encode($jsonOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $ext = 'json';
        } elseif ($format === 'markdown') {
            $ext = 'md';
        } else {
            $ext = ($format === 'html') ? 'html' : 'txt';
        }

        return [
            'content' => $content,
            'ext'     => $ext,
            'title'   => $project['title']
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
        $ext     = $result['ext'];

        if ($format === 'vector') {
            header('Content-Type: application/json');
        } elseif ($format === 'markdown') {
            header('Content-Type: text/plain; charset=utf-8');
        } else {
            header('Content-Type: ' . ($format === 'html' ? 'text/html' : 'text/plain'));
        }

        $filename = "project_{$pid}_" . date('Ymd_His') . "_{$format}." . $ext;
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        exit;
    }

    private function htmlToMarkdown(string $html): string
    {
        $md = $html;

        for ($i = 6; $i >= 1; $i--) {
            $hashes = str_repeat('#', $i);
            $md = preg_replace('/<h' . $i . '[^>]*>(.*?)<\/h' . $i . '>/si', "\n{$hashes} \$1\n\n", $md);
        }

        $md = preg_replace('/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/si', '**$2**', $md);
        $md = preg_replace('/<(em|i)[^>]*>(.*?)<\/(em|i)>/si', '*$2*', $md);
        $md = preg_replace('/<s[^>]*>(.*?)<\/s>/si', '~~$1~~', $md);
        $md = preg_replace('/<u[^>]*>(.*?)<\/u>/si', '$1', $md);
        $md = preg_replace('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si', '[$2]($1)', $md);

        $md = preg_replace_callback('/<blockquote[^>]*>(.*?)<\/blockquote>/si', function ($m) {
            $text = strip_tags($m[1]);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
            return "\n> " . str_replace("\n", "\n> ", trim($text)) . "\n\n";
        }, $md);

        $md = preg_replace('/<code[^>]*>(.*?)<\/code>/si', '`$1`', $md);
        $md = preg_replace('/<li[^>]*>(.*?)<\/li>/si', "- $1\n", $md);
        $md = preg_replace('/<\/(ul|ol)>/si', "\n", $md);
        $md = preg_replace('/<br\s*\/?>/i', "\n", $md);
        $md = preg_replace('/<\/(p|div)>/i', "\n\n", $md);
        $md = strip_tags($md);
        $md = html_entity_decode(html_entity_decode($md, ENT_QUOTES | ENT_HTML5), ENT_QUOTES | ENT_HTML5);
        $md = str_replace("\u{00A0}", ' ', $md);
        $md = preg_replace("/\n{3,}/", "\n\n", $md);

        return trim($md);
    }

    private function sanitizeToXhtml($html)
    {
        $html = preg_replace('/<(br|hr|img|input)([^>]*)>/i', '<$1$2 />', $html);
        $html = str_replace('//>', '/>', $html);
        $html = preg_replace('/ & /', ' &amp; ', $html);
        return $html;
    }

    private function generateEpub($pid)
    {
        if (!$this->hasProjectAccess((int)$pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=?', $pid])[0];

        $epubUser = $this->currentUser();
        $author   = $epubUser['username'] ?? 'Auteur inconnu';

        $sectionModel = new Section();
        $chapterModel = new Chapter();
        $noteModel    = new Note();

        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $allChapters    = $chapterModel->getAllByProject($pid);
        $sectionsAfter  = $sectionModel->getAfterChapters($pid);
        $notes          = array_values(array_filter(
            $noteModel->getAllByProject($pid),
            fn($n) => ($n['type'] ?? 'note') !== 'scenario'
        ));

        $scenarioModel = new Scenario();
        $scenarios     = $scenarioModel->getAllByProject($pid);

        $coverImage = null;
        $coverKey   = null;
        foreach ($sectionsBefore as $k => $sec) {
            if ($sec['type'] === 'cover') {
                $coverKey = $k;
                if (!empty($sec['image_path'])) {
                    $coverDir = 'data/' . $epubUser['email'] . '/projects/' . $pid . '/sections/';
                    foreach (glob($coverDir . 'cover.*') as $f) {
                        $coverImage = $f;
                        break;
                    }
                }
                if (!empty($sec['title'])) {
                    $project['description'] = $sec['title'];
                    if (preg_match('/^(.*)\s*\((.+)\)$/u', $sec['title'], $matches)) {
                        $project['description'] = trim($matches[1]);
                        $author = trim($matches[2]);
                    }
                }
                if (!empty($sec['content'])) {
                    $authorHtml = html_entity_decode($sec['content']);
                    $authorHtml = str_replace(['</p>', '<br>', '<br/>', '</div>'], " ", $authorHtml);
                    $author     = trim(strip_tags($authorHtml));
                }
                break;
            }
        }
        if ($coverKey !== null) {
            unset($sectionsBefore[$coverKey]);
        }

        $contentList = [];

        $templateElements = [];
        $db = $this->f3->get('DB');
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId    = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            if (!$templateId) {
                $template = $templateModel->getDefault();
            } else {
                $template = $templateModel->findAndCast(['id=?', $templateId]);
                $template = $template ? $template[0] : $templateModel->getDefault();
            }
            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        $chaptersByAct       = [];
        $chaptersWithoutAct  = [];
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
        $acts     = $actModel->getAllByProject($pid);

        $customElementsByType = [];
        if ($db->exists('elements')) {
            $elementModel   = new Element();
            $customElements = $elementModel->getAllByProject($pid);
            foreach ($customElements as $elem) {
                $tid = $elem['template_element_id'];
                if (!isset($customElementsByType[$tid])) $customElementsByType[$tid] = [];
                $customElementsByType[$tid][] = $elem;
            }
        }

        $addItem = function ($item, $type) use (&$contentList) {
            if (isset($item['is_exported']) && (int) $item['is_exported'] === 0) return;
            $title       = $item['title'] ?? ($item['name'] ?? '');
            $contentList[] = ['title' => $title, 'content' => $item['content'] ?? '', 'type' => $type];
        };

        if (!empty($templateElements)) {
            foreach ($templateElements as $elem) {
                if (!$elem['is_enabled']) continue;
                switch ($elem['element_type']) {
                    case 'section':
                        $sections = ($elem['section_placement'] === 'before') ? $sectionsBefore : $sectionsAfter;
                        foreach ($sections as $sec) {
                            if ($sec['type'] === $elem['element_subtype']) {
                                $addItem($sec, 'section');
                            }
                        }
                        break;
                    case 'act':
                        foreach ($acts as $act) {
                            if (!isset($chaptersByAct[$act['id']])) continue;
                            $contentList[] = ['title' => $act['title'], 'content' => $act['content'] ?? '', 'type' => 'act-title'];
                            $actChaps = $chaptersByAct[$act['id']];
                            usort($actChaps, function ($a, $b) {
                                return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                            });
                            foreach ($actChaps as $ch) {
                                $addItem($ch, 'chapter');
                                foreach ($ch['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                            }
                        }
                        break;
                    case 'chapter':
                        usort($chaptersWithoutAct, function ($a, $b) {
                            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                        });
                        foreach ($chaptersWithoutAct as $ch) {
                            $addItem($ch, 'chapter');
                            foreach ($ch['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                        }
                        break;
                    case 'note':
                        foreach ($notes as $note) { $addItem($note, 'note'); }
                        break;
                    case 'scenario':
                        foreach ($scenarios as $sc) {
                            if (isset($sc['is_exported']) && (int) $sc['is_exported'] === 0) continue;
                            $sc['content'] = preg_replace(
                                '/<pre[^>]*>(?:<code[^>]*>)?([\s\S]*?)(?:<\/code>)?<\/pre>/i',
                                '<p>$1</p>',
                                $sc['content'] ?? ''
                            );
                            $addItem($sc, 'note');
                        }
                        break;
                    case 'element':
                        $elements        = $customElementsByType[$elem['id']] ?? [];
                        $topElements     = [];
                        $subElementsByParent = [];
                        foreach ($elements as $e) {
                            if ($e['parent_id']) $subElementsByParent[$e['parent_id']][] = $e;
                            else $topElements[] = $e;
                        }
                        usort($topElements, function ($a, $b) {
                            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                        });
                        foreach ($topElements as $topElem) {
                            $addItem($topElem, 'section');
                            $subs = $subElementsByParent[$topElem['id']] ?? [];
                            usort($subs, function ($a, $b) {
                                return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                            });
                            foreach ($subs as $sub) { $addItem($sub, 'sub-chapter'); }
                        }
                        break;
                }
            }
        } else {
            foreach ($sectionsBefore as $sec) { $addItem($sec, 'section'); }

            $rootItems = [];
            foreach ($acts as $act) { $act['is_act'] = true; $rootItems[] = $act; }
            foreach ($chaptersWithoutAct as $ch) { $ch['is_act'] = false; $rootItems[] = $ch; }
            usort($rootItems, function ($a, $b) {
                return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
            });
            foreach ($rootItems as $item) {
                if ($item['is_act']) {
                    $contentList[] = ['title' => $item['title'], 'content' => $item['content'] ?? '', 'type' => 'act-title'];
                    if (isset($chaptersByAct[$item['id']])) {
                        $actChaps = $chaptersByAct[$item['id']];
                        usort($actChaps, function ($a, $b) {
                            return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
                        });
                        foreach ($actChaps as $ch) {
                            $addItem($ch, 'chapter');
                            foreach ($ch['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                        }
                    }
                } else {
                    $addItem($item, 'chapter');
                    foreach ($item['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                }
            }
            foreach ($sectionsAfter as $sec) { $addItem($sec, 'section'); }
            foreach ($notes as $note) { $addItem($note, 'note'); }
            foreach ($scenarios as $sc) {
                if (isset($sc['is_exported']) && (int) $sc['is_exported'] === 0) continue;
                $sc['content'] = preg_replace(
                    '/<pre[^>]*>(?:<code[^>]*>)?([\s\S]*?)(?:<\/code>)?<\/pre>/i',
                    '<p>$1</p>',
                    $sc['content'] ?? ''
                );
                $addItem($sc, 'note');
            }
        }

        // --- ZIP Creation ---
        $filename = "project_{$pid}_" . date('Ymd_His') . ".epub";
        $tempFile = tempnam(sys_get_temp_dir(), 'epub');
        $zip      = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);

        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');

        $css = "body{font-family:serif; line-height:1.6;} h1{text-align:center; page-break-before:always;} img{max-width:100%;} .act-title{font-size:2em; margin-top:20%; text-transform:uppercase;} .title-page{text-align:center; margin-top:10%;} .title-page__cover{max-height:500px;}";
        $zip->addFromString('OEBPS/style.css', $css);

        $manifestItems = [];
        $spineRefs     = [];
        $navPoints     = "";

        $titlePageHtml = "<html xmlns='http://www.w3.org/1999/xhtml'><head><title>Title Page</title><link rel='stylesheet' type='text/css' href='style.css'/></head><body>";
        $titlePageHtml .= "<div class='title-page'>";

        if ($coverImage && file_exists($coverImage)) {
            $zip->addFile($coverImage, 'OEBPS/cover.jpg');
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
        $spineRefs[]     = "<itemref idref='title-page'/>";

        $playOrder  = 1;
        $navLiItems = "";

        foreach ($contentList as $idx => $item) {
            $itemId = "item_" . $idx;
            $file   = "$itemId.xhtml";

            $safeContent = $this->sanitizeToXhtml($item['content']);

            $xhtml = "<?xml version='1.0' encoding='utf-8'?><html xmlns='http://www.w3.org/1999/xhtml'><head><title>" . htmlspecialchars($item['title']) . "</title><link rel='stylesheet' type='text/css' href='style.css'/></head><body>";

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
            $spineRefs[]     = "<itemref idref='$itemId'/>";

            if ($item['title']) {
                $navPoints  .= "<navPoint id='nav_$itemId' playOrder='$playOrder'><navLabel><text>" . htmlspecialchars($item['title']) . "</text></navLabel><content src='$file'/></navPoint>";
                $navLiItems .= "<li><a href='" . $file . "'>" . htmlspecialchars($item['title']) . "</a></li>\n";
                $playOrder++;
            }
        }

        $manifestItems[] = "<item id='css' href='style.css' media-type='text/css'/>";
        $manifestItems[] = "<item id='ncx' href='toc.ncx' media-type='application/x-dtbncx+xml'/>";
        $manifestItems[] = "<item id='nav' href='nav.xhtml' media-type='application/xhtml+xml' properties='nav'/>";

        $navXhtml = "<?xml version='1.0' encoding='utf-8'?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xmlns:epub='http://www.idpf.org/2007/ops' lang='fr'>
<head><meta charset='utf-8'/><title>Table des matières</title></head>
<body>
<nav epub:type='toc' id='toc'>
  <h1>Table des matières</h1>
  <ol>
    <li><a href='title.xhtml'>Page de titre</a></li>
    $navLiItems
  </ol>
</nav>
</body></html>";
        $zip->addFromString('OEBPS/nav.xhtml', $navXhtml);

        $manifestXml = implode("\n    ", $manifestItems);
        $spineXml    = implode("\n    ", $spineRefs);
        $modified    = gmdate('Y-m-d\TH:i:s\Z');

        $opf = "<?xml version='1.0' encoding='utf-8'?>
<package xmlns='http://www.idpf.org/2007/opf' unique-identifier='bookid' version='3.0' xml:lang='fr'>
  <metadata xmlns:dc='http://purl.org/dc/elements/1.1/'>
    <dc:title>" . htmlspecialchars($project['title']) . "</dc:title>
    <dc:creator>" . htmlspecialchars($author) . "</dc:creator>
    <dc:language>fr</dc:language>
    <dc:identifier id='bookid'>urn:uuid:EcrivainProject{$pid}</dc:identifier>
    <meta property='dcterms:modified'>{$modified}</meta>
  </metadata>
  <manifest>
    $manifestXml
  </manifest>
  <spine toc='ncx'>
    $spineXml
  </spine>
</package>";

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

    public function exportOdt()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        if (!class_exists('ZipArchive')) {
            $this->f3->error(500, 'Extension ZipArchive manquante');
            return;
        }
        $this->generateOdt($pid);
    }

    private function generateOdt(int $pid): void
    {
        if (!$this->hasProjectAccess($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=?', $pid])[0];
        $user         = $this->currentUser();
        $author       = $user['username'] ?? 'Auteur inconnu';

        $sectionModel = new Section();
        $chapterModel = new Chapter();
        $noteModel    = new Note();

        $sectionsBefore = $sectionModel->getBeforeChapters($pid);
        $allChapters    = $chapterModel->getAllByProject($pid);
        $sectionsAfter  = $sectionModel->getAfterChapters($pid);
        $notes          = array_values(array_filter(
            $noteModel->getAllByProject($pid),
            fn($n) => ($n['type'] ?? 'note') !== 'scenario'
        ));

        $scenarioModel = new Scenario();
        $scenarios     = $scenarioModel->getAllByProject($pid);

        // Extract author/description from cover section
        $coverKey = null;
        foreach ($sectionsBefore as $k => $sec) {
            if ($sec['type'] === 'cover') {
                $coverKey = $k;
                if (!empty($sec['title']))   $project['description'] = $sec['title'];
                if (!empty($sec['content'])) {
                    $a = html_entity_decode($sec['content']);
                    $a = str_replace(['</p>', '<br>', '<br/>', '</div>'], ' ', $a);
                    $author = trim(strip_tags($a));
                }
                break;
            }
        }
        if ($coverKey !== null) unset($sectionsBefore[$coverKey]);

        // Template system
        $db = $this->f3->get('DB');
        $templateElements = [];
        if ($db->exists('templates') && $db->exists('template_elements')) {
            $templateId    = $project['template_id'] ?? null;
            $templateModel = new ProjectTemplate();
            if (!$templateId) {
                $template = $templateModel->getDefault();
            } else {
                $template = $templateModel->findAndCast(['id=?', $templateId]);
                $template = $template ? $template[0] : $templateModel->getDefault();
            }
            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        // Build chapter/act structure
        $chaptersByAct       = [];
        $chaptersWithoutAct  = [];
        $subChaptersByParent = [];
        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) $subChaptersByParent[$ch['parent_id']][] = $ch;
        }
        foreach ($allChapters as $ch) {
            if (!$ch['parent_id']) {
                $subs = $subChaptersByParent[$ch['id']] ?? [];
                usort($subs, fn($a, $b) => ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']));
                $ch['subs'] = $subs;
                if ($ch['act_id']) $chaptersByAct[$ch['act_id']][] = $ch;
                else               $chaptersWithoutAct[] = $ch;
            }
        }
        $actModel = new Act();
        $acts     = $actModel->getAllByProject($pid);

        $customElementsByType = [];
        if ($db->exists('elements')) {
            $elementModel = new Element();
            foreach ($elementModel->getAllByProject($pid) as $elem) {
                $tid = $elem['template_element_id'];
                if (!isset($customElementsByType[$tid])) $customElementsByType[$tid] = [];
                $customElementsByType[$tid][] = $elem;
            }
        }

        // Build content list (same logic as generateEpub)
        $contentList = [];
        $addItem = function ($item, $type) use (&$contentList) {
            if (isset($item['is_exported']) && (int) $item['is_exported'] === 0) return;
            $contentList[] = [
                'title'   => $item['title'] ?? ($item['name'] ?? ''),
                'content' => $item['content'] ?? '',
                'type'    => $type,
            ];
        };

        if (!empty($templateElements)) {
            foreach ($templateElements as $elem) {
                if (!$elem['is_enabled']) continue;
                switch ($elem['element_type']) {
                    case 'section':
                        $sections = ($elem['section_placement'] === 'before') ? $sectionsBefore : $sectionsAfter;
                        foreach ($sections as $sec) {
                            if ($sec['type'] === $elem['element_subtype']) $addItem($sec, 'section');
                        }
                        break;
                    case 'act':
                        foreach ($acts as $act) {
                            if (!isset($chaptersByAct[$act['id']])) continue;
                            $contentList[] = ['title' => $act['title'], 'content' => $act['content'] ?? '', 'type' => 'act-title'];
                            $actChaps = $chaptersByAct[$act['id']];
                            usort($actChaps, fn($a, $b) => ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']));
                            foreach ($actChaps as $ch) {
                                $addItem($ch, 'chapter');
                                foreach ($ch['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                            }
                        }
                        break;
                    case 'chapter':
                        usort($chaptersWithoutAct, fn($a, $b) => ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']));
                        foreach ($chaptersWithoutAct as $ch) {
                            $addItem($ch, 'chapter');
                            foreach ($ch['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                        }
                        break;
                    case 'note':
                        foreach ($notes as $note) { $addItem($note, 'note'); }
                        break;
                    case 'scenario':
                        foreach ($scenarios as $sc) {
                            if (isset($sc['is_exported']) && (int) $sc['is_exported'] === 0) continue;
                            $sc['content'] = preg_replace('/<pre[^>]*>(?:<code[^>]*>)?([\s\S]*?)(?:<\/code>)?<\/pre>/i', '<p>$1</p>', $sc['content'] ?? '');
                            $addItem($sc, 'section');
                        }
                        break;
                    case 'element':
                        $elements = $customElementsByType[$elem['id']] ?? [];
                        $topElements = []; $subElementsByParent = [];
                        foreach ($elements as $e) {
                            if ($e['parent_id']) $subElementsByParent[$e['parent_id']][] = $e;
                            else $topElements[] = $e;
                        }
                        usort($topElements, fn($a, $b) => ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']));
                        foreach ($topElements as $topElem) {
                            $addItem($topElem, 'section');
                            $subs = $subElementsByParent[$topElem['id']] ?? [];
                            usort($subs, fn($a, $b) => ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']));
                            foreach ($subs as $sub) { $addItem($sub, 'sub-chapter'); }
                        }
                        break;
                }
            }
        } else {
            foreach ($sectionsBefore as $sec) { $addItem($sec, 'section'); }
            $rootItems = [];
            foreach ($acts as $act) { $act['is_act'] = true; $rootItems[] = $act; }
            foreach ($chaptersWithoutAct as $ch) { $ch['is_act'] = false; $rootItems[] = $ch; }
            usort($rootItems, fn($a, $b) => ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']));
            foreach ($rootItems as $item) {
                if ($item['is_act']) {
                    $contentList[] = ['title' => $item['title'], 'content' => $item['content'] ?? '', 'type' => 'act-title'];
                    if (isset($chaptersByAct[$item['id']])) {
                        $actChaps = $chaptersByAct[$item['id']];
                        usort($actChaps, fn($a, $b) => ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']));
                        foreach ($actChaps as $ch) {
                            $addItem($ch, 'chapter');
                            foreach ($ch['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                        }
                    }
                } else {
                    $addItem($item, 'chapter');
                    foreach ($item['subs'] as $sub) { $addItem($sub, 'sub-chapter'); }
                }
            }
            foreach ($sectionsAfter as $sec) { $addItem($sec, 'section'); }
            foreach ($notes as $note) { $addItem($note, 'note'); }
            foreach ($scenarios as $sc) {
                if (isset($sc['is_exported']) && (int) $sc['is_exported'] === 0) continue;
                $sc['content'] = preg_replace('/<pre[^>]*>(?:<code[^>]*>)?([\s\S]*?)(?:<\/code>)?<\/pre>/i', '<p>$1</p>', $sc['content'] ?? '');
                $addItem($sc, 'section');
            }
        }

        // Generate ODT body XML
        $x = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $cleanText = fn(string $html): string => trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $body  = '';
        $body .= '<text:p text:style-name="Title">'    . $x($project['title']) . '</text:p>';
        $body .= '<text:p text:style-name="Subtitle">' . $x($cleanText($author)) . '</text:p>';
        if (!empty($project['description'])) {
            $body .= '<text:p text:style-name="Description">' . $x($cleanText($project['description'])) . '</text:p>';
        }

        foreach ($contentList as $item) {
            if ($item['type'] === 'act-title') {
                $body .= '<text:p text:style-name="Act_Title">' . $x($item['title']) . '</text:p>';
            } else {
                if ($item['title']) {
                    $lvl  = ($item['type'] === 'sub-chapter') ? 2 : 1;
                    $body .= '<text:h text:style-name="Heading_' . $lvl . '" text:outline-level="' . $lvl . '">' . $x($item['title']) . '</text:h>';
                }
                $body .= $this->htmlToOdtParagraphs($item['content']);
            }
        }

        // Build ODT ZIP
        $tempFile = tempnam(sys_get_temp_dir(), 'odt');
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // mimetype must be first file, stored (no compression)
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->setCompressionIndex(0, \ZipArchive::CM_STORE);

        $zip->addFromString('META-INF/manifest.xml', $this->odtManifest());
        $zip->addFromString('styles.xml',             $this->odtStyles());
        $zip->addFromString('meta.xml',               $this->odtMeta($project['title'], strip_tags(html_entity_decode($author, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        $zip->addFromString('content.xml',            $this->odtContentXml($body));
        $zip->close();

        $filename = 'project_' . $pid . '_' . date('Ymd_His') . '.odt';
        header('Content-Type: application/vnd.oasis.opendocument.text');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }

    private function htmlToOdtParagraphs(string $html): string
    {
        if (trim($html) === '' || trim(strip_tags($html)) === '') {
            return '<text:p text:style-name="Text_Body"/>';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML('<html><head><meta charset="utf-8"/></head><body>' . $html . '</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING);
        $bodyEl = $dom->getElementsByTagName('body')->item(0);
        if (!$bodyEl) {
            return '<text:p text:style-name="Text_Body">' . htmlspecialchars(strip_tags($html), ENT_XML1, 'UTF-8') . '</text:p>';
        }

        $result = '';
        foreach ($bodyEl->childNodes as $node) {
            $result .= $this->odtBlock($node);
        }
        return $result ?: '<text:p text:style-name="Text_Body"/>';
    }

    private function odtBlock(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $t = trim($node->textContent);
            return $t !== '' ? '<text:p text:style-name="Text_Body">' . htmlspecialchars($t, ENT_XML1, 'UTF-8') . '</text:p>' : '';
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) return '';

        $tag = strtolower($node->nodeName);

        switch ($tag) {
            case 'p':
                $cls   = $node->hasAttribute('class') ? $node->getAttribute('class') : '';
                $style = 'Text_Body';
                if (str_contains($cls, 'ql-align-center'))  $style = 'Text_Center';
                elseif (str_contains($cls, 'ql-align-right'))   $style = 'Text_Right';
                elseif (str_contains($cls, 'ql-align-justify')) $style = 'Text_Justify';
                $inner = $this->odtInlineChildren($node);
                return '<text:p text:style-name="' . $style . '">' . $inner . '</text:p>';

            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                $lvl   = (int) $tag[1];
                $inner = $this->odtInlineChildren($node);
                return '<text:h text:style-name="Heading_' . $lvl . '" text:outline-level="' . $lvl . '">' . $inner . '</text:h>';

            case 'blockquote':
                $inner = $this->odtInlineChildren($node);
                return '<text:p text:style-name="Quotations">' . $inner . '</text:p>';

            case 'ul': case 'ol':
                $out = '';
                foreach ($node->childNodes as $li) {
                    if ($li->nodeType === XML_ELEMENT_NODE && strtolower($li->nodeName) === 'li') {
                        $out .= '<text:p text:style-name="List_Paragraph">' . $this->odtInlineChildren($li) . '</text:p>';
                    }
                }
                return $out;

            case 'br':
                return '<text:p text:style-name="Text_Body"/>';

            case 'div':
                $out = '';
                foreach ($node->childNodes as $child) { $out .= $this->odtBlock($child); }
                return $out ?: '<text:p text:style-name="Text_Body"/>';

            default:
                $inner = $this->odtInlineChildren($node);
                return $inner !== '' ? '<text:p text:style-name="Text_Body">' . $inner . '</text:p>' : '';
        }
    }

    private function odtInlineChildren(\DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) { $out .= $this->odtInline($child); }
        return $out;
    }

    private function odtInline(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->textContent, ENT_XML1, 'UTF-8');
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) return '';

        $tag   = strtolower($node->nodeName);
        $inner = $this->odtInlineChildren($node);

        return match ($tag) {
            'strong', 'b' => '<text:span text:style-name="Bold">'          . $inner . '</text:span>',
            'em',     'i' => '<text:span text:style-name="Italic">'        . $inner . '</text:span>',
            'u'           => '<text:span text:style-name="Underline">'     . $inner . '</text:span>',
            's', 'strike' => '<text:span text:style-name="Strikethrough">' . $inner . '</text:span>',
            'br'          => '<text:line-break/>',
            default       => $inner,
        };
    }

    private function odtManifest(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2">'
            . '<manifest:file-entry manifest:full-path="/" manifest:version="1.2" manifest:media-type="application/vnd.oasis.opendocument.text"/>'
            . '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="styles.xml"  manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="meta.xml"    manifest:media-type="text/xml"/>'
            . '</manifest:manifest>';
    }

    private function odtMeta(string $title, string $author): string
    {
        $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-meta'
            . ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"'
            . ' office:version="1.2">'
            . '<office:meta>'
            . '<dc:title>'    . $x($title)  . '</dc:title>'
            . '<dc:creator>'  . $x($author) . '</dc:creator>'
            . '<dc:language>fr</dc:language>'
            . '<meta:creation-date>' . gmdate('Y-m-d\TH:i:s') . '</meta:creation-date>'
            . '</office:meta></office:document-meta>';
    }

    private function odtContentXml(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-content'
            . ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
            . ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
            . ' xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"'
            . ' xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"'
            . ' office:version="1.2">'
            . '<office:automatic-styles/>'
            . '<office:body><office:text>'
            . $body
            . '</office:text></office:body>'
            . '</office:document-content>';
    }

    private function odtStyles(): string
    {
        $ps = function (string $name, string $textProps = '', string $paraProps = ''): string {
            $s = '<style:style style:name="' . $name . '" style:family="paragraph">';
            if ($paraProps) $s .= '<style:paragraph-properties ' . $paraProps . '/>';
            if ($textProps) $s .= '<style:text-properties ' . $textProps . '/>';
            return $s . '</style:style>';
        };
        $cs = function (string $name, string $textProps): string {
            return '<style:style style:name="' . $name . '" style:family="text">'
                . '<style:text-properties ' . $textProps . '/>'
                . '</style:style>';
        };

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-styles'
            . ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
            . ' xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"'
            . ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
            . ' xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"'
            . ' office:version="1.2">'

            . '<office:automatic-styles>'
            . '<style:page-layout style:name="PageLayout">'
            . '<style:page-layout-properties fo:page-width="21cm" fo:page-height="29.7cm"'
            . ' style:print-orientation="portrait"'
            . ' fo:margin-top="2.5cm" fo:margin-bottom="2.5cm"'
            . ' fo:margin-left="3cm" fo:margin-right="2.5cm"/>'
            . '</style:page-layout>'
            . '</office:automatic-styles>'

            . '<office:master-styles>'
            . '<style:master-page style:name="Standard" style:page-layout-name="PageLayout"/>'
            . '</office:master-styles>'

            . '<office:styles>'
            . '<style:default-style style:family="paragraph">'
            . '<style:paragraph-properties fo:line-height="150%"/>'
            . '<style:text-properties fo:font-size="12pt" fo:language="fr" fo:country="FR"/>'
            . '</style:default-style>'

            . $ps('Title',
                'fo:font-size="24pt" fo:font-weight="bold"',
                'fo:text-align="center" fo:margin-top="2cm" fo:margin-bottom="0.5cm"')
            . $ps('Subtitle',
                'fo:font-size="14pt" fo:color="#666666"',
                'fo:text-align="center" fo:margin-bottom="0.3cm"')
            . $ps('Description',
                'fo:font-size="11pt" fo:font-style="italic"',
                'fo:text-align="center" fo:margin-bottom="1cm"')
            . $ps('Act_Title',
                'fo:font-size="20pt" fo:font-weight="bold"',
                'fo:text-align="center" fo:break-before="page" fo:margin-top="3cm" fo:margin-bottom="1cm"')
            . $ps('Heading_1',
                'fo:font-size="16pt" fo:font-weight="bold"',
                'fo:break-before="page" fo:margin-top="0.5cm" fo:margin-bottom="0.4cm"')
            . $ps('Heading_2',
                'fo:font-size="13pt" fo:font-weight="bold"',
                'fo:margin-top="0.4cm" fo:margin-bottom="0.2cm"')
            . $ps('Heading_3',
                'fo:font-size="12pt" fo:font-weight="bold" fo:font-style="italic"',
                'fo:margin-top="0.3cm" fo:margin-bottom="0.1cm"')
            . $ps('Text_Body',
                'fo:font-size="12pt"',
                'fo:text-indent="1cm" fo:margin-bottom="0.15cm"')
            . $ps('Text_Center',
                'fo:font-size="12pt"',
                'fo:text-align="center" fo:margin-bottom="0.15cm"')
            . $ps('Text_Right',
                'fo:font-size="12pt"',
                'fo:text-align="end" fo:margin-bottom="0.15cm"')
            . $ps('Text_Justify',
                'fo:font-size="12pt"',
                'fo:text-align="justify" fo:text-indent="1cm" fo:margin-bottom="0.15cm"')
            . $ps('Quotations',
                'fo:font-size="11pt" fo:font-style="italic"',
                'fo:margin-left="1.5cm" fo:margin-right="1.5cm" fo:margin-bottom="0.2cm"')
            . $ps('List_Paragraph',
                'fo:font-size="12pt"',
                'fo:margin-left="1cm" fo:margin-bottom="0.1cm"')

            . $cs('Bold',          'fo:font-weight="bold"')
            . $cs('Italic',        'fo:font-style="italic"')
            . $cs('Underline',
                'style:text-underline-style="solid" style:text-underline-width="auto" style:text-underline-color="font-color"')
            . $cs('Strikethrough', 'style:text-line-through-style="solid"')

            . '</office:styles>'
            . '</office:document-styles>';
    }
}
