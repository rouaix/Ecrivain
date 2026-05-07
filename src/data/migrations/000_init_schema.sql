-- Migration 000: Schéma initial complet
-- Crée toutes les tables de base de l'application Écrivain.
-- Les tables du système de templates sont gérées par les migrations 002 et 003.
-- Ce fichier est exécuté en premier (ordre alphabétique) sur toute installation vierge.

-- Table: users
-- Comptes utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: projects
-- Projets d'écriture appartenant à un utilisateur
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `cover_image` TEXT NULL,
    `template_id` INT NULL,
    `lines_per_page` INT NOT NULL DEFAULT 38,
    `settings` TEXT NULL COMMENT 'JSON : paramètres avancés (ex: words_per_page)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: acts
-- Actes (grandes parties) d'un projet, ordonnés par order_index
CREATE TABLE IF NOT EXISTS `acts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `content` LONGTEXT NULL COMMENT 'Contenu HTML Quill',
    `resume` TEXT NULL COMMENT 'Résumé généré par IA ou saisi manuellement',
    `comment` TEXT NULL,
    `order_index` INT NOT NULL DEFAULT 0,
    `is_exported` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_order` (`project_id`, `order_index`),
    CONSTRAINT `fk_acts_project` FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chapters
-- Chapitres d'un projet, rattachés optionnellement à un acte et à un chapitre parent
CREATE TABLE IF NOT EXISTS `chapters` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `act_id` INT NULL COMMENT 'Acte parent (NULL = chapitre hors acte)',
    `parent_id` INT NULL COMMENT 'Chapitre parent pour les sous-chapitres',
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NULL COMMENT 'Contenu HTML Quill',
    `resume` TEXT NULL COMMENT 'Résumé généré par IA ou saisi manuellement',
    `order_index` INT NOT NULL DEFAULT 0,
    `is_exported` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_act_id` (`act_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_order` (`project_id`, `act_id`, `order_index`),
    CONSTRAINT `fk_chapters_project` FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_chapters_act` FOREIGN KEY (`act_id`)
        REFERENCES `acts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_chapters_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sections
-- Sections de métadonnées d'un projet (couverture, préface, annexes, etc.)
-- Types possibles : cover, preface, introduction, prologue, postface, appendices, back_cover
CREATE TABLE IF NOT EXISTS `sections` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NULL,
    `content` LONGTEXT NULL COMMENT 'Contenu HTML Quill',
    `comment` TEXT NULL,
    `image_path` VARCHAR(500) NULL,
    `order_index` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_type` (`project_id`, `type`),
    CONSTRAINT `fk_sections_project` FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notes
-- Notes et références attachées à un projet
CREATE TABLE IF NOT EXISTS `notes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `title` VARCHAR(255) NULL,
    `content` LONGTEXT NULL COMMENT 'Contenu HTML Quill',
    `comment` TEXT NULL,
    `image_path` VARCHAR(500) NULL,
    `order_index` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    CONSTRAINT `fk_notes_project` FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: characters
-- Fiches personnages d'un projet
CREATE TABLE IF NOT EXISTS `characters` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `comment` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_name` (`project_id`, `name`),
    CONSTRAINT `fk_characters_project` FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: comments
-- Annotations positionnées dans le texte d'un chapitre (start_pos / end_pos en caractères)
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `chapter_id` INT NOT NULL,
    `start_pos` INT NOT NULL DEFAULT 0,
    `end_pos` INT NOT NULL DEFAULT 0,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chapter_id` (`chapter_id`),
    CONSTRAINT `fk_comments_chapter` FOREIGN KEY (`chapter_id`)
        REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: project_files
-- Fichiers attachés à un projet (PDF, images, documents...)
CREATE TABLE IF NOT EXISTS `project_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL COMMENT 'Nom original du fichier',
    `filepath` VARCHAR(500) NOT NULL COMMENT 'Chemin relatif depuis src/',
    `filetype` VARCHAR(100) NULL COMMENT 'Type MIME',
    `filesize` INT NOT NULL DEFAULT 0 COMMENT 'Taille en octets',
    `comment` TEXT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    CONSTRAINT `fk_project_files_project` FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ai_usage
-- Suivi de la consommation de tokens IA par utilisateur et par modèle
CREATE TABLE IF NOT EXISTS `ai_usage` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `model_name` VARCHAR(100) NOT NULL COMMENT 'Ex : gpt-4o-mini, gemini-pro',
    `prompt_tokens` INT NOT NULL DEFAULT 0,
    `completion_tokens` INT NOT NULL DEFAULT 0,
    `total_tokens` INT NOT NULL DEFAULT 0,
    `feature_name` VARCHAR(100) NULL COMMENT 'Ex : continue, rephrase, summarize_chapter',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_model_name` (`model_name`),
    CONSTRAINT `fk_ai_usage_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Système de templates (également géré par migrations 002/003)
-- Inclus ici pour qu'une installation vierge soit complète
-- sans dépendre de l'ordre d'exécution des migrations suivantes
-- ============================================================

-- Table: templates
-- Templates de structure de projet (ex : Roman classique, Etudiant, Professeur)
CREATE TABLE IF NOT EXISTS `templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = template livre avec l application',
    `created_by` INT NULL COMMENT 'NULL = template systeme',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_is_default` (`is_default`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: template_elements
-- Definition des types d elements actives dans chaque template
CREATE TABLE IF NOT EXISTS `template_elements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT UNSIGNED NOT NULL,
    `element_type` ENUM('section','act','chapter','note','character','file','element') NOT NULL,
    `element_subtype` VARCHAR(50) NULL COMMENT 'Ex : cover, preface, introduction',
    `section_placement` ENUM('before','after') NULL COMMENT 'Position relative au contenu principal',
    `display_order` INT NOT NULL DEFAULT 0,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `config_json` TEXT NULL COMMENT 'JSON : label_singular, label_plural, label',
    PRIMARY KEY (`id`),
    KEY `idx_template_id` (`template_id`),
    KEY `idx_display_order` (`template_id`, `display_order`),
    CONSTRAINT `fk_template_elements_template` FOREIGN KEY (`template_id`)
        REFERENCES `templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: elements
-- Instances d elements personnalises crees par les utilisateurs dans leurs projets
CREATE TABLE IF NOT EXISTS `elements` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `template_element_id` INT UNSIGNED NOT NULL,
    `project_id` INT NOT NULL,
    `parent_id` INT NULL COMMENT 'Hierarchie parent/enfant entre elements',
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NULL COMMENT 'Contenu HTML Quill',
    `resume` TEXT NULL,
    `order_index` INT NOT NULL DEFAULT 0,
    `is_exported` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_template_element_id` (`template_element_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_order` (`project_id`, `template_element_id`, `order_index`),
    CONSTRAINT `fk_elements_template_element` FOREIGN KEY (`template_element_id`)
        REFERENCES `template_elements` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_elements_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `elements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Donnees initiales : templates systeme par defaut
-- INSERT IGNORE evite les doublons si la migration 003 tourne apres
-- ============================================================

INSERT IGNORE INTO `templates` (`name`, `description`, `is_default`, `is_system`, `created_by`) VALUES
('Roman classique', 'Structure traditionnelle pour romans : sections, actes et chapitres, notes, personnages et fichiers.', 1, 1, NULL),
('Etudiant', 'Structure pour travaux universitaires : introduction, parties/chapitres, conclusion, bibliographie, annexes et notes de recherche.', 0, 1, NULL),
('Professeur', 'Structure pour supports pedagogiques : presentation du cours, lecons, exercices, evaluations, annexes et ressources.', 0, 1, NULL);

-- Elements du template "Roman classique"
INSERT IGNORE INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section',   'cover',        'before', 0,  1, '{"label":"Couverture"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section',   'preface',      'before', 1,  1, '{"label":"Preface"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section',   'introduction', 'before', 2,  1, '{"label":"Introduction"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section',   'prologue',     'before', 3,  1, '{"label":"Prologue"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'act',       NULL,           NULL,     4,  1, '{"label_singular":"Acte","label_plural":"Actes"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'chapter',   NULL,           NULL,     5,  1, '{"label_singular":"Chapitre","label_plural":"Chapitres"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section',   'postface',     'after',  6,  1, '{"label":"Postface"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section',   'appendices',   'after',  7,  1, '{"label":"Annexes"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'section',   'back_cover',   'after',  8,  1, '{"label":"Quatrieme de couverture"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'note',      NULL,           NULL,     9,  1, '{"label_singular":"Note","label_plural":"Notes"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'character', NULL,           NULL,     10, 1, '{"label_singular":"Personnage","label_plural":"Personnages"}'),
((SELECT id FROM templates WHERE name='Roman classique' AND is_system=1), 'file',      NULL,           NULL,     11, 1, '{"label_singular":"Fichier","label_plural":"Fichiers"}');

-- Elements du template "Etudiant"
INSERT IGNORE INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'cover',        'before', 0, 1, '{"label":"Page de garde"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'introduction', 'before', 1, 1, '{"label":"Introduction"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'chapter', NULL,           NULL,     2, 1, '{"label_singular":"Partie","label_plural":"Parties"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'postface',     'after',  3, 1, '{"label":"Conclusion"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'section', 'appendices',   'after',  4, 1, '{"label":"Bibliographie / Annexes"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'note',    NULL,           NULL,     5, 1, '{"label_singular":"Note de recherche","label_plural":"Notes de recherche"}'),
((SELECT id FROM templates WHERE name='Etudiant' AND is_system=1), 'file',    NULL,           NULL,     6, 1, '{"label_singular":"Document","label_plural":"Documents joints"}');

-- Elements du template "Professeur"
INSERT IGNORE INTO `template_elements` (`template_id`, `element_type`, `element_subtype`, `section_placement`, `display_order`, `is_enabled`, `config_json`) VALUES
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'cover',        'before', 0, 1, '{"label":"Page de garde"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'introduction', 'before', 1, 1, '{"label":"Presentation du cours"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'preface',      'before', 2, 1, '{"label":"Programme et prerequis"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'act',     NULL,           NULL,     3, 1, '{"label_singular":"Module","label_plural":"Modules"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'chapter', NULL,           NULL,     4, 1, '{"label_singular":"Lecon","label_plural":"Lecons"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'element', NULL,           NULL,     5, 1, '{"label_singular":"Exercice","label_plural":"Exercices"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'element', NULL,           NULL,     6, 1, '{"label_singular":"Evaluation","label_plural":"Evaluations"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'section', 'appendices',   'after',  7, 1, '{"label":"Corriges et annexes"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'note',    NULL,           NULL,     8, 1, '{"label_singular":"Note pedagogique","label_plural":"Notes pedagogiques"}'),
((SELECT id FROM templates WHERE name='Professeur' AND is_system=1), 'file',    NULL,           NULL,     9, 1, '{"label_singular":"Ressource","label_plural":"Ressources"}');
