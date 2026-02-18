-- 007_new_templates.sql
-- Add predefined templates: Scénario, Nouvelle, Essai, Mémoire

-- ─── Scénario ───────────────────────────────────────────────────────────────
INSERT INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`)
VALUES ('Scénario', 'Structure pour scénarios de film ou de série : actes, scènes, logline, personnages.', 0, 1, NULL);

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, els.et, els.est, els.sp, els.ord, 1, els.cfg
FROM `templates` t
CROSS JOIN (
    SELECT 'section'   AS et, 'cover'        AS est, 'before' AS sp, 0 AS ord, '{"label":"Couverture"}'                                             AS cfg UNION ALL
    SELECT 'section',          'introduction',         'before',       1,        '{"label":"Logline / Synopsis"}'                                              UNION ALL
    SELECT 'act',              NULL,                   NULL,           2,        '{"label_singular":"Acte","label_plural":"Actes"}'                            UNION ALL
    SELECT 'chapter',          NULL,                   NULL,           3,        '{"label_singular":"Scène","label_plural":"Scènes"}'                         UNION ALL
    SELECT 'character',        NULL,                   NULL,           4,        '{"label_singular":"Personnage","label_plural":"Personnages"}'                UNION ALL
    SELECT 'note',             NULL,                   NULL,           5,        '{"label_singular":"Note de script","label_plural":"Notes de script"}'
) AS els
WHERE t.name = 'Scénario' AND t.is_system = 1;

-- ─── Nouvelle ────────────────────────────────────────────────────────────────
INSERT INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`)
VALUES ('Nouvelle', 'Structure légère pour les nouvelles : chapitres sans actes, personnages, notes.', 0, 1, NULL);

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, els.et, els.est, els.sp, els.ord, 1, els.cfg
FROM `templates` t
CROSS JOIN (
    SELECT 'section'   AS et, 'cover'  AS est, 'before' AS sp, 0 AS ord, '{"label":"Couverture"}'                                             AS cfg UNION ALL
    SELECT 'chapter',          NULL,            NULL,           1,        '{"label_singular":"Chapitre","label_plural":"Chapitres"}'                          UNION ALL
    SELECT 'character',        NULL,            NULL,           2,        '{"label_singular":"Personnage","label_plural":"Personnages"}'                       UNION ALL
    SELECT 'note',             NULL,            NULL,           3,        '{"label_singular":"Note","label_plural":"Notes"}'
) AS els
WHERE t.name = 'Nouvelle' AND t.is_system = 1;

-- ─── Essai ───────────────────────────────────────────────────────────────────
INSERT INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`)
VALUES ('Essai', 'Structure argumentative : introduction, parties, conclusion, bibliographie, sources.', 0, 1, NULL);

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, els.et, els.est, els.sp, els.ord, 1, els.cfg
FROM `templates` t
CROSS JOIN (
    SELECT 'section'   AS et, 'cover'        AS est, 'before' AS sp, 0 AS ord, '{"label":"Couverture"}'                                             AS cfg UNION ALL
    SELECT 'section',          'introduction',         'before',       1,        '{"label":"Introduction"}'                                                    UNION ALL
    SELECT 'chapter',          NULL,                   NULL,           2,        '{"label_singular":"Partie","label_plural":"Parties"}'                        UNION ALL
    SELECT 'note',             NULL,                   NULL,           3,        '{"label_singular":"Source","label_plural":"Sources et références"}'           UNION ALL
    SELECT 'section',          'postface',             'after',        4,        '{"label":"Conclusion"}'                                                      UNION ALL
    SELECT 'section',          'appendices',           'after',        5,        '{"label":"Bibliographie"}'
) AS els
WHERE t.name = 'Essai' AND t.is_system = 1;

-- ─── Mémoire ─────────────────────────────────────────────────────────────────
INSERT INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`)
VALUES ('Mémoire', 'Structure académique complète : résumé, parties, chapitres, bibliographie, annexes.', 0, 1, NULL);

INSERT INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`)
SELECT t.id, els.et, els.est, els.sp, els.ord, 1, els.cfg
FROM `templates` t
CROSS JOIN (
    SELECT 'section'   AS et, 'cover'        AS est, 'before' AS sp, 0 AS ord, '{"label":"Page de titre"}'                                          AS cfg UNION ALL
    SELECT 'section',          'preface',              'before',       1,        '{"label":"Résumé / Abstract"}'                                               UNION ALL
    SELECT 'section',          'introduction',         'before',       2,        '{"label":"Introduction"}'                                                    UNION ALL
    SELECT 'act',              NULL,                   NULL,           3,        '{"label_singular":"Partie","label_plural":"Parties"}'                         UNION ALL
    SELECT 'chapter',          NULL,                   NULL,           4,        '{"label_singular":"Chapitre","label_plural":"Chapitres"}'                     UNION ALL
    SELECT 'note',             NULL,                   NULL,           5,        '{"label_singular":"Note de recherche","label_plural":"Notes de recherche"}'   UNION ALL
    SELECT 'file',             NULL,                   NULL,           6,        '{"label_singular":"Document annexe","label_plural":"Documents annexes"}'      UNION ALL
    SELECT 'section',          'postface',             'after',        7,        '{"label":"Conclusion"}'                                                      UNION ALL
    SELECT 'section',          'appendices',           'after',        8,        '{"label":"Bibliographie et annexes"}'
) AS els
WHERE t.name = 'Mémoire' AND t.is_system = 1;
