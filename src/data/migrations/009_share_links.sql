-- Migration 009: Liens de partage de projets (table principale)
-- Pas de FK : les tables historiques (users) utilisent MyISAM
CREATE TABLE IF NOT EXISTS `share_links` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `token`      VARCHAR(64) NOT NULL,
    `user_id`    INT NOT NULL,
    `label`      VARCHAR(255) NULL,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
