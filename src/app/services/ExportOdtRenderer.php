<?php

/**
 * ExportOdtRenderer — genere un fichier ODT (OpenDocument Text) pour un projet.
 *
 * Extrait de ProjectExportController pour isoler la logique de rendu ODT.
 * La verification d'acces (hasProjectAccess) doit etre faite par l'appelant.
 */
class ExportOdtRenderer
{
    private $f3;
    private array $user;

    public function __construct($f3, array $user)
    {
        $this->f3   = $f3;
        $this->user = $user;
    }

    public function render(int $pid): void
    {
        $projectModel = new Project();
        $project      = $projectModel->findAndCast(['id=?', $pid])[0];
        $author       = $this->user['username'] ?? 'Auteur inconnu';

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

        // Build content list
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
        $x         = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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
                    $lvl   = ($item['type'] === 'sub-chapter') ? 2 : 1;
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
