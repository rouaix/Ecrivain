<?php

class ProjectDictionaryController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function dictionaryAdd()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $body = json_decode($this->f3->get('BODY'), true);
        $word = trim($body['word'] ?? '');
        if ($word === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Mot manquant']);
            return;
        }

        $rows    = $this->db->exec('SELECT ignored_words FROM projects WHERE id=?', [$pid]);
        $current = json_decode($rows[0]['ignored_words'] ?? '[]', true) ?: [];
        if (!in_array($word, $current)) {
            $current[] = $word;
        }
        $this->db->exec('UPDATE projects SET ignored_words=? WHERE id=?', [json_encode($current), $pid]);

        echo json_encode(['status' => 'ok', 'words' => $current]);
    }

    public function dictionaryRemove()
    {
        $pid  = (int) $this->f3->get('PARAMS.pid');
        $user = $this->currentUser();

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $pid, $user['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $body = json_decode($this->f3->get('BODY'), true);
        $word = trim($body['word'] ?? '');

        $rows    = $this->db->exec('SELECT ignored_words FROM projects WHERE id=?', [$pid]);
        $current = json_decode($rows[0]['ignored_words'] ?? '[]', true) ?: [];
        $current = array_values(array_filter($current, fn($w) => $w !== $word));
        $this->db->exec('UPDATE projects SET ignored_words=? WHERE id=?', [json_encode($current), $pid]);

        echo json_encode(['status' => 'ok', 'words' => $current]);
    }
}
