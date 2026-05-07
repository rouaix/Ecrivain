-- Migration 024: Champs enrichis pour les personnages
-- Ajout : âge, traits, motivations, arc narratif, défauts, avatar, groupe/faction

ALTER TABLE `characters` ADD COLUMN `age`         VARCHAR(50)  NULL AFTER `name`;
ALTER TABLE `characters` ADD COLUMN `traits`      TEXT         NULL AFTER `age`;
ALTER TABLE `characters` ADD COLUMN `motivations` TEXT         NULL AFTER `traits`;
ALTER TABLE `characters` ADD COLUMN `arc`         TEXT         NULL AFTER `motivations`;
ALTER TABLE `characters` ADD COLUMN `flaws`       TEXT         NULL AFTER `arc`;
ALTER TABLE `characters` ADD COLUMN `avatar`      VARCHAR(500) NULL AFTER `flaws`;
ALTER TABLE `characters` ADD COLUMN `group_name`  VARCHAR(100) NULL AFTER `avatar`;
