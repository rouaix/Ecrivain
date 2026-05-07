<?php

/**
 * BaseToolHandler — Classe de base pour tous les handlers d'outils MCP.
 * Fournit des méthodes helpers communes.
 */
class BaseToolHandler
{
    protected \DB\SQL $db;
    protected int $userId;

    public function __construct(\DB\SQL $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
    }

    protected function ok(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false];
    }

    protected function fail(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => '**Erreur :** ' . $message]], 'isError' => true];
    }

    protected function ownsProject(int $pid): bool
    {
        return !empty($this->db->exec(
            'SELECT id FROM projects WHERE id=? AND user_id=?', [$pid, $this->userId]
        ));
    }

    protected function ownsProjectForItem(int $pid, int $id, string $table, string $joinCol = 'project_id'): bool
    {
        $rows = $this->db->exec(
            "SELECT p.user_id FROM $table t JOIN projects p ON p.id=t.$joinCol WHERE t.id=?",
            [$id]
        );
        return !empty($rows) && $rows[0]['user_id'] == $this->userId;
    }

    protected function htmlToText(string $html): string
    {
        return ContentTransformer::htmlToText($html);
    }
}
