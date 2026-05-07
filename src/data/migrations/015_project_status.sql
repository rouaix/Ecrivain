-- Migration 015: Project status for Kanban board
ALTER TABLE `projects` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active';
