-- Migration 029: Add is_exported column to notes and characters tables

ALTER TABLE `notes` ADD COLUMN `is_exported` TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE `characters` ADD COLUMN `is_exported` TINYINT(1) NOT NULL DEFAULT 1;
