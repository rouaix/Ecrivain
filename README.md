# Écrivain

Plateforme de rédaction créative et d'aide à l'écriture de livres, avec des fonctionnalités d'intelligence artificielle. Développée en PHP avec le framework Fat-Free Framework (F3).

## Fonctionnalités

- **Gestion de projets** : Création et organisation de projets d'écriture (romans, nouvelles, scripts...)
- **Éditeur riche** : Éditeur de texte Quill.js avec mise en forme, commentaires, synonymes
- **Structure narrative** : Organisation en actes, chapitres, sections, notes et personnages
- **Assistance IA** : Génération de texte, reformulation, résumé de chapitres/actes via OpenAI, Gemini, Anthropic ou Mistral
- **Vérification grammaticale** : Intégration LanguageTool
- **Export** : Export en HTML, EPUB et PDF
- **Thèmes** : 7 thèmes visuels (sombre, clair, machine à écrire, etc.)
- **Mode lecture** : Mode lecture avec marque-pages
- **Système de templates** : Structure de projet personnalisable avec types d'éléments sur mesure
- **Authentification** : Connexion par session, tokens JWT, réinitialisation de mot de passe
- **PWA** : Application web progressive installable

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.4 |
| Framework | Fat-Free Framework (F3) v3.6.5 |
| Base de données | MySQL 8.0 |
| Éditeur | Quill.js 1.3.6 |
| Frontend | Vanilla JavaScript, CSS modulaire (40 fichiers) |
| Icônes | Font Awesome 6.4.0 |
| Auth | firebase/php-jwt |
| Export PDF | spipu/html2pdf |

## Prérequis

- PHP 8.4 avec les extensions : `pdo`, `pdo_mysql`, `dom`, `simplexml`, `mbstring`, `openssl`
- MySQL 8.0+
- Apache 2.4+ avec `mod_rewrite` activé
- Composer
- Accès en écriture sur `src/tmp/`, `src/logs/`, `src/public/uploads/`

## Installation

### 1. Cloner le dépôt

```bash
git clone <url-du-depot> ecrivain
cd ecrivain
```

### 2. Installer les dépendances PHP

```bash
cd src
composer install
```

### 3. Créer la base de données

Créez uniquement la base de données vide — les tables sont créées automatiquement au premier démarrage via le système de migrations :

```sql
CREATE DATABASE ecrivain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ecrivain'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
GRANT ALL PRIVILEGES ON ecrivain.* TO 'ecrivain'@'localhost';
FLUSH PRIVILEGES;
```

> **Pas d'import SQL manuel.** Le fichier `src/data/migrations/000_init_schema.sql` crée toutes les tables au premier chargement de l'application. Les migrations suivantes (002, 003…) s'enchaînent dans l'ordre pour compléter le schéma.

#### Tables créées automatiquement

| Table | Description |
|-------|-------------|
| `users` | Comptes utilisateurs (username, email, password hashé) |
| `projects` | Projets d'écriture (titre, description, couverture, paramètres) |
| `acts` | Actes / grandes parties (titre, contenu Quill, résumé IA, ordre) |
| `chapters` | Chapitres (contenu Quill, résumé, sous-chapitres via parent_id) |
| `sections` | Sections de métadonnées (couverture, préface, annexes, etc.) |
| `notes` | Notes et références attachées à un projet |
| `characters` | Fiches personnages |
| `comments` | Annotations positionnées dans le texte d'un chapitre |
| `project_files` | Fichiers joints à un projet (PDF, images…) |
| `ai_usage` | Suivi de la consommation de tokens IA par modèle |
| `migrations` | Suivi des migrations exécutées (auto-créée par le système) |
| `templates` | Templates de structure de projet (migration 002) |
| `template_elements` | Définition des types d'éléments par template (migration 002) |
| `elements` | Instances d'éléments personnalisés créés par les utilisateurs (migration 002) |
| `writing_stats` | Statistiques journalières de mots par projet (migration 004) |
| `chapter_versions` | Historique des versions de chapitres (migration 005) |
| `annotations` | Annotations du mode relecture (migration 008) |

### 4. Configurer l'environnement

#### Développement local

```bash
cp src/.env.example src/.env.local
```

Éditez `src/.env.local` :

```ini
DB_HOST=localhost
DB_NAME=ecrivain
DB_USER=ecrivain
DB_PASS=votre_mot_de_passe
DB_PORT=3306

SESSION_DOMAIN=
JWT_SECRET=une_chaine_aleatoire_de_64_caracteres_minimum

DEBUG=3
```

> En développement local, l'environnement est auto-détecté si le chemin du projet contient `Projets`.

#### Production

```bash
cp src/.env.example src/.env
```

Éditez `src/.env` avec vos valeurs de production :

```ini
DB_HOST=votre_host_mysql
DB_NAME=ecrivain
DB_USER=ecrivain
DB_PASS=mot_de_passe_securise
DB_PORT=3306

SESSION_DOMAIN=.votre-domaine.com
JWT_SECRET=une_chaine_aleatoire_de_64_caracteres_minimum

DEBUG=0
```

> **Générer un `JWT_SECRET` sécurisé**
>
> Sous Linux/macOS ou Git Bash (Windows) :
> ```bash
> openssl rand -base64 48
> ```
> Sous PowerShell (Windows) :
> ```powershell
> [Convert]::ToBase64String((1..48 | ForEach-Object { Get-Random -Maximum 256 }))
> ```
> Copiez la valeur générée et collez-la comme valeur de `JWT_SECRET` dans votre fichier `.env`.
> Cette clé sert à signer et vérifier les tokens d'authentification JWT — ne la partagez jamais et conservez-la en lieu sûr.

### 5. Liens symboliques

La racine du projet contient trois liens symboliques qui permettent de pointer Apache directement sur le dossier racine plutôt que sur `src/www/` :

| Lien | Cible | Rôle |
|------|-------|------|
| `.htaccess` | `src/www/.htaccess` | Réécriture d'URL Apache |
| `index.php` | `src/www/index.php` | Point d'entrée PHP |
| `public` | `src/public` | Assets CSS/JS/images |

Ces liens sont versionnés dans git (mode `120000`). Ils sont créés automatiquement lors du `git clone` sur Linux/macOS.

**Sur Windows**, git ne crée pas les liens symboliques par défaut. Deux options :

Option A — Activer le mode développeur Windows puis cloner avec les symlinks :
```bash
git clone -c core.symlinks=true <url-du-depot> ecrivain
```

Option B — Créer les liens manuellement après le clone (PowerShell en administrateur) :
```powershell
New-Item -ItemType SymbolicLink -Path ".htaccess" -Target "src\www\.htaccess"
New-Item -ItemType SymbolicLink -Path "index.php"  -Target "src\www\index.php"
New-Item -ItemType SymbolicLink -Path "public"     -Target "src\public"
```

> Si les liens ne fonctionnent pas, il est aussi possible de pointer le `DocumentRoot` Apache directement sur `src/www/` et d'ignorer ces liens.

### 6. Configurer Apache

Le point d'entrée est `src/www/index.php`. Exemple de VirtualHost avec les liens symboliques actifs :

```apache
<VirtualHost *:80>
    ServerName ecrivain.local
    DocumentRoot /var/www/ecrivain

    <Directory /var/www/ecrivain>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>
</VirtualHost>
```

Sans les liens symboliques, pointer directement sur `src/www/` :

```apache
<VirtualHost *:80>
    ServerName ecrivain.local
    DocumentRoot /var/www/ecrivain/src/www

    <Directory /var/www/ecrivain/src/www>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Activez `mod_rewrite` :

```bash
a2enmod rewrite
systemctl restart apache2
```

### 7. Permissions

```bash
chmod -R 775 src/tmp src/logs src/public/uploads
chown -R www-data:www-data src/tmp src/logs src/public/uploads
```

### 8. Vérifier l'installation

Accédez à l'application dans votre navigateur. Les migrations de base de données s'exécutent automatiquement au premier chargement.

Créez un compte utilisateur via `/register`.

## Configuration IA (optionnel)

Les clés API IA sont configurées par utilisateur dans `src/data/{email}/ai_config.json` :

```json
{
  "provider": "openai",
  "api_key": "sk-...",
  "model": "gpt-4o-mini",
  "prompts": {
    "system": "Tu es un assistant d'écriture créative.",
    "continue": "Continue ce texte en gardant le style :",
    "rephrase": "Reformule ce passage :"
  }
}
```

Providers supportés : `openai`, `gemini`, `anthropic`, `mistral`

## Structure du projet

```
ecrivain/
├── src/
│   ├── app/
│   │   ├── config.ini          # Routes et configuration F3
│   │   ├── controllers/        # Contrôleur de base
│   │   ├── core/               # Migrations, utilitaires
│   │   └── modules/            # Modules fonctionnels
│   │       ├── ai/             # Intégration IA
│   │       ├── acts/           # Actes
│   │       ├── auth/           # Authentification
│   │       ├── chapter/        # Chapitres
│   │       ├── characters/     # Personnages
│   │       ├── element/        # Éléments personnalisés
│   │       ├── lecture/        # Mode lecture / relecture
│   │       ├── note/           # Notes
│   │       ├── project/        # Projets (dashboard, export)
│   │       ├── search/         # Recherche plein texte
│   │       ├── section/        # Sections metadata
│   │       ├── stats/          # Statistiques d'écriture
│   │       └── template/       # Templates de structure
│   ├── data/
│   │   └── migrations/         # Migrations SQL numérotées (000 → 008)
│   ├── public/
│   │   ├── css/                # CSS modulaire (40 fichiers)
│   │   ├── js/                 # JavaScript
│   │   ├── uploads/            # Fichiers uploadés (doit être accessible en écriture)
│   │   └── style.css           # Point d'entrée CSS
│   ├── tmp/                    # Cache F3 (doit être accessible en écriture)
│   ├── logs/                   # Logs applicatifs (doit être accessible en écriture)
│   ├── www/
│   │   └── index.php           # Point d'entrée de l'application
│   ├── composer.json
│   ├── .env.example            # Modèle de configuration
│   └── .htaccess               # Réécriture d'URL + sécurité
└── README.md
```

## Développement

### Ajout de migrations SQL

Le système de migrations (`src/app/core/Migrations.php`) s'exécute automatiquement à chaque démarrage de l'application. Il compare les fichiers présents dans `src/data/migrations/` avec la table `migrations` en base de données, et exécute uniquement ceux qui ne l'ont pas encore été.

**Chaque migration ne s'exécute qu'une seule fois**, même si l'application redémarre. Les erreurs bénignes (colonne déjà existante, table déjà créée) sont ignorées pour rendre les migrations idempotentes.

#### Créer une nouvelle migration

**1. Déterminer le prochain numéro**

Regardez les fichiers existants dans `src/data/migrations/` et incrémentez le numéro le plus élevé :

```
000_init_schema.sql            ← schéma complet
002_create_template_system.sql ← système de templates
003_fix_template_data.sql      ← correctifs templates
004_writing_stats.sql          ← statistiques d'écriture
005_chapter_versions.sql       ← historique des versions
006_project_dictionary.sql     ← dictionnaire par projet
007_new_templates.sql          ← nouveaux templates prédéfinis
008_annotations.sql            ← annotations relecture
009_ma_nouvelle_migration.sql  ← à créer
```

**2. Créer le fichier SQL**

Le nom du fichier doit respecter le format `NNN_description_courte.sql` (tri alphabétique = ordre d'exécution) :

```sql
-- src/data/migrations/004_add_user_preferences.sql

ALTER TABLE users ADD COLUMN preferences JSON NULL AFTER email;

CREATE TABLE IF NOT EXISTS user_settings (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**3. Tester**

Rechargez n'importe quelle page de l'application. La migration s'exécute immédiatement et son nom est enregistré dans la table `migrations`. Pour vérifier :

```sql
SELECT * FROM migrations ORDER BY id DESC;
```

#### Règles importantes

- Toujours utiliser `CREATE TABLE IF NOT EXISTS` (jamais `CREATE TABLE` seul)
- Ne pas utiliser `ALTER TABLE ADD COLUMN IF NOT EXISTS` — incompatible avec MySQL < 8.0.17 ; créez plutôt une migration séparée pour chaque colonne
- Ne pas utiliser de variables SQL (`SET @var = ...`) — chaque instruction est exécutée indépendamment via `PDO::exec()`
- Pour annuler une migration, créez une **nouvelle** migration qui effectue l'opération inverse (pas de rollback automatique)

### Convention de nommage

- Les modèles étendent `KS\Mapper` (ActiveRecord)
- Ne jamais nommer un modèle `Template`, `Base`, `View`, `Auth` — noms réservés par F3
- Le nom du fichier doit correspondre exactement au nom de la classe

### Fichiers JS/CSS et cache navigateur

Après modification d'un fichier JS ou CSS, incrémentez le paramètre de version dans `src/app/modules/project/views/layouts/main.html` :

```html
<link rel="stylesheet" href="{{ @base }}/public/style.css?v=X.X">
<script src="{{ @base }}/public/js/quill-adapter.js?v=XX"></script>
```

### Fins de ligne

Le projet impose des fins de ligne LF (Unix) via `.gitattributes`. Sur Windows, vérifiez la configuration Git :

```bash
git config core.autocrlf false
```

## Licence

Projet privé — tous droits réservés.
