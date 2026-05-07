CREATE TABLE IF NOT EXISTS `project_collaborators` (
    `id`           INT NOT NULL AUTO_INCREMENT,
    `project_id`   INT NOT NULL,
    `owner_id`     INT NOT NULL,
    `user_id`      INT NOT NULL,
    `status`       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `accepted_at`  TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_project_user` (`project_id`, `user_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
