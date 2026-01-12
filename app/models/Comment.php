<?php

/**
 * Comment model stores annotations linked to a specific chapter. Each comment
 * records the start and end character positions in the chapter content to
 * indicate which part of the text is being annotated. This simple model
 * enables storing inline comments/annotations.
 */
class Comment
{
    protected $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Retrieve all comments for a chapter.
     *
     * @param int $chapterId
     * @return array
     */
    public function getByChapter(int $chapterId): array
    {
        $result = $this->db->execute_query(
            'SELECT * FROM comments WHERE chapter_id = ? ORDER BY start_pos ASC',
            [$chapterId]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Create a new comment/annotation.
     *
     * @param int $chapterId
     * @param int $start
     * @param int $end
     * @param string $content
     * @return int|false
     */
    public function create(int $chapterId, int $start, int $end, string $content)
    {
        try {
            $this->db->execute_query(
                'INSERT INTO comments (chapter_id, start_pos, end_pos, content) VALUES (?, ?, ?, ?)',
                [$chapterId, $start, $end, $content]
            );
            return (int) $this->db->insert_id;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}