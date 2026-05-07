CREATE TABLE IF NOT EXISTS inline_suggestions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    content_type VARCHAR(20)  NOT NULL,
    content_id   INT UNSIGNED NOT NULL,
    original_text TEXT        NOT NULL,
    suggested_text TEXT       NOT NULL,
    comment      TEXT         NULL,
    status       ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_content (content_type, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
