# Roadmap — Écrivain

Suivi des fonctionnalités proposées. Statuts : `[ ]` à faire · `[~]` en cours · `[x]` terminé · `[-]` abandonné

---

## Écriture & contenu

### 1. Mode focus (distraction-free)
**Statut** : `[x]`
**Description** : Plein écran sur l'éditeur Quill, sidebar masquée, compteur de mots en bas, timer Pomodoro optionnel.
**Fichiers concernés** : `chapter/views/edit.html`, `public/style.css`, `public/js/`
**Notes** :

---

### 2. Objectifs d'écriture
**Statut** : `[x]`
**Description** : Objectif quotidien/hebdomadaire de mots par projet. Barre de progression dans le dashboard. S'appuie sur le module `stats` existant.
**Fichiers concernés** : `stats/controllers/StatsController.php`, `project/views/`, DB migration
**Notes** :

---

### 3. Historique de versions par chapitre
**Statut** : `[x]`
**Description** : Snapshots manuels ou automatiques (ex : à chaque sauvegarde IA). Diff visuel entre deux versions.
**Fichiers concernés** : `chapter/controllers/ChapterController.php`, nouvelle table `chapter_versions`, migration
**Notes** :

---

### 4. Commentaires/annotations dans le texte
**Statut** : `[x]`
**Description** : Sélectionner un passage → ajouter une note interne non visible en lecture. Utile pour les rappels de relecture.
**Fichiers concernés** : `chapter/views/edit.html`, `quill-adapter.js`, nouvelle table `chapter_annotations`
**Notes** :

---

### 5. Timeline des événements
**Statut** : `[x]`
**Description** : Vue chronologique des scènes/actes avec glisser-déposer pour réorganiser la structure.
**Fichiers concernés** : nouveau module ou vue dans `acts/`, `section/`
**Notes** :

---

## Personnages & monde

### 6. Relations entre personnages
**Statut** : `[x]`
**Description** : Graphe visuel des liens entre personnages (famille, amour, rival…). Complémente le module `characters`.
**Fichiers concernés** : `characters/views/`, `characters/controllers/`, lib graphe (ex : vis.js ou D3 léger)
**Notes** :

---

### 7. Lexique du monde (worldbuilding)
**Statut** : `[x]`
**Description** : Glossaire de lieux, termes inventés, organisations. Liens automatiques dans le texte vers les définitions.
**Fichiers concernés** : nouveau module `glossary/`, intégration dans `chapter/views/edit.html`
**Notes** :

---

## IA & assistance

### 8. Résumé automatique de chapitre
**Statut** : `[x]`
**Description** : Bouton "Résumer ce chapitre" → envoi à l'IA → résumé stocké et visible dans la navigation.
**Fichiers concernés** : `ai/controllers/AiController.php`, `chapter/controllers/ChapterController.php`
**Notes** :

---

### 9. Détection d'incohérences
**Statut** : `[x]`
**Description** : Analyse IA comparant le contenu des chapitres aux fiches personnages pour signaler les contradictions.
**Fichiers concernés** : `ai/controllers/AiController.php`, `characters/models/`
**Notes** :

---

### 10. Suggestions de continuité
**Statut** : `[x]`
**Description** : "La dernière phrase du chapitre précédent était X, propose une ouverture pour ce chapitre."
**Fichiers concernés** : `ai/controllers/AiController.php`, `chapter/controllers/ChapterController.php`
**Notes** :

---

## Collaboration & partage

### 11. Mode relecture avec suggestions inline
**Statut** : `[ ]`
**Description** : Le collaborateur peut proposer des corrections inline (style Google Docs). Étend le module `collab` au-delà des `collaboration_requests`.
**Fichiers concernés** : `collab/`, `lecture/controllers/LectureController.php`, `quill-adapter.js`
**Notes** :

---

### 12. Export EPUB
**Statut** : `[ ]`
**Description** : Export EPUB avec couverture, métadonnées, table des matières auto-générée. Complète l'export existant.
**Fichiers concernés** : `project/controllers/ProjectExportController.php`, lib PHP EPUB (ex : `PHPePub`)
**Notes** :

---

## Productivité

### 13. Tableau de bord Kanban multi-projets
**Statut** : `[x]`
**Description** : Vue Kanban avec colonnes "En cours", "Relecture", "Terminé". Déplacer un projet change son statut.
**Fichiers concernés** : `project/controllers/ProjectController.php`, `project/views/`, champ `status` en DB
**Notes** :

---

### 14. Tags & catégories de projets
**Statut** : `[x]`
**Description** : Filtrer les projets par genre (fantasy, policier…), statut, date. Table pivot `project_tags`.
**Fichiers concernés** : `project/controllers/ProjectController.php`, migration, `project/views/`
**Notes** :

---

### 15. Notifications / rappels
**Statut** : `[ ]`
**Description** : Notification push PWA (manifest déjà présent) ou email si l'objectif du jour n'est pas atteint.
**Fichiers concernés** : `public/manifest.json`, `public/js/`, service worker, module `notifications/`
**Notes** :

---

## Priorisation suggérée

| Priorité | Fonctionnalité | Effort estimé |
|----------|---------------|---------------|
| 1 | Objectifs d'écriture (#2) | Faible — stats déjà en place |
| 2 | Historique de versions (#3) | Moyen |
| 3 | Export EPUB (#12) | Moyen |
| 4 | Résumé automatique IA (#8) | Faible — IA déjà intégrée |
| 5 | Mode focus (#1) | Faible — CSS/JS seulement |
| 6 | Relations personnages (#6) | Moyen |
| 7 | Tags & catégories (#14) | Faible |
| 8 | Annotations dans le texte (#4) | Moyen |
| 9 | Kanban multi-projets (#13) | Moyen |
| 10 | Suggestions de continuité IA (#10) | Faible — IA déjà intégrée |
| 11 | Lexique worldbuilding (#7) | Élevé |
| 12 | Timeline des événements (#5) | Élevé |
| 13 | Détection d'incohérences IA (#9) | Élevé |
| 14 | Relecture inline (#11) | Élevé |
| 15 | Notifications push (#15) | Élevé |
