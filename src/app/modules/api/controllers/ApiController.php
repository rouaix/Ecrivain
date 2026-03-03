<?php

/**
 * ApiController — REST API for MCP access.
 *
 * Authentication : Bearer token in Authorization header (or ?token= fallback).
 * All responses    : JSON (content converted to plain text for content_text fields).
 * No session       : user context resolved from token on every request.
 * No CSRF          : token-based authentication replaces CSRF protection.
 */
class ApiController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────────────────────

    public function beforeRoute(Base $f3)
    {
        $userId = $this->authenticateApiRequest();
        if (!$userId) {
            $this->jsonError('Token manquant ou invalide.', 401, 'UNAUTHORIZED');
        }
        // Inject user into session so currentUser() works normally downstream.
        $_SESSION['user_id'] = $userId;
    }

    // ──────────────────────────────────────────────────────────────
    // PROJECTS
    // ──────────────────────────────────────────────────────────────

    public function listProjects()
    {
        $user = $this->currentUser();
        $rows = $this->db->exec(
            'SELECT id, title, description, created_at, updated_at
             FROM projects WHERE user_id = ? ORDER BY updated_at DESC',
            [$user['id']]
        );
        $this->jsonOut(['projects' => $rows]);
    }

    public function createProject()
    {
        $user = $this->currentUser();
        $body = $this->getBody();
        $title = trim($body['title'] ?? '');
        if ($title === '') {
            $this->jsonError('Le titre est obligatoire.', 422, 'INVALID_INPUT');
        }
        $description = trim($body['description'] ?? '');

        // Use default template
        $tpl = $this->db->exec('SELECT id FROM templates WHERE is_default = 1 LIMIT 1');
        $templateId = $tpl ? (int)$tpl[0]['id'] : null;

        $this->db->exec(
            'INSERT INTO projects (user_id, title, description, template_id) VALUES (?, ?, ?, ?)',
            [$user['id'], $title, $description ?: null, $templateId]
        );
        $id = (int)$this->db->lastInsertId('projects');
        $this->jsonOut($this->fetchProject($id), 201);
    }

    public function getProject()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->hasProjectAccess($id)) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        $project = $this->fetchProject($id);
        if (!$project) {
            $this->jsonError('Projet introuvable.', 404, 'NOT_FOUND');
        }
        $this->jsonOut($project);
    }

    public function updateProject()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->isOwner($id)) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        $body = $this->getBody();
        $fields = [];
        $params = [];
        if (isset($body['title'])) {
            $title = trim($body['title']);
            if ($title === '') $this->jsonError('Le titre ne peut pas être vide.', 422, 'INVALID_INPUT');
            $fields[] = 'title = ?'; $params[] = $title;
        }
        if (array_key_exists('description', $body)) {
            $fields[] = 'description = ?'; $params[] = trim($body['description']) ?: null;
        }
        if (empty($fields)) {
            $this->jsonError('Aucun champ à mettre à jour.', 422, 'INVALID_INPUT');
        }
        $params[] = $id;
        $this->db->exec('UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        $this->jsonOut($this->fetchProject($id));
    }

    public function deleteProject()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->isOwner($id)) {
            $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        }
        $rows = $this->db->exec('SELECT id FROM projects WHERE id = ?', [$id]);
        if (!$rows) $this->jsonError('Projet introuvable.', 404, 'NOT_FOUND');
        $this->db->exec('DELETE FROM projects WHERE id = ?', [$id]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // ACTS
    // ──────────────────────────────────────────────────────────────

    public function listActs()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $acts = $this->db->exec(
            'SELECT a.id, a.title, a.description, a.resume, a.order_index,
                    COUNT(c.id) AS chapters_count
             FROM acts a
             LEFT JOIN chapters c ON c.act_id = a.id
             WHERE a.project_id = ?
             GROUP BY a.id
             ORDER BY a.order_index ASC, a.id ASC',
            [$pid]
        );
        $this->jsonOut(['acts' => $acts]);
    }

    public function createAct()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $body = $this->getBody();
        $title = trim($body['title'] ?? '');
        if ($title === '') $this->jsonError('Le titre est obligatoire.', 422, 'INVALID_INPUT');

        $res = $this->db->exec('SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM acts WHERE project_id = ?', [$pid]);
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO acts (project_id, title, description, order_index) VALUES (?, ?, ?, ?)',
            [$pid, $title, trim($body['description'] ?? '') ?: null, $order]
        );
        $id = (int)$this->db->lastInsertId('acts');
        $this->jsonOut($this->fetchAct($id), 201);
    }

    public function getAct()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $act = $this->fetchAct($id);
        if (!$act) $this->jsonError('Acte introuvable.', 404, 'NOT_FOUND');
        if (!$this->hasProjectAccess((int)$act['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->jsonOut($act);
    }

    public function updateAct()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $act = $this->db->exec('SELECT project_id FROM acts WHERE id = ?', [$id]);
        if (!$act) $this->jsonError('Acte introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$act[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $body = $this->getBody();
        $fields = []; $params = [];
        if (isset($body['title'])) {
            $t = trim($body['title']);
            if ($t === '') $this->jsonError('Le titre ne peut pas être vide.', 422, 'INVALID_INPUT');
            $fields[] = 'title = ?'; $params[] = $t;
        }
        if (array_key_exists('description', $body)) { $fields[] = 'description = ?'; $params[] = trim($body['description']) ?: null; }
        if (array_key_exists('resume', $body))      { $fields[] = 'resume = ?';      $params[] = trim($body['resume']) ?: null; }
        if (empty($fields)) $this->jsonError('Aucun champ à mettre à jour.', 422, 'INVALID_INPUT');

        $params[] = $id;
        $this->db->exec('UPDATE acts SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        $this->jsonOut($this->fetchAct($id));
    }

    public function deleteAct()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $act = $this->db->exec('SELECT project_id FROM acts WHERE id = ?', [$id]);
        if (!$act) $this->jsonError('Acte introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$act[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->db->exec('DELETE FROM acts WHERE id = ?', [$id]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // CHAPTERS
    // ──────────────────────────────────────────────────────────────

    public function getChapter()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $ch = $this->db->exec('SELECT * FROM chapters WHERE id = ?', [$id]);
        if (!$ch) $this->jsonError('Chapitre introuvable.', 404, 'NOT_FOUND');
        $ch = $ch[0];
        if (!$this->hasProjectAccess((int)$ch['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->jsonOut([
            'id'           => (int)$ch['id'],
            'project_id'   => (int)$ch['project_id'],
            'act_id'       => $ch['act_id'] ? (int)$ch['act_id'] : null,
            'parent_id'    => $ch['parent_id'] ? (int)$ch['parent_id'] : null,
            'title'        => $ch['title'],
            'content_html' => $ch['content'] ?? '',
            'content_text' => $this->htmlToText($ch['content'] ?? ''),
            'resume'       => $ch['resume'] ?? '',
            'word_count'   => (int)$ch['word_count'],
            'is_exported'  => (bool)$ch['is_exported'],
            'order_index'  => (int)$ch['order_index'],
            'created_at'   => $ch['created_at'],
            'updated_at'   => $ch['updated_at'],
        ]);
    }

    public function createChapter()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $body = $this->getBody();
        $title = trim($body['title'] ?? '');
        if ($title === '') $this->jsonError('Le titre est obligatoire.', 422, 'INVALID_INPUT');

        $actId    = isset($body['act_id']) ? (int)$body['act_id'] : null;
        $content  = $body['content'] ?? '';
        $resume   = trim($body['resume'] ?? '');
        $wc       = $this->countWords($content);

        $res = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM chapters WHERE project_id = ? AND act_id ' . ($actId ? '= ?' : 'IS NULL'),
            $actId ? [$pid, $actId] : [$pid]
        );
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO chapters (project_id, act_id, title, content, resume, word_count, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$pid, $actId, $title, $content, $resume ?: null, $wc, $order]
        );
        $id = (int)$this->db->lastInsertId('chapters');

        $ch = $this->db->exec('SELECT * FROM chapters WHERE id = ?', [$id]);
        $ch = $ch[0];
        $this->jsonOut([
            'id'           => (int)$ch['id'],
            'project_id'   => (int)$ch['project_id'],
            'act_id'       => $ch['act_id'] ? (int)$ch['act_id'] : null,
            'title'        => $ch['title'],
            'content_html' => $ch['content'] ?? '',
            'content_text' => $this->htmlToText($ch['content'] ?? ''),
            'resume'       => $ch['resume'] ?? '',
            'word_count'   => (int)$ch['word_count'],
            'created_at'   => $ch['created_at'],
            'updated_at'   => $ch['updated_at'],
        ], 201);
    }

    public function updateChapter()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $ch = $this->db->exec('SELECT * FROM chapters WHERE id = ?', [$id]);
        if (!$ch) $this->jsonError('Chapitre introuvable.', 404, 'NOT_FOUND');
        $ch = $ch[0];
        if (!$this->isOwner((int)$ch['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $body = $this->getBody();
        $fields = []; $params = [];

        if (isset($body['title'])) {
            $t = trim($body['title']);
            if ($t === '') $this->jsonError('Le titre ne peut pas être vide.', 422, 'INVALID_INPUT');
            $fields[] = 'title = ?'; $params[] = $t;
        }
        if (array_key_exists('content', $body)) {
            // Save version before overwriting
            $this->saveChapterVersion((int)$id, $ch['content'], (int)$ch['word_count']);
            $wc = $this->countWords($body['content']);
            $fields[] = 'content = ?';    $params[] = $body['content'];
            $fields[] = 'word_count = ?'; $params[] = $wc;
        }
        if (array_key_exists('resume', $body)) { $fields[] = 'resume = ?'; $params[] = trim($body['resume']) ?: null; }

        if (empty($fields)) $this->jsonError('Aucun champ à mettre à jour.', 422, 'INVALID_INPUT');
        $params[] = $id;
        $this->db->exec('UPDATE chapters SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

        $ch = $this->db->exec('SELECT * FROM chapters WHERE id = ?', [$id])[0];
        $this->jsonOut([
            'id'           => (int)$ch['id'],
            'title'        => $ch['title'],
            'content_html' => $ch['content'] ?? '',
            'content_text' => $this->htmlToText($ch['content'] ?? ''),
            'resume'       => $ch['resume'] ?? '',
            'word_count'   => (int)$ch['word_count'],
            'updated_at'   => $ch['updated_at'],
        ]);
    }

    public function deleteChapter()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $ch = $this->db->exec('SELECT project_id FROM chapters WHERE id = ?', [$id]);
        if (!$ch) $this->jsonError('Chapitre introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$ch[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->db->exec('DELETE FROM chapters WHERE id = ?', [$id]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // SECTIONS
    // ──────────────────────────────────────────────────────────────

    public function listSections()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $rows = $this->db->exec(
            'SELECT id, type, title, comment, image_path, order_index, updated_at
             FROM sections WHERE project_id = ? ORDER BY order_index ASC, id ASC',
            [$pid]
        );
        $typeLabels = [
            'cover'        => 'Couverture',
            'preface'      => 'Préface',
            'introduction' => 'Introduction',
            'prologue'     => 'Prologue',
            'postface'     => 'Postface',
            'appendices'   => 'Annexes',
            'back_cover'   => 'Quatrième de couverture',
        ];
        foreach ($rows as &$r) {
            $r['type_label'] = $typeLabels[$r['type']] ?? $r['type'];
            $r['has_image']  = !empty($r['image_path']);
            unset($r['image_path']);
        }
        $this->jsonOut(['sections' => $rows]);
    }

    public function createSection()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $body = $this->getBody();
        $type = trim($body['type'] ?? '');
        $validTypes = ['cover','preface','introduction','prologue','postface','appendices','back_cover'];
        if (!in_array($type, $validTypes)) $this->jsonError('Type de section invalide.', 422, 'INVALID_INPUT');

        $this->db->exec(
            'INSERT INTO sections (project_id, type, title, content, comment) VALUES (?, ?, ?, ?, ?)',
            [$pid, $type, trim($body['title'] ?? '') ?: null,
             $body['content'] ?? null, trim($body['comment'] ?? '') ?: null]
        );
        $id = (int)$this->db->lastInsertId('sections');
        $this->jsonOut($this->fetchSection($id), 201);
    }

    public function getSection()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $sec = $this->fetchSection($id);
        if (!$sec) $this->jsonError('Section introuvable.', 404, 'NOT_FOUND');
        if (!$this->hasProjectAccess((int)$sec['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->jsonOut($sec);
    }

    public function updateSection()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM sections WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Section introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $body = $this->getBody();
        $fields = []; $params = [];
        if (array_key_exists('title',   $body)) { $fields[] = 'title = ?';   $params[] = trim($body['title']) ?: null; }
        if (array_key_exists('content', $body)) { $fields[] = 'content = ?'; $params[] = $body['content']; }
        if (array_key_exists('comment', $body)) { $fields[] = 'comment = ?'; $params[] = trim($body['comment']) ?: null; }
        if (empty($fields)) $this->jsonError('Aucun champ à mettre à jour.', 422, 'INVALID_INPUT');

        $params[] = $id;
        $this->db->exec('UPDATE sections SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        $this->jsonOut($this->fetchSection($id));
    }

    public function deleteSection()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM sections WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Section introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->db->exec('DELETE FROM sections WHERE id = ?', [$id]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // NOTES
    // ──────────────────────────────────────────────────────────────

    public function listNotes()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $rows = $this->db->exec(
            'SELECT id, title, comment, order_index, updated_at FROM notes WHERE project_id = ? ORDER BY order_index ASC, id ASC',
            [$pid]
        );
        $this->jsonOut(['notes' => $rows]);
    }

    public function createNote()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $body = $this->getBody();
        $title = trim($body['title'] ?? '');

        $res = $this->db->exec('SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM notes WHERE project_id = ?', [$pid]);
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO notes (project_id, title, content, comment, order_index) VALUES (?, ?, ?, ?, ?)',
            [$pid, $title ?: null, $body['content'] ?? null, trim($body['comment'] ?? '') ?: null, $order]
        );
        $id = (int)$this->db->lastInsertId('notes');
        $this->jsonOut($this->fetchNote($id), 201);
    }

    public function getNote()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        $note = $this->fetchNote($id);
        if (!$note) $this->jsonError('Note introuvable.', 404, 'NOT_FOUND');
        if (!$this->hasProjectAccess((int)$note['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->jsonOut($note);
    }

    public function updateNote()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM notes WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Note introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $body = $this->getBody();
        $fields = []; $params = [];
        if (array_key_exists('title',   $body)) { $fields[] = 'title = ?';   $params[] = trim($body['title']) ?: null; }
        if (array_key_exists('content', $body)) { $fields[] = 'content = ?'; $params[] = $body['content']; }
        if (array_key_exists('comment', $body)) { $fields[] = 'comment = ?'; $params[] = trim($body['comment']) ?: null; }
        if (empty($fields)) $this->jsonError('Aucun champ à mettre à jour.', 422, 'INVALID_INPUT');

        $params[] = $id;
        $this->db->exec('UPDATE notes SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        $this->jsonOut($this->fetchNote($id));
    }

    public function deleteNote()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM notes WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Note introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->db->exec('DELETE FROM notes WHERE id = ?', [$id]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // CHARACTERS
    // ──────────────────────────────────────────────────────────────

    public function listCharacters()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $rows = $this->db->exec(
            'SELECT id, name, description, comment, created_at, updated_at FROM characters WHERE project_id = ? ORDER BY name ASC',
            [$pid]
        );
        $this->jsonOut(['characters' => $rows]);
    }

    public function createCharacter()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $body = $this->getBody();
        $name = trim($body['name'] ?? '');
        if ($name === '') $this->jsonError('Le nom est obligatoire.', 422, 'INVALID_INPUT');

        $this->db->exec(
            'INSERT INTO characters (project_id, name, description, comment) VALUES (?, ?, ?, ?)',
            [$pid, $name, trim($body['description'] ?? '') ?: null, trim($body['comment'] ?? '') ?: null]
        );
        $id = (int)$this->db->lastInsertId('characters');
        $this->jsonOut($this->fetchCharacter($id), 201);
    }

    public function getCharacter()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $chr = $this->fetchCharacter($id);
        if (!$chr) $this->jsonError('Personnage introuvable.', 404, 'NOT_FOUND');
        if (!$this->hasProjectAccess((int)$chr['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->jsonOut($chr);
    }

    public function updateCharacter()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM characters WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Personnage introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $body = $this->getBody();
        $fields = []; $params = [];
        if (isset($body['name'])) {
            $n = trim($body['name']);
            if ($n === '') $this->jsonError('Le nom ne peut pas être vide.', 422, 'INVALID_INPUT');
            $fields[] = 'name = ?'; $params[] = $n;
        }
        if (array_key_exists('description', $body)) { $fields[] = 'description = ?'; $params[] = trim($body['description']) ?: null; }
        if (array_key_exists('comment',     $body)) { $fields[] = 'comment = ?';     $params[] = trim($body['comment']) ?: null; }
        if (empty($fields)) $this->jsonError('Aucun champ à mettre à jour.', 422, 'INVALID_INPUT');

        $params[] = $id;
        $this->db->exec('UPDATE characters SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        $this->jsonOut($this->fetchCharacter($id));
    }

    public function deleteCharacter()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM characters WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Personnage introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->db->exec('DELETE FROM characters WHERE id = ?', [$id]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // ELEMENTS
    // ──────────────────────────────────────────────────────────────

    public function listElements()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $rows = $this->db->exec(
            'SELECT e.id, e.title, e.parent_id, e.order_index, e.template_element_id,
                    te.element_type, te.config_json
             FROM elements e
             LEFT JOIN template_elements te ON te.id = e.template_element_id
             WHERE e.project_id = ?
             ORDER BY te.display_order ASC, e.order_index ASC, e.id ASC',
            [$pid]
        );
        foreach ($rows as &$r) {
            $cfg = json_decode($r['config_json'] ?? '{}', true);
            $r['type_label'] = $cfg['label_singular'] ?? $cfg['label'] ?? $r['element_type'];
            unset($r['config_json']);
        }
        $this->jsonOut(['elements' => $rows]);
    }

    public function createElement()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $body = $this->getBody();
        $title = trim($body['title'] ?? '');
        if ($title === '') $this->jsonError('Le titre est obligatoire.', 422, 'INVALID_INPUT');
        $teid = (int)($body['template_element_id'] ?? 0);
        if (!$teid) $this->jsonError('template_element_id est obligatoire.', 422, 'INVALID_INPUT');

        $check = $this->db->exec('SELECT id FROM template_elements WHERE id = ?', [$teid]);
        if (!$check) $this->jsonError('template_element_id invalide.', 422, 'INVALID_INPUT');

        $parentId = isset($body['parent_id']) ? (int)$body['parent_id'] : null;
        $res = $this->db->exec(
            'SELECT COALESCE(MAX(order_index),0)+1 AS nxt FROM elements WHERE project_id = ? AND template_element_id = ? AND parent_id IS NULL',
            [$pid, $teid]
        );
        $order = (int)$res[0]['nxt'];

        $this->db->exec(
            'INSERT INTO elements (project_id, template_element_id, title, parent_id, order_index) VALUES (?, ?, ?, ?, ?)',
            [$pid, $teid, $title, $parentId, $order]
        );
        $id = (int)$this->db->lastInsertId('elements');
        $this->jsonOut($this->fetchElement($id), 201);
    }

    public function getElement()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $el  = $this->fetchElement($id);
        if (!$el) $this->jsonError('Élément introuvable.', 404, 'NOT_FOUND');
        if (!$this->hasProjectAccess((int)$el['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->jsonOut($el);
    }

    public function updateElement()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM elements WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Élément introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $body = $this->getBody();
        $fields = []; $params = [];
        if (isset($body['title'])) {
            $t = trim($body['title']);
            if ($t === '') $this->jsonError('Le titre ne peut pas être vide.', 422, 'INVALID_INPUT');
            $fields[] = 'title = ?'; $params[] = $t;
        }
        if (array_key_exists('content', $body)) { $fields[] = 'content = ?'; $params[] = $body['content']; }
        if (array_key_exists('resume',  $body)) { $fields[] = 'resume = ?';  $params[] = trim($body['resume']) ?: null; }
        if (empty($fields)) $this->jsonError('Aucun champ à mettre à jour.', 422, 'INVALID_INPUT');

        $params[] = $id;
        $this->db->exec('UPDATE elements SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        $this->jsonOut($this->fetchElement($id));
    }

    public function deleteElement()
    {
        $id  = (int)$this->f3->get('PARAMS.id');
        $row = $this->db->exec('SELECT project_id FROM elements WHERE id = ?', [$id]);
        if (!$row) $this->jsonError('Élément introuvable.', 404, 'NOT_FOUND');
        if (!$this->isOwner((int)$row[0]['project_id'])) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $this->db->exec('DELETE FROM elements WHERE id = ?', [$id]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // IMAGES (project files)
    // ──────────────────────────────────────────────────────────────

    public function listImages()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->hasProjectAccess($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        $rows = $this->db->exec(
            'SELECT id, filename, filepath, filetype, filesize, uploaded_at
             FROM project_files WHERE project_id = ? ORDER BY uploaded_at DESC',
            [$pid]
        );
        $base = $this->f3->get('BASE');
        foreach ($rows as &$r) {
            $r['url']      = $base . '/' . ltrim(str_replace('\\', '/', $r['filepath']), '/');
            $r['size_kb']  = round($r['filesize'] / 1024, 1);
        }
        $this->jsonOut(['images' => $rows]);
    }

    public function uploadImage()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
        if (empty($_FILES['file'])) $this->jsonError('Aucun fichier reçu (champ: file).', 422, 'INVALID_INPUT');

        $validation = $this->validateImageUpload($_FILES['file'], 5);
        if (!$validation['success']) $this->jsonError($validation['error'], 422, 'INVALID_INPUT');

        $ownerEmail = $this->getProjectOwnerEmail($pid);
        $uploadDir  = $this->f3->get('BASEPATH') . 'public/uploads/' . $pid . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = $validation['extension'];
        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $uploadDir . $safeName;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
            $this->jsonError('Erreur lors de la sauvegarde du fichier.', 500, 'SERVER_ERROR');
        }

        $relPath = 'public/uploads/' . $pid . '/' . $safeName;
        $this->db->exec(
            'INSERT INTO project_files (project_id, filename, filepath, filetype, filesize) VALUES (?, ?, ?, ?, ?)',
            [$pid, $_FILES['file']['name'], $relPath, $_FILES['file']['type'], $_FILES['file']['size']]
        );
        $fid  = (int)$this->db->lastInsertId('project_files');
        $base = $this->f3->get('BASE');
        $this->jsonOut([
            'id'       => $fid,
            'filename' => $_FILES['file']['name'],
            'url'      => $base . '/' . $relPath,
            'size_kb'  => round($_FILES['file']['size'] / 1024, 1),
        ], 201);
    }

    public function deleteImage()
    {
        $pid = (int)$this->f3->get('PARAMS.pid');
        $fid = (int)$this->f3->get('PARAMS.fid');
        if (!$this->isOwner($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $row = $this->db->exec('SELECT filepath FROM project_files WHERE id = ? AND project_id = ?', [$fid, $pid]);
        if (!$row) $this->jsonError('Fichier introuvable.', 404, 'NOT_FOUND');

        $fullPath = $this->f3->get('BASEPATH') . $row[0]['filepath'];
        if (file_exists($fullPath)) @unlink($fullPath);

        $this->db->exec('DELETE FROM project_files WHERE id = ?', [$fid]);
        $this->jsonOut(['status' => 'ok', 'deleted_id' => $fid]);
    }

    // ──────────────────────────────────────────────────────────────
    // EXPORT MARKDOWN
    // ──────────────────────────────────────────────────────────────

    public function exportMarkdown()
    {
        $id = (int)$this->f3->get('PARAMS.id');
        if (!$this->hasProjectAccess($id)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');

        $exporter = new ProjectExportController();
        $content  = $exporter->generateExportContent($id, 'markdown');
        if ($content === null) $this->jsonError('Projet introuvable.', 404, 'NOT_FOUND');

        header('Content-Type: text/markdown; charset=utf-8');
        echo $content;
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // SEARCH
    // ──────────────────────────────────────────────────────────────

    public function search()
    {
        $q   = trim($this->f3->get('GET.q') ?? '');
        $pid = $this->f3->get('GET.pid') ? (int)$this->f3->get('GET.pid') : null;

        if ($q === '') $this->jsonError('Paramètre q obligatoire.', 422, 'INVALID_INPUT');
        if (strlen($q) < 2) $this->jsonError('La recherche doit contenir au moins 2 caractères.', 422, 'INVALID_INPUT');

        $user    = $this->currentUser();
        $results = [];
        $like    = '%' . $q . '%';

        // Build project scope: only projects accessible to the user
        if ($pid) {
            if (!$this->hasProjectAccess($pid)) $this->jsonError('Accès refusé.', 403, 'FORBIDDEN');
            $projectIds = [$pid];
        } else {
            $ownedRows = $this->db->exec('SELECT id FROM projects WHERE user_id = ?', [$user['id']]);
            $collabRows = $this->db->exec(
                'SELECT project_id AS id FROM project_collaborators WHERE user_id = ? AND status = "accepted"',
                [$user['id']]
            );
            $projectIds = array_column(array_merge($ownedRows, $collabRows), 'id');
        }
        if (empty($projectIds)) { $this->jsonOut(['query' => $q, 'results' => []]); }

        $inList = implode(',', array_map('intval', $projectIds));

        // Chapters
        $rows = $this->db->exec(
            "SELECT 'chapter' AS type, id, project_id, title,
                    SUBSTRING(content, GREATEST(1, LOCATE(?, content)-60), 120) AS excerpt
             FROM chapters WHERE project_id IN ($inList) AND (title LIKE ? OR content LIKE ?)",
            [$q, $like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'chapter', 'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'], 'excerpt' => strip_tags($r['excerpt'])];
        }

        // Characters
        $rows = $this->db->exec(
            "SELECT id, project_id, name AS title, description AS excerpt
             FROM characters WHERE project_id IN ($inList) AND (name LIKE ? OR description LIKE ?)",
            [$like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'character', 'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'], 'excerpt' => substr(strip_tags($r['excerpt'] ?? ''), 0, 120)];
        }

        // Notes
        $rows = $this->db->exec(
            "SELECT id, project_id, title,
                    SUBSTRING(content, GREATEST(1, LOCATE(?, content)-60), 120) AS excerpt
             FROM notes WHERE project_id IN ($inList) AND (title LIKE ? OR content LIKE ?)",
            [$q, $like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'note', 'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'] ?? '(sans titre)', 'excerpt' => strip_tags($r['excerpt'])];
        }

        // Elements
        $rows = $this->db->exec(
            "SELECT e.id, e.project_id, e.title,
                    SUBSTRING(e.content, GREATEST(1, LOCATE(?, e.content)-60), 120) AS excerpt,
                    te.element_type
             FROM elements e
             LEFT JOIN template_elements te ON te.id = e.template_element_id
             WHERE e.project_id IN ($inList) AND (e.title LIKE ? OR e.content LIKE ?)",
            [$q, $like, $like]
        );
        foreach ($rows as $r) {
            $results[] = ['type' => 'element', 'element_type' => $r['element_type'],
                          'id' => (int)$r['id'], 'project_id' => (int)$r['project_id'],
                          'title' => $r['title'], 'excerpt' => strip_tags($r['excerpt'])];
        }

        $this->jsonOut(['query' => $q, 'results' => $results]);
    }

    // ──────────────────────────────────────────────────────────────
    // Private fetch helpers
    // ──────────────────────────────────────────────────────────────

    private function fetchProject(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM projects WHERE id = ?', [$id]);
        if (!$rows) return null;
        $p = $rows[0];

        $acts = $this->db->exec(
            'SELECT a.id, a.title, a.description, a.resume, a.order_index FROM acts a
             WHERE a.project_id = ? ORDER BY a.order_index ASC, a.id ASC',
            [$id]
        );
        foreach ($acts as &$a) {
            $a['chapters'] = $this->db->exec(
                'SELECT id, title, resume, word_count, order_index FROM chapters
                 WHERE project_id = ? AND act_id = ? ORDER BY order_index ASC, id ASC',
                [$id, $a['id']]
            );
        }
        // Chapters without act
        $freeChapters = $this->db->exec(
            'SELECT id, title, resume, word_count, order_index FROM chapters
             WHERE project_id = ? AND act_id IS NULL ORDER BY order_index ASC, id ASC',
            [$id]
        );

        $sections = $this->db->exec(
            'SELECT id, type, title, order_index FROM sections WHERE project_id = ? ORDER BY order_index ASC', [$id]
        );
        $typeLabels = ['cover'=>'Couverture','preface'=>'Préface','introduction'=>'Introduction',
                       'prologue'=>'Prologue','postface'=>'Postface','appendices'=>'Annexes','back_cover'=>'Quatrième de couverture'];
        foreach ($sections as &$s) { $s['type_label'] = $typeLabels[$s['type']] ?? $s['type']; }

        $counts = $this->db->exec(
            'SELECT
               (SELECT COUNT(*) FROM characters WHERE project_id = ?) AS characters_count,
               (SELECT COUNT(*) FROM notes      WHERE project_id = ?) AS notes_count,
               (SELECT COUNT(*) FROM elements   WHERE project_id = ?) AS elements_count',
            [$id, $id, $id]
        )[0];

        return [
            'id'               => (int)$p['id'],
            'title'            => $p['title'],
            'description'      => $p['description'],
            'template_id'      => $p['template_id'] ? (int)$p['template_id'] : null,
            'created_at'       => $p['created_at'],
            'updated_at'       => $p['updated_at'],
            'acts'             => $acts,
            'chapters_without_act' => $freeChapters,
            'sections'         => $sections,
            'characters_count' => (int)$counts['characters_count'],
            'notes_count'      => (int)$counts['notes_count'],
            'elements_count'   => (int)$counts['elements_count'],
        ];
    }

    private function fetchAct(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM acts WHERE id = ?', [$id]);
        if (!$rows) return null;
        $a = $rows[0];
        $chapters = $this->db->exec(
            'SELECT id, title, resume, word_count, order_index FROM chapters
             WHERE act_id = ? ORDER BY order_index ASC, id ASC', [$id]
        );
        return [
            'id'          => (int)$a['id'],
            'project_id'  => (int)$a['project_id'],
            'title'       => $a['title'],
            'description' => $a['description'],
            'resume'      => $a['resume'],
            'order_index' => (int)$a['order_index'],
            'chapters'    => $chapters,
        ];
    }

    private function fetchSection(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM sections WHERE id = ?', [$id]);
        if (!$rows) return null;
        $s = $rows[0];
        $typeLabels = ['cover'=>'Couverture','preface'=>'Préface','introduction'=>'Introduction',
                       'prologue'=>'Prologue','postface'=>'Postface','appendices'=>'Annexes','back_cover'=>'Quatrième de couverture'];
        return [
            'id'           => (int)$s['id'],
            'project_id'   => (int)$s['project_id'],
            'type'         => $s['type'],
            'type_label'   => $typeLabels[$s['type']] ?? $s['type'],
            'title'        => $s['title'],
            'content_html' => $s['content'] ?? '',
            'content_text' => $this->htmlToText($s['content'] ?? ''),
            'comment'      => $s['comment'],
            'image_url'    => $s['image_path'] ? $this->f3->get('BASE') . '/' . ltrim($s['image_path'], '/') : null,
            'updated_at'   => $s['updated_at'],
        ];
    }

    private function fetchNote(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM notes WHERE id = ?', [$id]);
        if (!$rows) return null;
        $n = $rows[0];
        return [
            'id'           => (int)$n['id'],
            'project_id'   => (int)$n['project_id'],
            'title'        => $n['title'],
            'content_html' => $n['content'] ?? '',
            'content_text' => $this->htmlToText($n['content'] ?? ''),
            'comment'      => $n['comment'],
            'updated_at'   => $n['updated_at'],
        ];
    }

    private function fetchCharacter(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM characters WHERE id = ?', [$id]);
        if (!$rows) return null;
        $c = $rows[0];
        return [
            'id'          => (int)$c['id'],
            'project_id'  => (int)$c['project_id'],
            'name'        => $c['name'],
            'description' => $c['description'],
            'comment'     => $c['comment'],
            'created_at'  => $c['created_at'],
            'updated_at'  => $c['updated_at'],
        ];
    }

    private function fetchElement(int $id): ?array
    {
        $rows = $this->db->exec(
            'SELECT e.*, te.element_type, te.config_json
             FROM elements e
             LEFT JOIN template_elements te ON te.id = e.template_element_id
             WHERE e.id = ?',
            [$id]
        );
        if (!$rows) return null;
        $e = $rows[0];
        $cfg = json_decode($e['config_json'] ?? '{}', true);
        $subElements = $this->db->exec(
            'SELECT id, title, order_index FROM elements WHERE parent_id = ? ORDER BY order_index ASC', [$id]
        );
        return [
            'id'                  => (int)$e['id'],
            'project_id'          => (int)$e['project_id'],
            'template_element_id' => (int)$e['template_element_id'],
            'element_type'        => $e['element_type'],
            'type_label'          => $cfg['label_singular'] ?? $cfg['label'] ?? $e['element_type'],
            'title'               => $e['title'],
            'content_html'        => $e['content'] ?? '',
            'content_text'        => $this->htmlToText($e['content'] ?? ''),
            'resume'              => $e['resume'],
            'parent_id'           => $e['parent_id'] ? (int)$e['parent_id'] : null,
            'order_index'         => (int)$e['order_index'],
            'sub_elements'        => $subElements,
            'updated_at'          => $e['updated_at'],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Utility helpers
    // ──────────────────────────────────────────────────────────────

    private function jsonOut(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function jsonError(string $message, int $status, string $code): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message, 'code' => $code], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getBody(): array
    {
        $raw = $this->f3->get('BODY') ?: file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function htmlToText(string $html): string
    {
        if (empty($html)) return '';
        $text = str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>', '</h1>', '</h2>', '</h3>'], "\n", $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\n{3,}/', "\n\n", $text));
    }

    private function countWords(string $html): int
    {
        return str_word_count($this->htmlToText($html));
    }

    private function saveChapterVersion(int $chapterId, ?string $content, int $wordCount): void
    {
        if (empty($content)) return;
        $this->db->exec(
            'INSERT INTO chapter_versions (chapter_id, content, word_count) VALUES (?, ?, ?)',
            [$chapterId, $content, $wordCount]
        );
        // Keep max 10 versions per chapter
        $this->db->exec(
            'DELETE FROM chapter_versions WHERE chapter_id = ?
             AND id NOT IN (SELECT id FROM (
                 SELECT id FROM chapter_versions WHERE chapter_id = ?
                 ORDER BY created_at DESC LIMIT 10
             ) AS keep)',
            [$chapterId, $chapterId]
        );
    }
}
