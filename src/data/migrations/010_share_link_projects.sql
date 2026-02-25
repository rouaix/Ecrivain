-- Migration 010: Association liens de partage <-> projets
-- Pas de FK : les tables historiques (projects) utilisent MyISAM
CREATE TABLE IF NOT EXISTS `share_link_projects` (
    `share_link_id` INT NOT NULL,
    `project_id`    INT NOT NULL,
    PRIMARY KEY (`share_link_id`, `project_id`),
    KEY `idx_share_link_id` (`share_link_id`),
    KEY `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
