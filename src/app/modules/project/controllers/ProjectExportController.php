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
        (new ExportEpubRenderer($this->f3, $this->currentUser()))->render($pid);
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
        $templateElements = $this->loadProjectTemplateElements($project);

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

    // sanitizeToXhtml() and generateEpub() -> ExportEpubRenderer

    public function exportOdt()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        if (!class_exists('ZipArchive')) {
            $this->f3->error(500, 'Extension ZipArchive manquante');
            return;
        }
        (new ExportOdtRenderer($this->f3, $this->currentUser()))->render($pid);
    }

    // generateOdt() and ODT helpers -> ExportOdtRenderer
}
