# Système de Migrations de Base de Données

## 📋 Description

Ce système de migrations permet de gérer automatiquement les modifications de la base de données lors des mises à jour de l'application.

## 🚀 Comment ça marche

1. **Au démarrage de l'application**, le système vérifie automatiquement s'il y a de nouvelles migrations à exécuter
2. Les migrations sont exécutées **dans l'ordre** (001, 002, 003, etc.)
3. Une table `migrations` garde la trace des migrations déjà exécutées
4. Chaque migration n'est **jamais exécutée deux fois**

## 📝 Créer une nouvelle migration

### 1. Nommage des fichiers

Les fichiers de migration doivent suivre cette convention de nommage:

```
XXX_description_de_la_migration.sql
```

Où `XXX` est un numéro séquentiel (001, 002, 003, etc.)

**Exemples:**
- `001_create_project_files_table.sql`
- `002_add_tags_to_chapters.sql`
- `003_create_bookmarks_table.sql`

### 2. Contenu du fichier SQL

Le fichier doit contenir du SQL valide. Vous pouvez inclure:
- Plusieurs instructions SQL (séparées par `;`)
- Commentaires (lignes commençant par `--`)
- CREATE TABLE, ALTER TABLE, INSERT, UPDATE, etc.

**Exemple:**

```sql
-- Migration: Add tags support to chapters
CREATE TABLE IF NOT EXISTS `chapter_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `tag` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chapter_id` (`chapter_id`),
  CONSTRAINT `chapter_tags_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add tag_count column to chapters
ALTER TABLE `chapters` ADD COLUMN `tag_count` int(11) DEFAULT 0;
```

### 3. Placement du fichier

Placez simplement votre fichier `.sql` dans le répertoire:
```
src/data/migrations/
```

### 4. Exécution automatique

La migration sera **automatiquement exécutée** au prochain démarrage de l'application (prochain rechargement de page en développement, ou redémarrage du serveur en production).

## 🔍 Vérification

Pour vérifier quelles migrations ont été exécutées, consultez la table `migrations` dans votre base de données:

```sql
SELECT * FROM migrations ORDER BY id;
```

## ⚠️ Bonnes pratiques

1. **Toujours utiliser `IF NOT EXISTS`** pour les CREATE TABLE
2. **Tester vos migrations localement** avant de les déployer en production
3. **Ne jamais modifier une migration déjà exécutée** - créez une nouvelle migration à la place
4. **Numérotez séquentiellement** - vérifiez le dernier numéro avant d'ajouter une nouvelle migration
5. **Utilisez des transactions implicites** - le système exécute chaque instruction séparément

## 🛠️ Gestion des erreurs

- Si une migration échoue, l'erreur est **loggée** mais l'application continue de fonctionner
- Consultez les logs pour diagnostiquer les problèmes:
  - Développement: `error_log` de PHP
  - Production: logs du serveur

## 📁 Structure

```
src/
├── app/
│   └── core/
│       └── Migrations.php          # Gestionnaire de migrations
├── data/
│   └── migrations/
│       ├── README.md               # Ce fichier
│       ├── 001_*.sql              # Première migration
│       ├── 002_*.sql              # Deuxième migration
│       └── ...
└── www/
    └── index.php                   # Appel automatique aux migrations
```

## 🔄 Rollback

Le système ne gère pas automatiquement les rollbacks. Si vous devez annuler une migration:

1. Créez une **nouvelle migration** qui inverse les changements
2. Par exemple, si `005_add_column.sql` ajoute une colonne, créez `006_remove_column.sql`

## 📚 Exemples de migrations courantes

### Créer une table

```sql
-- 002_create_bookmarks.sql
CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `position` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Ajouter une colonne

```sql
-- 003_add_theme_to_projects.sql
ALTER TABLE `projects` ADD COLUMN `theme` varchar(50) DEFAULT 'default';
```

### Modifier une colonne

```sql
-- 004_increase_title_length.sql
ALTER TABLE `chapters` MODIFY COLUMN `title` varchar(500) NOT NULL;
```

### Ajouter des données

```sql
-- 005_add_default_sections.sql
INSERT INTO `section_types` (`name`, `position`)
VALUES ('preface', 'before'), ('epilogue', 'after')
ON DUPLICATE KEY UPDATE `name` = `name`;
```
