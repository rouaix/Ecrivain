<?php

/**
 * ImageToolHandler — Gère les outils MCP liés aux images.
 */
class ImageToolHandler extends BaseToolHandler
{
    private Base $f3;

    public function __construct(\DB\SQL $db, Base $f3, int $userId)
    {
        parent::__construct($db, $userId);
        $this->f3 = $f3;
    }

    /**
     * Liste toutes les images d'un projet.
     */
    public function listImages(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec(
            'SELECT id, filename, filesize, uploaded_at FROM project_files WHERE project_id=? ORDER BY uploaded_at DESC',
            [$pid]
        );
        
        if (!$rows) return $this->ok("Aucune image dans ce projet.");
        
        $md = "# Images du projet $pid\n\n";
        foreach ($rows as $r) {
            $kb = round($r['filesize'] / 1024, 1);
            $md .= "- **{$r['filename']}** (ID: {$r['id']}) — {$kb} Ko · {$r['uploaded_at']}\n";
        }
        return $this->ok($md);
    }

    /**
     * Supprime une image.
     */
    public function deleteImage(int $pid, int $imageId): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec(
            'SELECT id, filename, filepath FROM project_files WHERE id=? AND project_id=?',
            [$imageId, $pid]
        );
        
        if (!$rows) return $this->fail("Image $imageId introuvable.");
        
        $filepath = $this->f3->get('BASEPATH') . $rows[0]['filepath'];
        if (file_exists($filepath)) unlink($filepath);
        
        $this->db->exec('DELETE FROM project_files WHERE id=?', [$imageId]);
        return $this->ok("Image {$rows[0]['filename']} (ID: $imageId) supprimée.");
    }
}
