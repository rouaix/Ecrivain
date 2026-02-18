-- Migration 005: Chapter version history
-- Stores up to 10 content snapshots per chapter (managed by application logic)

CREATE TABLE IF NOT EXISTS `chapter_versions` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `chapter_id` INT NOT NULL,
    `content`    LONGTEXT NULL,
    `word_count` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chapter_id` (`chapter_id`),
    KEY `idx_chapter_date` (`chapter_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
