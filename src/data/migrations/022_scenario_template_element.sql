-- Migration 022: Add 'scenario' element to the 'Scénario' system template

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, 'scenario', NULL, NULL, 6, 1, '{"label_singular":"Scénario","label_plural":"Scénarios"}'
FROM `templates` t
WHERE t.name = 'Scénario' AND t.is_system = 1
LIMIT 1;
