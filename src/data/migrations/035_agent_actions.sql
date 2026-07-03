-- Migration 035 : Actions des agents IA
-- Chaque agent définit sa propre liste d'actions exécutables.
-- output_mode : display (affichage seul) | replace (remplace la source) | append (ajoute une note)
CREATE TABLE IF NOT EXISTS `ai_agent_actions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `agent_id` INT NOT NULL,
    `label` VARCHAR(120) NOT NULL,
    `action_key` VARCHAR(60) NOT NULL DEFAULT '',
    `instruction` MEDIUMTEXT NULL COMMENT 'Template de prompt de la tâche',
    `output_mode` VARCHAR(30) NOT NULL DEFAULT 'display',
    `max_tokens` INT NOT NULL DEFAULT 800,
    `order_index` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_actions_agent` (`agent_id`),
    CONSTRAINT `fk_actions_agent` FOREIGN KEY (`agent_id`)
        REFERENCES `ai_agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
