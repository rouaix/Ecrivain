-- Migration 006: Recreate elements table
-- Previous migration 005 may have DROPped the table but failed to CREATE it
-- because projects table uses MyISAM (no FK support)
-- This migration creates elements without FK to projects

DROP TABLE IF EXISTS `elements`;

CREATE TABLE `elements` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `template_element_id` INT UNSIGNED NOT NULL,
    `project_id` INT NOT NULL,
    `parent_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NULL,
    `resume` TEXT NULL,
    `order_index` INT NOT NULL DEFAULT 0,
    `is_exported` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_template_element_id` (`template_element_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_order` (`project_id`, `template_element_id`, `order_index`),
    CONSTRAINT `fk_elements_template_element` FOREIGN KEY (`template_element_id`)
        REFERENCES `template_elements` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_elements_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `elements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
