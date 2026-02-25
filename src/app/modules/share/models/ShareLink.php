<?php

use KS\Mapper;

class ShareLink extends Mapper
{
    const TABLE = 'share_links';

    /**
     * Find a share link by its token. Returns data array or null.
     */
    public function findByToken(string $token): ?array
    {
        $result = $this->findAndCast(['token=?', $token]);
        return $result ? $result[0] : null;
    }

    /**
     * Return all links for a user, each with their associated projects.
     */
    public function getAllByUser(int $userId): array
    {
        $links = $this->findAndCast(['user_id=?', $userId]) ?? [];
        foreach ($links as &$link) {
            $link['projects'] = $this->getProjects((int)$link['id']);
        }
        return $links;
    }

    /**
     * Return project IDs associated with a share link.
     */
    public function getProjectIds(int $linkId): array
    {
        $rows = $this->db->exec(
            'SELECT project_id FROM share_link_projects WHERE share_link_id=?',
            [$linkId]
        );
        return array_column($rows ?? [], 'project_id');
    }

    /**
     * Return basic project info (id, title, description) for a share link.
     */
    public function getProjects(int $linkId): array
    {
        return $this->db->exec(
            'SELECT p.id, p.title, p.description
             FROM share_link_projects slp
             JOIN projects p ON p.id = slp.project_id
             WHERE slp.share_link_id = ?',
            [$linkId]
        ) ?? [];
    }

    /**
     * Replace the project list for a share link.
     */
    public function setProjects(int $linkId, array $projectIds): void
    {
        $this->db->exec(
            'DELETE FROM share_link_projects WHERE share_link_id=?',
            [$linkId]
        );
        foreach ($projectIds as $pid) {
            $this->db->exec(
                'INSERT INTO share_link_projects (share_link_id, project_id) VALUES (?,?)',
                [$linkId, (int)$pid]
            );
        }
    }

    /**
     * Generate a cryptographically secure token.
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
