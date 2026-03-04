<?php

class TimelineController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getProject(int $pid): ?array
    {
        $pm   = new Project();
        $rows = $pm->findAndCast(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);
        return $rows ? $rows[0] : null;
    }

    // ── Timeline view ─────────────────────────────────────────────────────────

    public function index()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $project = $this->getProject($pid);
        if (!$project) { $this->f3->error(404); return; }

        $actModel = new Act();
        $acts     = $actModel->getAllByProject($pid);

        // Load all chapters (top-level + sub) to compute total wc per top-level chapter
        $allChapters = $this->db->exec(
            'SELECT id, title, act_id, order_index, parent_id, content
             FROM chapters
             WHERE project_id = ?',
            [$pid]
        );

        // Build wc map and sub-chapter wc map
        $contentByid = [];
        foreach ($allChapters as $ch) {
            $clean = strip_tags(html_entity_decode($ch['content'] ?? ''));
            $contentByid[$ch['id']] = ['wc' => str_word_count($clean), 'parent_id' => $ch['parent_id']];
        }

        // Sum sub-chapter wc into their top-level parent
        $subWc = [];
        foreach ($contentByid as $id => $data) {
            if ($data['parent_id']) {
                $pid2 = $data['parent_id'];
                // Walk up to top-level
                while (isset($contentByid[$pid2]) && $contentByid[$pid2]['parent_id']) {
                    $pid2 = $contentByid[$pid2]['parent_id'];
                }
                $subWc[$pid2] = ($subWc[$pid2] ?? 0) + $data['wc'];
            }
        }

        // Extract top-level chapters in correct order
        $rows = [];
        foreach ($allChapters as $ch) {
            if ($ch['parent_id'] !== null) continue;
            $ownWc      = $contentByid[$ch['id']]['wc'] ?? 0;
            $ch['wc']   = $ownWc + ($subWc[$ch['id']] ?? 0);
            unset($ch['content'], $ch['parent_id']);
            $rows[] = $ch;
        }

        // Sort: chapters without act last, then by act_id, then order_index
        usort($rows, function ($a, $b) {
            $aNull = ($a['act_id'] === null) ? 1 : 0;
            $bNull = ($b['act_id'] === null) ? 1 : 0;
            if ($aNull !== $bNull) return $aNull - $bNull;
            if ($a['act_id'] !== $b['act_id']) return ($a['act_id'] ?? 0) - ($b['act_id'] ?? 0);
            if ($a['order_index'] !== $b['order_index']) return $a['order_index'] - $b['order_index'];
            return $a['id'] - $b['id'];
        });

        // Group chapters by act_id (null = no act)
        $byAct = ['__none__' => []];
        foreach ($acts as $a) {
            $byAct[$a['id']] = [];
        }
        foreach ($rows as $ch) {
            $key = $ch['act_id'] ?? '__none__';
            $byAct[$key][] = $ch;
        }

        // Stats per act
        $actStats = [];
        foreach ($acts as $a) {
            $wc = 0;
            foreach ($byAct[$a['id']] ?? [] as $ch) {
                $wc += (int)$ch['wc'];
            }
            $actStats[$a['id']] = ['wc' => $wc, 'count' => count($byAct[$a['id']] ?? [])];
        }
        $noneWc = 0;
        foreach ($byAct['__none__'] as $ch) { $noneWc += (int)$ch['wc']; }
        $actStats['__none__'] = ['wc' => $noneWc, 'count' => count($byAct['__none__'])];

        $this->render('acts/timeline.html', [
            'title'     => 'Timeline — ' . $project['title'],
            'project'   => $project,
            'acts'      => $acts,
            'byAct'     => $byAct,
            'actStats'  => $actStats,
            'totalWc'   => array_sum(array_column($rows, 'wc')),
            'totalCh'   => count($rows),
        ]);
    }

    // ── AJAX: move chapter to act and/or reorder ──────────────────────────────

    public function reorder()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $project = $this->getProject($pid);
        if (!$project) {
            http_response_code(403);
            echo json_encode(['status' => 'error']);
            return;
        }

        $data      = json_decode($this->f3->get('BODY'), true) ?? [];
        $chapterId = (int) ($data['chapter_id'] ?? 0);
        $actId     = isset($data['act_id']) && $data['act_id'] !== '' ? (int)$data['act_id'] : null;
        $position  = (int) ($data['position'] ?? 0); // 0-based index among siblings

        if (!$chapterId) {
            echo json_encode(['status' => 'error', 'msg' => 'missing chapter_id']);
            return;
        }

        // Load chapter, verify it belongs to project
        $ch = $this->db->exec(
            'SELECT id, act_id, order_index FROM chapters WHERE id=? AND project_id=? AND parent_id IS NULL',
            [$chapterId, $pid]
        );
        if (!$ch) {
            http_response_code(404);
            echo json_encode(['status' => 'error']);
            return;
        }

        // Update act_id first
        $this->db->exec(
            'UPDATE chapters SET act_id=? WHERE id=?',
            [$actId, $chapterId]
        );

        // Re-fetch all siblings in the target act group (after act_id update)
        if ($actId) {
            $siblings = $this->db->exec(
                'SELECT id FROM chapters WHERE project_id=? AND act_id=? AND parent_id IS NULL ORDER BY order_index ASC, id ASC',
                [$pid, $actId]
            );
        } else {
            $siblings = $this->db->exec(
                'SELECT id FROM chapters WHERE project_id=? AND act_id IS NULL AND parent_id IS NULL ORDER BY order_index ASC, id ASC',
                [$pid]
            );
        }

        $ids = array_column($siblings, 'id');
        // Remove the moved chapter and re-insert at position
        $ids = array_values(array_filter($ids, fn($id) => $id != $chapterId));
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, $chapterId);

        $this->db->begin();
        foreach ($ids as $idx => $id) {
            $this->db->exec('UPDATE chapters SET order_index=? WHERE id=?', [$idx, $id]);
        }
        $this->db->commit();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }

    // ── AJAX: reorder acts ────────────────────────────────────────────────────

    public function reorderActs()
    {
        $pid     = (int) $this->f3->get('PARAMS.pid');
        $project = $this->getProject($pid);
        if (!$project) {
            http_response_code(403);
            echo json_encode(['status' => 'error']);
            return;
        }

        $data    = json_decode($this->f3->get('BODY'), true) ?? [];
        $actId   = (int) ($data['act_id'] ?? 0);
        $position = (int) ($data['position'] ?? 0);

        if (!$actId) {
            echo json_encode(['status' => 'error', 'msg' => 'missing act_id']);
            return;
        }

        $acts = $this->db->exec(
            'SELECT id FROM acts WHERE project_id=? ORDER BY order_index ASC, id ASC',
            [$pid]
        );
        $ids = array_column($acts, 'id');
        $ids = array_values(array_filter($ids, fn($id) => $id != $actId));
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, $actId);

        $this->db->begin();
        foreach ($ids as $idx => $id) {
            $this->db->exec('UPDATE acts SET order_index=? WHERE id=?', [$idx, $id]);
        }
        $this->db->commit();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }
}
