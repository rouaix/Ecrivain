<?php

/**
 * SearchToolHandler — Gère les outils MCP liés à la recherche.
 */
class SearchToolHandler extends BaseToolHandler
{
    public function __construct(\DB\SQL $db, int $userId)
    {
        parent::__construct($db, $userId);
    }

    /**
     * Recherche dans tous les projets de l'utilisateur.
     */
    public function search(string $query): array
    {
        if (strlen(trim($query)) < 2) return $this->fail('Requête trop courte (min. 2 caractères).');
        
        $like = '%' . $query . '%';
        $results = [];

        // Chapitres
        $rows = $this->db->exec(
            'SELECT c.id, c.title, p.title as pt
             FROM chapters c JOIN projects p ON p.id=c.project_id
             WHERE p.user_id=? AND (c.title LIKE ? OR c.content LIKE ?) LIMIT 20',
            [$this->userId, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Chapitre** {$r['title']} (ID: {$r['id']}) — _{$r['pt']}_";

        // Notes
        $rows = $this->db->exec(
            'SELECT n.id, n.title, p.title as pt
             FROM notes n JOIN projects p ON p.id=n.project_id
             WHERE p.user_id=? AND (n.title LIKE ? OR n.content LIKE ?) LIMIT 10',
            [$this->userId, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Note** {$r['title']} (ID: {$r['id']}) — _{$r['pt']}_";

        // Personnages
        $rows = $this->db->exec(
            'SELECT c.id, c.name, p.title as pt
             FROM characters c JOIN projects p ON p.id=c.project_id
             WHERE p.user_id=? AND (c.name LIKE ? OR c.description LIKE ?) LIMIT 10',
            [$this->userId, $like, $like]
        );
        foreach ($rows as $r) $results[] = "**Personnage** {$r['name']} (ID: {$r['id']}) — _{$r['pt']}_";

        if (!$results) return $this->ok("Aucun résultat pour « $query ».");
        return $this->ok("# Résultats pour « $query »\n\n" . implode("\n", $results));
    }
}
