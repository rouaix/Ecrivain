-- Migration 004: Writing statistics
-- Adds word_count to chapters and creates daily snapshot table

ALTER TABLE `chapters` ADD COLUMN `word_count` INT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `writing_stats` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `user_id`    INT NOT NULL,
    `chapter_id` INT NOT NULL,
    `project_id` INT NOT NULL,
    `stat_date`  DATE NOT NULL,
    `word_count` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_chapter_date` (`chapter_id`, `stat_date`),
    KEY `idx_user_date` (`user_id`, `stat_date`),
    KEY `idx_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
