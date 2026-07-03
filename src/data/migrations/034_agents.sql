-- Migration 034 : Agents IA
-- Agents IA appartenant à un utilisateur (globaux, réutilisables sur tous ses projets).
-- Le champ system_prompt est « le skill » de l'agent, éditable depuis l'interface.
CREATE TABLE IF NOT EXISTS `ai_agents` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `role` VARCHAR(80) NOT NULL DEFAULT '',
    `description` TEXT NULL,
    `icon` VARCHAR(40) NOT NULL DEFAULT 'robot',
    `color` VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    `system_prompt` MEDIUMTEXT NULL COMMENT 'Le skill de l''agent (prompt système)',
    `provider` VARCHAR(30) NULL COMMENT 'NULL = hérite du provider actif de l''utilisateur',
    `model` VARCHAR(60) NULL,
    `temperature` DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Agent par défaut fourni au premier usage',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_agents_user` (`user_id`)
    -- Pas de FK vers `users` : cette table est en MyISAM (ne supporte pas les FK).
    -- L'intégrité user_id est assurée applicativement (comme projects.user_id).
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
