<?php

/**
 * McpToolHandlerService — Dispatcher MCP.
 * Déléague les appels d'outils aux handlers spécialisés.
 */
class McpToolHandlerService
{
    private \DB\SQL $db;
    private Base $f3;
    private int $userId;

    // Handlers par domaine
    private ProjectToolHandler $projectHandler;
    private ActToolHandler $actHandler;
    private ChapterToolHandler $chapterHandler;
    private SectionToolHandler $sectionHandler;
    private NoteToolHandler $noteHandler;
    private CharacterToolHandler $characterHandler;
    private ElementToolHandler $elementHandler;
    private ImageToolHandler $imageHandler;
    private SynopsisToolHandler $synopsisHandler;
    private ExportToolHandler $exportHandler;
    private SearchToolHandler $searchHandler;

    public function __construct(\DB\SQL $db, Base $f3, int $userId)
    {
        $this->db = $db;
        $this->f3 = $f3;
        $this->userId = $userId;

        // Initialisation des handlers
        $this->projectHandler = new ProjectToolHandler($db, $userId);
        $this->actHandler = new ActToolHandler($db, $userId);
        $this->chapterHandler = new ChapterToolHandler($db, $userId);
        $this->sectionHandler = new SectionToolHandler($db, $userId);
        $this->noteHandler = new NoteToolHandler($db, $userId);
        $this->characterHandler = new CharacterToolHandler($db, $userId);
        $this->elementHandler = new ElementToolHandler($db, $userId);
        $this->imageHandler = new ImageToolHandler($db, $f3, $userId);
        $this->synopsisHandler = new SynopsisToolHandler($db, $userId);
        $this->exportHandler = new ExportToolHandler($db, $userId);
        $this->searchHandler = new SearchToolHandler($db, $userId);
    }

    /**
     * Exécute un outil MCP.
     */
    public function callTool(string $name, array $arguments): array
    {
        try {
            return match ($name) {
                // Projets
                'list_projects'    => $this->projectHandler->listProjects(),
                'get_project'      => $this->projectHandler->getProject((int) ($arguments['id'] ?? 0)),
                'create_project'   => $this->projectHandler->createProject($arguments),
                'update_project'   => $this->projectHandler->updateProject($arguments),
                'delete_project'   => $this->projectHandler->deleteProject((int) ($arguments['id'] ?? 0)),

                // Actes
                'list_acts'        => $this->actHandler->listActs((int) ($arguments['project_id'] ?? 0)),
                'get_act'          => $this->actHandler->getAct((int) ($arguments['id'] ?? 0)),
                'create_act'       => $this->actHandler->createAct($arguments),
                'update_act'       => $this->actHandler->updateAct($arguments),
                'delete_act'       => $this->actHandler->deleteAct((int) ($arguments['id'] ?? 0)),

                // Chapitres
                'list_chapters'    => $this->chapterHandler->listChapters(
                    (int) ($arguments['project_id'] ?? 0),
                    isset($arguments['act_id']) ? (int) $arguments['act_id'] : null
                ),
                'get_chapter'      => $this->chapterHandler->getChapter((int) ($arguments['id'] ?? 0)),
                'create_chapter'   => $this->chapterHandler->createChapter($arguments),
                'update_chapter'   => $this->chapterHandler->updateChapter($arguments),
                'delete_chapter'   => $this->chapterHandler->deleteChapter((int) ($arguments['id'] ?? 0)),

                // Sections
                'list_sections'    => $this->sectionHandler->listSections((int) ($arguments['project_id'] ?? 0)),
                'get_section'      => $this->sectionHandler->getSection((int) ($arguments['id'] ?? 0)),
                'create_section'   => $this->sectionHandler->createSection($arguments),
                'update_section'   => $this->sectionHandler->updateSection($arguments),
                'delete_section'   => $this->sectionHandler->deleteSection((int) ($arguments['id'] ?? 0)),

                // Notes
                'list_notes'       => $this->noteHandler->listNotes((int) ($arguments['project_id'] ?? 0)),
                'get_note'         => $this->noteHandler->getNote((int) ($arguments['id'] ?? 0)),
                'create_note'      => $this->noteHandler->createNote($arguments),
                'update_note'      => $this->noteHandler->updateNote($arguments),
                'delete_note'      => $this->noteHandler->deleteNote((int) ($arguments['id'] ?? 0)),

                // Personnages
                'list_characters'  => $this->characterHandler->listCharacters((int) ($arguments['project_id'] ?? 0)),
                'get_character'    => $this->characterHandler->getCharacter((int) ($arguments['id'] ?? 0)),
                'create_character' => $this->characterHandler->createCharacter($arguments),
                'update_character' => $this->characterHandler->updateCharacter($arguments),
                'delete_character' => $this->characterHandler->deleteCharacter((int) ($arguments['id'] ?? 0)),

                // Éléments
                'list_element_types' => $this->elementHandler->listElementTypes((int) ($arguments['project_id'] ?? 0)),
                'list_elements'    => $this->elementHandler->listElements((int) ($arguments['project_id'] ?? 0)),
                'get_element'      => $this->elementHandler->getElement((int) ($arguments['id'] ?? 0)),
                'create_element'   => $this->elementHandler->createElement($arguments),
                'update_element'   => $this->elementHandler->updateElement($arguments),
                'delete_element'   => $this->elementHandler->deleteElement((int) ($arguments['id'] ?? 0)),

                // Images
                'list_images'      => $this->imageHandler->listImages((int) ($arguments['project_id'] ?? 0)),
                'delete_image'     => $this->imageHandler->deleteImage(
                    (int) ($arguments['project_id'] ?? 0),
                    (int) ($arguments['image_id'] ?? 0)
                ),

                // Synopsis
                'get_synopsis'     => $this->synopsisHandler->getSynopsis((int) ($arguments['project_id'] ?? 0)),
                'update_synopsis'  => $this->synopsisHandler->updateSynopsis(
                    (int) ($arguments['project_id'] ?? 0),
                    $arguments
                ),

                // Export
                'export_markdown'  => $this->exportHandler->exportMarkdown((int) ($arguments['project_id'] ?? 0)),

                // Recherche
                'search'           => $this->searchHandler->search($arguments['query'] ?? ''),

                default            => $this->fail('Outil inconnu : ' . $name),
            };
        } catch (\Throwable $e) {
            error_log('McpToolHandlerService::callTool error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }

    // =========================================================================
    // Méthodes utilitaires (pour la rétrocompatibilité)
    // =========================================================================

    private function ok(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false];
    }

    private function fail(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => '**Erreur :** ' . $message]], 'isError' => true];
    }
}
