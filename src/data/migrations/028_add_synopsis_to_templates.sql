-- Migration 028: Add synopsis element to fiction system templates
-- Templates concernés : Roman classique, Scénario, Nouvelle

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, 'synopsis', NULL, NULL, 99, 1, '{"label":"Synopsis"}'
FROM `templates` t
WHERE t.name = 'Roman classique' AND t.is_system = 1
  AND NOT EXISTS (
      SELECT 1 FROM `template_elements` te
      WHERE te.template_id = t.id AND te.element_type = 'synopsis'
  );

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, 'synopsis', NULL, NULL, 99, 1, '{"label":"Synopsis"}'
FROM `templates` t
WHERE t.name = 'Scénario' AND t.is_system = 1
  AND NOT EXISTS (
      SELECT 1 FROM `template_elements` te
      WHERE te.template_id = t.id AND te.element_type = 'synopsis'
  );

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, 'synopsis', NULL, NULL, 99, 1, '{"label":"Synopsis"}'
FROM `templates` t
WHERE t.name = 'Nouvelle' AND t.is_system = 1
  AND NOT EXISTS (
      SELECT 1 FROM `template_elements` te
      WHERE te.template_id = t.id AND te.element_type = 'synopsis'
  );
