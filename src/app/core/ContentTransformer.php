<?php

/**
 * ContentTransformer — shared HTML/text conversion utilities.
 *
 * Previously duplicated in ApiController, McpController and ProjectExportController.
 * Centralised here so all modules benefit from the same logic.
 */
class ContentTransformer
{
    /**
     * Convert HTML (Quill output) to plain text, preserving paragraph breaks.
     */
    public static function htmlToText(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Replace block-level closing tags with line breaks before stripping.
        $text = str_replace(
            ['</p>', '<br>', '<br/>', '<br />', '</li>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'],
            "\n",
            $html
        );
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse runs of 3+ newlines to a single blank line.
        return trim(preg_replace('/\n{3,}/', "\n\n", $text));
    }

    /**
     * Count words in an HTML string.
     */
    public static function countWords(string $html): int
    {
        return str_word_count(self::htmlToText($html));
    }

    /**
     * Remove spurious consecutive empty <p> tags produced by Quill on repeated saves.
     * Mirrors Controller::cleanQuillHtml() — kept here for use outside controllers.
     */
    public static function cleanQuillHtml(string $html): string
    {
        if (empty(trim($html))) {
            return $html;
        }
        $html = preg_replace('/(<p><br><\/p>\s*){2,}/i', '<p><br></p>', $html);
        $html = preg_replace('/(<p>\s*<\/p>\s*){2,}/i', '', $html);
        $html = preg_replace('/^(<p><br><\/p>\s*)+/i', '', $html);
        $html = preg_replace('/(<p><br><\/p>\s*)+$/i', '<p><br></p>', $html);
        return $html;
    }
}
