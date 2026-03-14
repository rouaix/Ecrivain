<?php

class CharacterController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function index()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        $charModel = new Character();
        $characters = $charModel->getAllByProject($pid);

        $this->render('characters/index.html', [
            'title' => 'Personnages - ' . $project['title'],
            'project' => $project,
            'characters' => $characters
        ]);
    }

    public function create()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        $this->render('characters/edit.html', [
            'title' => 'Nouveau personnage',
            'project' => $project,
            'character' => ['name' => '', 'description' => '', 'comment' => '', 'id' => null],
            'errors' => []
        ]);
    }

    public function store()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(404);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $comment = $_POST['comment'] ?? '';
        $comment = $this->cleanQuillHtml($comment);

        if (!$name) {
            $this->render('characters/edit.html', [
                'title' => 'Nouveau personnage',
                'project' => $projectModel->findAndCast(['id=?', $pid])[0],
                'character' => ['name' => $name, 'description' => $description, 'comment' => $comment, 'id' => null],
                'errors' => ['Le nom est obligatoire']
            ]);
            return;
        }

        $charModel = new Character();
        $charModel->project_id = $pid;
        $charModel->name = $name;
        $charModel->description = $description;
        $charModel->comment = $comment;
        $charModel->save();

        $this->f3->reroute('/project/' . $pid . '/characters');
    }

    public function edit()
    {
        $id = (int) $this->f3->get('PARAMS.id');
        $charModel = new Character();
        $charModel->load(['id=?', $id]);
        if ($charModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $charModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }
        $project = $projectModel->findAndCast(['id=?', $charModel->project_id])[0];

        // All chapters of the project (for timeline)
        $allChapters = $this->db->exec(
            'SELECT c.id, c.title, c.order_index, c.act_id,
                    a.title AS act_title, a.order_index AS act_order
             FROM chapters c
             LEFT JOIN acts a ON a.id = c.act_id
             WHERE c.project_id = ?
             ORDER BY COALESCE(a.order_index, 0) ASC, c.order_index ASC, c.id ASC',
            [$charModel->project_id]
        ) ?: [];

        // Chapters where the character name is mentioned
        $mentionedIds = [];
        $mentions     = [];
        if ($charModel->name && $allChapters) {
            $rows = $this->db->exec(
                'SELECT c.id, c.title, c.order_index, c.act_id,
                        a.title AS act_title, a.order_index AS act_order
                 FROM chapters c
                 LEFT JOIN acts a ON a.id = c.act_id
                 WHERE c.project_id = ?
                   AND (c.content LIKE ? OR c.resume LIKE ?)
                 ORDER BY COALESCE(a.order_index, 0) ASC, c.order_index ASC, c.id ASC',
                [$charModel->project_id, '%' . $charModel->name . '%', '%' . $charModel->name . '%']
            ) ?: [];

            foreach ($rows as $row) {
                $mentionedIds[$row['id']] = true;
                $actKey   = $row['act_id'] ?? 0;
                $actLabel = $row['act_title'] ?: 'Sans acte';
                if (!isset($mentions[$actKey])) {
                    $mentions[$actKey] = ['label' => $actLabel, 'chapters' => []];
                }
                $mentions[$actKey]['chapters'][] = $row;
            }
        }

        // Build timeline: all chapters grouped by act, with presence flag
        $timeline = [];
        foreach ($allChapters as $ch) {
            $actKey   = $ch['act_id'] ?? 0;
            $actLabel = $ch['act_title'] ?: 'Sans acte';
            if (!isset($timeline[$actKey])) {
                $timeline[$actKey] = ['label' => $actLabel, 'chapters' => []];
            }
            $ch['mentioned'] = isset($mentionedIds[$ch['id']]) ? 1 : 0;
            $timeline[$actKey]['chapters'][] = $ch;
        }

        $this->render('characters/edit.html', [
            'title'         => 'Modifier personnage',
            'project'       => $project,
            'character'     => $charModel->cast(),
            'mentions'      => $mentions,
            'timeline'      => array_values($timeline),
            'totalChapters' => count($allChapters),
            'mentionCount'  => count($mentionedIds),
            'errors'        => []
        ]);
    }

    public function update()
    {
        $id = (int) $this->f3->get('PARAMS.id');
        $charModel = new Character();
        $charModel->load(['id=?', $id]);
        if ($charModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $charModel->project_id, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $comment = $_POST['comment'] ?? '';
        $comment = $this->cleanQuillHtml($comment);

        if (!$name) {
            $this->render('characters/edit.html', [
                'title' => 'Modifier personnage',
                'project' => $projectModel->findAndCast(['id=?', $charModel->project_id])[0],
                'character' => ['id' => $id, 'name' => $name, 'description' => $description, 'comment' => $comment],
                'errors' => ['Le nom est obligatoire']
            ]);
            return;
        }

        $charModel->name = $name;
        $charModel->description = $description;
        $charModel->comment = $comment;
        $charModel->save();

        $this->f3->reroute('/project/' . $charModel->project_id . '/characters');
    }

    public function delete()
    {
        $id = (int) $this->f3->get('PARAMS.id');
        $charModel = new Character();
        $charModel->load(['id=?', $id]);
        if (!$charModel->dry()) {
            $projectModel = new Project();
            if ($projectModel->count(['id=? AND user_id=?', $charModel->project_id, $this->currentUser()['id']])) {
                $pid = $charModel->project_id;
                $charModel->erase();
                $this->f3->reroute('/project/' . $pid . '/characters');
                return;
            }
        }
        $this->f3->error(404);
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function relations()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) {
            $this->f3->error(403);
            return;
        }

        $projectModel = new Project();
        $project = $projectModel->findAndCast(['id=?', $pid])[0];

        $charModel  = new Character();
        $characters = $charModel->getAllByProject($pid);

        $relations = $this->db->exec(
            'SELECT cr.id, cr.char_from, cr.char_to, cr.label, cr.color,
                    cf.name AS name_from, ct.name AS name_to
             FROM character_relations cr
             JOIN characters cf ON cf.id = cr.char_from
             JOIN characters ct ON ct.id = cr.char_to
             WHERE cr.project_id = ?
             ORDER BY cr.id ASC',
            [$pid]
        ) ?: [];

        $this->render('characters/relations.html', [
            'title'      => 'Relations — ' . $project['title'],
            'project'    => $project,
            'characters' => $characters,
            'relations'  => $relations,
        ]);
    }

    public function addRelation()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            return;
        }

        $charFrom = (int) ($_POST['char_from'] ?? 0);
        $charTo   = (int) ($_POST['char_to']   ?? 0);
        $label    = mb_substr(trim($_POST['label'] ?? ''), 0, 100);
        $color    = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#94a3b8';

        if (!$charFrom || !$charTo || $charFrom === $charTo) {
            echo json_encode(['success' => false, 'error' => 'Personnages invalides']);
            return;
        }

        // Verify both characters belong to this project
        $charModel = new Character();
        if (!$charModel->count(['id=? AND project_id=?', $charFrom, $pid]) ||
            !$charModel->count(['id=? AND project_id=?', $charTo, $pid])) {
            echo json_encode(['success' => false, 'error' => 'Personnage introuvable']);
            return;
        }

        $this->db->exec(
            'INSERT INTO character_relations (project_id, char_from, char_to, label, color) VALUES (?, ?, ?, ?, ?)',
            [$pid, $charFrom, $charTo, $label, $color]
        );
        $rid = (int) $this->db->exec('SELECT LAST_INSERT_ID() AS id')[0]['id'];

        $nameFrom = $this->db->exec('SELECT name FROM characters WHERE id=?', [$charFrom])[0]['name'] ?? '';
        $nameTo   = $this->db->exec('SELECT name FROM characters WHERE id=?', [$charTo])[0]['name'] ?? '';

        echo json_encode([
            'success'   => true,
            'relation'  => [
                'id'        => $rid,
                'char_from' => $charFrom,
                'char_to'   => $charTo,
                'label'     => $label,
                'color'     => $color,
                'name_from' => $nameFrom,
                'name_to'   => $nameTo,
            ]
        ]);
    }

    public function deleteRelation()
    {
        $pid = (int) $this->f3->get('PARAMS.pid');
        $rid = (int) $this->f3->get('PARAMS.rid');

        if (!$this->isOwner($pid)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            return;
        }

        $this->db->exec(
            'DELETE FROM character_relations WHERE id = ? AND project_id = ?',
            [$rid, $pid]
        );

        echo json_encode(['success' => true]);
    }
}
