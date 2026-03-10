-- Migration 023: Add is_exported column to scenarios table

ALTER TABLE `scenarios` ADD COLUMN `is_exported` TINYINT(1) NOT NULL DEFAULT 1;
