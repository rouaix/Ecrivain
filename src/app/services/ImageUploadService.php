<?php

/**
 * ImageUploadService — gestion centralisée du déplacement des fichiers uploadés.
 *
 * La validation (MIME, extension, taille) reste dans Controller::validateImageUpload()
 * car elle s'exécute avant d'instancier ce service.
 *
 * Usage type dans un contrôleur :
 *   $validation = $this->validateImageUpload($_FILES['image'], 2);
 *   if ($validation['success']) {
 *       $svc = new ImageUploadService();
 *       $path = $svc->move($_FILES['image'], $validation['extension'], 'uploads/chars/', 'char_42_abc');
 *       if ($path === null) { // erreur move }
 *   }
 */
class ImageUploadService
{
    /**
     * Déplace un fichier uploadé vers la destination finale.
     *
     * @param array  $file      Entrée $_FILES correspondante (avec tmp_name)
     * @param string $extension Extension validée (sans point), ex. "jpg"
     * @param string $dir       Répertoire de destination (relatif à ROOT ou absolu)
     * @param string $basename  Nom de base sans extension, ex. "cover" ou "char_42_abc"
     * @return string|null      Chemin relatif du fichier créé (ex. "uploads/chars/char_42_abc.jpg"),
     *                          ou null en cas d'erreur.
     */
    /**
     * Validates an uploaded image file (extension, MIME type, magic bytes, size).
     *
     * @param array $file     $_FILES entry
     * @param int   $maxSizeMB
     * @return array ['success' => bool, 'error' => string|null, 'extension' => string|null]
     */
    public function validate(array $file, int $maxSizeMB = 5): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Erreur lors de l\'upload.', 'extension' => null];
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            return ['success' => false, 'error' => 'Extension non autorisée. Formats acceptés : JPG, PNG, WEBP, GIF.', 'extension' => null];
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        $actualMimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $actualMimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }
        if (!$actualMimeType && function_exists('mime_content_type')) {
            $actualMimeType = mime_content_type($file['tmp_name']);
        }
        if (!$actualMimeType && $imageInfo !== false) {
            $actualMimeType = $imageInfo['mime'] ?? null;
        }
        if (!$actualMimeType) {
            return ['success' => false, 'error' => 'Impossible de détecter le type MIME du fichier.', 'extension' => null];
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($actualMimeType, $allowedMimeTypes)) {
            return ['success' => false, 'error' => 'Type de fichier invalide (détecté : ' . $actualMimeType . ').', 'extension' => null];
        }

        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'Le fichier n\'est pas une image valide.', 'extension' => null];
        }

        if ($file['size'] > $maxSizeMB * 1024 * 1024) {
            return ['success' => false, 'error' => 'Fichier trop volumineux (max ' . $maxSizeMB . ' Mo).', 'extension' => null];
        }

        return ['success' => true, 'error' => null, 'extension' => $ext];
    }

    public function move(array $file, string $extension, string $dir, string $basename): ?string
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return null;
            }
        }

        $filename = $basename . '.' . $extension;
        $dest     = rtrim($dir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }

        return $dest;
    }

    /**
     * Supprime les anciens fichiers correspondant à un motif glob.
     * Utile pour nettoyer un fichier précédent lors d'un remplacement.
     *
     * @param string $dir     Répertoire contenant les fichiers
     * @param string $pattern Motif glob (ex. "cover.*", "char_42_*")
     * @param string $except  Nom de fichier à exclure de la suppression (le nouveau)
     */
    public function deleteOld(string $dir, string $pattern, string $except = ''): void
    {
        $dir = rtrim($dir, '/');
        foreach (glob($dir . '/' . $pattern) ?: [] as $file) {
            if ($except === '' || basename($file) !== $except) {
                @unlink($file);
            }
        }
    }
}
