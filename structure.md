# Structure du Projet Écrivain

## Table des Matières
1. [Aperçu Général](#aperçu-général)
2. [Structure des Dossiers](#structure-des-dossiers)
3. [Analyse des Dépendances (Vendor)](#analyse-des-dépendances-vendor)
4. [Organisation des Modules](#organisation-des-modules)
5. [Structure des Données](#structure-des-données)
6. [Fonctionnement du Projet](#fonctionnement-du-projet)
7. [Fonctionnalités Principales](#fonctionnalités-principales)
8. [Flux de Données](#flux-de-données)
9. [Architecture Technique](#architecture-technique)
10. [Points d'Extension](#points-dextension)

## Aperçu Général

Écrivain est une application web PHP pour la gestion de projets d'écriture, construite avec le framework Fat-Free (F3). L'application permet aux utilisateurs de créer, organiser et gérer des projets d'écriture avec des fonctionnalités avancées comme l'intégration d'IA, la gestion de personnages, et l'export de contenu.

## Structure des Dossiers

```
/
├── public/                  # Fichiers publics (CSS, JS, images)
│   ├── css/                 # Styles CSS organisés par modules
│   ├── icons/               # Icônes de l'application
│   ├── js/                  # Scripts JavaScript
│   └── media-queries.css    # Styles pour différents appareils
├── src/                     # Code source principal
│   ├── app/                 # Logique applicative
│   │   ├── controllers/    # Contrôleurs principaux
│   │   ├── core/            # Noyau de l'application
│   │   ├── models/          # Modèles de données
│   │   ├── modules/         # Modules fonctionnels
│   │   └── views/           # Vues (templates)
│   ├── config/              # Configuration de l'application
│   ├── vendor/              # Dépendances externes (Composer)
│   └── web/                 # Points d'entrée web
└── .htaccess                # Configuration Apache
```

## Analyse des Dépendances (Vendor)

Le dossier `vendor/` contient les dépendances gérées par Composer. Voici les principales dépendances identifiées :

### Dépendances Principales

1. **Fat-Free Framework (F3)**
   - Framework PHP léger utilisé comme base de l'application
   - Fournit le routage, le moteur de template, et la gestion des requêtes

2. **PHPMailer**
   - Bibliothèque pour l'envoi d'emails
   - Utilisée pour les notifications et la récupération de mot de passe

3. **HTML2PDF**
   - Bibliothèque pour la génération de PDF
   - Utilisée pour l'export des projets au format PDF

4. **PhpSpreadsheet**
   - Bibliothèque pour la manipulation de fichiers Excel
   - Utilisée pour les exports au format XLSX

5. **GuzzleHTTP**
   - Client HTTP pour les requêtes API
   - Utilisé pour les intégrations avec les services d'IA

6. **Monolog**
   - Bibliothèque de logging
   - Utilisée pour le suivi des erreurs et des événements

7. **Doctrine DBAL**
   - Couche d'abstraction de base de données
   - Utilisée pour les interactions avec la base de données

### Dépendances d'IA

1. **OpenAI PHP Client**
   - Client pour l'API OpenAI
   - Utilisé pour l'intégration avec les modèles GPT

2. **Mistral AI Client**
   - Client pour l'API Mistral
   - Utilisé pour l'intégration avec les modèles Mistral

3. **Anthropic Client**
   - Client pour l'API Anthropic
   - Utilisé pour l'intégration avec les modèles Claude

4. **Google Gemini Client**
   - Client pour l'API Gemini
   - Utilisé pour l'intégration avec les modèles Google

## Organisation des Modules

L'application est organisée en modules fonctionnels dans `src/app/modules/` :

### Modules Principaux

1. **Auth**
   - Gestion de l'authentification
   - Inscription, connexion, récupération de mot de passe
   - Gestion des tokens d'accès

2. **Projects**
   - Création et gestion des projets
   - Tableau de bord des projets
   - Paramètres des projets

3. **Chapters**
   - Gestion des chapitres
   - Édition de contenu avec éditeur riche
   - Organisation des chapitres

4. **Characters**
   - Création et gestion des personnages
   - Fiches de personnages détaillées
   - Relations entre personnages

5. **AI**
   - Intégration des services d'IA
   - Génération de contenu assistée
   - Analyse de texte

6. **Export**
   - Export des projets au format PDF
   - Export au format XLSX
   - Options d'export personnalisées

7. **Settings**
   - Paramètres utilisateur
   - Préférences d'affichage
   - Gestion du compte

### Modules Secondaires

1. **Notes**
   - Gestion des notes et idées
   - Organisation par projet

2. **Stats**
   - Statistiques d'écriture
   - Suivi de la progression

3. **Search**
   - Fonctionnalité de recherche globale
   - Recherche dans le contenu des projets

## Structure des Données

### Base de Données

L'application utilise une base de données MySQL avec les tables principales suivantes :

1. **users**
   - id, username, email, password_hash, created_at, updated_at
   - Rôle : Stockage des informations utilisateur

2. **projects**
   - id, user_id, title, description, created_at, updated_at
   - Rôle : Stockage des projets d'écriture

3. **chapters**
   - id, project_id, title, content, order, created_at, updated_at
   - Rôle : Stockage des chapitres des projets

4. **characters**
   - id, project_id, name, description, age, gender, role, created_at, updated_at
   - Rôle : Stockage des personnages des projets

5. **notes**
   - id, project_id, title, content, created_at, updated_at
   - Rôle : Stockage des notes associées aux projets

6. **ai_usage**
   - id, user_id, model, tokens_used, cost, created_at
   - Rôle : Suivi de l'utilisation des services d'IA

7. **export_history**
   - id, user_id, project_id, format, created_at
   - Rôle : Historique des exports de projets

### Relations entre Tables

```
users (1) ← (n) projects (1) ← (n) chapters
          ← (n) characters
          ← (n) notes
          ← (n) ai_usage
          ← (n) export_history
```

## Fonctionnement du Projet

### Architecture Globale

L'application suit une architecture MVC (Modèle-Vue-Contrôleur) avec les composants suivants :

1. **Contrôleurs** : Gèrent la logique métier et les requêtes
2. **Modèles** : Interagissent avec la base de données
3. **Vues** : Affichent l'interface utilisateur
4. **Services** : Fournissent des fonctionnalités réutilisables

### Flux de Requête

1. Une requête HTTP arrive sur le serveur
2. Le routeur F3 dirige la requête vers le contrôleur approprié
3. Le contrôleur traite la requête, interagit avec les modèles si nécessaire
4. Le contrôleur rend une vue ou retourne une réponse JSON
5. La réponse est envoyée au client

### Points d'Entrée Principaux

1. **Index** : Point d'entrée principal pour les utilisateurs authentifiés
2. **Auth** : Gestion de l'authentification
3. **API** : Points d'entrée pour les requêtes AJAX et API

## Fonctionnalités Principales

### Gestion de Projets

- Création et édition de projets
- Organisation des chapitres
- Gestion des personnages
- Ajout de notes et idées
- Tableau de bord de projet

### Édition de Contenu

- Éditeur riche (Quill.js)
- Mise en forme avancée
- Gestion des versions
- Prévisualisation du contenu

### Intégration d'IA

- Génération de contenu assistée
- Suggestions d'écriture
- Analyse de texte
- Comparaison de modèles d'IA

### Export et Publication

- Export au format PDF
- Export au format XLSX
- Options de personnalisation
- Historique des exports

### Gestion Utilisateur

- Création de compte
- Connexion sécurisée
- Récupération de mot de passe
- Paramètres de compte
- Préférences d'affichage

### Fonctionnalités Avancées

- Recherche globale
- Statistiques d'écriture
- Mode hors ligne (via service worker)
- Thèmes personnalisables
- Support multilingue

## Flux de Données

### Flux de Création de Projet

```
Utilisateur → Interface → Contrôleur Projects → Modèle Projects → Base de Données
                                      ↑
                                      ← Validation ←
```

### Flux de Génération d'IA

```
Utilisateur → Interface → Contrôleur AI → Service AI → API Externe (OpenAI/Mistral/...)
                                      ↑
                                      ← Réponse ←
```

### Flux d'Export

```
Utilisateur → Interface → Contrôleur Export → Service Export → Génération de Fichier
                                      ↑
                                      ← Fichier ←
```

## Architecture Technique

### Technologies Utilisées

- **Backend** : PHP 8.x avec Fat-Free Framework
- **Frontend** : HTML5, CSS3, JavaScript (jQuery)
- **Base de Données** : MySQL 5.7+
- **Cache** : (Optionnel) Redis/Memcached
- **Filesystem** : Stockage local pour les exports
- **Services Externes** : APIs d'IA (OpenAI, Mistral, Anthropic, Google)

### Patterns de Conception

1. **MVC** : Séparation des préoccupations
2. **Repository** : Accès aux données
3. **Service** : Logique métier réutilisable
4. **Factory** : Création d'objets complexes
5. **Strategy** : Sélection des services d'IA

### Bonnes Pratiques

1. **Sécurité** : Validation des entrées, protection CSRF
2. **Performance** : Mise en cache des requêtes fréquentes
3. **Maintenabilité** : Code modulaire et bien documenté
4. **Extensibilité** : Architecture basée sur des interfaces

## Points d'Extension

### Points d'Extension Identifiés

1. **Nouveaux Services d'IA**
   - Interface `AIServiceInterface` pour ajouter de nouveaux fournisseurs
   - Configuration dans `src/config/ai.php`

2. **Nouveaux Formats d'Export**
   - Interface `ExportServiceInterface` pour ajouter de nouveaux formats
   - Enregistrement dans le conteneur de services

3. **Nouveaux Modules**
   - Structure modulaire permettant d'ajouter des fonctionnalités
   - Intégration via le système de routage F3

4. **Thèmes Personnalisés**
   - Système de thèmes CSS dans `public/css/theme-*.css`
   - Sélection via les paramètres utilisateur

5. **Intégrations Externes**
   - Système d'extension pour les services tiers
   - Points d'intégration dans les contrôleurs principaux

### Exemple d'Extension

Pour ajouter un nouveau service d'IA :

1. Créer une classe implémentant `AIServiceInterface`
2. Configurer le service dans `src/config/ai.php`
3. Ajouter les options dans l'interface utilisateur
4. Tester l'intégration avec le système existant

## Conclusion

Écrivain est une application web bien structurée pour la gestion de projets d'écriture, avec une architecture modulaire qui permet une extension facile. L'intégration de multiples services d'IA et les fonctionnalités d'export en font un outil puissant pour les écrivains. La structure actuelle permet d'ajouter facilement de nouvelles fonctionnalités tout en maintenant un code propre et maintenable.
