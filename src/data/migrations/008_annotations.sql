-- 008_annotations.sql
-- Review mode: annotations table (no FK constraints)

CREATE TABLE IF NOT EXISTS `annotations` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `project_id`    INT NOT NULL,
    `user_id`       INT NOT NULL,
    `content_type`  VARCHAR(20) NOT NULL DEFAULT 'chapter',
    `content_id`    INT NOT NULL,
    `selected_text` TEXT NOT NULL,
    `comment`       TEXT NULL,
    `category`      VARCHAR(20) NOT NULL DEFAULT 'to_check',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_content` (`content_type`, `content_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
