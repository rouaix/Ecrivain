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
                $selector          = $selectorMap[$key];
                $assigned[$selector] = true;
                $rules[]           = $selector . '{order:' . $order . '}';
            }

            $order++;
        }

        return implode("\n", $rules);
    }
}
