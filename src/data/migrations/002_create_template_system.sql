-- Migration 002: Creation du systeme de templates modulaires
-- Date: 2026-02-06
-- Description: Creation des tables templates, template_elements, elements

-- Table: templates
CREATE TABLE IF NOT EXISTS `templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_is_default` (`is_default`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: template_elements
CREATE TABLE IF NOT EXISTS `template_elements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT UNSIGNED NOT NULL,
    `element_type` ENUM('section', 'act', 'chapter', 'note', 'character', 'file', 'element') NOT NULL,
    `element_subtype` VARCHAR(50) NULL,
    `section_placement` ENUM('before', 'after') NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `config_json` TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_template_id` (`template_id`),
    KEY `idx_display_order` (`template_id`, `display_order`),
    CONSTRAINT `fk_template_elements_template` FOREIGN KEY (`template_id`)
        REFERENCES `templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: elements
-- project_id is INT (signed) to match projects.id
-- template_element_id is INT UNSIGNED to match template_elements.id
CREATE TABLE IF NOT EXISTS `elements` (
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
