<?php

class SearchController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function search()
    {
        $user      = $this->currentUser();
        $q         = trim($this->f3->get('GET.q') ?? '');
        $projectId = (int) ($this->f3->get('GET.project_id') ?? 0);

        $results = [];
        $error   = null;

        if (mb_strlen($q) >= 2) {
            $results = $this->doSearch($q, $user['id'], $projectId ?: null);
        } elseif ($q !== '') {
            $error = 'Veuillez saisir au moins 2 caractères.';
        }

        $projectModel = new Project();
        $projects = $projectModel->find(['user_id=?', $user['id']], ['order' => 'title ASC']) ?: [];

        $this->render('search/results.html', [
            'title'     => $q ? 'Recherche : ' . $q : 'Recherche',
            'q'         => $q,
            'projectId' => $projectId,
            'projects'  => $projects,
            'results'   => $results,
            'error'     => $error,
        ]);
    }

    private function doSearch(string $q, int $userId, ?int $projectId): array
    {
        $term    = '%' . $q . '%';
        $results = [];

        $projectClause = $projectId ? 'AND t.project_id = ?' : 'AND p.user_id = ?';
        $projectParam  = $projectId ?: $userId;

        $highlight = function (string $html, string $q, int $ctxLen = 120): string {
            $text = strip_tags($html);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            if ($text === '') {
                return '';
            }
            $pos   = mb_stripos($text, $q);
            $start = $pos !== false ? max(0, $pos - $ctxLen) : 0;
            $len   = min(mb_strlen($text) - $start, $ctxLen * 2 + mb_strlen($q));

            $extract = mb_substr($text, $start, $len);
            $prefix  = $start > 0 ? '…' : '';
            $suffix  = ($start + $len) < mb_strlen($text) ? '…' : '';

            $encoded  = htmlspecialchars($extract, ENT_QUOTES, 'UTF-8');
            $qEncoded = preg_quote(htmlspecialchars($q, ENT_QUOTES, 'UTF-8'), '/');
            $marked   = preg_replace('/(' . $qEncoded . ')/iu', '<mark>$1</mark>', $encoded);

            return $prefix . $marked . $suffix;
        };

        // --- Chapters ---
        try {
            $rows = $this->db->exec(
                "SELECT t.id, t.project_id, t.title, t.content
                 FROM chapters t JOIN projects p ON p.id = t.project_id
                 WHERE (t.title LIKE ? OR t.content LIKE ?) $projectClause LIMIT 20",
                [$term, $term, $projectParam]
            );
            foreach ((array) $rows as $row) {
                $results[] = [
                    'type'    => 'Chapitre',
                    'icon'    => 'fa-book-open',
                    'title'   => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                    'excerpt' => $highlight($row['content'] ?? '', $q),
                    'url'     => '/chapter/' . $row['id'],
                ];
            }
        } catch (Exception $e) {
            error_log('Search chapters error: ' . $e->getMessage());
        }

        // --- Acts ---
        try {
            $rows = $this->db->exec(
                "SELECT t.id, t.project_id, t.title, t.content
                 FROM acts t JOIN projects p ON p.id = t.project_id
                 WHERE (t.title LIKE ? OR t.content LIKE ?) $projectClause LIMIT 20",
                [$term, $term, $projectParam]
            );
            foreach ((array) $rows as $row) {
                $results[] = [
                    'type'    => 'Acte',
                    'icon'    => 'fa-layer-group',
                    'title'   => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                    'excerpt' => $highlight($row['content'] ?? '', $q),
                    'url'     => '/act/' . $row['id'] . '/edit',
                ];
            }
        } catch (Exception $e) {
            error_log('Search acts error: ' . $e->getMessage());
        }

        // --- Notes ---
        try {
            $rows = $this->db->exec(
                "SELECT t.id, t.project_id, t.title, t.content
                 FROM notes t JOIN projects p ON p.id = t.project_id
                 WHERE (t.title LIKE ? OR t.content LIKE ?) $projectClause LIMIT 20",
                [$term, $term, $projectParam]
            );
            foreach ((array) $rows as $row) {
                $results[] = [
                    'type'    => 'Note',
                    'icon'    => 'fa-sticky-note',
                    'title'   => htmlspecialchars($row['title'] ?: '(sans titre)', ENT_QUOTES, 'UTF-8'),
                    'excerpt' => $highlight($row['content'] ?? '', $q),
                    'url'     => '/project/' . $row['project_id'] . '/note/edit?id=' . $row['id'],
                ];
            }
        } catch (Exception $e) {
            error_log('Search notes error: ' . $e->getMessage());
        }

        // --- Characters ---
        try {
            $rows = $this->db->exec(
                "SELECT t.id, t.project_id, t.name AS title, t.description AS content
                 FROM characters t JOIN projects p ON p.id = t.project_id
                 WHERE (t.name LIKE ? OR t.description LIKE ?) $projectClause LIMIT 20",
                [$term, $term, $projectParam]
            );
            foreach ((array) $rows as $row) {
                $results[] = [
                    'type'    => 'Personnage',
                    'icon'    => 'fa-user',
                    'title'   => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                    'excerpt' => $highlight($row['content'] ?? '', $q),
                    'url'     => '/character/' . $row['id'] . '/edit',
                ];
            }
        } catch (Exception $e) {
            error_log('Search characters error: ' . $e->getMessage());
        }

        // --- Elements (optional table) ---
        try {
            if ($this->db->exists('elements')) {
                $rows = $this->db->exec(
                    "SELECT t.id, t.project_id, t.title, t.content
                     FROM elements t JOIN projects p ON p.id = t.project_id
                     WHERE (t.title LIKE ? OR t.content LIKE ?) $projectClause LIMIT 20",
                    [$term, $term, $projectParam]
                );
                foreach ((array) $rows as $row) {
                    $results[] = [
                        'type'    => 'Élément',
                        'icon'    => 'fa-puzzle-piece',
                        'title'   => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                        'excerpt' => $highlight($row['content'] ?? '', $q),
                        'url'     => '/element/' . $row['id'],
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('Search elements error: ' . $e->getMessage());
        }

        return $results;
    }
}
