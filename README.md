# Assistant

Cette application est un exemple de logiciel d’écriture en ligne développé en PHP en s’inspirant du cadre léger **Fat‑Free Framework**. Bien qu’elle ne soit pas aussi complète que la plateforme **Assistant**, elle met en œuvre plusieurs fonctionnalités clés pour aider les auteur·rice·s à planifier et rédiger leurs projets.

## Fonctionnalités principales

* **Gestion des comptes** : inscription, authentification et déconnexion.
* **Projets d’écriture** : création de projets avec titre, description et objectif de nombre de mots. Les projets sont listés dans le tableau de bord personnel.
* **Chapitres** : chaque projet contient des chapitres ordonnés. Vous pouvez créer, modifier ou supprimer des chapitres. Un éditeur de texte simplifié permet d’écrire le contenu et d’afficher un compteur de mots en temps réel.
* **Compteur de mots et progression** : le tableau de bord du projet affiche la somme des mots écrits sur l’ensemble des chapitres et calcule la progression par rapport à l’objectif de mots fixé.
* **Fiches personnages** : possibilité de créer des personnages liés à un projet et de modifier ou supprimer ces fiches.
* **Suggestions de synonymes** : dans l’éditeur, sélectionnez un mot puis cliquez sur « Suggestions de synonymes » pour obtenir une liste de synonymes issus d’un petit dictionnaire intégré.

## Parallèle avec Assistant

Le véritable site **Assistant** offre bien plus d’outils, notamment :

* des fiches personnages interconnectées via une mindmap, permettant de visualiser les interactions entre personnages.
* des objectifs d’écriture basés sur le temps ou le nombre de mots, avec des statistiques détaillées et des sauvegardes automatiques.
* un moteur de recherche de synonymes et un détecteur de répétitions pour enrichir le style.
* une interface hautement personnalisable avec différents thèmes et un assistant IA.

Cette version s’efforce de reproduire l’esprit de ces fonctionnalités tout en restant simple à déployer.

## Installation et exécution

1. **Pré‑requis** : installez PHP 7.4 ou supérieur avec l’extension MySQLi et une base MySQL disponible.
2. **Déploiement** : placez l’ensemble du dossier `Assistant` dans un serveur web supportant PHP.
3. **Lancement avec le serveur interne** :

   ```bash
   php -S localhost:8080 -t Assistant
   ```

   Rendez‑vous ensuite sur <http://localhost:8080/index.php> dans votre navigateur.

4. **Déploiement en production (recommandé)** :

   * **Configurer vos variables d’environnement** (ou un fichier `.env` hors web‑root) :
     * `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
     * `DB_AUTO_CREATE=false` pour désactiver la création automatique de la base
     * `SESSION_DOMAIN` si vous souhaitez limiter la portée du cookie de session
   * **Forcer HTTPS** et activer HSTS côté serveur web.
   * **Désactiver l’affichage des erreurs** en production (`display_errors=Off`) et activer le logging.
   * **Supprimer les fichiers de debug** (phpinfo, scripts utilitaires).

5. **Structure du projet** :

   * `index.php` : point d’entrée de l’application et définition des routes.
   * `lib/Base.php` : micro‑framework léger inspiré de Fat‑Free Framework pour la gestion des routes et du rendu.
   * `app/models/` : classes modèles pour les utilisateurs, projets, chapitres et personnages.
   * `app/views/` : templates HTML organisés par section (authentification, projets, éditeur, personnages).
   * `public/uploads/` : stockage des images de couverture uploadées.
   * `public/style.css` : feuille de styles minimale.

## Remarque

Ce code est fourni à des fins d’illustration pédagogique et n’est pas destiné à être utilisé tel quel en production. Pour un usage professionnel, il est recommandé d’utiliser le véritable framework Fat‑Free et d’enrichir les fonctionnalités (gestion des droits, API, mise en page avancée, sauvegardes automatiques, etc.).
