-- Migration 036 : Historique des exécutions d'agents IA
-- Trace chaque exécution (agent + action + données sources + résultat + tokens).
CREATE TABLE IF NOT EXISTS `ai_agent_runs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `agent_id` INT NOT NULL,
    `action_id` INT NULL,
    `user_id` INT NOT NULL,
    `project_id` INT NULL,
    `context_type` VARCHAR(40) NULL,
    `context_ids` TEXT NULL COMMENT 'JSON : ids des documents sources',
    `input_excerpt` TEXT NULL,
    `output` MEDIUMTEXT NULL,
    `model` VARCHAR(60) NULL,
    `prompt_tokens` INT NOT NULL DEFAULT 0,
    `completion_tokens` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_runs_agent` (`agent_id`),
    KEY `idx_runs_project` (`project_id`),
    CONSTRAINT `fk_runs_agent` FOREIGN KEY (`agent_id`)
        REFERENCES `ai_agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
