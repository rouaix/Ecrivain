-- Migration 006: Project-specific ignored words dictionary
-- Stores a JSON array of words to ignore during grammar checking

ALTER TABLE `projects` ADD COLUMN `ignored_words` TEXT NULL DEFAULT NULL;
