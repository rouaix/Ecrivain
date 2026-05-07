<?php

/**
 * Base controller for all project sub-controllers.
 * Provides shared helpers used across ProjectController, ProjectExportController,
 * ProjectMindmapController, ProjectFileController, etc.
 */
class ProjectBaseController extends Controller
{
    /**
     * Get the correct data directory for a project, respecting collaborative/share modes.
     * Returns the project owner's data directory path.
     */
    protected function getProjectDataDir(int $projectId): string
    {
        $ownerEmail = $this->getProjectOwnerEmail($projectId);
        return $this->getUserDataDir($ownerEmail);
    }

    /**
     * Check if current user can access a project (owner, collaborator, or via share link).
     * For public share routes, the token must be provided in PARAMS.token.
     */
    protected function canAccessProject(int $projectId): bool
    {
        // 1. Check if user is authenticated and has direct access (owner or collaborator)
        $user = $this->currentUser();
        if ($user && $this->hasProjectAccess($projectId)) {
            return true;
        }

        // 2. Check if access is via a valid share link (public access)
        $token = $this->f3->get('PARAMS.token');
        if ($token) {
            $shareLink = new ShareLink();
            $link = $shareLink->findByToken($token);

            if ($link && $link['is_active']) {
                $projectIds = $shareLink->getProjectIds((int)$link['id']);
                if (in_array($projectId, array_map('intval', $projectIds))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recursively decode HTML entities and strip tags from data structures.
     */
    public function supHtml($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->supHtml($v);
            }
            return $data;
        }

        if (is_string($data)) {
            $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $data = strip_tags($data);
            $data = trim($data);
            $data = preg_replace('/\x{00A0}/u', ' ', $data);
            $data = preg_replace('/\s+/u', ' ', $data);
            return $data;
        }

        return $data;
    }

    /**
     * Loads user's first and last name from data/{email}/profile.json.
     */
    protected function getUserFullName(?array $user): string
    {
        if (!$user || empty($user['email'])) {
            return '';
        }

        $file = $this->getUserDataDir($user['email']) . '/profile.json';
        if (!file_exists($file)) {
            return '';
        }

        $profile = json_decode(file_get_contents($file), true);
        if (!is_array($profile)) {
            return '';
        }

        $first = trim($profile['firstname'] ?? '');
        $last  = trim($profile['lastname'] ?? '');
        return trim($first . ' ' . $last);
    }

    /**
     * Charge les elements du template associe au projet.
     * Retourne un tableau vide si les tables templates/template_elements n'existent pas.
     */
    protected function loadProjectTemplateElements(array $project): array
    {
        $db = $this->f3->get('DB');
        if (!$db->exists('templates') || !$db->exists('template_elements')) {
            return [];
        }
        $templateId    = $project['template_id'] ?? null;
        $templateModel = new ProjectTemplate();
        if (!$templateId) {
            $template = $templateModel->getDefault();
        } else {
            $template = $templateModel->findAndCast(['id=?', $templateId]);
            $template = $template ? $template[0] : $templateModel->getDefault();
        }
        return $template ? $templateModel->getElements($template['id']) : [];
    }

    /**
     * Build CSS rules to order panels based on template configuration.
     */
    protected function buildPanelOrderCss(array $templateElements): string
    {
        if (empty($templateElements)) {
            return '';
        }

        $selectorMap = [
            'section_before' => '#panel-before',
            'section_after'  => '#panel-after',
            'act'            => '#panel-content',
            'chapter'        => '#panel-content',
            'note'           => '#panel-notes',
            'character'      => '#panel-characters',
            'file'           => '#panel-files',
            'scenario'       => '#panel-scenarios',
        ];

        $rules    = [];
        $assigned = [];
        $order    = 0;

        foreach ($templateElements as $te) {
            if (!$te['is_enabled']) {
                $order++;
                continue;
            }

            $key = $te['element_type'];
            if ($key === 'section') {
                $key = 'section_' . ($te['section_placement'] ?? 'before');
            }

            if ($key === 'element') {
                $rules[] = '#panel-element-' . (int)$te['id'] . '{order:' . $order . '}';
            } elseif (isset($selectorMap[$key]) && !isset($assigned[$selectorMap[$key]])) {
                $selector            = $selectorMap[$key];
                $assigned[$selector] = true;
                $rules[]             = $selector . '{order:' . $order . '}';
            }

            $order++;
        }

        return implode("\n", $rules);
    }

    /**
     * When a project changes template, migrate elements.template_element_id
     * from the old template's element types to the new template's, matching by label.
     * Unmatched elements are left untouched (data preserved, just not visible until remapped).
     */
    protected function migrateElementsOnTemplateChange(int $projectId, int $oldTemplateId, int $newTemplateId): void
    {
        // Get 'element'-type entries from both templates
        $oldTypes = $this->db->exec(
            "SELECT id, config_json FROM template_elements WHERE template_id = ? AND element_type = 'element' ORDER BY display_order ASC",
            [$oldTemplateId]
        );
        $newTypes = $this->db->exec(
            "SELECT id, config_json FROM template_elements WHERE template_id = ? AND element_type = 'element' ORDER BY display_order ASC",
            [$newTemplateId]
        );

        if (empty($oldTypes) || empty($newTypes)) {
            return;
        }

        // Index new types by normalized label_plural for matching
        $newByLabel = [];
        $newByPos   = [];
        foreach ($newTypes as $i => $nt) {
            $cfg   = json_decode($nt['config_json'] ?? '{}', true);
            $label = strtolower(trim($cfg['label_plural'] ?? ''));
            if ($label && !isset($newByLabel[$label])) {
                $newByLabel[$label] = (int)$nt['id'];
            }
            $newByPos[$i] = (int)$nt['id'];
        }

        foreach ($oldTypes as $i => $ot) {
            $cfg      = json_decode($ot['config_json'] ?? '{}', true);
            $label    = strtolower(trim($cfg['label_plural'] ?? ''));
            $oldId    = (int)$ot['id'];

            // Match by label first, then by position
            $newId = $newByLabel[$label] ?? $newByPos[$i] ?? null;
            if (!$newId) {
                continue;
            }

            // Check if project has elements of this old type
            $count = $this->db->exec(
                'SELECT COUNT(*) AS n FROM elements WHERE project_id = ? AND template_element_id = ?',
                [$projectId, $oldId]
            );
            if (empty($count[0]['n'])) {
                continue;
            }

            $this->db->exec(
                'UPDATE elements SET template_element_id = ? WHERE project_id = ? AND template_element_id = ?',
                [$newId, $projectId, $oldId]
            );
        }
    }
}
