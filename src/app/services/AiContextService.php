<?php

/**
 * AiContextService — Charge le contexte document pour les requêtes IA.
 */
class AiContextService
{
    private \DB\SQL $db;
    private int $userId;

    public function __construct(\DB\SQL $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * Charge le contexte d'un document (chapitre, section, etc.) pour une requête IA.
     *
     * @param string $contextType Type de contexte ('chapter', 'section', 'synopsis', etc.)
     * @param int $contextId ID du document
     * @return array Context document ou tableau vide
     */
    public function loadDocumentContext(string $contextType, int $contextId): array
    {
        switch ($contextType) {
            case 'chapter':
                return $this->loadChapterContext($contextId);

            case 'section':
                return $this->loadSectionContext($contextId);

            case 'act':
                return $this->loadActContext($contextId);

            case 'synopsis':
                return $this->loadSynopsisContext($contextId);

            case 'note':
                return $this->loadNoteContext($contextId);

            case 'element':
                return $this->loadElementContext($contextId);

            default:
                return [];
        }
    }

    /**
     * Charge le contexte d'un chapitre.
     */
    private function loadChapterContext(int $chapterId): array
    {
        $chapter = new \Chapter($this->db);
        $chapter->load(['id=?', $chapterId]);

        if ($chapter->dry()) {
            return [];
        }

        // Vérifier l'accès
        $projectModel = new \Project($this->db);
        if (!$projectModel->count(['id=? AND user_id=?', $chapter->project_id, $this->userId])) {
            return [];
        }

        $projectTitle = $this->db->exec('SELECT title FROM projects WHERE id=?', [$chapter->project_id])[0]['title'] ?? '';
        $actTitle = '';
        if ($chapter->act_id) {
            $actTitle = $this->db->exec('SELECT title FROM acts WHERE id=?', [$chapter->act_id])[0]['title'] ?? '';
        }

        return [
            'type' => 'chapter',
            'id' => $chapter->id,
            'project' => $projectTitle,
            'act' => $actTitle,
            'title' => $chapter->title,
            'content' => $chapter->content,
            'resume' => $chapter->resume ?? '',
            'project_id' => $chapter->project_id,
            'act_id' => $chapter->act_id,
        ];
    }

    /**
     * Charge le contexte d'une section.
     */
    private function loadSectionContext(int $sectionId): array
    {
        $section = new \Section($this->db);
        $section->load(['id=?', $sectionId]);

        if ($section->dry()) {
            return [];
        }

        // Vérifier l'accès
        $projectModel = new \Project($this->db);
        if (!$projectModel->count(['id=? AND user_id=?', $section->project_id, $this->userId])) {
            return [];
        }

        $projectTitle = $this->db->exec('SELECT title FROM projects WHERE id=?', [$section->project_id])[0]['title'] ?? '';

        return [
            'type' => 'section',
            'id' => $section->id,
            'project' => $projectTitle,
            'title' => $section->title,
            'content' => $section->content,
            'section_type' => $section->type,
            'project_id' => $section->project_id,
        ];
    }

    /**
     * Charge le contexte d'un acte.
     */
    private function loadActContext(int $actId): array
    {
        $act = new \Act($this->db);
        $act->load(['id=?', $actId]);

        if ($act->dry()) {
            return [];
        }

        // Vérifier l'accès
        $projectModel = new \Project($this->db);
        if (!$projectModel->count(['id=? AND user_id=?', $act->project_id, $this->userId])) {
            return [];
        }

        $projectTitle = $this->db->exec('SELECT title FROM projects WHERE id=?', [$act->project_id])[0]['title'] ?? '';

        return [
            'type' => 'act',
            'id' => $act->id,
            'project' => $projectTitle,
            'title' => $act->title,
            'content' => $act->content,
            'resume' => $act->resume ?? '',
            'project_id' => $act->project_id,
        ];
    }

    /**
     * Charge le contexte d'un synopsis.
     */
    private function loadSynopsisContext(int $synopsisId): array
    {
        if (!$this->db->exists('synopsis')) {
            return [];
        }

        $synopsisModel = new \Synopsis($this->db);
        $synopsis = $synopsisModel->findAndCast(['id=?', $synopsisId]);

        if (empty($synopsis)) {
            return [];
        }

        $synopsisData = $synopsis[0];
        $projectId = $synopsisData['project_id'] ?? 0;

        // Vérifier l'accès
        $projectModel = new \Project($this->db);
        if (!$projectModel->count(['id=? AND user_id=?', $projectId, $this->userId])) {
            return [];
        }

        $projectTitle = $this->db->exec('SELECT title FROM projects WHERE id=?', [$projectId])[0]['title'] ?? '';

        return [
            'type' => 'synopsis',
            'id' => $synopsisData['id'],
            'project' => $projectTitle,
            'title' => 'Synopsis',
            'logline' => $synopsisData['logline'] ?? '',
            'pitch' => $synopsisData['pitch'] ?? '',
            'situation' => $synopsisData['situation'] ?? '',
            'trigger_evt' => $synopsisData['trigger_evt'] ?? '',
            'plot_point1' => $synopsisData['plot_point1'] ?? '',
            'development' => $synopsisData['development'] ?? '',
            'midpoint' => $synopsisData['midpoint'] ?? '',
            'crisis' => $synopsisData['crisis'] ?? '',
            'climax' => $synopsisData['climax'] ?? '',
            'resolution' => $synopsisData['resolution'] ?? '',
            'project_id' => $projectId,
        ];
    }

    /**
     * Charge le contexte d'une note.
     */
    private function loadNoteContext(int $noteId): array
    {
        $note = new \Note($this->db);
        $note->load(['id=?', $noteId]);

        if ($note->dry()) {
            return [];
        }

        // Vérifier l'accès
        $projectModel = new \Project($this->db);
        if (!$projectModel->count(['id=? AND user_id=?', $note->project_id, $this->userId])) {
            return [];
        }

        $projectTitle = $this->db->exec('SELECT title FROM projects WHERE id=?', [$note->project_id])[0]['title'] ?? '';

        return [
            'type' => 'note',
            'id' => $note->id,
            'project' => $projectTitle,
            'title' => $note->title,
            'content' => $note->content,
            'note_type' => $note->type ?? 'note',
            'project_id' => $note->project_id,
        ];
    }

    /**
     * Charge le contexte d'un élément.
     */
    private function loadElementContext(int $elementId): array
    {
        if (!$this->db->exists('elements')) {
            return [];
        }

        $element = new \Element($this->db);
        $element->load(['id=?', $elementId]);

        if ($element->dry()) {
            return [];
        }

        // Vérifier l'accès
        $projectModel = new \Project($this->db);
        if (!$projectModel->count(['id=? AND user_id=?', $element->project_id, $this->userId])) {
            return [];
        }

        $projectTitle = $this->db->exec('SELECT title FROM projects WHERE id=?', [$element->project_id])[0]['title'] ?? '';

        return [
            'type' => 'element',
            'id' => $element->id,
            'project' => $projectTitle,
            'title' => $element->title,
            'content' => $element->content,
            'element_type' => $element->type ?? 'element',
            'project_id' => $element->project_id,
        ];
    }

    /**
     * Formate le contexte document pour l'inclusion dans un prompt.
     *
     * @param array $docContext Contexte document chargé
     * @param string $task Type de tâche ('continue', 'rephrase', 'custom', etc.)
     * @return string Texte de contexte formaté
     */
    public function formatContextText(array $docContext, string $task): string
    {
        if (empty($docContext)) {
            return '';
        }

        if ($task === 'rephrase' || $task === 'custom') {
            $parts = [];
            if (!empty($docContext['project'])) {
                $parts[] = 'Projet: ' . $docContext['project'];
            }
            if (!empty($docContext['act'])) {
                $parts[] = 'Acte: ' . $docContext['act'];
            }
            if (!empty($docContext['title'])) {
                $typeLabel = ucfirst($docContext['type'] ?? 'document');
                $parts[] = $typeLabel . ': ' . $docContext['title'];
            }
            return '\n[Contexte] ' . implode(' | ', $parts);
        }

        // Pour les autres tâches, inclure le contenu
        $rawContent = strip_tags($docContext['content'] ?? '');
        $contentLen = mb_strlen($rawContent);

        if ($contentLen > 3000) {
            $rawContent = '…' . mb_substr($rawContent, $contentLen - 3000);
        }

        $lines = [];
        if (!empty($docContext['project'])) {
            $lines[] = "Projet: " . $docContext['project'];
        }
        if (!empty($docContext['act'])) {
            $lines[] = "Acte: " . $docContext['act'];
        }
        if (!empty($docContext['title'])) {
            $typeLabel = ucfirst($docContext['type'] ?? 'document');
            $lines[] = $typeLabel . ": " . $docContext['title'];
        }
        if (!empty($rawContent)) {
            $lines[] = "Fin du texte actuel:\n" . $rawContent;
        }

        return "\n\n[CONTEXTE]\n" . implode("\n", $lines);
    }
}
