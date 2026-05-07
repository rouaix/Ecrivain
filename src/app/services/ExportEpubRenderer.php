<?php

/**
 * ExportEpubRenderer — generates an EPUB 3 file for a project.
 * Extracted from ProjectExportController::generateEpub().
 */
class ExportEpubRenderer
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

        $author = $this->user['username'] ?? 'Auteur inconnu';

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
                    $coverDir = 'data/' . $this->user['email'] . '/projects/' . $pid . '/sections/';
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

        $templateElements = $this->loadTemplateElements($project);

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
        $db = $this->f3->get('DB');
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
            $title         = $item['title'] ?? ($item['name'] ?? '');
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

    private function loadTemplateElements(array $project): array
    {
        $db = $this->f3->get('DB');
        if (!$db->exists('templates') || !$db->exists('template_elements')) {
            return [];
        }
        $templateId    = $project['template_id'] ?? null;
        $templateModel = new ProjectTemplate();
        if (!$templateId) {
            $template = $templateModel->getDefault();
        } else {
            $template = $templateModel->findAndCast(['id=?', $templateId]);
            $template = $template ? $template[0] : $templateModel->getDefault();
        }
        return $template ? $templateModel->getElements($template['id']) : [];
    }

    private function sanitizeToXhtml(string $html): string
    {
        $html = preg_replace('/<(br|hr|img|input)([^>]*)>/i', '<$1$2 />', $html);
        $html = str_replace('//>', '/>', $html);
        $html = preg_replace('/ & /', ' &amp; ', $html);
        return $html;
    }
}
