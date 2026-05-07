<?php

use KS\Mapper;

class Comment extends Mapper
{
    const TABLE = 'comments';

    /**
     * Retrieve all comments for a chapter.
     */
    public function getByChapter(int $chapterId): array
    {
        return $this->findAndCast(['chapter_id=?', $chapterId], ['order' => 'start_pos ASC']);
    }

    /**
     * Create a new comment/annotation.
     */
    public function create(int $chapterId, int $start, int $end, string $content)
    {
        $this->reset();
        $this->chapter_id = $chapterId;
        $this->start_pos = $start;
        $this->end_pos = $end;
        $this->content = $content;
        try {
            $this->save();
            return $this->id;
        } catch (\PDOException $e) {
            return false;
        }
    }
}