<?php

use KS\Mapper;

class ProjectTemplate extends Mapper
{
    const TABLE = 'templates';

    /**
     * Get all available templates (system + user-created).
     * If userId provided, include user's templates and system templates.
     */
    public function getAllAvailable(?int $userId = null): array
    {
        if ($userId === null) {
            // Return only system templates
            return $this->findAndCast(['is_system=?', 1], ['order' => 'name ASC']);
        }

        // Return system templates + user's templates
        $systemTemplates = $this->findAndCast(['is_system=?', 1], ['order' => 'name ASC']);
        $userTemplates = $this->findAndCast(['created_by=?', $userId], ['order' => 'name ASC']);

        return array_merge($systemTemplates, $userTemplates);
    }

    /**
     * Get the default template.
     */
    public function getDefault(): ?array
    {
        $result = $this->findAndCast(['is_default=?', 1]);
        return $result ? $result[0] : null;
    }

    /**
     * Get all template elements for a template, sorted by display_order.
     */
    public function getElements(int $templateId): array
    {
        $templateElementModel = new TemplateElement();
        return $templateElementModel->getAllByTemplate($templateId);
    }

    /**
     * Get only enabled template elements.
     */
    public function getEnabledElements(int $templateId): array
    {
        $templateElementModel = new TemplateElement();
        return $templateElementModel->getEnabledByTemplate($templateId);
    }

    /**
     * Check if template is in use by any project.
     */
    public function isInUse(int $templateId): bool
    {
        $count = $this->db->exec('SELECT COUNT(*) as cnt FROM projects WHERE template_id = ?', [$templateId]);
        return ($count[0]['cnt'] ?? 0) > 0;
    }

    /**
     * Duplicate template with all its elements.
     * If $isSystem is true, the copy is marked as a system template (created_by = NULL).
     */
    public function duplicate(int $sourceTemplateId, string $newName, int $userId, bool $isSystem = false): ?int
    {
        $this->db->begin();
        try {
            // Load source template
            $this->load(['id=?', $sourceTemplateId]);
            if ($this->dry()) {
                throw new Exception('Source template not found');
            }

            // Create new template
            $newTemplate = new ProjectTemplate();
            $newTemplate->name = $newName;
            $newTemplate->description = $this->description;
            $newTemplate->is_default = 0;
            $newTemplate->is_system = $isSystem ? 1 : 0;
            $newTemplate->created_by = $isSystem ? null : $userId;
            $newTemplate->save();
            $newTemplateId = $newTemplate->id;

            // Copy all template elements
            $sourceElements = $this->getElements($sourceTemplateId);
            foreach ($sourceElements as $elem) {
                $this->db->exec(
                    'INSERT INTO template_elements (template_id, element_type, element_subtype, section_placement, display_order, is_enabled, config_json)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $newTemplateId,
                        $elem['element_type'],
                        $elem['element_subtype'],
                        $elem['section_placement'],
                        $elem['display_order'],
                        $elem['is_enabled'],
                        $elem['config_json']
                    ]
                );
            }

            $this->db->commit();
            return $newTemplateId;
        } catch (Exception $e) {
            $this->db->rollback();
            return null;
        }
    }
}
