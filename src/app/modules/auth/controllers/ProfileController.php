<?php

class ProfileController extends Controller
{
    public function index()
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->f3->reroute('/login');
            return;
        }

        // Load extended profile data from JSON
        $profileData = $this->loadProfileData($user['email']);

        // Merge with user data (but priority to JSON for extended fields if any conflict, though keys should be distinct)
        // Ensure defaults for extended fields to avoid Undefined Index with DEBUG=3
        $defaults = [
            'lastname' => '',
            'firstname' => '',
            'dob' => '',
            'bio' => ''
        ];
        $profileData = array_merge($defaults, $profileData);

        $data = array_merge($user, $profileData);

        $this->render('auth/profile.html', [
            'title' => 'Mon Profil',
            'user' => $data,
            'errors' => [],
            'success' => $this->f3->get('GET.success')
        ]);
    }

    public function update()
    {
        $userModel = new User();
        $user = $this->currentUser();
        if (!$user) {
            $this->f3->reroute('/login');
            return;
        }

        $userId = $user['id'];
        $email = $user['email'];
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Extended fields
        $lastname = trim($_POST['lastname'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $dob = $_POST['dob'] ?? '';
        $bio = $_POST['bio'] ?? ''; // TinyMCE content

        $errors = [];

        // 1. Update Core User Data (Username, Password)
        $userModel->load(['id=?', $userId]);
        if (!$userModel->dry()) {
            // Check username uniqueness if changed
            if ($username !== $user['username']) {
                if ($userModel->count(['username=? AND id!=?', $username, $userId])) {
                    $errors[] = 'Ce nom d’utilisateur est déjà pris.';
                } else {
                    // Rename photo check BEFORE saving new username (to keep track of old)
                    // Actually we have $user['username'] as old username
                    $this->renamePhoto($email, $user['username'], $username);
                    $userModel->username = $username;
                }
            }

            if (!empty($password)) {
                $userModel->password = password_hash($password, PASSWORD_DEFAULT);
            }

            if (empty($errors)) {
                $userModel->save();
            }
        } else {
            $errors[] = 'Utilisateur introuvable.';
        }

        // 2. Handle Photo Upload
        if (empty($errors) && !empty($_FILES['photo']['name'])) {
            $uploadResult = $this->handlePhotoUpload($email, $username);
            if ($uploadResult['error']) {
                $errors[] = $uploadResult['error'];
            }
        }

        // 3. Update Extended Profile Data (JSON)
        if (empty($errors)) {
            $this->saveProfileData($email, [
                'lastname' => $lastname,
                'firstname' => $firstname,
                'dob' => $dob,
                'bio' => $bio
            ]);

            $this->f3->reroute('/profile?success=1');
        } else {
            // Reload with errors and submitted data
            $currentData = $this->loadProfileData($email);
            $submittedData = [
                'username' => $username, // Core
                // Don't sync password back
                'lastname' => $lastname,
                'firstname' => $firstname,
                'dob' => $dob,
                'bio' => $bio
            ];
            // Merge to keep other potential fields from JSON
            $displayData = array_merge($user, $currentData, $submittedData);

            $this->render('auth/profile.html', [
                'title' => 'Mon Profil',
                'user' => $displayData,
                'errors' => $errors
            ]);
        }
    }

    public function photo()
    {
        $username = $this->f3->get('PARAMS.username');

        // We need to find the email associated with this username to locate the folder
        // For security, only allow viewing photo of logged in user? 
        // OR the requirement implies public/system access. 
        // Given the path structure `data/user@email/photo_$username`, we need the email.

        // If the user is logged in and requests their own photo, it's easy.
        // If we need to support arbitrary users, we'd need to lookup email by username from DB.

        $userModel = new User();
        $userModel->load(['username=?', $username]);
        if ($userModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $email = $userModel->email;
        $filename = 'photo_' . $username; // The requirement says photo_$username. We need to handle extension.

        $dir = $this->getDataDir($email);

        // Find file with any image extension
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filePath = null;
        $mime = '';

        foreach ($extensions as $ext) {
            if (file_exists($dir . '/' . $filename . '.' . $ext)) {
                $filePath = $dir . '/' . $filename . '.' . $ext;
                $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                break;
            }
        }

        if ($filePath) {
            header('Content-Type: ' . $mime);
            readfile($filePath);
        } else {
            // Return default placeholder or 404
            // Let's generate a simple placeholder or return 404
            $this->f3->error(404);
        }
    }

    private function loadProfileData($email)
    {
        $file = $this->getDataDir($email) . '/profile.json';
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
            return is_array($json) ? $json : [];
        }
        return [];
    }

    private function saveProfileData($email, $data)
    {
        $dir = $this->getDataDir($email);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/profile.json';

        // Merge with existing to avoid data loss if we partial update later
        $existing = $this->loadProfileData($email);
        $final = array_merge($existing, $data);

        file_put_contents($file, json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function getDataDir($email)
    {
        // Use sanitized path from parent Controller
        return $this->getUserDataDir($email);
    }

    private function renamePhoto($email, $oldUsername, $newUsername)
    {
        $dir = $this->getDataDir($email);
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($extensions as $ext) {
            $oldFile = $dir . '/photo_' . $oldUsername . '.' . $ext;
            if (file_exists($oldFile)) {
                $newFile = $dir . '/photo_' . $newUsername . '.' . $ext;
                rename($oldFile, $newFile);
                // Stop after finding the first one (assuming only one exists)
                break;
            }
        }
    }

    private function handlePhotoUpload($email, $username)
    {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Erreur lors de l\'upload.'];
        }

        // SECURITY: Multi-level validation to prevent malicious uploads

        // 1. Validate file extension (whitelist)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            return ['error' => 'Extension non autorisée. Formats acceptés : JPG, PNG, WEBP, GIF.'];
        }

        // 2. Verify actual MIME type (not client-provided)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($actualMimeType, $allowedMimeTypes)) {
            return ['error' => 'Type de fichier invalide (MIME type détecté : ' . $actualMimeType . ').'];
        }

        // 3. Verify it's actually an image (using GD)
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['error' => 'Le fichier n\'est pas une image valide.'];
        }

        // 4. Check file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['error' => 'Fichier trop volumineux (max 5 Mo).'];
        }

        $dir = $this->getDataDir($email);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Use validated extension
        $targetName = 'photo_' . $username . '.' . $ext;
        $targetPath = $dir . '/' . $targetName;

        // Requirement: "chargement photo et enregistrament dans data/user@email/photo_$username"
        // Also: "Affichage de la photo du user en rond en haut à gauche" implies we might want to resize it or just CSS it.
        // Let's just move it for now.

        // Remove old photos of this user (incase extension changed)
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        foreach ($extensions as $e) {
            $p = $dir . '/photo_' . $username . '.' . $e;
            if (file_exists($p)) {
                unlink($p);
            }
        }

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['error' => null];
        }

        return ['error' => 'Erreur lors de l\'enregistrement du fichier.'];
    }
}
