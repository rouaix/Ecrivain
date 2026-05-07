<?php

use KS\Mapper;

/**
 * CollaboratorInvite — DAO pour la table project_collaborators.
 *
 * Remplace les requêtes SQL inline dispersées dans CollabInviteController.
 * Les méthodes de lecture retournent des tableaux bruts (pas des instances Mapper)
 * pour être compatibles avec les vues existantes.
 */
class CollaboratorInvite extends Mapper
{
    const TABLE = 'project_collaborators';

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Tous les collaborateurs (toutes statuts) d'un projet, avec username et email.
     *
     * @return array<array<string, mixed>>
     */
    public function findByProject(int $projectId): array
    {
        return $this->db->exec(
            'SELECT pc.*, u.username, u.email
             FROM project_collaborators pc
             JOIN users u ON u.id = pc.user_id
             WHERE pc.project_id = ?
             ORDER BY pc.created_at ASC',
            [$projectId]
        ) ?: [];
    }

    /**
     * Toutes les invitations reçues par un utilisateur (toutes statuts), avec contexte projet/propriétaire.
     *
     * @return array<array<string, mixed>>
     */
    public function findByUser(int $userId): array
    {
        return $this->db->exec(
            'SELECT pc.*, p.title AS project_title, u.username AS owner_username
             FROM project_collaborators pc
             JOIN projects p ON p.id = pc.project_id
             JOIN users u ON u.id = pc.owner_id
             WHERE pc.user_id = ?
             ORDER BY pc.created_at DESC',
            [$userId]
        ) ?: [];
    }

    /**
     * Vérifie si un utilisateur est déjà dans la table (quelle que soit la statut).
     */
    public function existsForUser(int $projectId, int $userId): bool
    {
        $rows = $this->db->exec(
            'SELECT id FROM project_collaborators WHERE project_id = ? AND user_id = ?',
            [$projectId, $userId]
        );
        return !empty($rows);
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    /**
     * Créer une invitation (statut pending par défaut).
     */
    public function invite(int $projectId, int $ownerId, int $userId): void
    {
        $this->db->exec(
            'INSERT INTO project_collaborators (project_id, owner_id, user_id) VALUES (?, ?, ?)',
            [$projectId, $ownerId, $userId]
        );
    }

    /**
     * Supprimer un collaborateur d'un projet.
     */
    public function removeUser(int $projectId, int $userId): void
    {
        $this->db->exec(
            'DELETE FROM project_collaborators WHERE project_id = ? AND user_id = ?',
            [$projectId, $userId]
        );
    }

    /**
     * Accepter une invitation (uniquement si elle appartient à l'utilisateur et est pending).
     */
    public function accept(int $id, int $userId): void
    {
        $this->db->exec(
            'UPDATE project_collaborators SET status = "accepted", accepted_at = NOW()
             WHERE id = ? AND user_id = ? AND status = "pending"',
            [$id, $userId]
        );
    }

    /**
     * Décliner une invitation.
     */
    public function decline(int $id, int $userId): void
    {
        $this->db->exec(
            'UPDATE project_collaborators SET status = "declined"
             WHERE id = ? AND user_id = ? AND status = "pending"',
            [$id, $userId]
        );
    }
}
