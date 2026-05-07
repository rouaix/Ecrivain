CREATE TABLE IF NOT EXISTS project_activity_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    user_id     INT NOT NULL,
    action      VARCHAR(20)  NOT NULL,
    entity_type VARCHAR(50)  NOT NULL,
    entity_id   INT          DEFAULT NULL,
    entity_label VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pal_project  (project_id),
    INDEX idx_pal_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
