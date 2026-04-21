<?php

class ChapterVersionController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    /**
     * Charge le chapitre et verifie que l'utilisateur courant en est proprietaire.
     * Retourne le mapper charge, ou null si acces refuse (erreur deja envoyee).
     */
    private function loadOwned(int $cid, bool $jsonError = false): ?Chapter
    {
        $chapter = new Chapter();
        $chapter->load(['id=?', $cid]);

        if ($chapter->dry()) {
            if ($jsonError) {
                http_response_code(404);
                echo json_encode(['error' => 'Chapitre introuvable']);
                exit;
            }
            $this->f3->error(404);
            return null;
        }

        $projectModel = new Project();
        if (!$projectModel->count(['id=? AND user_id=?', $chapter->project_id, $this->currentUser()['id']])) {
            if ($jsonError) {
                http_response_code(403);
                echo json_encode(['error' => 'Acces refuse']);
                exit;
            }
            $this->f3->error(403);
            return null;
        }

        return $chapter;
    }

    public function list()
    {
        $cid     = (int) $this->f3->get('PARAMS.id');
        $chapter = $this->loadOwned($cid);
        if (!$chapter) return;

        $versions = $this->db->exec(
            'SELECT id, word_count, created_at FROM chapter_versions WHERE chapter_id = ? ORDER BY created_at DESC',
            [$cid]
        ) ?: [];

        $this->render('chapter/versions.html', [
            'title'    => 'Historique — ' . $chapter->title,
            'chapter'  => $chapter->cast(),
            'versions' => $versions,
        ]);
    }

    public function preview()
    {
        $cid     = (int) $this->f3->get('PARAMS.id');
        $vid     = (int) $this->f3->get('PARAMS.vid');
        $chapter = $this->loadOwned($cid, true);
        if (!$chapter) return;

        $rows = $this->db->exec(
            'SELECT content, word_count, created_at FROM chapter_versions WHERE id = ? AND chapter_id = ?',
            [$vid, $cid]
        );
        if (!$rows) {
            http_response_code(404);
            echo json_encode(['error' => 'Version introuvable']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'content'    => $rows[0]['content'],
            'word_count' => (int) $rows[0]['word_count'],
            'created_at' => $rows[0]['created_at'],
        ]);
        exit;
    }

    public function delete()
    {
        $cid     = (int) $this->f3->get('PARAMS.id');
        $vid     = (int) $this->f3->get('PARAMS.vid');
        $chapter = $this->loadOwned($cid);
        if (!$chapter) return;

        $this->db->exec(
            'DELETE FROM chapter_versions WHERE id = ? AND chapter_id = ?',
            [$vid, $cid]
        );

        $this->f3->reroute('/chapter/' . $cid . '/versions');
    }

    public function restore()
    {
        $cid     = (int) $this->f3->get('PARAMS.id');
        $vid     = (int) $this->f3->get('PARAMS.vid');
        $chapter = $this->loadOwned($cid);
        if (!$chapter) return;

        $rows = $this->db->exec(
            'SELECT content, word_count FROM chapter_versions WHERE id = ? AND chapter_id = ?',
            [$vid, $cid]
        );
        if (!$rows) {
            $this->f3->error(404);
            return;
        }

        $chapter->content    = $rows[0]['content'];
        $chapter->word_count = (int) $rows[0]['word_count'];
        $chapter->save();

        $this->f3->set('SESSION.success', 'Version restauree avec succes.');
        $this->f3->reroute('/chapter/' . $cid);
    }
}
