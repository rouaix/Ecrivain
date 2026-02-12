<?php

use KS\Mapper;

class Section extends Mapper
{
    const TABLE = 'sections';

    // Define section types and their display order
    const SECTION_TYPES = [
        'cover' => ['name' => 'Couverture', 'order' => 1, 'position' => 'before'],
        'preface' => ['name' => 'Préface', 'order' => 2, 'position' => 'before'],
        'introduction' => ['name' => 'Introduction', 'order' => 3, 'position' => 'before'],
        'prologue' => ['name' => 'Prologue', 'order' => 4, 'position' => 'before'],
        'postface' => ['name' => 'Postface', 'order' => 5, 'position' => 'after'],
        'appendices' => ['name' => 'Annexes', 'order' => 6, 'position' => 'after'],
        'back_cover' => ['name' => 'Quatrième de couverture', 'order' => 8, 'position' => 'after'],
    ];

    /**
     * Get all sections for a given project sorted by their defined order.
     */
    public function getAllByProject(int $projectId): array
    {
        $sections = $this->findAndCast(['project_id=?', $projectId], ['order' => 'order_index ASC']);

        // Sort primarily by order_index, then by the predefined type order for unranked items
        usort($sections, function ($a, $b) {
            $idxA = $a['order_index'] ?? 0;
            $idxB = $b['order_index'] ?? 0;

            if ($idxA !== $idxB) {
                return $idxA - $idxB;
            }

            $typeOrderA = self::SECTION_TYPES[$a['type']]['order'] ?? 999;
            $typeOrderB = self::SECTION_TYPES[$b['type']]['order'] ?? 999;
            return $typeOrderA - $typeOrderB;
        });

        return $sections;
    }

    /**
     * Get sections that appear before chapters.
     */
    public function getBeforeChapters(int $projectId): array
    {
        $all = $this->getAllByProject($projectId);
        return array_filter($all, function ($section) {
            return self::SECTION_TYPES[$section['type']]['position'] === 'before';
        });
    }

    /**
     * Get sections that appear after chapters.
     */
    public function getAfterChapters(int $projectId): array
    {
        $all = $this->getAllByProject($projectId);
        return array_filter($all, function ($section) {
            return self::SECTION_TYPES[$section['type']]['position'] === 'after';
        });
    }

    /**
     * Create or update a section.
     */
    public function create(int $projectId, string $type, ?string $title = null, ?string $content = null, ?string $comment = null, ?string $imagePath = null, int $orderIndex = 0)
    {
        $this->reset();
        $this->project_id = $projectId;
        $this->type = $type;
        $this->title = $title;
        $this->content = $content;
        $this->comment = $comment;
        $this->image_path = $imagePath;
        $this->order_index = $orderIndex;

        try {
            $this->save();
            return $this->id;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Create or update a section logic.
     */
    public function createOrUpdate(int $projectId, string $type, ?string $title = null, ?string $content = null, ?string $comment = null, ?string $imagePath = null, ?int $id = null)
    {
        if ($id) {
            $this->load(['id=?', $id]);
            if (!$this->dry()) {
                $this->title = $title;
                $this->content = $content;
                $this->comment = $comment;
                $this->image_path = $imagePath;
                $this->save();
                return $this->id;
            }
            return false;
        }

        // Multi-entry types always create new if no ID provided
        if ($type === 'notes' || $type === 'appendices') {
            return $this->create($projectId, $type, $title, $content, $comment, $imagePath);
        }

        // Single-entry types check for existing
        $existing = $this->findByType($projectId, $type);
        if ($existing) {
            // findByType returns array via db->exec in old model, 
            // but here findByType returns array from cast? No I haven't implemented it yet.
            // Im implementing findByType below.

            // If I have the ID:
            $this->load(['id=?', $existing['id']]);
            $this->title = $title;
            $this->content = $content;
            $this->comment = $comment;
            $this->image_path = $imagePath;
            $this->save();
            return $this->id;
        }

        return $this->create($projectId, $type, $title, $content, $comment, $imagePath);
    }

    /**
     * Find a section by its type for a specific project.
     * Returns array (casted) for compatibility.
     */
    public function findByType(int $projectId, string $type): ?array
    {
        // We use a separate instance or reset? 
        // Best to use a new find call or static-like usage.
        // KS\Mapper findAndCast is instance method.
        // But if I use $this->load() it changes state of *this* object.
        // So for "finding" without changing *this* state (if I'm in middle of something), I should be careful.
        // But here createOrUpdate is likely called on a fresh model instance.

        // I'll use a dry check with load, but that changes HEAD.
        // Better:
        $res = $this->db->exec('SELECT * FROM sections WHERE project_id = ? AND type = ? LIMIT 1', [$projectId, $type]);
        return $res ? $res[0] : null;
    }

    /**
     * Batch update the order of sections for a project.
     */
    public function reorder(int $projectId, array $order): bool
    {
        $this->db->begin();
        try {
            foreach ($order as $index => $id) {
                $this->db->exec(
                    'UPDATE sections SET order_index = ? WHERE id = ? AND project_id = ?',
                    [$index, $id, $projectId]
                );
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Get the display name for a section type.
     */
    public static function getTypeName(string $type): string
    {
        return self::SECTION_TYPES[$type]['name'] ?? $type;
    }

    /**
     * Get all available section types.
     */
    public static function getAvailableTypes(): array
    {
        return self::SECTION_TYPES;
    }
}
