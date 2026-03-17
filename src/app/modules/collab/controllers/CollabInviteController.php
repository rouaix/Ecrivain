<?php

class CollabInviteController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * GET /project/@pid/collaborateurs — liste des collaborateurs (propriétaire)
     */
    public function index()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $this->requireOwner($pid);

        $invite  = new CollaboratorInvite();
        $project = $this->loadProject($pid);

        $this->render('collab/invite.html', [
            'title'         => 'Collaborateurs — ' . ($project['title'] ?? ''),
            'project'       => $project,
            'collaborators' => $invite->findByProject($pid),
            'errors'        => [],
        ]);
    }

    /**
     * POST /project/@pid/collaborateurs/invite — envoyer une invitation
     */
    public function invite()
    {
        $pid    = (int) $this->f3->get('PARAMS.pid');
        $user   = $this->currentUser();
        $this->requireOwner($pid);

        $invite = new CollaboratorInvite();
        $email  = trim($_POST['email'] ?? '');
        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        } elseif (strtolower($email) === strtolower($user['email'])) {
            $errors[] = 'Vous ne pouvez pas vous inviter vous-même.';
        } else {
            $target = $this->db->exec('SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [$email]);
            if (empty($target)) {
                $errors[] = 'Aucun compte trouvé pour cette adresse e-mail.';
            } else {
                $targetId = (int) $target[0]['id'];
                if ($invite->existsForUser($pid, $targetId)) {
                    $errors[] = 'Cet utilisateur a déjà été invité sur ce projet.';
                } else {
                    $invite->invite($pid, $user['id'], $targetId);

                    $projectRow   = $this->db->exec('SELECT title FROM projects WHERE id = ?', [$pid]);
                    $projectTitle = $projectRow[0]['title'] ?? 'un projet';
                    $scheme       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $invitationsUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                        . $this->f3->get('BASE') . '/collab/invitations';

                    (new NotificationService())->sendCollabInvitationEmail(
                        $email, $user['username'], $projectTitle, $invitationsUrl
                    );

                    $this->f3->reroute('/project/' . $pid . '/collaborateurs');
                    return;
                }
            }
        }

        $project = $this->loadProject($pid);
        $this->render('collab/invite.html', [
            'title'         => 'Collaborateurs — ' . ($project['title'] ?? ''),
            'project'       => $project,
            'collaborators' => $invite->findByProject($pid),
            'errors'        => $errors,
        ]);
    }

    /**
     * POST /project/@pid/collaborateurs/@uid/remove — retirer un collaborateur
     */
    public function remove()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $uid = (int) $this->f3->get('PARAMS.uid');
        $this->requireOwner($pid);

        (new CollaboratorInvite())->removeUser($pid, $uid);
        $this->f3->reroute('/project/' . $pid . '/collaborateurs');
    }

    /**
     * GET /collab/invitations — invitations reçues (collaborateur)
     */
    public function myInvitations()
    {
        $user   = $this->currentUser();
        $invite = new CollaboratorInvite();

        $this->render('collab/my_invitations.html', [
            'title'       => 'Mes invitations',
            'invitations' => $invite->findByUser($user['id']),
        ]);
    }

    /**
     * POST /collab/invitation/@id/accept
     */
    public function accept()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();
        (new CollaboratorInvite())->accept($id, $user['id']);
        $this->f3->reroute('/collab/invitations');
    }

    /**
     * POST /collab/invitation/@id/decline
     */
    public function decline()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();
        (new CollaboratorInvite())->decline($id, $user['id']);
        $this->f3->reroute('/collab/invitations');
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function loadProject(int $pid): ?array
    {
        $rows = (new Project())->findAndCast(['id=?', $pid]);
        return $rows ? $rows[0] : null;
    }
}
