# Plan de refonte UI — Interface Professionnelle

> **Principe directeur** : L'interface actuelle reste intacte et fonctionnelle. Une nouvelle interface "Pro" est construite en parallèle. L'utilisateur switche entre les deux via un bouton persistant. Toutes les fonctionnalités sont conservées — seule leur présentation change.

---

## Table des matières

1. [Philosophie de design](#1-philosophie-de-design)
2. [Architecture technique](#2-architecture-technique)
3. [Système de switch](#3-système-de-switch)
4. [Design system Pro](#4-design-system-pro)
5. [Nouveau chrome — Header & Navigation](#5-nouveau-chrome--header--navigation)
6. [Refonte page par page](#6-refonte-page-par-page)
7. [Composants redessinés](#7-composants-redessinés)
8. [Responsive & Mobile](#8-responsive--mobile)
9. [Ordre d'implémentation](#9-ordre-dimplémentation)

---

## 1. Philosophie de design

### Principe : "App, pas site"

L'interface actuelle est construite comme un **site web** (header horizontal, pages qui se rechargent, contenu centré). L'interface Pro se comporte comme une **application métier** :

- Navigation latérale fixe (sidebar) — toujours visible, jamais cachée dans un menu
- Zone de contenu à droite — scrollable, sans la distraction du chrome
- Densité d'information contrôlée — chaque pixel justifié
- Feedback immédiat sur chaque action

### Ce qui change

| Aspect | Interface actuelle | Interface Pro |
|--------|-------------------|---------------|
| Navigation | Barre horizontale en haut | Sidebar verticale fixe à gauche |
| Header projet | Hero avec image, stats larges | Bandeau compact, onglets inline |
| Panneaux projet | Grille de cartes | Liste triable avec expand/collapse |
| Modales | Overlay centré | Panneau latéral droit (slide-in) |
| Boutons d'action | Dispersés dans les vues | Regroupés dans une barre d'actions contextuelle |
| Formulaires | Pleine largeur, visuellement lourds | Colonnes compactes, labels inline |

### Ce qui ne change pas

- Toutes les routes PHP (mêmes URLs)
- Toute la logique JavaScript (même code)
- Toutes les fonctionnalités
- Les tokens de couleurs CSS (mêmes variables)
- Le mécanisme des thèmes (les 5 thèmes restent compatibles)

---

## 2. Architecture technique

### Fichiers à créer (ne touche à rien d'existant)

```
src/
├── app/
│   ├── modules/
│   │   └── project/
│   │       └── views/
│   │           └── layouts/
│   │               └── main-pro.html          ← nouveau layout shell
│   └── controllers/
│       └── Controller.php                     ← modifier render() uniquement (2 lignes)
│
└── public/
    ├── css/
    │   └── pro/
    │       ├── pro-layout.css                 ← sidebar, topbar, content area
    │       ├── pro-nav.css                    ← navigation latérale
    │       ├── pro-project.css                ← vue projet en mode pro
    │       ├── pro-editor.css                 ← éditeur en mode pro
    │       ├── pro-forms.css                  ← formulaires compacts
    │       ├── pro-components.css             ← boutons, badges, tables pro
    │       ├── pro-dashboard.css              ← dashboard
    │       └── pro-overrides.css              ← reset des styles "classiques" dans le contexte pro
    └── js/
        └── pro-ui.js                          ← comportements spécifiques Pro (sidebar toggle, etc.)
```

### Principe d'isolation CSS

Tout le CSS Pro est préfixé par `body.ui-pro`. Ainsi :

```css
/* N'affecte QUE l'interface Pro */
body.ui-pro .app-shell { display: flex; }
body.ui-pro .sidebar   { width: 240px; }

/* Le CSS classique (header, nav, etc.) est neutralisé dans le contexte pro */
body.ui-pro header { display: none; }
```

Les thèmes existants (`body.theme-dark`, etc.) restent pleinement compatibles car ils agissent sur les variables CSS — indépendant du layout.

### Modification minimale du Controller

Dans `src/app/controllers/Controller.php`, la méthode `render()` lit le cookie `ui_mode` et sélectionne le layout :

```php
protected function render(string $view, array $data = []): void
{
    $layout = (($_COOKIE['ui_mode'] ?? 'classic') === 'pro')
        ? 'project/views/layouts/main-pro.html'
        : 'project/views/layouts/main.html';
    // ... reste identique
}
```

Deux lignes. Aucun autre fichier PHP modifié.

---

## 3. Système de switch

### Stockage

Cookie HTTP `ui_mode` avec valeurs `classic` ou `pro`. Durée : 1 an. Même mécanique que le cookie `theme`.

### Route PHP à créer

```
POST /ui-mode   →  UiModeController->switch
```

Lit `$_POST['mode']`, pose le cookie, redirige vers la page courante (`$_SERVER['HTTP_REFERER']`).

### Bouton de switch

Présent **dans les deux interfaces** pour pouvoir revenir à tout moment.

**Dans l'interface classique** (ajout dans `main.html`) :
```html
<!-- Dans la nav, à côté du sélecteur de thème -->
<form action="/ui-mode" method="POST" style="display:inline">
    <input type="hidden" name="csrf_token" value="{{ @csrfToken }}">
    <input type="hidden" name="mode" value="pro">
    <button type="submit" class="button small" title="Passer à l'interface Pro">
        <i class="fas fa-th-large"></i> Interface Pro
    </button>
</form>
```

**Dans l'interface Pro** (dans la sidebar) :
```html
<form action="/ui-mode" method="POST">
    <input type="hidden" name="csrf_token" value="{{ @csrfToken }}">
    <input type="hidden" name="mode" value="classic">
    <button type="submit" class="pro-nav-item" title="Revenir à l'interface classique">
        <i class="fas fa-undo"></i> Interface classique
    </button>
</form>
```

---

## 4. Design system Pro

### Typographie

| Rôle | Taille | Graisee | Usage |
|------|--------|---------|-------|
| Titre de page | 18px | 600 | `<h1>` dans le topbar |
| Section header | 13px | 600 | Titres groupes sidebar, labels sections |
| Corps | 14px | 400 | Texte courant |
| Secondaire | 13px | 400 | Métadonnées, dates, compteurs |
| Micro | 11px | 500 | Badges, étiquettes |

Police : héritée du thème actif. Pas de nouvelle police.

### Espacements (8px grid)

```
--pro-space-1: 4px
--pro-space-2: 8px
--pro-space-3: 12px
--pro-space-4: 16px
--pro-space-5: 24px
--pro-space-6: 32px
```

### Couleurs Pro (héritées des variables existantes)

Aucune nouvelle couleur. Le Pro utilise exclusivement les tokens existants :
- `var(--header-bg)` → couleur principale (sidebar fond si thème sombre, bordures si clair)
- `var(--body-bg)` → fond de la zone content
- `var(--card-bg)` → fond des panneaux, tableaux
- `var(--text-main)` / `var(--text-muted)` → textes
- `var(--button-primary-bg)` → CTA principaux

### Élévation et séparation

Dans l'interface Pro, l'espace est structuré par des **bordures fines** plutôt que par des ombres et marges larges :

```css
/* Pro : séparation par bordure */
body.ui-pro .pro-panel { border: 1px solid var(--input-border); border-radius: var(--radius-md); }

/* Classique : séparation par ombre */
.card { box-shadow: var(--shadow-md); }
```

---

## 5. Nouveau chrome — Header & Navigation

### Anatomie du layout Pro

```
┌─────────────────────────────────────────────────────────┐
│  TOPBAR  [Logo] [Fil d'ariane]          [Search][Avatar] │  48px fixe
├──────────┬──────────────────────────────────────────────┤
│          │                                              │
│ SIDEBAR  │           CONTENT AREA                       │
│  240px   │           (scrollable)                       │
│  fixe    │                                              │
│          │                                              │
└──────────┴──────────────────────────────────────────────┘
```

### Topbar (48px, position: sticky top: 0)

Contenu de gauche à droite :
1. **Logo / nom de l'app** — clique → dashboard
2. **Fil d'ariane contextuel** — auto-généré selon la route courante
   - Ex : `Mes projets > Mon Roman > Chapitre 3`
3. **Espace libre**
4. **Icône recherche** — ouvre le même overlay Ctrl+K
5. **Icône IA** — ouvre la même modale IA
6. **Badge collaborations** — même logique que l'actuel
7. **Avatar + menu utilisateur** (dropdown) — profil, thème, switch UI, déconnexion

### Sidebar (240px, position: fixed, hauteur 100vh)

```
┌──────────────────────────┐
│  ← [Retour projets]      │  (si dans un projet)
├──────────────────────────┤
│  NAVIGATION GLOBALE      │
│  ▸ Tableau de bord       │
│  ▸ Mes projets           │
│  ▸ Statistiques          │
│  ▸ Partages              │
│  ▸ Templates             │
│  ▸ Configuration IA      │
├──────────────────────────┤
│  PROJET COURANT          │  (visible seulement dans un projet)
│  ▸ Vue d'ensemble        │
│  ▸ Synopsis              │  (si template actif l'inclut)
│  ▸ Structure             │  (actes / chapitres)
│  ▸ Personnages           │
│  ▸ Notes                 │
│  ▸ Glossaire             │
│  ▸ Scénario              │  (si template actif)
│  ▸ Sections              │
│  ▸ Fichiers              │
│  ─────────────────────── │
│  ▸ Collaborateurs        │
│  ▸ Partage               │
│  ▸ Activité              │
│  ▸ Exporter ▾            │
├──────────────────────────┤
│  [⚙ Profil]              │
│  [◑ Thème : Défaut ▾]    │
│  [⇄ Interface classique] │
└──────────────────────────┘
```

**Comportement** :
- Item actif : fond `var(--button-primary-bg)` à 10% d'opacité + bordure gauche colorée
- Hover : fond léger `var(--table-row-hover-bg)`
- Section "Projet courant" n'apparaît que si `@project` est défini dans le contexte
- Les items conditionnels (synopsis, scénario) vérifient `@panelConfig`

### Topbar — fil d'ariane

Généré automatiquement dans `main-pro.html` à partir des variables F3 disponibles (`@project`, `@chapter`, `@act`, etc.) :

```html
<nav class="pro-breadcrumb">
    <a href="{{ @base }}/dashboard">Projets</a>
    <check if="{{ isset(@project) }}">
        <span>›</span>
        <a href="{{ @base }}/project/{{ @project.id }}">{{ @project.title }}</a>
    </check>
    <check if="{{ isset(@chapter) }}">
        <span>›</span>
        <span>{{ @chapter.title }}</span>
    </check>
</nav>
```

---

## 6. Refonte page par page

### 6.1 Dashboard (`/dashboard`)

**Actuel** : Sélecteur de thème + liste de projets en grille de cartes

**Pro** :
- La grille de projets devient un **tableau compact** : titre, template, date, progression, statut — une ligne par projet
- Bouton "Nouveau projet" dans la topbar (barre d'actions)
- Sélecteur de thème déplacé dans le menu utilisateur (topbar)
- Carte "Statistiques rapides" en haut : total mots, projets actifs, dernière session (3 métriques en ligne)

```
┌──────────────────────────────────────────────────────┐
│ [+ Nouveau projet]          [🔍 Filtrer] [⚙ Trier]  │
├──────────────────────────────────────────────────────┤
│ Titre              │ Template │ Mots   │ Modifié      │
├──────────────────────────────────────────────────────┤
│ ▸ Mon Roman        │ Roman    │ 42 350 │ Il y a 2h    │
│ ▸ Le Scénario      │ Scénario │  8 200 │ Hier         │
│ ▸ Ma Nouvelle      │ Nouvelle │  3 100 │ Il y a 5j    │
└──────────────────────────────────────────────────────┘
```

### 6.2 Page projet (`/project/{id}`)

**Actuel** : Hero avec couverture + panneaux en accordéon

**Pro** :
- Le hero est remplacé par un **bandeau compact** (titre + template + progression inline)
- Les panneaux (actes/chapitres, personnages, notes...) sont des **sections dans la même page**
- Structure en deux colonnes : contenu principal (70%) + sidebar contextuelle (30%)
- La sidebar contextuelle affiche : résumé du projet, derniers chapitres édités, accès rapide synopsis

```
┌──────────────────────────┬─────────────────────┐
│ STRUCTURE                │ INFOS PROJET         │
│                          │ ─────────────────── │
│ ▸ Acte 1 (3 chap.)       │ Mots : 42 350        │
│   ├ Chapitre 1  —  2h ago │ Pages : ~141         │
│   ├ Chapitre 2           │                      │
│   └ Chapitre 3           │ Dernier edit :        │
│ ▸ Acte 2 (2 chap.)       │ Chapitre 3           │
│                          │ Aujourd'hui 14h      │
│ ─────────────────────── │                      │
│ PERSONNAGES (4)          │ ─────────────────── │
│ NOTES (7)                │ Synopsis             │
│ GLOSSAIRE                │ [Voir →]             │
└──────────────────────────┴─────────────────────┘
```

### 6.3 Éditeur de chapitre (`/project/{id}/chapter/{cid}/edit`)

**Actuel** : Éditeur pleine largeur avec toolbar Quill au-dessus

**Pro** :
- Layout "focus" : sidebar réduite (icônes seules, 56px) sauf survol
- Topbar affiche : titre chapitre éditable inline + compteur mots + statut "Sauvegardé"
- Quill occupe 100% de la zone content
- Panneau droit escamotable : résumé, notes liées, IA inline
- Barre d'état basse : progression chapitre / objectif de mots

```
┌──────────────────────────────────────────────────────┐
│ [☰] Chapitre 3 : Le Carrefour  ←[éditable]  💾 42s │
├────┬─────────────────────────────────────┬───────────┤
│    │                                     │  Résumé   │
│ ⊞  │         ÉDITEUR QUILL               │  ─────    │
│ ⊠  │                                     │  Notes    │
│ ⊟  │                                     │  liées    │
│    │                                     │  ─────    │
│    │                                     │  [IA ▸]   │
├────┴─────────────────────────────────────┴───────────┤
│ 1 243 mots · Objectif : 2 000 · ████░░░░ 62%        │
└──────────────────────────────────────────────────────┘
```

### 6.4 Synopsis (`/project/{id}/synopsis`)

**Actuel** : Formulaire vertical long

**Pro** :
- Deux colonnes : métadonnées (gauche, 30%) + contenu narratif (droite, 70%)
- Les champs de structure narrative sont présentés comme une **frise timeline** dépliable
- Logline toujours visible en haut (c'est l'information la plus importante)
- Statut et méthode : sélecteurs compacts dans l'en-tête de section

```
┌─────────────────┬────────────────────────────────────┐
│ MÉTADONNÉES     │ LOGLINE                            │
│                 │ ───────────────────────────────── │
│ Genre :         │ [Quand X se produit, Y doit Z     │
│ Public :        │  pour W, mais...]                  │
│ Ton :           │                                   │
│ Thèmes :        │ PITCH                             │
│ Statut :        │ [Éditeur Quill compact]           │
│ Structure :     │                                   │
│                 │ STRUCTURE ──────[Freytag ▾]──     │
│                 │ ▸ Situation initiale              │
│                 │ ▸ Élément déclencheur             │
│                 │ ▸ Point tournant 1                │
│                 │   ...                             │
└─────────────────┴────────────────────────────────────┘
```

### 6.5 Personnages (`/project/{id}/characters`)

**Actuel** : Grille de cartes avec portrait

**Pro** :
- Table avec colonnes : Portrait miniature (32px), Nom, Rôle, Traits (chips), Actions
- Clic sur une ligne → panneau droit slide-in avec la fiche complète (pas de nouvelle page)
- Bouton "Graphe des relations" persistant en haut de la liste

### 6.6 Notes (`/project/{id}/notes`)

**Actuel** : Liste de cartes

**Pro** :
- Layout deux colonnes : liste compacte (gauche) + aperçu/édition (droite)
- Même logique que l'éditeur de fichiers : sélectionner = afficher à droite
- Tags visuels (couleurs) sur chaque note dans la liste

### 6.7 Collaboration

**Actuel** : Pages séparées pour invitations et demandes

**Pro** :
- Toutes les actions collab dans un seul panneau (onglets : Invitations / Demandes / Historique)
- Badge dans la sidebar (chiffre rouge) plutôt que dans la topbar

### 6.8 Templates (`/templates`)

**Actuel** : Liste de cartes

**Pro** :
- Table : Nom, Type (système/perso), Éléments actifs, Projets utilisant, Actions
- Édition inline du nom (double-clic)
- La page d'édition d'un template utilise un **éditeur drag-and-drop visuel** (les éléments sont des blocs déplaçables)

### 6.9 Configuration IA (`/ai/config`)

**Actuel** : Formulaire unique

**Pro** :
- Onglets par fournisseur (OpenAI / Gemini / Anthropic / Mistral)
- Chaque onglet : clé API + sélection modèle + test de connexion inline
- Statut de connexion visible (✓ Connecté / ✗ Clé invalide)

### 6.10 Statistiques (`/stats`)

**Actuel** : Graphiques

**Pro** :
- Dashboard compact : 4 métriques KPI en ligne (total mots, sessions, moyenne/jour, meilleur jour)
- Graphique d'activité sous les métriques
- Filtre par projet dans la topbar

### 6.11 Formulaires de création/édition (projet, personnage, acte...)

**Actuel** : Page dédiée centrée

**Pro** :
- Panneau slide-in depuis la droite (pas de nouvelle page) pour les créations simples
- Pages dédiées uniquement pour les formulaires longs (édition projet avancée)
- Tous les champs utilisent des labels flottants (placeholder qui monte au focus)

### 6.12 Page d'accueil publique (`/`)

**Actuel** : Landing marketing

**Pro** : Inchangée — la landing ne fait pas partie de l'interface applicative.

### 6.13 Pages publiques (`/s/{token}/...`)

**Actuel** : Layout standalone (share)

**Pro** : Inchangées — elles ne font pas partie de l'interface authentifiée.

### 6.14 Mode lecture (`/project/{id}/lecture`)

**Actuel** : Mise en page typographique

**Pro** : Inchangé — le mode lecture est déjà une interface dédiée, intentionnellement différente.

---

## 7. Composants redessinés

### Boutons Pro

```css
/* Moins de padding, coins moins arrondis, poids réduit */
body.ui-pro .button {
    padding: 6px 12px;
    font-size: 13px;
    border-radius: var(--radius-sm);
    font-weight: 500;
}

/* Bouton ghost (le plus courant dans le Pro) */
body.ui-pro .button.ghost {
    background: transparent;
    border: 1px solid var(--input-border);
    color: var(--text-main);
}
```

### Tables Pro

Toutes les listes de données (projets, personnages, notes...) deviennent des tables :
- En-têtes cliquables pour trier
- Lignes sélectionnables
- Actions sur la ligne (hover → boutons apparaissent)
- Densité : `padding: 8px 12px` (vs 16px+ actuel)

### Badges / Statuts

```
[● En cours]  [● Premier jet]  [● Révisé]  [✓ Prêt]
```
Points colorés + texte. Réutilise `var(--color-success)`, `var(--color-warning)`, etc.

### Modales → Panneaux latéraux

Les modales (création d'acte, de personnage, d'élément) deviennent des **panneaux qui glissent depuis la droite** (slide-in panel, width: 480px) :

```css
body.ui-pro .slide-panel {
    position: fixed;
    top: 48px; /* sous le topbar */
    right: 0;
    width: 480px;
    height: calc(100vh - 48px);
    background: var(--card-bg);
    border-left: 1px solid var(--input-border);
    transform: translateX(100%);
    transition: transform 0.2s ease;
}
body.ui-pro .slide-panel.is-open {
    transform: translateX(0);
}
```

Les modales classiques (confirmations de suppression, export) restent des modales centrées.

---

## 8. Responsive & Mobile

### Breakpoints Pro

| Écran | Sidebar | Topbar | Contenu |
|-------|---------|--------|---------|
| ≥ 1200px | 240px fixe visible | Plein | 100% - 240px |
| 900–1200px | 200px fixe visible | Plein | 100% - 200px |
| 640–900px | Icônes seules (56px) | Compact | 100% - 56px |
| < 640px | Cachée (overlay via bouton ☰) | ☰ + titre | 100% |

### Mobile (< 640px)

- Sidebar accessible via bouton hamburger → overlay plein écran
- Slide panels passent en bottom sheets (glissent depuis le bas, hauteur 80vh)
- Tables → format carte empilé (chaque ligne devient une carte)
- Topbar : logo + hamburger + icône IA uniquement

---

## 9. Ordre d'implémentation

### Phase 1 — Infrastructure (prérequis)

1. Créer la route `POST /ui-mode` + `UiModeController`
2. Modifier `Controller::render()` pour lire le cookie (2 lignes)
3. Créer `layouts/main-pro.html` (shell de base, sans contenu spécifique)
4. Créer `pro/pro-layout.css` + `pro/pro-nav.css`
5. Ajouter le bouton switch dans `main.html` (interface classique)

**Livrable** : On peut switcher → l'interface pro affiche le même contenu dans le nouveau shell (sidebar + topbar).

### Phase 2 — Navigation & Chrome

6. Sidebar complète avec tous les items et états actifs
7. Topbar : fil d'ariane, recherche, menu utilisateur
8. Menu utilisateur : thème, switch UI, profil, déconnexion
9. `pro-components.css` : boutons, badges, formulaires de base

**Livrable** : Navigation fluide entre toutes les pages dans le nouveau shell.

### Phase 3 — Pages clés (haute valeur)

10. Dashboard Pro (table de projets)
11. Page projet Pro (layout deux colonnes)
12. Éditeur chapitre Pro (focus mode)
13. Synopsis Pro (deux colonnes)

**Livrable** : Le cœur de l'application est utilisable en mode Pro.

### Phase 4 — Pages secondaires

14. Personnages Pro (table + slide panel)
15. Notes Pro (liste + aperçu)
16. Templates Pro (table + édition)
17. Config IA Pro (onglets)
18. Statistiques Pro
19. Collaboration Pro

### Phase 5 — Finitions

20. Transitions et micro-animations
21. Responsive mobile complet
22. Tests de cohérence avec les 5 thèmes
23. `pro-overrides.css` — neutralisation des derniers résidus de l'interface classique

---

## Estimation des fichiers

| Catégorie | Fichiers à créer | Fichiers modifiés |
|-----------|-----------------|-------------------|
| Layout PHP | 1 (`main-pro.html`) | 1 (`Controller.php` — 2 lignes) |
| Routes/Controllers | 1 (`UiModeController.php`) | 1 (`config.ini` — 2 lignes) |
| CSS Pro | ~9 fichiers | 0 |
| JS Pro | 1 (`pro-ui.js`) | 0 |
| Vues Pro spécifiques | ~8 vues (pages clés) | 0 |
| Interface classique | 0 | 1 (`main.html` — bouton switch) |

**Total : ~20 nouveaux fichiers, 3 modifications mineures dans l'existant.**

---

*Plan rédigé le 2026-03-19. À mettre à jour au fil de l'implémentation.*
