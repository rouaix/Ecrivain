<?php

class ProjectFileController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function uploadFile()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Projet non trouvé']);
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Aucun fichier téléchargé']);
            return;
        }

        $file    = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($file['size'] > $maxSize) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 10 MB)']);
            return;
        }

        $userFilesDir = $this->f3->get('ROOT') . '/' . $this->getUserDataDir($user['email']) . '/files';
        if (!is_dir($userFilesDir)) {
            mkdir($userFilesDir, 0755, true);
        }

        $originalName = basename($file['name']);
        $ext          = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName     = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $uniqueName   = $safeName . '_' . time() . '.' . $ext;
        $filepath     = $userFilesDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement du fichier']);
            return;
        }

        $relativePath = $this->getUserDataDir($user['email']) . '/files/' . $uniqueName;

        $fileModel             = new ProjectFile();
        $fileModel->project_id = $pid;
        $fileModel->filename   = $originalName;
        $fileModel->filepath   = $relativePath;
        $fileModel->filetype   = $file['type'];
        $fileModel->filesize   = $file['size'];
        $fileModel->comment    = $_POST['comment'] ?? '';
        $fileModel->save();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'file_id' => $fileModel->id]);
    }

    public function downloadFile()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $fid  = (int) $this->f3->get('PARAMS.fid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            $this->f3->error(403);
            return;
        }

        $fileModel = new ProjectFile();
        $fileModel->load(['id=? AND project_id=?', $fid, $pid]);

        if ($fileModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $absolutePath = $this->f3->get('ROOT') . '/' . $fileModel->filepath;

        if (!file_exists($absolutePath)) {
            $this->f3->error(404);
            return;
        }

        header('Content-Type: ' . $fileModel->filetype);
        header('Content-Disposition: inline; filename="' . $fileModel->filename . '"');
        header('Content-Length: ' . filesize($absolutePath));
        readfile($absolutePath);
        exit;
    }

    public function deleteFile()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $fid  = (int) $this->f3->get('PARAMS.fid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            $this->f3->error(403);
            return;
        }

        $fileModel = new ProjectFile();
        $fileModel->load(['id=? AND project_id=?', $fid, $pid]);

        if ($fileModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $absolutePath = $this->f3->get('ROOT') . '/' . $fileModel->filepath;
        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        $fileModel->erase();
        $this->f3->reroute('/project/' . $pid);
    }
}
