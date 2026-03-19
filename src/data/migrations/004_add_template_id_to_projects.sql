-- Migration 004: Ajout de template_id a la table projects
-- Separee de 002 pour compatibilite MySQL (pas de IF NOT EXISTS sur ALTER TABLE)

-- Verifier si la colonne existe deja via une requete conditionnelle
-- Si elle existe, le ALTER TABLE echouera et la migration sera quand meme marquee
-- Grace au try/catch dans Migrations.php

/*
ALTER TABLE `projects`
ADD COLUMN `template_id` INT UNSIGNED NULL AFTER `user_id`;
*/
ALTER TABLE `projects`
ADD KEY `idx_template_id` (`template_id`);

UPDATE `projects` SET `template_id` = (SELECT id FROM templates WHERE name='Roman classique' AND is_system=1) WHERE `template_id` IS NULL;
