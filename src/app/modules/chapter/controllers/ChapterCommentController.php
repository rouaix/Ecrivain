<?php

class ChapterCommentController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Verifie que le chapitre existe et appartient a l'utilisateur courant.
     * Retourne le mapper charge, ou null (reponse JSON d'erreur deja envoyee).
     */
    private function loadOwned(int $cid): ?Chapter
    {
        $chapter = new Chapter();
        $chapter->load(['id=?', $cid]);

        if ($chapter->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Chapitre introuvable']);
            return null;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']])) {
            http_response_code(403);
            echo json_encode(['error' => 'Acces non autorise']);
            return null;
        }

        return $chapter;
    }

    public function add()
    {
        $cid  = (int) $this->f3->get('PARAMS.id');
        $json = json_decode($this->f3->get('BODY'), true);

        $content = $json['content'] ?? '';
        $start   = (int) ($json['start'] ?? 0);
        $end     = (int) ($json['end'] ?? 0);

        if (!$content) {
            http_response_code(400);
            return;
        }

        if (!$this->loadOwned($cid)) return;

        $commentModel             = new Comment();
        $commentModel->chapter_id = $cid;
        $commentModel->content    = $content;
        $commentModel->start_pos  = $start;
        $commentModel->end_pos    = $end;
        $commentModel->created_at = date('Y-m-d H:i:s');
        $commentModel->save();

        echo json_encode(['status' => 'ok']);
    }

    public function delete()
    {
        header('Content-Type: application/json');
        $cid = (int) $this->f3->get('PARAMS.cid');

        $commentModel = new Comment();
        $commentModel->load(['id=?', $cid]);

        if ($commentModel->dry()) {
            http_response_code(404);
            echo json_encode(['error' => 'Annotation introuvable']);
            return;
        }

        if (!$this->loadOwned((int) $commentModel->chapter_id)) return;

        $chapterId = $commentModel->chapter_id;
        $commentModel->erase();

        echo json_encode((new Comment())->getByChapter($chapterId));
    }

    public function list()
    {
        $cid = (int) $this->f3->get('PARAMS.id');
        if (!$this->loadOwned($cid)) return;

        echo json_encode((new Comment())->getByChapter($cid));
    }
}
