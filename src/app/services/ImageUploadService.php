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
