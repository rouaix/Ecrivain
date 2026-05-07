<?php

class ShareManageController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * List all share links for the current user.
     */
    public function index()
    {
        $user = $this->currentUser();
        $shareLink = new ShareLink();
        $links = $shareLink->getAllByUser((int)$user['id']);

        $origin = $this->f3->get('SCHEME') . '://' . $this->f3->get('HOST');
        $base   = $this->f3->get('BASE');
        foreach ($links as &$link) {
            $link['public_url'] = $origin . $base . '/s/' . $link['token'];
        }

        $this->render('share/manage/index', [
            'title' => 'Mes partages',
            'links' => $links,
        ]);
    }

    /**
     * Create a new share link (GET = form, POST = store).
     */
    public function create()
    {
        $user = $this->currentUser();

        if ($this->f3->get('VERB') === 'POST') {
            $label      = trim($this->f3->get('POST.label') ?? '');
            $projectIds = (array)($this->f3->get('POST.project_ids') ?? []);

            $validIds = $this->filterOwnedProjects($projectIds, (int)$user['id']);

            if (empty($validIds)) {
                $this->f3->reroute('/share/create');
                return;
            }

            $shareLink          = new ShareLink();
            $shareLink->token   = $shareLink->generateToken();
            $shareLink->user_id = (int)$user['id'];
            $shareLink->label   = $label !== '' ? $label : null;
            $shareLink->is_active = 1;
            $shareLink->save();

            $shareLink->setProjects((int)$shareLink->id, $validIds);

            $this->f3->reroute('/share');
            return;
        }

        $projectModel = new Project();
        $projects = $projectModel->findAndCast(['user_id=?', $user['id']]) ?? [];

        $this->render('share/manage/form', [
            'title'    => 'Nouveau lien de partage',
            'projects' => $projects,
            'link'     => null,
        ]);
    }

    /**
     * Edit a share link (GET = form, POST = update).
     */
    public function edit()
    {
        $id   = (int)$this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $shareLink = new ShareLink();
        $shareLink->load(['id=? AND user_id=?', $id, $user['id']]);

        if ($shareLink->dry()) {
            $this->f3->error(404);
            return;
        }

        if ($this->f3->get('VERB') === 'POST') {
            $label      = trim($this->f3->get('POST.label') ?? '');
            $projectIds = (array)($this->f3->get('POST.project_ids') ?? []);

            $validIds = $this->filterOwnedProjects($projectIds, (int)$user['id']);

            $shareLink->label = $label !== '' ? $label : null;
            $shareLink->save();

            $shareLink->setProjects($id, $validIds);

            $this->f3->reroute('/share');
            return;
        }

        $link               = $shareLink->cast();
        $link['project_ids'] = $shareLink->getProjectIds($id);

        $projectModel = new Project();
        $projects = $projectModel->findAndCast(['user_id=?', $user['id']]) ?? [];

        $this->render('share/manage/form', [
            'title'    => 'Modifier le lien de partage',
            'projects' => $projects,
            'link'     => $link,
        ]);
    }

    /**
     * Toggle is_active on a share link (AJAX POST).
     */
    public function toggle()
    {
        header('Content-Type: application/json');
        $id   = (int)$this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $shareLink = new ShareLink();
        $shareLink->load(['id=? AND user_id=?', $id, $user['id']]);

        if ($shareLink->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        $shareLink->is_active = $shareLink->is_active ? 0 : 1;
        $shareLink->save();

        echo json_encode(['is_active' => (int)$shareLink->is_active]);
    }

    /**
     * Delete a share link (POST).
     */
    public function delete()
    {
        $id   = (int)$this->f3->get('PARAMS.id');
        $user = $this->currentUser();

        $shareLink = new ShareLink();
        $shareLink->load(['id=? AND user_id=?', $id, $user['id']]);

        if (!$shareLink->dry()) {
            $shareLink->erase();
        }

        $this->f3->reroute('/share');
    }

    /**
     * Return only the project IDs that belong to the given user.
     */
    private function filterOwnedProjects(array $ids, int $userId): array
    {
        $projectModel = new Project();
        $valid = [];
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            if ($pid > 0 && $projectModel->count(['id=? AND user_id=?', $pid, $userId])) {
                $valid[] = $pid;
            }
        }
        return $valid;
    }
}
