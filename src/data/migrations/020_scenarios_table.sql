-- Migration 020: Create scenarios table
-- Replaces notes with type='scenario' with a dedicated table

CREATE TABLE IF NOT EXISTS `scenarios` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NULL,
    `saison` VARCHAR(10) NULL,
    `episode` VARCHAR(10) NULL,
    `genre` VARCHAR(100) NULL,
    `source_chapter_ids` TEXT NULL,
    `previous_episode_ids` TEXT NULL,
    `markdown` LONGTEXT NULL,
    `order_index` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
