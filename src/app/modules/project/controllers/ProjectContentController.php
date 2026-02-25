<?php

class ProjectContentController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function reorderItem()
    {
        $pid          = (int) $this->f3->get('PARAMS.id');
        $projectModel = new Project();
        $projectModel->load(['id=? AND user_id=?', $pid, $this->currentUser()['id']]);

        if ($projectModel->dry()) {
            $this->f3->error(404);
            return;
        }

        $data     = json_decode($this->f3->get('BODY'), true);
        $type     = $data['type'] ?? '';
        $itemId   = (int) ($data['id'] ?? 0);
        $newIndex = (int) ($data['new_index'] ?? 0);

        // Position 1-based from UI → 0-based index
        $newIndex = max(0, $newIndex - 1);

        $db = $this->f3->get('DB');

        if ($type === 'chapter') {
            $item = new Chapter();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry()) return;

            $parentId = $item->parent_id;
            $actId    = $item->act_id;

            if ($parentId) {
                $siblings = $item->findAndCast(['parent_id=?', $parentId], ['order' => 'order_index ASC, id ASC']);
            } elseif ($actId) {
                $siblings = $item->findAndCast(['act_id=? AND parent_id IS NULL', $actId], ['order' => 'order_index ASC, id ASC']);
            } else {
                $siblings = $item->findAndCast(['project_id=? AND act_id IS NULL AND parent_id IS NULL', $pid], ['order' => 'order_index ASC, id ASC']);
            }

            $table = 'chapters';

        } elseif ($type === 'section') {
            $item = new Section();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry()) return;

            $sectionTypes       = Section::SECTION_TYPES;
            $currentTypeConf    = $sectionTypes[$item->type] ?? null;
            $currentPosition    = $currentTypeConf['position'] ?? 'before';
            $allSections        = $item->findAndCast(['project_id=?', $pid], ['order' => 'order_index ASC, id ASC']);

            $siblings = [];
            foreach ($allSections as $sec) {
                $t = $sec['type'];
                $p = $sectionTypes[$t]['position'] ?? 'before';
                if ($p === $currentPosition) {
                    $siblings[] = $sec;
                }
            }

            $table = 'sections';

        } elseif ($type === 'note') {
            $item = new Note();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry()) return;

            $siblings = $item->findAndCast(['project_id=?', $pid], ['order' => 'order_index ASC, id ASC']);
            $table    = 'notes';

        } elseif ($type === 'act') {
            $item = new Act();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry()) return;

            $siblings = $item->findAndCast(['project_id=?', $pid], ['order' => 'order_index ASC, id ASC']);
            $table    = 'acts';

        } elseif ($type === 'element') {
            if (!$this->f3->get('DB')->exists('elements')) { $this->f3->error(400); return; }
            $item = new Element();
            $item->load(['id=? AND project_id=?', $itemId, $pid]);
            if ($item->dry()) return;

            $parentId          = $item->parent_id;
            $templateElementId = $item->template_element_id;

            if ($parentId) {
                $siblings = $item->findAndCast(['parent_id=?', $parentId], ['order' => 'order_index ASC, id ASC']);
            } else {
                $siblings = $item->findAndCast(['project_id=? AND template_element_id=? AND parent_id IS NULL', $pid, $templateElementId], ['order' => 'order_index ASC, id ASC']);
            }

            $table = 'elements';

        } else {
            $this->f3->error(400, 'Invalid type');
            return;
        }

        $idList = array_column($siblings, 'id');
        $key    = array_search($itemId, $idList);
        if ($key !== false) {
            unset($idList[$key]);
        }
        $idList = array_values($idList);
        array_splice($idList, $newIndex, 0, $itemId);

        $db->begin();
        foreach ($idList as $idx => $id) {
            $db->exec("UPDATE $table SET order_index = ? WHERE id = ?", [$idx, $id]);
        }
        $db->commit();

        echo json_encode(['status' => 'ok']);
    }

    public function reorderChapters()
    {
        $pid   = (int) $this->f3->get('PARAMS.pid');
        $body  = json_decode($this->f3->get('BODY'), true);
        $order = $body['order'] ?? [];

        if (empty($order)) {
            echo json_encode(['status' => 'error', 'message' => 'No order provided']);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            return;
        }

        $chapterModel = new Chapter();
        foreach ($order as $index => $id) {
            $chapterModel->load(['id=?', $id]);
            if (!$chapterModel->dry() && $chapterModel->project_id == $pid) {
                // order_index update intentionally left as legacy stub
            }
            $chapterModel->reset();
        }

        echo json_encode(['status' => 'ok']);
    }

    public function reorderSections()
    {
        $pid   = (int) $this->f3->get('PARAMS.pid');
        $body  = json_decode($this->f3->get('BODY'), true);
        $order = $body['order'] ?? [];
        $type  = $body['type'] ?? '';

        if (empty($order)) {
            echo json_encode(['status' => 'error']);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            return;
        }

        $model = ($type === 'note' || $type === 'notes') ? new Note() : new Section();

        foreach ($order as $index => $id) {
            $model->load(['id=? AND project_id=?', (int) $id, $pid]);
            if (!$model->dry()) {
                $model->order_index = $index;
                $model->save();
            }
            $model->reset();
        }

        echo json_encode(['status' => 'ok']);
    }

    public function toggleExport()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $body = json_decode($this->f3->get('BODY'), true);
        $id   = $body['id'] ?? null;
        $type = $body['type'] ?? '';
        $state = $body['is_exported'] ?? 1;

        if (!$id || !$type) {
            echo json_encode(['status' => 'error']);
            return;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            http_response_code(403);
            return;
        }

        if ($type === 'chapter') {
            $model = new Chapter();
        } elseif ($type === 'note') {
            $model = new Note();
        } elseif ($type === 'act') {
            $model = new Act();
        } elseif ($type === 'character') {
            $model = new Character();
        } elseif ($type === 'element') {
            if (!$this->f3->get('DB')->exists('elements')) { return; }
            $model = new Element();
        } else {
            $model = new Section();
        }

        $model->load(['id=?', $id]);
        if (!$model->dry() && $model->project_id == $pid) {
            $model->is_exported = $state ? 1 : 0;
            $model->save();
        }

        echo json_encode(['status' => 'ok']);
    }

    public function getPreview()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $type = $this->f3->get('PARAMS.type');
        $id   = (int) $this->f3->get('PARAMS.id');

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $this->currentUser()['id']])) {
            $this->f3->error(403);
            return;
        }

        if ($type === 'chapter') {
            $model = new Chapter();
        } elseif ($type === 'note') {
            $model = new Note();
        } elseif ($type === 'element') {
            if (!$this->f3->get('DB')->exists('elements')) { $this->f3->error(404); return; }
            $model = new Element();
        } else {
            $model = new Section();
        }

        $model->load(['id=?', $id]);
        if ($model->dry() || $model->project_id != $pid) {
            $this->f3->error(404);
            return;
        }

        $title   = $model->title ?: ($model->name ?? 'Sans titre');
        $content = $model->content ?? '';
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        header('Content-Type: application/json');
        echo json_encode([
            'title'   => $title,
            'content' => $content
        ]);
    }
}
