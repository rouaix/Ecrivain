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
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        if (!$this->isOwner($pid)) {
            $this->f3->error(403, 'Accès réservé au propriétaire.');
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        $project = $project ? $project[0] : null;

        $collaborators = $this->db->exec(
            'SELECT pc.*, u.username, u.email
             FROM project_collaborators pc
             JOIN users u ON u.id = pc.user_id
             WHERE pc.project_id = ?
             ORDER BY pc.created_at ASC',
            [$pid]
        ) ?: [];

        $this->render('collab/invite.html', [
            'title'         => 'Collaborateurs — ' . ($project['title'] ?? ''),
            'project'       => $project,
            'collaborators' => $collaborators,
            'errors'        => [],
        ]);
    }

    /**
     * POST /project/@pid/collaborateurs/invite — envoyer une invitation
     */
    public function invite()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        if (!$this->isOwner($pid)) {
            $this->f3->error(403);
            return;
        }

        $email  = trim($_POST['email'] ?? '');
        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        } elseif (strtolower($email) === strtolower($user['email'])) {
            $errors[] = 'Vous ne pouvez pas vous inviter vous-même.';
        } else {
            $target = $this->db->exec(
                'SELECT id FROM users WHERE LOWER(email) = LOWER(?)',
                [$email]
            );
            if (empty($target)) {
                $errors[] = 'Aucun compte trouvé pour cette adresse e-mail.';
            } else {
                $targetId = (int) $target[0]['id'];
                $exists = $this->db->exec(
                    'SELECT id FROM project_collaborators WHERE project_id = ? AND user_id = ?',
                    [$pid, $targetId]
                );
                if ($exists) {
                    $errors[] = 'Cet utilisateur a déjà été invité sur ce projet.';
                } else {
                    $this->db->exec(
                        'INSERT INTO project_collaborators (project_id, owner_id, user_id) VALUES (?, ?, ?)',
                        [$pid, $user['id'], $targetId]
                    );
                    $this->f3->reroute('/project/' . $pid . '/collaborateurs');
                    return;
                }
            }
        }

        $projectModel  = new Project();
        $project       = $projectModel->findAndCast(['id=?', $pid]);
        $project       = $project ? $project[0] : null;
        $collaborators = $this->db->exec(
            'SELECT pc.*, u.username, u.email
             FROM project_collaborators pc
             JOIN users u ON u.id = pc.user_id
             WHERE pc.project_id = ?
             ORDER BY pc.created_at ASC',
            [$pid]
        ) ?: [];

        $this->render('collab/invite.html', [
            'title'         => 'Collaborateurs — ' . ($project['title'] ?? ''),
            'project'       => $project,
            'collaborators' => $collaborators,
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

        if (!$this->isOwner($pid)) {
            $this->f3->error(403);
            return;
        }

        $this->db->exec(
            'DELETE FROM project_collaborators WHERE project_id = ? AND user_id = ?',
            [$pid, $uid]
        );

        $this->f3->reroute('/project/' . $pid . '/collaborateurs');
    }

    /**
     * GET /collab/invitations — invitations reçues (collaborateur)
     */
    public function myInvitations()
    {
        $user = $this->currentUser();

        $invitations = $this->db->exec(
            'SELECT pc.*, p.title AS project_title, u.username AS owner_username
             FROM project_collaborators pc
             JOIN projects p ON p.id = pc.project_id
             JOIN users u ON u.id = pc.owner_id
             WHERE pc.user_id = ?
             ORDER BY pc.created_at DESC',
            [$user['id']]
        ) ?: [];

        $this->render('collab/my_invitations.html', [
            'title'       => 'Mes invitations',
            'invitations' => $invitations,
        ]);
    }

    /**
     * POST /collab/invitation/@id/accept
     */
    public function accept()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $this->db->exec(
            'UPDATE project_collaborators SET status = "accepted", accepted_at = NOW()
             WHERE id = ? AND user_id = ? AND status = "pending"',
            [$id, $user['id']]
        );

        $this->f3->reroute('/collab/invitations');
    }

    /**
     * POST /collab/invitation/@id/decline
     */
    public function decline()
    {
        $id   = (int) $this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $this->db->exec(
            'UPDATE project_collaborators SET status = "declined"
             WHERE id = ? AND user_id = ? AND status = "pending"',
            [$id, $user['id']]
        );

        $this->f3->reroute('/collab/invitations');
    }
}
