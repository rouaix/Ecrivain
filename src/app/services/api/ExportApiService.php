<?php

/**
 * ExportApiService — Service API pour les opérations d'export.
 */
class ExportApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
    }

    /**
     * Génère le contenu d'export Markdown pour un projet.
     */
    public function generateMarkdownExport(int $projectId): ?string
    {
        // Vérification que le projet existe
        $project = $this->db->exec('SELECT id FROM projects WHERE id = ?', [$projectId]);
        if (!$project) {
            return null;
        }

        $exporter = new ProjectExportController();
        return $exporter->generateExportContent($projectId, 'markdown');
    }

    /**
     * Génère le contenu d'export pour un projet.
     */
    public function exportProject(int $projectId, string $format): ?string
    {
        $project = $this->db->exec('SELECT id FROM projects WHERE id = ?', [$projectId]);
        if (!$project) {
            return null;
        }

        $exporter = new ProjectExportController();
        return $exporter->generateExportContent($projectId, $format);
    }

    /**
     * Vérifie si l'utilisateur a accès à un projet.
     */
    public function hasProjectAccess(int $projectId, int $userId): bool
    {
        return !empty($this->db->exec(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$projectId, $userId]
        ));
    }
}
