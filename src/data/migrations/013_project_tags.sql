-- Migration 013: Project tags
-- Tags par projet, par utilisateur

CREATE TABLE IF NOT EXISTS `project_tags` (
    `id`      INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `name`    VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_tag` (`user_id`, `name`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tag_links` (
    `project_id` INT NOT NULL,
    `tag_id`     INT NOT NULL,
    PRIMARY KEY (`project_id`, `tag_id`),
    KEY `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
