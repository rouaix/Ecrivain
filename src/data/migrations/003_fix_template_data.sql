/* 
ALTER TABLE `projects` ADD COLUMN `template_id` INT NULL;
UPDATE `projects` SET `template_id` = (SELECT id FROM templates WHERE name='Roman classique' AND is_system=1) WHERE `template_id` IS NULL;

INSERT IGNORE INTO migrations (name) VALUES ('002_create_template_system');  
INSERT IGNORE INTO migrations (name) VALUES ('003_fix_template_data');
INSERT IGNORE INTO migrations (name) VALUES ('004_add_template_id_to_projects'); 
*/

DELETE FROM template_elements WHERE template_id IN (SELECT id FROM templates WHERE is_system = 1);

DELETE FROM templates WHERE is_system = 1;

INSERT INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`) VALUES
('Roman classique', 'Structure traditionnelle pour romans : sections, actes et chapitres, notes, personnages et fichiers.', 1, 1, NULL);

INSERT INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`) VALUES
('Etudiant', 'Structure pour travaux universitaires : introduction, parties/chapitres, conclusion, bibliographie, annexes et notes de recherche.', 0, 1, NULL);

INSERT INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`) VALUES
('Professeur', 'Structure pour supports pedagogiques : presentation du cours, lecons, exercices, evaluations, annexes et ressources.', 0, 1, NULL);

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section', 'cover', 'before', 0, 1, '{"label":"Couverture"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section', 'preface', 'before', 1, 1, '{"label":"Preface"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section', 'introduction', 'before', 2, 1, '{"label":"Introduction"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section', 'prologue', 'before', 3, 1, '{"label":"Prologue"}');

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'act', NULL, NULL, 4, 1, '{"label_singular":"Acte","label_plural":"Actes"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'chapter', NULL, NULL, 5, 1, '{"label_singular":"Chapitre","label_plural":"Chapitres"}');

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section', 'postface', 'after', 6, 1, '{"label":"Postface"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section', 'appendices', 'after', 7, 1, '{"label":"Annexes"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section', 'back_cover', 'after', 8, 1, '{"label":"Quatrieme de couverture"}');

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'note', NULL, NULL, 9, 1, '{"label_singular":"Note","label_plural":"Notes"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'character', NULL, NULL, 10, 1, '{"label_singular":"Personnage","label_plural":"Personnages"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'file', NULL, NULL, 11, 1, '{"label_singular":"Fichier","label_plural":"Fichiers"}');

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'cover', 'before', 0, 1, '{"label":"Page de garde"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'introduction', 'before', 1, 1, '{"label":"Introduction"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'chapter', NULL, NULL, 2, 1, '{"label_singular":"Partie","label_plural":"Parties"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'postface', 'after', 3, 1, '{"label":"Conclusion"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'appendices', 'after', 4, 1, '{"label":"Bibliographie / Annexes"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'note', NULL, NULL, 5, 1, '{"label_singular":"Note de recherche","label_plural":"Notes de recherche"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'file', NULL, NULL, 6, 1, '{"label_singular":"Document","label_plural":"Documents joints"}');

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'cover', 'before', 0, 1, '{"label":"Page de garde"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'introduction', 'before', 1, 1, '{"label":"Presentation du cours"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'preface', 'before', 2, 1, '{"label":"Programme et prerequis"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'act', NULL, NULL, 3, 1, '{"label_singular":"Module","label_plural":"Modules"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'chapter', NULL, NULL, 4, 1, '{"label_singular":"Lecon","label_plural":"Lecons"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'element', NULL, NULL, 5, 1, '{"label_singular":"Exercice","label_plural":"Exercices"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'element', NULL, NULL, 6, 1, '{"label_singular":"Evaluation","label_plural":"Evaluations"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'appendices', 'after', 7, 1, '{"label":"Corriges et annexes"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'note', NULL, NULL, 8, 1, '{"label_singular":"Note pedagogique","label_plural":"Notes pedagogiques"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'file', NULL, NULL, 9, 1, '{"label_singular":"Ressource","label_plural":"Ressources"}');

UPDATE `projects` SET `template_id` = (SELECT id FROM templates WHERE name='Roman classique' AND is_system=1) WHERE `template_id` IS NULL;
