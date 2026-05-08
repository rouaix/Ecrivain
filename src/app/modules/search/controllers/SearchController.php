<?php

class SearchController extends Controller
{
    private const VALID_TYPES = ['chapitre', 'acte', 'note', 'personnage', 'element', 'glossaire'];

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
        $type      = $this->f3->get('GET.type') ?? '';
        $dialogues = (bool) ($this->f3->get('GET.dialogues') ?? false);

        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = '';
        }

        $results = [];
        $error   = null;

        if (mb_strlen($q) >= 2) {
            $results = $this->doSearch($q, $user['id'], $projectId ?: null, $type, $dialogues);
        } elseif ($q !== '') {
            $error = 'Veuillez saisir au moins 2 caractères.';
        }

        $projectModel = $this->projectModel();
        $projects = $projectModel->find(['user_id=?', $user['id']], ['order' => 'title ASC']) ?: [];

        $this->render('search/results.html', [
            'title'      => $q ? 'Recherche : ' . $q : 'Recherche',
            'q'          => $q,
            'projectId'  => $projectId,
            'activeType' => $type,
            'dialogues'  => $dialogues,
            'projects'   => $projects,
            'results'    => $results,
            'error'      => $error,
        ]);
    }

    private function doSearch(string $q, int $userId, ?int $projectId, string $type, bool $dialoguesOnly): array
    {
        $term    = '%' . $q . '%';
        $results = [];

        $projectClause = $projectId ? 'AND t.project_id = ?' : 'AND p.user_id = ?';
        $projectParam  = $projectId ?: $userId;

        // Dialogue content restriction: chapters/notes/acts containing French dialogue markers
        $dialogueClause = $dialoguesOnly
            ? "AND (t.content LIKE '%\xe2\x80\x94%' OR t.content LIKE '%\xc2\xab%')"
            : '';

        $cleanTitle = function (string $raw): string {
            return strip_tags(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'));
        };

        $highlight = function (string $html, string $q, int $ctxLen = 120, bool $dlgOnly = false): string {
            if ($dlgOnly) {
                // Extract only dialogue paragraphs (lines starting with — or «)
                $normalized = str_replace(['</p>', '</P>', '<br>', '<br/>', '<br />'], "\n", $html);
                $rawText    = strip_tags($normalized);
                $rawText    = html_entity_decode($rawText, ENT_QUOTES, 'UTF-8');
                $lines      = preg_split('/\n+/', $rawText) ?: [];
                $dlgLines   = array_filter($lines, function ($line) {
                    $t = ltrim($line);
                    return $t !== '' && ($t[0] === '—' || $t[0] === '«');
                });
                $text = implode(' … ', array_map('trim', $dlgLines));
            } else {
                $text = strip_tags($html);
                $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            }

            if ($text === '') {
                return '';
            }

            $pos   = mb_stripos($text, $q);
            $start = $pos !== false ? max(0, $pos - $ctxLen) : 0;
            $len   = min(mb_strlen($text) - $start, $ctxLen * 2 + mb_strlen($q));

            $extract  = mb_substr($text, $start, $len);
            $prefix   = $start > 0 ? '…' : '';
            $suffix   = ($start + $len) < mb_strlen($text) ? '…' : '';
            $encoded  = htmlspecialchars($extract, ENT_QUOTES, 'UTF-8');
            $qEncoded = preg_quote(htmlspecialchars($q, ENT_QUOTES, 'UTF-8'), '/');
            $marked   = preg_replace('/(' . $qEncoded . ')/iu', '<mark>$1</mark>', $encoded);

            return $prefix . $marked . $suffix;
        };

        $all = ($type === '');

        // --- Chapters ---
        if ($all || $type === 'chapitre') {
            try {
                $rows = $this->db->exec(
                    "SELECT t.id, t.project_id, t.title, t.content, t.parent_id,
                            parent.title AS parent_title
                     FROM chapters t JOIN projects p ON p.id = t.project_id
                     LEFT JOIN chapters parent ON parent.id = t.parent_id
                     WHERE (t.title LIKE ? OR t.content LIKE ?) $projectClause $dialogueClause LIMIT 20",
                    [$term, $term, $projectParam]
                );
                foreach ((array) $rows as $row) {
                    $isSub   = (bool) $row['parent_id'];
                    $results[] = [
                        'type'    => $isSub ? 'Sous-chapitre' : 'Chapitre',
                        'context' => $isSub ? $cleanTitle($row['parent_title'] ?? '') : null,
                        'icon'    => 'fa-book-open',
                        'title'   => $cleanTitle($row['title']),
                        'excerpt' => $highlight($row['content'] ?? '', $q, 120, $dialoguesOnly),
                        'url'     => '/chapter/' . $row['id'],
                    ];
                }
            } catch (Exception $e) {
                error_log('Search chapters error: ' . $e->getMessage());
            }
        }

        // --- Acts ---
        if ($all || $type === 'acte') {
            try {
                $rows = $this->db->exec(
                    "SELECT t.id, t.project_id, t.title, t.content
                     FROM acts t JOIN projects p ON p.id = t.project_id
                     WHERE (t.title LIKE ? OR t.content LIKE ?) $projectClause $dialogueClause LIMIT 20",
                    [$term, $term, $projectParam]
                );
                foreach ((array) $rows as $row) {
                    $results[] = [
                        'type'    => 'Acte',
                        'icon'    => 'fa-layer-group',
                        'title'   => $cleanTitle($row['title']),
                        'excerpt' => $highlight($row['content'] ?? '', $q, 120, $dialoguesOnly),
                        'url'     => '/act/' . $row['id'] . '/edit',
                    ];
                }
            } catch (Exception $e) {
                error_log('Search acts error: ' . $e->getMessage());
            }
        }

        // --- Notes ---
        if ($all || $type === 'note') {
            try {
                $rows = $this->db->exec(
                    "SELECT t.id, t.project_id, t.title, t.content
                     FROM notes t JOIN projects p ON p.id = t.project_id
                     WHERE (t.title LIKE ? OR t.content LIKE ?) $projectClause $dialogueClause LIMIT 20",
                    [$term, $term, $projectParam]
                );
                foreach ((array) $rows as $row) {
                    $results[] = [
                        'type'    => 'Note',
                        'icon'    => 'fa-sticky-note',
                        'title'   => $cleanTitle($row['title'] ?: '(sans titre)'),
                        'excerpt' => $highlight($row['content'] ?? '', $q, 120, $dialoguesOnly),
                        'url'     => '/project/' . $row['project_id'] . '/note/edit?id=' . $row['id'],
                    ];
                }
            } catch (Exception $e) {
                error_log('Search notes error: ' . $e->getMessage());
            }
        }

        // --- Characters ---
        if ($all || $type === 'personnage') {
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
                        'title'   => $cleanTitle($row['title']),
                        'excerpt' => $highlight($row['content'] ?? '', $q),
                        'url'     => '/character/' . $row['id'] . '/edit',
                    ];
                }
            } catch (Exception $e) {
                error_log('Search characters error: ' . $e->getMessage());
            }
        }

        // --- Elements (optional table) ---
        if ($all || $type === 'element') {
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
                            'title'   => $cleanTitle($row['title']),
                            'excerpt' => $highlight($row['content'] ?? '', $q),
                            'url'     => '/element/' . $row['id'],
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log('Search elements error: ' . $e->getMessage());
            }
        }

        // --- Glossary ---
        if ($all || $type === 'glossaire') {
            try {
                if ($this->db->exists('glossary_entries')) {
                    $rows = $this->db->exec(
                        "SELECT t.id, t.project_id, t.term AS title, t.definition AS content
                         FROM glossary_entries t JOIN projects p ON p.id = t.project_id
                         WHERE (t.term LIKE ? OR t.definition LIKE ?) $projectClause LIMIT 20",
                        [$term, $term, $projectParam]
                    );
                    foreach ((array) $rows as $row) {
                        $results[] = [
                            'type'    => 'Glossaire',
                            'icon'    => 'fa-book',
                            'title'   => $cleanTitle($row['title']),
                            'excerpt' => $highlight($row['content'] ?? '', $q),
                            'url'     => '/project/' . $row['project_id'] . '/glossary/' . $row['id'] . '/edit',
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log('Search glossary error: ' . $e->getMessage());
            }
        }

        return $results;
    }
}
