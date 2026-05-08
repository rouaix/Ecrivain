# TODO — Écrivain

## 1. Analyse du code — Simplification & Optimisation

---

### 🟡 Priorité basse

#### CSS — Variables dupliquées / inutilisées
`--text-primary` (4 usages) et `--text-secondary` (6 usages) font doublon avec `--text-main` et `--text-muted`.
**Action** : Supprimer ou rediriger vers les variables canoniques dans `variables.css`.

#### CSS — `reset.css` et `base.css` définissent tous deux `body`
`reset.css` définit `font-family: Arial` et `font-size: 1em`, aussitôt écrasés par `base.css`.
**Action** : Fusionner `reset.css` dans `base.css` pour éviter la cascade confuse.


---

## 2. Nouvelles fonctionnalités

### ✍️ Écriture & Éditeur

**Import depuis Markdown ou Word (.docx)**
Permettre l'import d'un fichier `.md` ou `.docx` pour créer un chapitre. Utiliser `pandoc` côté serveur ou un parser PHP Markdown pur. Réduction de la friction d'onboarding pour les auteurs qui ont déjà du contenu.

---

### 🧠 Intelligence Artificielle

**Analyse de cohérence narrative**
Demander à l'IA de vérifier la cohérence entre chapitres : un personnage mort qui reparaît, une date contradictoire, un lieu mal décrit. Envoyer le synopsis + les chapitres clés comme contexte. Résultat sous forme de rapport d'alertes.

**Générateur de noms de personnages**
Depuis la fiche personnage, bouton « Suggérer un nom » → appel IA avec le contexte du roman (époque, univers, nationalité). Simple, rapide à implémenter via le système AI existant.


---

### 👥 Personnages & Univers

**Gestion des lieux**
Un module miroir de `characters` pour les décors/lieux. Chaque lieu a : nom, description, image, chapitres où il apparaît. Recherche de lieux dans les chapitres comme pour les personnages. Table `locations(project_id, name, description, image)`.

**Arc narratif des personnages**
Dans la fiche personnage, timeline visuelle montrant dans quels actes/chapitres le personnage est actif, avec une note d'évolution (début, pivot, fin). Visualisation SVG simple ou liste chronologique.

**Relations entre personnages (enrichissement)**
Le fichier `relations.html` existe. L'enrichir avec : type de relation (famille, allié, ennemi, amour), intensité, évolution dans le temps. Visualisation en graphe interactif (D3.js ou force-directed layout vanilla).

---

### 📊 Statistiques & Suivi

**Statistiques de lisibilité**
Sur la page chapitre ou dans le panneau stats : indice de lisibilité (longueur moyenne des phrases, ratio mots complexes, fréquence des dialogues). Calculé côté PHP depuis le texte brut. Aide à maintenir un style cohérent.

---

### 🛡️ Qualité & Confort

### 🔧 À améliorer

**Mobile — layout trois colonnes non adapté**
`theme-bibliotheque.css` n'a pas encore de breakpoints mobile. En dessous de 768px, les trois colonnes débordent. Définir un layout colonne unique avec navigation en tiroir ou onglets.

---

