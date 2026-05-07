<?php

/**
 * ReadingContentService — Gère la construction du contenu de lecture avec pagination.
 */
class ReadingContentService
{
    private \DB\SQL $db;
    private Controller $controller;

    public function __construct(\DB\SQL $db, Controller $controller)
    {
        $this->db = $db;
        $this->controller = $controller;
    }

    /**
     * Prépare le contenu pour affichage (nettoie le HTML Quill).
     */
    public function prepareContent(?string $content): string
    {
        $decoded = html_entity_decode($content ?? '');
        return $this->controller->cleanQuillHtml($decoded);
    }

    /**
     * Prépare le contenu d'un scénario (déballage des blocs <pre><code>).
     */
    public function prepareScenario(?string $content): string
    {
        $html = html_entity_decode($content ?? '');
        $html = preg_replace_callback(
            '/<pre[^>]*>(?:<code[^>]*>)?([\s\S]*?)(?:<\/code>)?<\/pre>/i',
            function ($m) {
                $text = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                $lines = preg_split('/\r?\n/', trim($text));
                $result = '';
                foreach ($lines as $line) {
                    if (trim($line) !== '') {
                        $result .= '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
                    }
                }
                return $result ?: '';
            },
            $html
        );
        return $this->controller->cleanQuillHtml($html);
    }

    /**
     * Calcule le nombre de pages pour un contenu donné.
     */
    public function calculatePages(?string $content, int $linesPerPage = 38): int
    {
        $cleanContent = strip_tags(html_entity_decode($content ?? ''));
        $lines = 0;
        if ($cleanContent !== '') {
            $lines = ceil(strlen($cleanContent) / 80);
        }
        return max(1, ceil($lines / $linesPerPage));
    }

    /**
     * Charge et organise les données nécessaires pour la lecture.
     *
     * @param int $projectId ID du projet
     * @param int $linesPerPage Lignes par page
     * @return array Données organisées pour la lecture
     */
    public function loadReadingData(int $projectId, int $linesPerPage = 38): array
    {
        // Charger tous les chapitres
        $chapterModel = new Chapter();
        $allChapters = $chapterModel->getAllByProject($projectId);

        // Charger les actes
        $actModel = new Act();
        $acts = $actModel->getAllByProject($projectId);

        // Charger les sections
        $sectionModel = new Section();
        $sectionsBeforeChapters = $sectionModel->getBeforeChapters($projectId);
        $sectionsAfterChapters = $sectionModel->getAfterChapters($projectId);

        // Charger les notes (exclure les scénarios)
        $noteModel = new Note();
        $notes = array_values(array_filter(
            $noteModel->getAllByProject($projectId),
            fn($n) => ($n['type'] ?? 'note') !== 'scenario'
        ));

        // Charger les scénarios
        $scenarioModel = new Scenario();
        $scenarios = $scenarioModel->getAllByProject($projectId);

        // Charger les éléments personnalisés
        $customElements = [];
        $customSubElementsByParent = [];
        if ($this->db->exists('elements')) {
            $elementModel = new Element();
            $customElements = $elementModel->getAllByProject($projectId);
        }

        // Organiser les chapitres par hiérarchie
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
                if ($ch['act_id']) {
                    $chaptersByAct[$ch['act_id']][] = $ch;
                } else {
                    $chaptersWithoutAct[] = $ch;
                }
            }
        }

        // Organiser les éléments personnalisés
        $customElementsByType = [];
        foreach ($customElements as $elem) {
            $tid = $elem['template_element_id'];
            if (!isset($customElementsByType[$tid])) {
                $customElementsByType[$tid] = [];
            }

            if ($elem['parent_id']) {
                $customSubElementsByParent[$elem['parent_id']][] = $elem;
            } else {
                $customElementsByType[$tid][] = $elem;
            }
        }

        return [
            'allChapters' => $allChapters,
            'acts' => $acts,
            'sectionsBeforeChapters' => $sectionsBeforeChapters,
            'sectionsAfterChapters' => $sectionsAfterChapters,
            'notes' => $notes,
            'scenarios' => $scenarios,
            'customElements' => $customElements,
            'customElementsByType' => $customElementsByType,
            'customSubElementsByParent' => $customSubElementsByParent,
            'chaptersByAct' => $chaptersByAct,
            'chaptersWithoutAct' => $chaptersWithoutAct,
            'subChaptersByParent' => $subChaptersByParent,
            'linesPerPage' => $linesPerPage,
        ];
    }

    /**
     * Charge les éléments de template pour un projet.
     *
     * @param array $project Projet
     * @return array Éléments de template
     */
    public function loadTemplateElements(array $project): array
    {
        $templateElements = [];
        $db = $this->db;

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

            $templateElements = $template ? $templateModel->getElements($template['id']) : [];
        }

        return $templateElements;
    }

    /**
     * Construire le contenu de lecture et la table des matières.
     *
     * @param array $readingData Données de lecture chargées
     * @param array $templateElements Éléments de template
     * @param int $linesPerPage Lignes par page
     * @return array ['readingContent' => [...], 'tocItems' => [...], 'totalPages' => int]
     */
    public function buildReadingContent(array $readingData, array $templateElements): array
    {
        extract($readingData);

        $readingContent = [];
        $tocItems = [];
        $currentPage = 1;

        // Synopsis (préfixé avant le contenu du template s'il est exporté)
        if ($this->db->exists('synopsis')) {
            $synopsisModel = new Synopsis();
            $synopsisData = $synopsisModel->getByProject((int)($readingData['allChapters'][0]['project_id'] ?? 0));
            if ($synopsisData && ($synopsisData['is_exported'] ?? 1)) {
                $readingContent[] = [
                    'type' => 'synopsis',
                    'id' => $synopsisData['id'] ?? null,
                    'title' => 'Synopsis',
                    'logline' => $synopsisData['logline'] ?? '',
                    'pitch' => $this->prepareContent($synopsisData['pitch'] ?? ''),
                    'situation' => $synopsisData['situation'] ?? '',
                    'trigger_evt' => $synopsisData['trigger_evt'] ?? '',
                    'plot_point1' => $synopsisData['plot_point1'] ?? '',
                    'development' => $this->prepareContent($synopsisData['development'] ?? ''),
                    'midpoint' => $synopsisData['midpoint'] ?? '',
                    'crisis' => $synopsisData['crisis'] ?? '',
                    'climax' => $this->prepareContent($synopsisData['climax'] ?? ''),
                    'resolution' => $this->prepareContent($synopsisData['resolution'] ?? ''),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage,
                ];
            }
        }

        // BOUCLE SUR LES ÉLÉMENTS DE TEMPLATE
        foreach ($templateElements as $elem) {
            if (!$elem['is_enabled']) continue;

            switch ($elem['element_type']) {
                case 'section':
                    $this->processSectionElement(
                        $elem,
                        $sectionsBeforeChapters,
                        $sectionsAfterChapters,
                        $readingContent,
                        $tocItems,
                        $currentPage,
                        $linesPerPage
                    );
                    break;

                case 'act':
                    $this->processActElement(
                        $elem,
                        $acts,
                        $chaptersByAct,
                        $chaptersWithoutAct,
                        $subChaptersByParent,
                        $readingContent,
                        $tocItems,
                        $currentPage,
                        $linesPerPage
                    );
                    break;

                case 'chapter':
                    $this->processChapterElement(
                        $chaptersWithoutAct,
                        $subChaptersByParent,
                        $readingContent,
                        $tocItems,
                        $currentPage,
                        $linesPerPage
                    );
                    break;

                case 'note':
                    $this->processNoteElement(
                        $notes,
                        $readingContent,
                        $tocItems,
                        $currentPage,
                        $linesPerPage
                    );
                    break;

                case 'element':
                    $this->processCustomElement(
                        $elem,
                        $customElementsByType,
                        $customSubElementsByParent,
                        $readingContent,
                        $tocItems,
                        $currentPage,
                        $linesPerPage
                    );
                    break;

                case 'scenario':
                    $this->processScenarioElement(
                        $scenarios,
                        $readingContent,
                        $tocItems,
                        $currentPage,
                        $linesPerPage
                    );
                    break;

                case 'character':
                case 'file':
                    // Les personnages et fichiers ne sont pas affichés en mode lecture
                    break;
            }
        }

        return [
            'readingContent' => $readingContent,
            'tocItems' => $tocItems,
            'totalPages' => $currentPage - 1,
        ];
    }

    /**
     * Traite un élément de type section.
     */
    private function processSectionElement(
        array $elem,
        array $sectionsBeforeChapters,
        array $sectionsAfterChapters,
        array &$readingContent,
        array &$tocItems,
        int &$currentPage,
        int $linesPerPage
    ): void {
        $sections = ($elem['section_placement'] === 'before') ? $sectionsBeforeChapters : $sectionsAfterChapters;
        foreach ($sections as $sec) {
            if ($sec['type'] !== $elem['element_subtype']) continue;
            if (!($sec['is_exported'] ?? 1)) continue;

            $pages = $this->calculatePages($sec['content'], $linesPerPage);
            $typeName = Section::getTypeName($sec['type']);

            $readingContent[] = [
                'type' => 'section',
                'id' => $sec['id'],
                'title' => $sec['title'] ?: $typeName,
                'content' => $this->prepareContent($sec['content']),
                'page_start' => $currentPage,
                'page_end' => $currentPage + $pages - 1
            ];

            $tocItems[] = [
                'title' => $sec['title'] ?: $typeName,
                'page' => $currentPage,
                'level' => 0
            ];

            $currentPage += $pages;
        }
    }

    /**
     * Traite un élément de type act.
     */
    private function processActElement(
        array $elem,
        array $acts,
        array $chaptersByAct,
        array $chaptersWithoutAct,
        array $subChaptersByParent,
        array &$readingContent,
        array &$tocItems,
        int &$currentPage,
        int $linesPerPage
    ): void {
        foreach ($acts as $act) {
            $actChapters = $chaptersByAct[$act['id']] ?? [];
            $hasExportedChapters = false;
            foreach ($actChapters as $ch) {
                if ($ch['is_exported'] ?? 1) {
                    $hasExportedChapters = true;
                    break;
                }
            }

            $actHasContent = !empty($act['content']) && ($act['is_exported'] ?? 1);
            if (!$actHasContent && !$hasExportedChapters) {
                continue;
            }

            $tocItems[] = [
                'title' => $act['title'],
                'page' => $currentPage,
                'level' => 0
            ];

            if ($actHasContent) {
                $pages = $this->calculatePages($act['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'act',
                    'id' => $act['id'],
                    'title' => $act['title'],
                    'content' => $this->prepareContent($act['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $pages - 1
                ];
                $currentPage += $pages;
            }

            foreach ($actChapters as $ch) {
                if (!($ch['is_exported'] ?? 1)) continue;

                $pages = $this->calculatePages($ch['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'chapter',
                    'id' => $ch['id'],
                    'title' => $ch['title'],
                    'content' => $this->prepareContent($ch['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $pages - 1
                ];

                $tocItems[] = [
                    'title' => $ch['title'],
                    'page' => $currentPage,
                    'level' => 1
                ];

                $currentPage += $pages;

                // Sous-chapitres
                $subs = $subChaptersByParent[$ch['id']] ?? [];
                foreach ($subs as $sub) {
                    if (!($sub['is_exported'] ?? 1)) continue;

                    $subPages = $this->calculatePages($sub['content'], $linesPerPage);
                    $readingContent[] = [
                        'type' => 'subchapter',
                        'id' => $sub['id'],
                        'title' => $sub['title'],
                        'content' => $this->prepareContent($sub['content']),
                        'page_start' => $currentPage,
                        'page_end' => $currentPage + $subPages - 1
                    ];

                    $tocItems[] = [
                        'title' => $sub['title'],
                        'page' => $currentPage,
                        'level' => 2
                    ];

                    $currentPage += $subPages;
                }
            }
        }

        // Traiter les chapitres sans acte
        foreach ($chaptersWithoutAct as $ch) {
            if (!($ch['is_exported'] ?? 1)) continue;

            $pages = $this->calculatePages($ch['content'], $linesPerPage);
            $readingContent[] = [
                'type' => 'chapter',
                'id' => $ch['id'],
                'title' => $ch['title'],
                'content' => $this->prepareContent($ch['content']),
                'page_start' => $currentPage,
                'page_end' => $currentPage + $pages - 1
            ];

            $tocItems[] = [
                'title' => $ch['title'],
                'page' => $currentPage,
                'level' => 0
            ];

            $currentPage += $pages;

            $subs = $subChaptersByParent[$ch['id']] ?? [];
            foreach ($subs as $sub) {
                if (!($sub['is_exported'] ?? 1)) continue;

                $subPages = $this->calculatePages($sub['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'subchapter',
                    'id' => $sub['id'],
                    'title' => $sub['title'],
                    'content' => $this->prepareContent($sub['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $subPages - 1
                ];

                $tocItems[] = [
                    'title' => $sub['title'],
                    'page' => $currentPage,
                    'level' => 1
                ];

                $currentPage += $subPages;
            }
        }
    }

    /**
     * Traite un élément de type chapter (chapitre orphelin).
     */
    private function processChapterElement(
        array $chaptersWithoutAct,
        array $subChaptersByParent,
        array &$readingContent,
        array &$tocItems,
        int &$currentPage,
        int $linesPerPage
    ): void {
        foreach ($chaptersWithoutAct as $ch) {
            if (!($ch['is_exported'] ?? 1)) continue;

            $pages = $this->calculatePages($ch['content'], $linesPerPage);
            $readingContent[] = [
                'type' => 'chapter',
                'id' => $ch['id'],
                'title' => $ch['title'],
                'content' => $this->prepareContent($ch['content']),
                'page_start' => $currentPage,
                'page_end' => $currentPage + $pages - 1
            ];

            $tocItems[] = [
                'title' => $ch['title'],
                'page' => $currentPage,
                'level' => 0
            ];

            $currentPage += $pages;

            $subs = $subChaptersByParent[$ch['id']] ?? [];
            foreach ($subs as $sub) {
                if (!($sub['is_exported'] ?? 1)) continue;

                $subPages = $this->calculatePages($sub['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'subchapter',
                    'id' => $sub['id'],
                    'title' => $sub['title'],
                    'content' => $this->prepareContent($sub['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $subPages - 1
                ];

                $tocItems[] = [
                    'title' => $sub['title'],
                    'page' => $currentPage,
                    'level' => 1
                ];

                $currentPage += $subPages;
            }
        }
    }

    /**
     * Traite un élément de type note.
     */
    private function processNoteElement(
        array $notes,
        array &$readingContent,
        array &$tocItems,
        int &$currentPage,
        int $linesPerPage
    ): void {
        foreach ($notes as $note) {
            if (!($note['is_exported'] ?? 1)) continue;

            $pages = $this->calculatePages($note['content'], $linesPerPage);
            $readingContent[] = [
                'type' => 'note',
                'id' => $note['id'],
                'title' => $note['title'],
                'content' => $this->prepareContent($note['content']),
                'page_start' => $currentPage,
                'page_end' => $currentPage + $pages - 1
            ];

            $tocItems[] = [
                'title' => $note['title'],
                'page' => $currentPage,
                'level' => 0
            ];

            $currentPage += $pages;
        }
    }

    /**
     * Traite un élément personnalisé.
     */
    private function processCustomElement(
        array $elem,
        array $customElementsByType,
        array $customSubElementsByParent,
        array &$readingContent,
        array &$tocItems,
        int &$currentPage,
        int $linesPerPage
    ): void {
        $elements = $customElementsByType[$elem['id']] ?? [];
        foreach ($elements as $e) {
            if (!($e['is_exported'] ?? 1)) continue;

            $pages = $this->calculatePages($e['content'], $linesPerPage);
            $readingContent[] = [
                'type' => 'element',
                'id' => $e['id'],
                'title' => $e['title'],
                'content' => $this->prepareContent($e['content']),
                'page_start' => $currentPage,
                'page_end' => $currentPage + $pages - 1
            ];

            $tocItems[] = [
                'title' => $e['title'],
                'page' => $currentPage,
                'level' => $e['parent_id'] ? 1 : 0
            ];

            $currentPage += $pages;

            $subs = $customSubElementsByParent[$e['id']] ?? [];
            foreach ($subs as $sub) {
                if (!($sub['is_exported'] ?? 1)) continue;

                $subPages = $this->calculatePages($sub['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'subelement',
                    'id' => $sub['id'],
                    'title' => $sub['title'],
                    'content' => $this->prepareContent($sub['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $subPages - 1
                ];

                $tocItems[] = [
                    'title' => $sub['title'],
                    'page' => $currentPage,
                    'level' => 2
                ];

                $currentPage += $subPages;
            }
        }
    }

    /**
     * Traite un élément de type scénario.
     */
    private function processScenarioElement(
        array $scenarios,
        array &$readingContent,
        array &$tocItems,
        int &$currentPage,
        int $linesPerPage
    ): void {
        foreach ($scenarios as $sc) {
            if (!($sc['is_exported'] ?? 1)) continue;

            $pages = $this->calculatePages($sc['content'], $linesPerPage);
            $readingContent[] = [
                'type' => 'scenario',
                'id' => $sc['id'],
                'title' => $sc['title'],
                'content' => $this->prepareScenario($sc['content']),
                'page_start' => $currentPage,
                'page_end' => $currentPage + $pages - 1
            ];

            $tocItems[] = [
                'title' => $sc['title'],
                'page' => $currentPage,
                'level' => 0
            ];

            $currentPage += $pages;
        }
    }

    /**
     * Vérifie si le contenu de lecture contient des chapitres/actes.
     */
    public function hasRenderedChapters(array $readingContent): bool
    {
        foreach ($readingContent as $rc) {
            if (in_array($rc['type'], ['chapter', 'subchapter', 'act'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Construire le contenu de fallback si le template n'a pas produit de chapitres.
     */
    public function buildFallbackContent(
        array $readingData,
        array &$readingContent,
        array &$tocItems,
        int &$currentPage
    ): void {
        extract($readingData);

        // Chapters without act
        foreach ($chaptersWithoutAct as $ch) {
            if (!($ch['is_exported'] ?? 1)) continue;

            $pages = $this->calculatePages($ch['content'], $linesPerPage);
            $readingContent[] = [
                'type' => 'chapter',
                'id' => $ch['id'],
                'title' => $ch['title'],
                'content' => $this->prepareContent($ch['content']),
                'page_start' => $currentPage,
                'page_end' => $currentPage + $pages - 1
            ];
            $tocItems[] = ['title' => $ch['title'], 'page' => $currentPage, 'level' => 0];
            $currentPage += $pages;

            $subs = $subChaptersByParent[$ch['id']] ?? [];
            foreach ($subs as $sub) {
                if (!($sub['is_exported'] ?? 1)) continue;
                $subPages = $this->calculatePages($sub['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'subchapter',
                    'id' => $sub['id'],
                    'title' => $sub['title'],
                    'content' => $this->prepareContent($sub['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $subPages - 1
                ];
                $tocItems[] = ['title' => $sub['title'], 'page' => $currentPage, 'level' => 1];
                $currentPage += $subPages;
            }
        }

        // Acts with their chapters
        foreach ($acts as $act) {
            $actChapters = $chaptersByAct[$act['id']] ?? [];
            $actHasContent = !empty($act['content']) && ($act['is_exported'] ?? 1);
            $hasExportedChapters = false;
            foreach ($actChapters as $ch) {
                if ($ch['is_exported'] ?? 1) {
                    $hasExportedChapters = true;
                    break;
                }
            }
            if (!$actHasContent && !$hasExportedChapters) continue;

            $tocItems[] = ['title' => $act['title'], 'page' => $currentPage, 'level' => 0];

            if ($actHasContent) {
                $pages = $this->calculatePages($act['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'act',
                    'id' => $act['id'],
                    'title' => $act['title'],
                    'content' => $this->prepareContent($act['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $pages - 1
                ];
                $currentPage += $pages;
            }

            foreach ($actChapters as $ch) {
                if (!($ch['is_exported'] ?? 1)) continue;
                $pages = $this->calculatePages($ch['content'], $linesPerPage);
                $readingContent[] = [
                    'type' => 'chapter',
                    'id' => $ch['id'],
                    'title' => $ch['title'],
                    'content' => $this->prepareContent($ch['content']),
                    'page_start' => $currentPage,
                    'page_end' => $currentPage + $pages - 1
                ];
                $tocItems[] = ['title' => $ch['title'], 'page' => $currentPage, 'level' => 1];
                $currentPage += $pages;

                $subs = $subChaptersByParent[$ch['id']] ?? [];
                foreach ($subs as $sub) {
                    if (!($sub['is_exported'] ?? 1)) continue;
                    $subPages = $this->calculatePages($sub['content'], $linesPerPage);
                    $readingContent[] = [
                        'type' => 'subchapter',
                        'id' => $sub['id'],
                        'title' => $sub['title'],
                        'content' => $this->prepareContent($sub['content']),
                        'page_start' => $currentPage,
                        'page_end' => $currentPage + $subPages - 1
                    ];
                    $tocItems[] = ['title' => $sub['title'], 'page' => $currentPage, 'level' => 2];
                    $currentPage += $subPages;
                }
            }
        }
    }
}
