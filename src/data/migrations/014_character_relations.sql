-- Migration 014: Character relationships
-- Liens entre personnages d'un même projet

CREATE TABLE IF NOT EXISTS `character_relations` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `char_from`  INT NOT NULL,
    `char_to`    INT NOT NULL,
    `label`      VARCHAR(100) NOT NULL DEFAULT '',
    `color`      VARCHAR(20)  NOT NULL DEFAULT '#94a3b8',
    PRIMARY KEY (`id`),
    KEY `idx_project` (`project_id`),
    KEY `idx_char_from` (`char_from`),
    KEY `idx_char_to` (`char_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
