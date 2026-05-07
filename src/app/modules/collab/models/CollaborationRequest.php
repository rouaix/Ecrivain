<?php

use KS\Mapper;

/**
 * CollaborationRequest — DAO pour la table collaboration_requests.
 *
 * Remplace les requêtes SQL inline dispersées dans CollabRequestController.
 */
class CollaborationRequest extends Mapper
{
    const TABLE = 'collaboration_requests';

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Demandes d'un collaborateur sur un projet donné, du plus récent au plus ancien.
     *
     * @return array<array<string, mixed>>
     */
    public function findByProjectAndUser(int $projectId, int $userId): array
    {
        return $this->db->exec(
            'SELECT * FROM collaboration_requests
             WHERE project_id = ? AND user_id = ?
             ORDER BY created_at DESC',
            [$projectId, $userId]
        ) ?: [];
    }

    /**
     * File de revue propriétaire : toutes les demandes du projet avec username du collaborateur.
     *
     * @return array<array<string, mixed>>
     */
    public function findByProject(int $projectId): array
    {
        return $this->db->exec(
            'SELECT cr.*, u.username AS collab_username
             FROM collaboration_requests cr
             JOIN users u ON u.id = cr.user_id
             WHERE cr.project_id = ?
             ORDER BY cr.status ASC, cr.created_at DESC',
            [$projectId]
        ) ?: [];
    }

    /**
     * Récupérer une demande pending appartenant au propriétaire du projet.
     * Utilisé dans approve() et reject() pour valider l'accès.
     *
     * @return array<string, mixed>|null
     */
    public function findPendingForOwner(int $requestId, int $ownerId): ?array
    {
        $rows = $this->db->exec(
            'SELECT cr.* FROM collaboration_requests cr
             JOIN projects p ON p.id = cr.project_id
             WHERE cr.id = ? AND p.user_id = ? AND cr.status = "pending"',
            [$requestId, $ownerId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Vérifier qu'une demande pending appartient bien à l'utilisateur (pour cancel).
     */
    public function findPendingByIdAndUser(int $requestId, int $userId): ?array
    {
        $rows = $this->db->exec(
            'SELECT id FROM collaboration_requests WHERE id = ? AND user_id = ? AND status = "pending"',
            [$requestId, $userId]
        );
        return $rows[0] ?? null;
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    /**
     * Soumettre une nouvelle demande et retourner son ID.
     */
    public function submit(
        int $projectId,
        int $userId,
        string $requestType,
        string $contentType,
        ?int $contentId,
        ?string $contentTitle,
        ?string $currentSnapshot,
        ?string $proposedContent,
        ?string $message
    ): int {
        $this->db->exec(
            'INSERT INTO collaboration_requests
                (project_id, user_id, request_type, content_type, content_id,
                 content_title, current_snapshot, proposed_content, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $projectId, $userId, $requestType, $contentType, $contentId,
                $contentTitle ?: null, $currentSnapshot, $proposedContent,
                $message ?: null,
            ]
        );
        $rows = $this->db->exec('SELECT LAST_INSERT_ID() AS id');
        return (int) ($rows[0]['id'] ?? 0);
    }

    /**
     * Annuler une demande pending (par le collaborateur qui l'a soumise).
     */
    public function cancelByUser(int $requestId, int $userId): void
    {
        $this->db->exec(
            'DELETE FROM collaboration_requests WHERE id = ? AND user_id = ?',
            [$requestId, $userId]
        );
    }

    /**
     * Marquer une demande comme approuvée.
     */
    public function approve(int $requestId, int $reviewedBy): void
    {
        $this->db->exec(
            'UPDATE collaboration_requests
             SET status = "approved", reviewed_at = NOW(), reviewed_by = ?
             WHERE id = ?',
            [$reviewedBy, $requestId]
        );
    }

    /**
     * Marquer une demande comme rejetée avec une note optionnelle.
     */
    public function reject(int $requestId, int $reviewedBy, ?string $note): void
    {
        $this->db->exec(
            'UPDATE collaboration_requests
             SET status = "rejected", owner_note = ?, reviewed_at = NOW(), reviewed_by = ?
             WHERE id = ?',
            [$note ?: null, $reviewedBy, $requestId]
        );
    }
}
