<?php

/**
 * ImageApiService — Service API pour les opérations sur les images (fichiers projet).
 */
class ImageApiService
{
    private \DB\SQL $db;
    private ApiFetchService $fetchService;
    private Base $f3;

    public function __construct(\DB\SQL $db, ApiFetchService $fetchService, Base $f3)
    {
        $this->db = $db;
        $this->fetchService = $fetchService;
        $this->f3 = $f3;
    }

    /**
     * Liste les images d'un projet.
     */
    public function listImages(int $projectId, string $baseUrl): array
    {
        $rows = $this->db->exec(
            'SELECT id, filename, filepath, filetype, filesize, uploaded_at
             FROM project_files WHERE project_id = ? ORDER BY uploaded_at DESC',
            [$projectId]
        );
        $images = [];
        foreach ($rows as $r) {
            $r['url'] = $baseUrl . '/' . ltrim(str_replace('\\', '/', $r['filepath']), '/');
            $r['size_kb'] = round($r['filesize'] / 1024, 1);
            $images[] = $r;
        }
        return ['images' => $images];
    }

    /**
     * Enregistre une nouvelle image en base de données.
     */
    public function createImage(int $projectId, string $filename, string $filepath, string $filetype, int $filesize): array
    {
        $this->db->exec(
            'INSERT INTO project_files (project_id, filename, filepath, filetype, filesize) VALUES (?, ?, ?, ?, ?)',
            [$projectId, $filename, $filepath, $filetype, $filesize]
        );
        $id = (int)$this->db->lastInsertId('project_files');
        return [
            'id' => $id,
            'filename' => $filename,
            'filepath' => $filepath,
            'filetype' => $filetype,
            'filesize' => $filesize,
        ];
    }

    /**
     * Supprime une image.
     */
    public function deleteImage(int $projectId, int $fileId, int $userId): bool
    {
        // Vérifier que le projet appartient à l'utilisateur
        if (!$this->hasProjectAccess($projectId, $userId)) {
            return false;
        }

        $file = $this->db->exec('SELECT filepath FROM project_files WHERE id = ? AND project_id = ?', [$fileId, $projectId]);
        if (!$file) {
            return false;
        }

        $filepath = $this->f3->get('BASEPATH') . ltrim($file[0]['filepath'], '/');
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $this->db->exec('DELETE FROM project_files WHERE id = ?', [$fileId]);
        return true;
    }

    /**
     * Valide un upload d'image.
     */
    public function validateImageUpload(array $file, int $maxSizeMb): array
    {
        $maxSize = $maxSizeMb * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Erreur lors de l\'upload: code ' . $file['error']];
        }

        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Fichier trop volumineux (max ' . $maxSizeMb . 'Mo).'];
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes)) {
            return ['success' => false, 'error' => 'Type de fichier non autorisé: ' . $mime];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($extension), $allowedExts)) {
            return ['success' => false, 'error' => 'Extension de fichier non autorisée.'];
        }

        return ['success' => true, 'extension' => $extension];
    }

    /**
     * Récupère l'email du propriétaire d'un projet.
     */
    public function getProjectOwnerEmail(int $projectId): ?string
    {
        $row = $this->db->exec('SELECT u.email FROM projects p JOIN users u ON p.user_id = u.id WHERE p.id = ?', [$projectId]);
        return $row ? $row[0]['email'] : null;
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
