# Roadmap — Écrivain

Suivi des fonctionnalités proposées. Statuts : `[ ]` à faire · `[~]` en cours · `[x]` terminé · `[-]` abandonné

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

### 9. Détection d'incohérences
**Statut** : `[x]`
**Description** : Analyse IA comparant le contenu des chapitres aux fiches personnages pour signaler les contradictions.
**Fichiers concernés** : `ai/controllers/AiController.php`, `characters/models/`
**Notes** :

---
