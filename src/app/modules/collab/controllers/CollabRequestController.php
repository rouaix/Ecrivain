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

        $req = new CollaborationRequest();
        $id  = $req->submit(
            $pid, $user['id'], $requestType, $contentType, $contentId,
            $contentTitle, $currentSnapshot, $proposedContent, $message
        );
        echo json_encode(['status' => 'ok', 'id' => $id]);
    }

    /**
     * GET /project/@pid/collab/mes-demandes — liste des demandes du collaborateur
     */
    public function myRequests()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();
        if (!$this->isCollaborator($pid)) { $this->f3->error(403); return; }

        $project  = $this->loadProject($pid);
        $requests = (new CollaborationRequest())->findByProjectAndUser($pid, $user['id']);

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

        $req = new CollaborationRequest();
        if (!$req->findPendingByIdAndUser($rid, $user['id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Demande introuvable.']);
            return;
        }

        $req->cancelByUser($rid, $user['id']);
        echo json_encode(['status' => 'ok']);
    }

    // ── Propriétaire ─────────────────────────────────────────────────────────

    /**
     * GET /project/@pid/collab/demandes — file de revue (propriétaire)
     */
    public function ownerQueue()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $this->requireOwner($pid);

        $project  = $this->loadProject($pid);
        $requests = (new CollaborationRequest())->findByProject($pid);

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

        $req    = new CollaborationRequest();
        $record = $req->findPendingForOwner($rid, $user['id']);

        if (!$record) {
            echo json_encode(['status' => 'error', 'message' => 'Demande introuvable.']);
            return;
        }

        try {
            $this->applyRequest($record);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        $req->approve($rid, $user['id']);
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

        $req = new CollaborationRequest();
        if (!$req->findPendingForOwner($rid, $user['id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Demande introuvable.']);
            return;
        }

        $req->reject($rid, $user['id'], $note ?: null);
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

    private function loadProject(int $pid): ?array
    {
        $rows = (new Project())->findAndCast(['id=?', $pid]);
        return $rows ? $rows[0] : null;
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
