<?php

/**
 * SynopsisToolHandler — Gère les outils MCP liés au synopsis.
 */
class SynopsisToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Formate les données de synopsis en Markdown.
     */
    private function formatSynopsis(array $s): string
    {
        $md = "# Synopsis du projet (ID: {$s['project_id']})\n\n";

        $meta = [];
        if (!empty($s['genre']))            $meta[] = "**Genre** : {$s['genre']}";
        if (!empty($s['subgenre']))         $meta[] = "**Sous-genre** : {$s['subgenre']}";
        if (!empty($s['audience']))         $meta[] = "**Public** : {$s['audience']}";
        if (!empty($s['tone']))             $meta[] = "**Ton** : {$s['tone']}";
        if (!empty($s['themes']))           $meta[] = "**Thèmes** : {$s['themes']}";
        if (!empty($s['comps']))            $meta[] = "**Comparables** : {$s['comps']}";
        if (!empty($s['status']))           $meta[] = "**Statut** : {$s['status']}";
        if (!empty($s['structure_method'])) $meta[] = "**Méthode** : {$s['structure_method']}";
        if ($meta) $md .= implode(' | ', $meta) . "\n\n";

        if (!empty($s['logline']))    $md .= "## Logline\n{$s['logline']}\n\n";
        if (!empty($s['pitch']))      $md .= "## Pitch\n" . $this->htmlToText($s['pitch']) . "\n\n";

        $beats = [
            'situation'   => 'Situation initiale',
            'trigger_evt' => 'Élément déclencheur',
            'plot_point1' => 'Point tournant 1',
            'development' => 'Développement',
            'midpoint'    => 'Midpoint',
            'crisis'      => 'Crise',
            'climax'      => 'Climax',
            'resolution'  => 'Résolution',
        ];
        $hasBeats = false;
        foreach ($beats as $k => $_) { if (!empty($s[$k])) { $hasBeats = true; break; } }
        if ($hasBeats) {
            $md .= "## Structure narrative\n\n";
            foreach ($beats as $k => $label) {
                if (!empty($s[$k])) $md .= "**{$label}** : " . $this->htmlToText($s[$k]) . "\n\n";
            }
        }

        return trim($md);
    }

    /**
     * Récupère le synopsis d'un projet.
     */
    public function getSynopsis(int $pid): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");
        
        $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id=?', [$pid]);
        if (!$rows) {
            $this->db->exec('INSERT INTO synopsis (project_id) VALUES (?)', [$pid]);
            $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id=?', [$pid]);
        }
        $s = $rows[0];
        return $this->ok($this->formatSynopsis($s));
    }

    /**
     * Met à jour le synopsis d'un projet.
     */
    public function updateSynopsis(int $pid, array $a): array
    {
        if (!$this->ownsProject($pid)) return $this->fail("Projet $pid introuvable.");

        $allowed = [
            'genre', 'subgenre', 'audience', 'tone', 'themes', 'comps',
            'status', 'structure_method',
            'logline', 'pitch', 'situation', 'trigger_evt', 'plot_point1',
            'development', 'midpoint', 'crisis', 'climax', 'resolution',
        ];

        $rows = $this->db->exec('SELECT id FROM synopsis WHERE project_id=?', [$pid]);
        if (!$rows) {
            $this->db->exec('INSERT INTO synopsis (project_id) VALUES (?)', [$pid]);
        }

        $fields = []; $vals = [];
        foreach ($allowed as $f) {
            if (isset($a[$f])) { $fields[] = "$f=?"; $vals[] = $a[$f]; }
        }
        if (!$fields) return $this->fail('Aucun champ valide fourni.');

        $fields[] = 'updated_at=NOW()';
        $vals[] = $pid;
        $this->db->exec('UPDATE synopsis SET ' . implode(',', $fields) . ' WHERE project_id=?', $vals);

        $rows = $this->db->exec('SELECT * FROM synopsis WHERE project_id=?', [$pid]);
        return $this->ok($this->formatSynopsis($rows[0]));
    }
}
