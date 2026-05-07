<?php

class ActivityLogController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * GET /project/@pid/activity
     */
    public function index()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $project = $this->requireOwnedProject($pid);

        $logs = $this->db->exec(
            'SELECT l.*, u.username, u.email
             FROM project_activity_logs l
             JOIN users u ON u.id = l.user_id
             WHERE l.project_id = ?
             ORDER BY l.created_at DESC
             LIMIT 200',
            [$pid]
        ) ?: [];

        $this->render('project/activity_log.html', [
            'title'   => 'Journal d\'activité — ' . $project['title'],
            'project' => $project,
            'logs'    => $logs,
        ]);
    }
}
