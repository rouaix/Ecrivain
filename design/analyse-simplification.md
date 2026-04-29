# Analyse de complexité — Axes de simplification
Date : 2026-04-29

---

## Problème central

L'application a deux couches CSS qui se chevauchent et se contredisent :

1. **`style.css`** — 60+ fichiers importés, conçu pour une UI classique multi-thèmes
2. **`pro.css`** — 7 fichiers supplémentaires, conçu pour l'UI Pro

Depuis la migration vers le thème unique Bibliothèque, **tous les utilisateurs passent par `main-pro.html`**. L'UI classique (`main.html`) n'est plus utilisée en pratique. Pourtant, les deux couches CSS coexistent et créent des conflits de spécificité constants (c'est pourquoi `theme-bibliotheque.css` est rempli de `!important`).

---

## Inventaire chiffré

| Catégorie | Fichiers | Lignes |
|-----------|----------|--------|
| style.css (base) | 60 fichiers | ~12 500 lignes |
| pro/ | 7 fichiers | ~5 900 lignes |
| themes/ | 2 fichiers | ~1 577 lignes |
| **Total CSS** | **~66 fichiers** | **~18 400 lignes** |
| Views HTML | ~96 fichiers | — |
| Views projet (inc/) | ~30 fichiers | — |

---

## Problèmes identifiés par ordre de priorité

### 1. DEUX LAYOUTS HTML MAINTENUS POUR UN SEUL USAGE

**Situation actuelle :**
- `main.html` — layout classique (sidebar legacy, thèmes colorés)
- `main-pro.html` — layout Pro (topbar + sidebar Pro)
- `Controller.php` force `main-pro.html` pour tous les utilisateurs

**Problème :** `main.html` et tout ce qu'il implique (thèmes couleurs, toolbar classique, css modules legacy) est chargé dans `style.css` mais jamais utilisé en production. Il crée des règles CSS parasites que `pro.css` et `theme-bibliotheque.css` doivent neutraliser avec `!important`.

**Solution :** Fusionner en un seul layout. Supprimer `main.html`, renommer `main-pro.html` en `main.html`. Nettoyer `style.css` de tout ce qui n'est plus utilisé.

---

### 2. `theme-bibliotheque.css` EST UN PATCH, PAS UN THÈME

**Situation actuelle :** `theme-bibliotheque.css` (1 577 lignes) contient majoritairement :
- Des overrides de `pro.css` avec `!important` (ex: couleurs topbar, hover links)
- Des redéfinitions de composants déjà dans `pro-components.css`
- Des règles dupliquées entre la section "TOPBAR" du thème et `pro-nav.css`
- Une section "ANTI-PRO" qui neutralise les règles de `pro-features.css`

**Problème :** Ce fichier n'est pas un thème visuel — c'est un correctif de conflits. Son existence signale que `pro.css` et `style.css` contiennent des règles inadaptées au design actuel.

**Solution :** Corriger les valeurs directement dans `pro-nav.css`, `pro-layout.css`, `pro-components.css` pour correspondre au design Bibliothèque. `theme-bibliotheque.css` ne devrait contenir que les variables et les styles spécifiques au layout 3 colonnes (`tm-root`, `tm-left-rail`, etc.).

---

### 3. `pro-features.css` EST TROP VOLUMINEUX (2 279 lignes)

**Situation actuelle :** `pro-features.css` regroupe les styles des pages d'édition, IA, collab, et toutes les "features" en Pro. Deux problèmes :
- Il override des composants de base au lieu de les utiliser tels quels
- Il contient des styles pour des pages qui ont déjà leurs propres fichiers CSS dans `style.css` (ex: styles IA alors que `css/ai/` existe déjà)

**Solution :** Auditer `pro-features.css` pour identifier ce qui est réellement spécifique au contexte Pro vs. ce qui est une duplication. Supprimer les doublons.

---

### 4. FRAGMENTATION EXCESSIVE DU CSS DE BASE

**Situation actuelle :** `style.css` importe 60 fichiers dont beaucoup sont minuscules :
- `css/components/search.css` : 6 lignes
- `css/layout/footer.css` : 8 lignes
- `css/layout/grid.css` : 10 lignes
- `css/editor/editor-tools.css` : 5 lignes
- `css/modules/sections.css` : 20 lignes

**Problème :** La fragmentation ne facilite pas la maintenance — elle la complique. Trouver où overrider un style impose de chercher dans 60 fichiers.

**Solution :** Fusionner par catégorie fonctionnelle. Cible raisonnable : ~15 fichiers CSS au lieu de 60.

---

### 5. MODULE PROJECT — 30 FICHIERS INCLUDES IMBRIQUÉS

**Situation actuelle :**
```
show.html
  → layout-bibliotheque.html
      → _layout-workspace-nav.html
      → _layout-sections-nav.html
      → new_body.html
          → _project_overview_panel_css.html  ← CSS inline !
          → _project_overview_collab_banner.html
          → _project_overview_topbar.html
          → _project_overview_sections_before.html
          → _project_overview_content.html
          → _project_overview_custom_elements.html
          → _project_overview_sections_after.html
          → _project_overview_scenarios.html
          → _project_overview_notes.html
          → _project_overview_files.html
          → _project_overview_aside.html  ← masqué par CSS !
          → _project_overview_modals.html
      → _layout-right-rail.html
```

**Problèmes :**
- `_project_overview_panel_css.html` génère du **CSS inline** dans le `<body>` — une anomalie grave
- `_project_overview_aside.html` est inclus mais masqué via CSS (`display: none !important`) — code mort
- `header.html` (avec `.project-page.professional-show`) est inclus dans `new_body.html` mais masqué via CSS dans `theme-bibliotheque.css`
- Plusieurs panneaux (`acts_chapters_panel.html`, `characters_panel.html`, etc.) font doublon avec les données déjà dans `layout-bibliotheque.html`

**Solution :**
- Supprimer `_project_overview_aside.html` et `header.html` (morts depuis la migration Bibliothèque)
- Déplacer le CSS de `_project_overview_panel_css.html` dans un vrai fichier CSS
- Simplifier la chaîne d'includes : viser 5-6 includes au lieu de 15+

---

### 6. CSS MEDIA QUERIES MAL ORGANISÉS

**Situation actuelle :**
- `css/core/media-queries.css` : 138 lignes (tablette/desktop ≥481px)
- `css/core/mq-mobile.css` : **1 383 lignes** (mobile ≤480px, ≤767px, ≤380px)

**Problème :** Les media queries sont regroupées en fin de `style.css` au lieu d'être colocalisées avec les composants qu'elles modifient. `mq-mobile.css` est une accumulation de surcharges difficile à maintenir.

**Solution :** Intégrer les media queries dans chaque fichier de composant (la règle responsive de `.btn` va dans `buttons.css`, etc.). Supprimer les deux fichiers mq séparés.

---

## Plan de simplification recommandé

### Phase A — Nettoyage immédiat (sans casser quoi que ce soit)

1. **Supprimer les fichiers HTML morts** :
   - `inc/_project_overview_aside.html` (masqué par CSS)
   - `inc/header.html` (masqué par CSS depuis la migration)
   - `inc/body.html` et `inc/dynamic_body.html` (anciens, remplacés par `new_body.html`)
   - Les 4 layouts supprimés de la branche mais potentiellement référencés ailleurs

2. **Sortir le CSS inline** de `_project_overview_panel_css.html` vers un vrai fichier CSS

3. **Supprimer les imports inutiles** dans `style.css` :
   - `theme-selector.css` (déjà supprimé)
   - `layout-picker.css` (déjà supprimé)
   - Vérifier si `css/features/landing.css` est encore utilisé
   - Vérifier si `css/auth/tokens.css` est encore utilisé

### Phase B — Fusion des couches CSS

Objectif : une seule couche CSS au lieu de deux (`style.css` + `pro.css` + `theme-bibliotheque.css`)

1. **Migrer les valeurs Pro directement dans les variables** (`variables.css`) pour les couleurs, fonts, rayons déjà identiques
2. **Fusionner `pro-layout.css` + `pro-nav.css` dans `layout/`** puisqu'il n'y a plus qu'un seul layout
3. **Réduire `theme-bibliotheque.css`** à ses seuls styles spécifiques (`.tm-root`, `.tm-left-rail`, `.tm-center`, `.tm-right-rail`, composants tb-*)
4. **Nettoyer `pro-features.css`** des doublons avec `css/ai/`, `css/auth/`, `css/modules/`

### Phase C — Architecture cible

```
style.css
  ├── core/variables.css       (palette + typo + tokens)
  ├── core/reset.css
  ├── core/base.css
  ├── layout/shell.css         (topbar + sidebar + content — fusion pro-layout + layout/)
  ├── layout/nav.css           (sidebar items, topbar elements — fusion pro-nav + header)
  ├── components/              (buttons, forms, modals, tables, cards...)
  ├── modules/                 (project, chapters, characters, notes... — nettoyés)
  ├── editor/                  (Quill)
  ├── features/                (reading, mindmap, relecture, export...)
  ├── project-overview.css     (tm-root 3 colonnes — l'essentiel de theme-bibliotheque)
  └── utilities/
```

Cible : ~20 fichiers CSS, ~10 000 lignes (vs 66 fichiers / 18 400 lignes actuellement).

---

## Résumé des gains attendus

| Problème | Avant | Après |
|----------|-------|-------|
| Fichiers CSS | 66 | ~20 |
| Lignes CSS | 18 400 | ~10 000 |
| Couches CSS conflictuelles | 3 (style + pro + theme) | 1 |
| Usage de `!important` | ~180 occurrences | < 20 (légitimes) |
| Includes projet (show) | 15+ | ~6 |
| Fichiers HTML morts | ~6 | 0 |

---

## Risques

- **Régression visuelle** sur les pages peu testées (scenariste, glossaire, timeline)
- **Breakage JS** si des classes supprimées sont référencées en JS (vérifier `querySelectorAll` dans les views)
- **Partage public** (`/s/@token/...`) a son propre layout sans `pro.css` — à tester séparément après chaque changement CSS de base
