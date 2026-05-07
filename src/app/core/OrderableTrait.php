<?php

/**
 * OrderableTrait — mutualisé entre les modèles ordonnables (Act, Chapter, Section, Element).
 *
 * Pré-requis : la classe utilisatrice doit étendre KS\Mapper et définir const TABLE.
 * $this->db est disponible via KS\Mapper (hérité de SQL\Mapper).
 */
trait OrderableTrait
{
    /**
     * Réordonne les lignes de la table selon le tableau d'IDs fourni.
     * L'index commence à 0 et suit l'ordre du tableau.
     * Sécurisé : la contrainte project_id empêche toute manipulation inter-projets.
     */
    public function reorder(int $projectId, array $orderedIds): bool
    {
        $table = static::TABLE;
        $this->db->begin();
        try {
            $index = 0;
            foreach ($orderedIds as $id) {
                $this->db->exec(
                    "UPDATE {$table} SET order_index = ? WHERE id = ? AND project_id = ?",
                    [$index++, (int) $id, $projectId]
                );
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
}
