<?php

/**
 * Loads and prepares all data needed to render the project show page.
 * Extracted from ProjectController::show() to keep the controller thin.
 */
class ProjectShowService
{
    private $f3;
    private array $user;

    public function __construct($f3, array $user)
    {
        $this->f3   = $f3;
        $this->user = $user;
    }

    /**
     * Returns the full data array expected by project/show.html.
     */
    public function load(int $pid, array $project, bool $isOwner): array
    {
        $wpp = $project['words_per_page'] ?: 350;
        $lpp = $project['lines_per_page'] ?: 38;

        [$panelConfig, $panelLabels, $customElementPanels, $customElementsByType, $panelCss] =
            $this->buildPanelData($pid, $project, $wpp);

        [$chaptersByAct, $chaptersWithoutAct, $totalWords, $totalLines] =
            $this->loadChapters($pid, $wpp, $lpp);

        $sectionsBeforeChapters = $this->enrichSections(
            $this->loadModel(Section::class)->getBeforeChapters($pid), $wpp, $lpp, $totalWords, $totalLines
        );
        $sectionsAfterChapters = $this->enrichSections(
            $this->loadModel(Section::class)->getAfterChapters($pid), $wpp, $lpp, $totalWords, $totalLines
        );

        $notes      = $this->loadNotes($pid, $wpp, $lpp, $totalWords, $totalLines);
        $scenarios  = $this->supHtml($this->loadModel(Scenario::class)->getAllByProject($pid));
        $characters = $this->supHtml($this->loadModel(Character::class)->getAllByProject($pid));
        $acts       = $this->loadActs($pid, $wpp, $chaptersByAct);
        $glossaryEntries = $this->loadModel(GlossaryEntry::class)->getAllByProject($pid);
        $files      = $this->loadFiles($pid);

        $coverImage = null;
        foreach ($sectionsBeforeChapters as $sec) {
            if ($sec['type'] === 'cover' && !empty($sec['image_path'])) {
                $coverImage = $sec['image_path'];
                break;
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
            'has_content'      => (count($acts) > 0 || array_sum(array_map('count', $chaptersByAct)) + count($chaptersWithoutAct) > 0),
        ];

        $sectionBeforeConfig = $panelConfig['_sectionBeforeConfig'] ?? null;
        $sectionAfterConfig  = $panelConfig['_sectionAfterConfig']  ?? null;
        unset($panelConfig['_sectionBeforeConfig'], $panelConfig['_sectionAfterConfig']);

        $orphanStats = ['chapters' => count($chaptersWithoutAct), 'pages' => 0];
        if (!empty($chaptersWithoutAct)) {
            $orphanTotalWc = array_sum(array_column($chaptersWithoutAct, 'total_wc'));
            $orphanStats['pages'] = ceil($orphanTotalWc / $wpp);
        }

        $beforeGroups = $this->filterBeforeGroups($sectionsBeforeChapters, $sectionBeforeConfig);
        $afterGroups  = $this->prepareSectionGroups(
            $sectionsAfterChapters,
            ['postface', 'appendices', 'back_cover'],
            $sectionAfterConfig
        );

        return [
            'project'              => $project,
            'acts'                 => $acts,
            'chaptersByAct'        => $chaptersByAct,
            'chaptersWithoutAct'   => $chaptersWithoutAct,
            'orphanStats'          => $orphanStats,
            'characters'           => $characters,
            'coverImage'           => $coverImage,
            'stats'                => $stats,
            'beforeGroups'         => $beforeGroups,
            'afterGroups'          => $afterGroups,
            'notes'                => $notes,
            'scenarios'            => $scenarios,
            'files'                => $files,
            'glossaryEntries'      => $glossaryEntries,
            'authorName'           => $this->getUserFullName(),
            'template'             => null,
            'templateElements'     => [],
            'panelConfig'          => $panelConfig,
            'panelLabels'          => $panelLabels,
            'customElementPanels'  => $customElementPanels,
            'customElementsByType' => $customElementsByType,
            'panelCss'             => $panelCss,
            'isOwner'              => $isOwner,
            'isCollaborator'       => !$isOwner,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function loadModel(string $class)
    {
        return new $class();
    }

    private function cleanForWordCount(string $html): string
    {
        $s = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $s = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\xC2\xA0", ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function wordStats(string $content, int $wpp, int $lpp): array
    {
        $clean = $this->cleanForWordCount($content);
        $wc    = $clean !== '' ? str_word_count($clean) : 0;
        $lines = $clean !== '' ? ceil(mb_strlen($clean) / 80) : 0;
        return [
            'words' => $wc,
            'lines' => $lines,
            'pages' => max(0, max(ceil($wc / $wpp), ceil($lines / $lpp))),
        ];
    }

    private function enrichSections(array $sections, int $wpp, int $lpp, int &$totalWords, int &$totalLines): array
    {
        foreach ($sections as &$sec) {
            $isExported = ($sec['is_exported'] ?? 1);
            $s          = $this->wordStats($sec['content'] ?? '', $wpp, $lpp);
            $sec['wc']    = $s['words'];
            $sec['lines'] = $s['lines'];
            $sec['pages'] = $s['pages'];
            $sec['type_name']        = \Section::getTypeName($sec['type']);
            $sec['is_exported_attr'] = $isExported ? 'checked' : '';
            if ($isExported) {
                $totalWords += $s['words'];
                $totalLines += $s['lines'];
            }
        }
        return $sections;
    }

    private function loadChapters(int $pid, int $wpp, int $lpp): array
    {
        $chapterModel = new Chapter();
        $allChapters  = $this->supHtml($chapterModel->getAllByProject($pid));

        $subChaptersByParent = [];
        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) {
                $subChaptersByParent[$ch['parent_id']][] = $ch;
            }
        }

        $totalWords = 0;
        $totalLines = 0;
        $chaptersByAct      = [];
        $chaptersWithoutAct = [];

        foreach ($allChapters as $ch) {
            if ($ch['parent_id']) continue;

            $isExported = ($ch['is_exported'] ?? 1);
            $myStats    = $this->wordStats($ch['content'] ?? '', $wpp, $lpp);
            $ch['wc']    = $myStats['words'];
            $ch['lines'] = $myStats['lines'];
            $ch['pages'] = $myStats['pages'];
            $ch['is_exported_attr'] = $isExported ? 'checked' : '';
            if ($isExported) {
                $totalWords += $myStats['words'];
                $totalLines += $myStats['lines'];
            }

            $subs      = $subChaptersByParent[$ch['id']] ?? [];
            $ch['subs']     = [];
            $subsWc    = 0;
            $subsLines = 0;
            $subsPages = 0;

            foreach ($subs as $sub) {
                $subIsExported = ($sub['is_exported'] ?? 1);
                $subStats      = $this->wordStats($sub['content'] ?? '', $wpp, $lpp);
                $sub['wc']     = $subStats['words'];
                $sub['lines']  = $subStats['lines'];
                $sub['pages']  = $subStats['pages'];
                $sub['is_exported_attr'] = $subIsExported ? 'checked' : '';
                $ch['subs'][]  = $sub;
                $subsWc       += $subStats['words'];
                $subsLines    += $subStats['lines'];
                $subsPages    += $subStats['pages'];
                if ($subIsExported) {
                    $totalWords += $subStats['words'];
                    $totalLines += $subStats['lines'];
                }
            }

            $ch['total_wc']     = $ch['wc'] + $subsWc;
            $ch['total_lines']  = $ch['lines'] + $subsLines;
            $ch['total_pages']  = $ch['pages'] + $subsPages;
            $ch['wc_subs_only'] = $subsWc;

            if ($ch['act_id']) {
                $chaptersByAct[$ch['act_id']][] = $ch;
            } else {
                $chaptersWithoutAct[] = $ch;
            }
        }

        return [$chaptersByAct, $chaptersWithoutAct, $totalWords, $totalLines];
    }

    private function loadNotes(int $pid, int $wpp, int $lpp, int &$totalWords, int &$totalLines): array
    {
        $noteModel = new Note();
        $raw = array_values(array_filter(
            $this->supHtml($noteModel->getAllByProject($pid)),
            fn($n) => ($n['type'] ?? 'note') !== 'scenario'
        ));

        foreach ($raw as &$note) {
            $isExported = ($note['is_exported'] ?? 1);
            $s = $this->wordStats($note['content'] ?? '', $wpp, $lpp);
            $note['wc']    = $s['words'];
            $note['lines'] = $s['lines'];
            $note['pages'] = $s['pages'];
            $note['is_exported_attr'] = $isExported ? 'checked' : '';
            if ($isExported) {
                $totalWords += $s['words'];
                $totalLines += $s['lines'];
            }
        }
        return $raw;
    }

    private function loadActs(int $pid, int $wpp, array $chaptersByAct): array
    {
        $acts = $this->supHtml((new Act())->getAllByProject($pid));

        foreach ($acts as &$act) {
            $actChapters = $chaptersByAct[$act['id']] ?? [];
            $act['stats_chapters']    = count($actChapters);
            $actTotalWc               = array_sum(array_column($actChapters, 'total_wc'));
            $act['stats_pages']       = ceil($actTotalWc / $wpp);
            $isExported               = ($act['is_exported'] ?? 1);
            $act['is_exported_attr']  = $isExported ? 'checked' : '';
        }
        unset($act);
        return $acts;
    }

    private function loadFiles(int $pid): array
    {
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
                'uploaded_at'    => $f->uploaded_at,
            ];
        }
        return $files;
    }

    private function buildPanelData(int $pid, array $project, int $wpp): array
    {
        $panelLabels = [
            'section_before' => 'Sections avant les chapitres',
            'section_after'  => 'Sections après les chapitres',
            'act'            => ['singular' => 'Acte',       'plural' => 'Actes'],
            'chapter'        => ['singular' => 'Chapitre',   'plural' => 'Chapitres'],
            'note'           => ['singular' => 'Note',       'plural' => 'Notes'],
            'character'      => ['singular' => 'Personnage', 'plural' => 'Personnages'],
            'file'           => ['singular' => 'Fichier',    'plural' => 'Fichiers'],
        ];
        $panelConfig = [
            'section_before' => true, 'content'  => true,
            'section_after'  => true, 'note'      => true,
            'character'      => true, 'file'      => true,
            'has_acts'       => true, 'scenario'  => true,
            'synopsis'       => true,
        ];
        $customElementPanels  = [];
        $customElementsByType = [];
        $panelCss             = '';
        $sectionBeforeConfig  = null;
        $sectionAfterConfig   = null;

        try {
            $templateElements = $this->loadTemplateElements($project);

            if (!empty($templateElements)) {
                foreach ($panelConfig as $k => $_) {
                    $panelConfig[$k] = false;
                }

                $db = $this->f3->get('DB');
                if ($db->exists('elements')) {
                    $customElementsByType = $this->buildCustomElements($pid, $wpp);
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
                            $sectionBeforeConfig          = $sectionBeforeConfig ?? [];
                            $sectionBeforeConfig[$subtype] = $label;
                        } else {
                            $sectionAfterConfig          = $sectionAfterConfig ?? [];
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
                    } elseif ($type === 'synopsis') {
                        $key = 'synopsis';
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
                'section_after'  => true, 'note'      => true,
                'character'      => true, 'file'      => true,
                'scenario'       => true, 'synopsis'  => true,
            ];
        }

        foreach (['act', 'chapter', 'note', 'character', 'file'] as $pk) {
            $panelLabels[$pk]['singular_lc'] = strtolower($panelLabels[$pk]['singular']);
            $panelLabels[$pk]['plural_lc']   = strtolower($panelLabels[$pk]['plural']);
        }

        // Stash section configs in panelConfig for retrieval by load()
        $panelConfig['_sectionBeforeConfig'] = $sectionBeforeConfig;
        $panelConfig['_sectionAfterConfig']  = $sectionAfterConfig;

        return [$panelConfig, $panelLabels, $customElementPanels, $customElementsByType, $panelCss];
    }

    private function buildCustomElements(int $pid, int $wpp): array
    {
        $elementModel   = new Element();
        $customElements = $elementModel->getAllByProject($pid);

        $subElementsByParent = [];
        foreach ($customElements as $elem) {
            if ($elem['parent_id']) {
                $subElementsByParent[$elem['parent_id']][] = $elem;
            }
        }

        $byType = [];
        foreach ($customElements as $elem) {
            if ($elem['parent_id']) continue;

            $tid  = $elem['template_element_id'];
            $clean = $this->cleanForWordCount($elem['content'] ?? '');
            $elem['wc']              = $clean !== '' ? str_word_count($clean) : 0;
            $elem['is_exported_attr'] = ($elem['is_exported'] ?? 1) ? 'checked' : '';

            $subsWc = 0;
            $elem['subs'] = [];
            foreach ($subElementsByParent[$elem['id']] ?? [] as $sub) {
                $cleanSub = $this->cleanForWordCount($sub['content'] ?? '');
                $sub['wc']              = $cleanSub !== '' ? str_word_count($cleanSub) : 0;
                $sub['is_exported_attr'] = ($sub['is_exported'] ?? 1) ? 'checked' : '';
                $subsWc += $sub['wc'];
                $elem['subs'][] = $sub;
            }

            $elem['total_wc']     = $elem['wc'] + $subsWc;
            $elem['wc_subs_only'] = $subsWc;

            $byType[$tid][] = $elem;
        }
        return $byType;
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

    private function buildPanelOrderCss(array $templateElements): string
    {
        if (empty($templateElements)) return '';

        $selectorMap = [
            'section_before' => '#panel-before',
            'section_after'  => '#panel-after',
            'act'            => '#panel-content',
            'chapter'        => '#panel-content',
            'note'           => '#panel-notes',
            'character'      => '#panel-characters',
            'file'           => '#panel-files',
            'scenario'       => '#panel-scenarios',
        ];

        $rules    = [];
        $assigned = [];
        $order    = 0;

        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) { $order++; continue; }

            $key = $te['element_type'];
            if ($key === 'section') {
                $key = 'section_' . ($te['section_placement'] ?? 'before');
            }

            if ($key === 'element') {
                $rules[] = '#panel-element-' . (int)$te['id'] . '{order:' . $order . '}';
            } elseif (isset($selectorMap[$key]) && !isset($assigned[$selectorMap[$key]])) {
                $selector            = $selectorMap[$key];
                $assigned[$selector] = true;
                $rules[]             = $selector . '{order:' . $order . '}';
            }
            $order++;
        }
        return implode("\n", $rules);
    }

    private function prepareSectionGroups(array $sections, array $typeOrder, ?array $sectionConfig = null): array
    {
        $grouped = [];
        foreach ($sections as $s) {
            $grouped[$s['type']][] = $s;
        }

        if ($sectionConfig !== null) {
            $orderedTypes = array_keys($sectionConfig);
            foreach ($sections as $s) {
                if (!in_array($s['type'], $orderedTypes)) $orderedTypes[] = $s['type'];
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
                if (!in_array($t, $seenTypes)) $orderedTypes[] = $t;
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
                'show_add'    => $isMulti,
            ];
        }
        return $finalGroups;
    }

    private function filterBeforeGroups(array $sections, ?array $sectionConfig = null): array
    {
        $grouped = [];
        foreach ($sections as $s) {
            $grouped[$s['type']][] = $s;
        }

        if ($sectionConfig !== null) {
            $types = array_keys($sectionConfig);
            foreach ($sections as $s) {
                if (!in_array($s['type'], $types)) $types[] = $s['type'];
            }
        } else {
            $types = ['cover', 'preface', 'introduction', 'prologue'];
            foreach ($sections as $s) {
                if (!in_array($s['type'], $types)) $types[] = $s['type'];
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
                'show_add'    => false,
            ];
        }
        return $final;
    }

    private function getUserFullName(): string
    {
        if (empty($this->user['email'])) return '';

        $email = $this->user['email'];
        $email = str_replace(['..', '/', '\\', "\0"], '', $email);
        $email = preg_replace('/[^a-zA-Z0-9@._-]/', '', $email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';

        $root = rtrim($this->f3->get('ROOT'), '/\\');
        $file = $root . '/data/' . $email . '/profile.json';
        if (!file_exists($file)) return '';

        $profile = json_decode(file_get_contents($file), true);
        if (!is_array($profile)) return '';

        return trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? ''));
    }

    private function supHtml($data)
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
            $data = preg_replace('/\x{00A0}/u', ' ', $data);
            $data = preg_replace('/\s+/u', ' ', $data);
        }
        return $data;
    }
}
