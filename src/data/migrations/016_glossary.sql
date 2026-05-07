-- Migration 016: Worldbuilding glossary
CREATE TABLE IF NOT EXISTS `glossary_entries` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `term`       VARCHAR(150) NOT NULL,
    `category`   VARCHAR(30)  NOT NULL DEFAULT 'terme',
    `definition` TEXT         NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_term`       (`project_id`, `term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
