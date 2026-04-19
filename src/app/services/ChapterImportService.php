<?php

/**
 * ChapterImportService — convertit divers formats de fichier en HTML Quill-compatible.
 * Supporte : .md, .txt, .docx, .odt
 */
class ChapterImportService
{
    /**
     * @return array{0: string, 1: string}  [$title, $html]
     */
    public function parse(string $raw, string $format): array
    {
        $title = '';
        $html  = '';

        switch ($format) {
            case 'md':
                if (preg_match('/^#\s+(.+)/m', $raw, $m)) {
                    $title = trim($m[1]);
                    $raw   = preg_replace('/^#\s+.+\r?\n?/m', '', $raw, 1);
                }
                $pd = new Parsedown();
                $pd->setSafeMode(true);
                $html = $pd->text(trim($raw));
                break;

            case 'txt':
                $lines = preg_split('/\r\n|\n|\r/', $raw);
                foreach ($lines as $i => $line) {
                    if (trim($line) !== '') {
                        $title = trim($line);
                        array_splice($lines, $i, 1);
                        break;
                    }
                }
                $paragraphs = preg_split('/\n{2,}/', trim(implode("\n", $lines)));
                foreach ($paragraphs as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $html .= '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>';
                    }
                }
                break;

            case 'docx':
                $html = $this->parseDocx($raw, $title);
                break;

            case 'odt':
                $html = $this->parseOdt($raw, $title);
                break;
        }

        return [$title, $html];
    }

    private function parseDocx(string $raw, string &$title): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tmp, $raw);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            unlink($tmp);
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tmp);
        return $xml !== false ? $this->docxXmlToHtml($xml, $title) : '';
    }

    private function docxXmlToHtml(string $xml, string &$title): string
    {
        $prev = libxml_use_internal_errors(true);
        $dom  = new DOMDocument();
        $dom->loadXML($xml);
        libxml_use_internal_errors($prev);

        $wNS        = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $html       = '';
        $titleFound = false;

        $body = $dom->getElementsByTagNameNS($wNS, 'body');
        if (!$body->length) return '';

        foreach ($body->item(0)->getElementsByTagNameNS($wNS, 'p') as $para) {
            $styleEl = $para->getElementsByTagNameNS($wNS, 'pStyle');
            $style   = $styleEl->length ? strtolower($styleEl->item(0)->getAttributeNS($wNS, 'val')) : '';

            $text = '';
            foreach ($para->getElementsByTagNameNS($wNS, 'r') as $run) {
                foreach ($run->getElementsByTagNameNS($wNS, 't') as $t) {
                    $text .= $t->nodeValue;
                }
            }
            if (trim($text) === '') continue;

            $escaped  = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            $isBold   = $para->getElementsByTagNameNS($wNS, 'b')->length > 0;
            $isItalic = $para->getElementsByTagNameNS($wNS, 'i')->length > 0;
            if ($isItalic) $escaped = "<em>$escaped</em>";
            if ($isBold)   $escaped = "<strong>$escaped</strong>";

            if (str_starts_with($style, 'heading') || $style === 'title') {
                $level = max(1, min(6, (int) substr($style, -1) ?: 1));
                if (!$titleFound && $level === 1) {
                    $title      = $text;
                    $titleFound = true;
                    continue;
                }
                $html .= "<h$level>$escaped</h$level>";
            } else {
                $html .= "<p>$escaped</p>";
            }
        }

        return $html;
    }

    private function parseOdt(string $raw, string &$title): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'odt_');
        file_put_contents($tmp, $raw);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            unlink($tmp);
            return '';
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        unlink($tmp);
        return $xml !== false ? $this->odtXmlToHtml($xml, $title) : '';
    }

    private function odtXmlToHtml(string $xml, string &$title): string
    {
        $prev = libxml_use_internal_errors(true);
        $dom  = new DOMDocument();
        $dom->loadXML($xml);
        libxml_use_internal_errors($prev);

        $textNS     = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
        $officeNS   = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
        $html       = '';
        $titleFound = false;

        $bodyList = $dom->getElementsByTagNameNS($officeNS, 'text');
        if (!$bodyList->length) return '';

        foreach ($bodyList->item(0)->childNodes as $node) {
            if (!($node instanceof DOMElement)) continue;
            $text = $node->textContent;
            if (trim($text) === '') continue;

            $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            if ($node->localName === 'h') {
                $level = max(1, min(6, (int) ($node->getAttributeNS($textNS, 'outline-level') ?: 1)));
                if (!$titleFound && $level === 1) {
                    $title      = $text;
                    $titleFound = true;
                    continue;
                }
                $html .= "<h$level>$escaped</h$level>";
            } else {
                $html .= "<p>$escaped</p>";
            }
        }

        return $html;
    }
}
