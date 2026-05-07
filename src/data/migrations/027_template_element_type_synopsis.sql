-- Migration 027: Add 'synopsis' to template_elements element_type ENUM

ALTER TABLE `template_elements`
    MODIFY COLUMN `element_type` ENUM('section', 'act', 'chapter', 'note', 'character', 'file', 'element', 'scenario', 'synopsis') NOT NULL;
