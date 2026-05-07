<?php

/**
 * ApiFetchService — Service centralisé pour récupérer les entités avec leurs relations.
 * Utilisé par ApiController pour éviter la duplication de code.
 */
class ApiFetchService
{
    private \DB\SQL $db;
    private Base $f3;

    public function __construct(\DB\SQL $db, Base $f3)
    {
        $this->db = $db;
        $this->f3 = $f3;
    }

    /**
     * Récupère un projet avec ses actes, chapitres, sections et comptes.
     */
    public function fetchProject(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM projects WHERE id = ?', [$id]);
        if (!$rows) return null;
        $p = $rows[0];

        $acts = $this->db->exec(
            'SELECT a.id, a.title, a.description, a.resume, a.order_index FROM acts a
             WHERE a.project_id = ? ORDER BY a.order_index ASC, a.id ASC',
            [$id]
        ) ?: [];
        foreach ($acts as &$a) {
            $a['chapters'] = $this->db->exec(
                'SELECT id, title, resume, word_count, order_index FROM chapters
                 WHERE project_id = ? AND act_id = ? ORDER BY order_index ASC, id ASC',
                [$id, $a['id']]
            ) ?: [];
        }
        // Chapters without act
        $freeChapters = $this->db->exec(
            'SELECT id, title, resume, word_count, order_index FROM chapters
             WHERE project_id = ? AND act_id IS NULL ORDER BY order_index ASC, id ASC',
            [$id]
        ) ?: [];

        $sections = $this->db->exec(
            'SELECT id, type, title, order_index FROM sections WHERE project_id = ? ORDER BY order_index ASC', [$id]
        );
        $typeLabels = [
            'cover' => 'Couverture',
            'preface' => 'Préface',
            'introduction' => 'Introduction',
            'prologue' => 'Prologue',
            'postface' => 'Postface',
            'appendices' => 'Annexes',
            'back_cover' => 'Quatrième de couverture'
        ];
        foreach ($sections as &$s) {
            $s['type_label'] = $typeLabels[$s['type']] ?? $s['type'];
        }

        $counts = $this->db->exec(
            'SELECT
               (SELECT COUNT(*) FROM characters WHERE project_id = ?) AS characters_count,
               (SELECT COUNT(*) FROM notes WHERE project_id = ?) AS notes_count,
               (SELECT COUNT(*) FROM elements WHERE project_id = ?) AS elements_count',
            [$id, $id, $id]
        )[0];

        return [
            'id' => (int)$p['id'],
            'title' => $p['title'],
            'description' => $p['description'],
            'template_id' => $p['template_id'] ? (int)$p['template_id'] : null,
            'created_at' => $p['created_at'],
            'updated_at' => $p['updated_at'],
            'acts' => $acts,
            'chapters_without_act' => $freeChapters,
            'sections' => $sections,
            'characters_count' => (int)($counts['characters_count'] ?? 0),
            'notes_count' => (int)($counts['notes_count'] ?? 0),
            'elements_count' => (int)($counts['elements_count'] ?? 0),
        ];
    }

    /**
     * Récupère un acte avec ses chapitres.
     */
    public function fetchAct(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM acts WHERE id = ?', [$id]);
        if (!$rows) return null;
        $a = $rows[0];
        $chapters = $this->db->exec(
            'SELECT id, title, resume, word_count, order_index FROM chapters
             WHERE act_id = ? ORDER BY order_index ASC, id ASC', [$id]
        ) ?: [];
        return [
            'id' => (int)$a['id'],
            'project_id' => (int)$a['project_id'],
            'title' => $a['title'],
            'description' => $a['description'],
            'resume' => $a['resume'],
            'order_index' => (int)$a['order_index'],
            'chapters' => $chapters,
        ];
    }

    /**
     * Récupère une section avec ses métadonnées.
     */
    public function fetchSection(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM sections WHERE id = ?', [$id]);
        if (!$rows) return null;
        $s = $rows[0];
        $typeLabels = [
            'cover' => 'Couverture',
            'preface' => 'Préface',
            'introduction' => 'Introduction',
            'prologue' => 'Prologue',
            'postface' => 'Postface',
            'appendices' => 'Annexes',
            'back_cover' => 'Quatrième de couverture'
        ];
        return [
            'id' => (int)$s['id'],
            'project_id' => (int)$s['project_id'],
            'type' => $s['type'],
            'type_label' => $typeLabels[$s['type']] ?? $s['type'],
            'title' => $s['title'],
            'content_html' => $s['content'] ?? '',
            'content_text' => $this->htmlToText($s['content'] ?? ''),
            'comment' => $s['comment'],
            'image_url' => $s['image_path'] ? $this->f3->get('BASE') . '/' . ltrim($s['image_path'], '/') : null,
            'updated_at' => $s['updated_at'],
        ];
    }

    /**
     * Récupère un chapitre.
     */
    public function fetchChapter(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM chapters WHERE id = ?', [$id]);
        if (!$rows) return null;
        $c = $rows[0];
        return [
            'id' => (int)$c['id'],
            'project_id' => (int)$c['project_id'],
            'act_id' => $c['act_id'] ? (int)$c['act_id'] : null,
            'parent_id' => $c['parent_id'] ? (int)$c['parent_id'] : null,
            'title' => $c['title'],
            'content_html' => $c['content'] ?? '',
            'content_text' => $this->htmlToText($c['content'] ?? ''),
            'resume' => $c['resume'] ?? '',
            'word_count' => (int)$c['word_count'],
            'is_exported' => (bool)$c['is_exported'],
            'order_index' => (int)$c['order_index'],
            'created_at' => $c['created_at'],
            'updated_at' => $c['updated_at'],
        ];
    }

    /**
     * Récupère une note.
     */
    public function fetchNote(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM notes WHERE id = ?', [$id]);
        if (!$rows) return null;
        $n = $rows[0];
        return [
            'id' => (int)$n['id'],
            'project_id' => (int)$n['project_id'],
            'title' => $n['title'],
            'content_html' => $n['content'] ?? '',
            'content_text' => $this->htmlToText($n['content'] ?? ''),
            'comment' => $n['comment'],
            'updated_at' => $n['updated_at'],
        ];
    }

    /**
     * Récupère un personnage.
     */
    public function fetchCharacter(int $id): ?array
    {
        $rows = $this->db->exec('SELECT * FROM characters WHERE id = ?', [$id]);
        if (!$rows) return null;
        $c = $rows[0];
        return [
            'id' => (int)$c['id'],
            'project_id' => (int)$c['project_id'],
            'name' => $c['name'],
            'description' => $c['description'],
            'comment' => $c['comment'],
            'created_at' => $c['created_at'],
            'updated_at' => $c['updated_at'],
        ];
    }

    /**
     * Récupère un élément avec ses sous-éléments.
     */
    public function fetchElement(int $id): ?array
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
        $subRows = $this->db->exec(
            'SELECT id, title, content, resume, order_index, updated_at FROM elements WHERE parent_id = ? ORDER BY order_index ASC', [$id]
        );
        $subElements = array_map(function ($s) {
            return [
                'id' => (int)$s['id'],
                'title' => $s['title'],
                'content_html' => $s['content'] ?? '',
                'content_text' => $this->htmlToText($s['content'] ?? ''),
                'resume' => $s['resume'] ?? '',
                'order_index' => (int)$s['order_index'],
                'updated_at' => $s['updated_at'],
            ];
        }, $subRows ?: []);
        return [
            'id' => (int)$e['id'],
            'project_id' => (int)$e['project_id'],
            'template_element_id' => (int)$e['template_element_id'],
            'element_type' => $e['element_type'],
            'type_label' => $cfg['label_singular'] ?? $cfg['label'] ?? $e['element_type'],
            'title' => $e['title'],
            'content_html' => $e['content'] ?? '',
            'content_text' => $this->htmlToText($e['content'] ?? ''),
            'resume' => $e['resume'],
            'parent_id' => $e['parent_id'] ? (int)$e['parent_id'] : null,
            'order_index' => (int)$e['order_index'],
            'sub_elements' => $subElements,
            'updated_at' => $e['updated_at'],
        ];
    }

    /**
     * Convertit HTML en texte brut.
     */
    public function htmlToText(string $html): string
    {
        return ContentTransformer::htmlToText($html);
    }

    /**
     * Compte le nombre de mots dans du HTML.
     */
    public function countWords(string $html): int
    {
        return ContentTransformer::countWords($html);
    }

    /**
     * Récupère ou crée un synopsis pour un projet.
     */
    public function fetchOrCreateSynopsis(int $projectId): array
    {
        $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id = ?', [$projectId]);
        if (!$rows) {
            $this->db->exec('INSERT INTO synopsis (project_id) VALUES (?)', [$projectId]);
            $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id = ?', [$projectId]);
        }
        $s = $rows[0];
        $htmlFields = ['pitch', 'development', 'climax', 'resolution'];
        $result = [
            'id' => (int)$s['id'],
            'project_id' => (int)$s['project_id'],
            'genre' => $s['genre'],
            'subgenre' => $s['subgenre'],
            'audience' => $s['audience'],
            'tone' => $s['tone'],
            'themes' => $s['themes'],
            'comps' => $s['comps'],
            'status' => $s['status'],
            'structure_method' => $s['structure_method'],
            'is_exported' => (bool)$s['is_exported'],
            'logline' => $s['logline'],
            'situation' => $s['situation'],
            'trigger_evt' => $s['trigger_evt'],
            'plot_point1' => $s['plot_point1'],
            'midpoint' => $s['midpoint'],
            'crisis' => $s['crisis'],
            'updated_at' => $s['updated_at'],
        ];
        foreach ($htmlFields as $f) {
            $result[$f . '_html'] = $s[$f] ?? '';
            $result[$f . '_text'] = $this->htmlToText($s[$f] ?? '');
        }
        return $result;
    }
}
