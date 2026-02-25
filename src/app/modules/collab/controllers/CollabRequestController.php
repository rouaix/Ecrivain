<?php

class CollabRequestController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    private static $validTypes    = ['chapter', 'act', 'section', 'note', 'element', 'character'];
    private static $validReqTypes = ['add', 'modify', 'delete', 'correct'];

    // ── Collaborateur ────────────────────────────────────────────────────────

    /**
     * POST /project/@pid/collab/request/add — soumettre une demande
     */
    public function submit()
    {
        header('Content-Type: application/json');
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        if (!$this->isCollaborator($pid)) {
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
            return;
        }

        $requestType     = trim($_POST['request_type'] ?? '');
        $contentType     = trim($_POST['content_type'] ?? '');
        $contentId       = $_POST['content_id'] !== '' ? (int) ($_POST['content_id'] ?? 0) : null;
        $contentTitle    = trim($_POST['content_title'] ?? '');
        $currentSnapshot = $_POST['current_snapshot'] ?? null;
        $proposedContent = $_POST['proposed_content'] ?? null;
        $message         = trim($_POST['message'] ?? '');

        if (!in_array($requestType, self::$validReqTypes)
            || !in_array($contentType, self::$validTypes)) {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides.']);
            return;
        }

        $this->db->exec(
            'INSERT INTO collaboration_requests
                (project_id, user_id, request_type, content_type, content_id,
                 content_title, current_snapshot, proposed_content, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $pid, $user['id'], $requestType, $contentType, $contentId,
                $contentTitle ?: null, $currentSnapshot, $proposedContent,
                $message ?: null,
            ]
        );

        $rows = $this->db->exec('SELECT LAST_INSERT_ID() AS id');
        echo json_encode(['status' => 'ok', 'id' => (int)($rows[0]['id'] ?? 0)]);
    }

    /**
     * GET /project/@pid/collab/mes-demandes — liste des demandes du collaborateur
     */
    public function myRequests()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        if (!$this->isCollaborator($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        $project = $project ? $project[0] : null;

        $requests = $this->db->exec(
            'SELECT * FROM collaboration_requests
             WHERE project_id = ? AND user_id = ?
             ORDER BY created_at DESC',
            [$pid, $user['id']]
        ) ?: [];

        $this->render('collab/requests_collab.html', [
            'title'    => 'Mes demandes — ' . ($project['title'] ?? ''),
            'project'  => $project,
            'requests' => $requests,
        ]);
    }

    /**
     * POST /collab/request/@rid/cancel — annuler une demande en attente
     */
    public function cancel()
    {
        header('Content-Type: application/json');
        $rid  = (int) $this->f3->get('PARAMS.rid');
        $user = $this->currentUser();

        $rows = $this->db->exec(
            'SELECT id FROM collaboration_requests WHERE id = ? AND user_id = ? AND status = "pending"',
            [$rid, $user['id']]
        );

        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Demande introuvable.']);
            return;
        }

        $this->db->exec('DELETE FROM collaboration_requests WHERE id = ?', [$rid]);
        echo json_encode(['status' => 'ok']);
    }

    // ── Propriétaire ─────────────────────────────────────────────────────────

    /**
     * GET /project/@pid/collab/demandes — file de revue (propriétaire)
     */
    public function ownerQueue()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');

        if (!$this->isOwner($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid]);
        $project = $project ? $project[0] : null;

        $requests = $this->db->exec(
            'SELECT cr.*, u.username AS collab_username
             FROM collaboration_requests cr
             JOIN users u ON u.id = cr.user_id
             WHERE cr.project_id = ?
             ORDER BY cr.status ASC, cr.created_at DESC',
            [$pid]
        ) ?: [];

        $this->render('collab/requests_owner.html', [
            'title'    => 'Demandes des collaborateurs — ' . ($project['title'] ?? ''),
            'project'  => $project,
            'requests' => $requests,
        ]);
    }

    /**
     * POST /collab/request/@rid/approve — approuver et appliquer
     */
    public function approve()
    {
        header('Content-Type: application/json');
        $rid  = (int) $this->f3->get('PARAMS.rid');
        $user = $this->currentUser();

        $rows = $this->db->exec(
            'SELECT cr.* FROM collaboration_requests cr
             JOIN projects p ON p.id = cr.project_id
             WHERE cr.id = ? AND p.user_id = ? AND cr.status = "pending"',
            [$rid, $user['id']]
        );

        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Demande introuvable.']);
            return;
        }

        $req = $rows[0];

        try {
            $this->applyRequest($req);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        $this->db->exec(
            'UPDATE collaboration_requests
             SET status = "approved", reviewed_at = NOW(), reviewed_by = ?
             WHERE id = ?',
            [$user['id'], $rid]
        );

        echo json_encode(['status' => 'ok']);
    }

    /**
     * POST /collab/request/@rid/reject — refuser avec note optionnelle
     */
    public function reject()
    {
        header('Content-Type: application/json');
        $rid  = (int) $this->f3->get('PARAMS.rid');
        $user = $this->currentUser();
        $note = trim($_POST['owner_note'] ?? '');

        $rows = $this->db->exec(
            'SELECT cr.id FROM collaboration_requests cr
             JOIN projects p ON p.id = cr.project_id
             WHERE cr.id = ? AND p.user_id = ? AND cr.status = "pending"',
            [$rid, $user['id']]
        );

        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Demande introuvable.']);
            return;
        }

        $this->db->exec(
            'UPDATE collaboration_requests
             SET status = "rejected", owner_note = ?, reviewed_at = NOW(), reviewed_by = ?
             WHERE id = ?',
            [$note ?: null, $user['id'], $rid]
        );

        echo json_encode(['status' => 'ok']);
    }

    // ── Application automatique ───────────────────────────────────────────────

    private function applyRequest(array $req): void
    {
        $type    = $req['request_type'];
        $ct      = $req['content_type'];
        $cid     = (int) $req['content_id'];
        $content = $req['proposed_content'] ?? '';
        $title   = $req['content_title'] ?? '';
        $pid     = (int) $req['project_id'];

        if ($type === 'modify' || $type === 'correct') {
            $this->applyModify($ct, $cid, $content, $title);
        } elseif ($type === 'add') {
            $this->applyAdd($ct, $pid, $title, $content);
        } elseif ($type === 'delete') {
            $this->applyDelete($ct, $cid);
        }
    }

    private function applyModify(string $ct, int $cid, string $content, string $title): void
    {
        $tables = [
            'chapter'   => 'chapters',
            'act'       => 'acts',
            'note'      => 'notes',
            'element'   => 'elements',
            'section'   => 'sections',
        ];

        if ($ct === 'character') {
            // Characters have structured fields; only update content (bio/description)
            $this->db->exec('UPDATE characters SET description = ? WHERE id = ?', [$content, $cid]);
            return;
        }

        if (!isset($tables[$ct])) {
            throw new \Exception('Type de contenu non supporté : ' . $ct);
        }

        $table = $tables[$ct];
        $check = $this->db->exec("SELECT id FROM {$table} WHERE id = ?", [$cid]);
        if (empty($check)) {
            throw new \Exception('Le contenu cible a été supprimé entre-temps.');
        }

        $sql = "UPDATE {$table} SET content = ?";
        $params = [$content];
        if ($title !== '') {
            $sql .= ', title = ?';
            $params[] = $title;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $cid;
        $this->db->exec($sql, $params);
    }

    private function applyAdd(string $ct, int $pid, string $title, string $content): void
    {
        if ($ct === 'chapter') {
            $this->db->exec(
                'INSERT INTO chapters (project_id, title, content, sort_order) VALUES (?, ?, ?, 9999)',
                [$pid, $title ?: 'Nouveau chapitre', $content]
            );
        } elseif ($ct === 'note') {
            $this->db->exec(
                'INSERT INTO notes (project_id, title, content, sort_order) VALUES (?, ?, ?, 9999)',
                [$pid, $title ?: 'Nouvelle note', $content]
            );
        } elseif ($ct === 'act') {
            $this->db->exec(
                'INSERT INTO acts (project_id, title, content, sort_order) VALUES (?, ?, ?, 9999)',
                [$pid, $title ?: 'Nouvel acte', $content]
            );
        } else {
            throw new \Exception('Type d\'ajout non supporté : ' . $ct);
        }
    }

    private function applyDelete(string $ct, int $cid): void
    {
        $tables = [
            'chapter'   => 'chapters',
            'act'       => 'acts',
            'note'      => 'notes',
            'element'   => 'elements',
            'section'   => 'sections',
            'character' => 'characters',
        ];

        if (!isset($tables[$ct])) {
            throw new \Exception('Type de contenu non supporté : ' . $ct);
        }

        $this->db->exec("DELETE FROM {$tables[$ct]} WHERE id = ?", [$cid]);
    }
}
