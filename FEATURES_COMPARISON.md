# Comparaison des fonctionnalités : Interface Classique vs Interface Pro

> Généré le 2026-04-13 — Analyse complète : vues (`views/**/*.html`), layouts, CSS Pro (`pro-overrides.css`, `pro-components.css`) et contrôleur base.

---

## Résumé exécutif

Les deux interfaces utilisent les **mêmes vues** (`views/*.html`) — seul le layout change (`main.html` vs `main-pro.html`).  
Cependant, le **CSS Pro masque ou remplace activement des éléments** présents dans les vues partagées, créant des différences fonctionnelles réelles.

**Mode par défaut** : Pro (cookie `ui_mode`, valeur par défaut = `pro`)

---

## ⚠️ Fonctionnalités PRÉSENTES en Classique et MANQUANTES en Pro

Ces éléments existent dans les vues HTML mais sont **masqués par `display: none` dans le CSS Pro**.

### Dans l'éditeur de chapitre (`body.ui-pro.editor-mode`)

Fichier CSS : `src/public/css/pro/pro-overrides.css` — section 17

| Fonctionnalité | Élément masqué | Impact |
|---|---|---|
| **Changer l'acte parent** | `select#act_id` (dans `.editor-grid-row`) | ❌ Impossible de changer l'acte pendant l'édition en Pro |
| **Changer le chapitre parent** (sous-chapitre) | `select#parent_id` (dans `.editor-grid-row`) | ❌ Impossible de changer le parent pendant l'édition en Pro |
| **Termes du glossaire détectés** | `#glossaryFoundSection` | ❌ Non visible en Pro (section entière cachée) |
| **Annotations / commentaires inline** | `#annotationsSection` | ❌ Non visible en Pro (section entière cachée) |
| **Compteur de mots inline** (dans le label) | `.word-count.inline` | ❌ Masqué en Pro (supposément remplacé par une status bar non implémentée) |

> **Note** : La règle CSS est `body.ui-pro.editor-mode .editor-grid-row { display: none; }`.  
> Le commentaire dans le CSS dit "accessible via sidebar" mais la sidebar ne propose pas de champ pour modifier l'acte ou le parent.

### Sur la page projet (Vue d'ensemble)

Fichier CSS : `src/public/css/pro/pro-components.css` — section PHASE 2

| Fonctionnalité | Élément masqué | Remplacé par en Pro |
|---|---|---|
| **Hero projet complet** (`.project-hero`) | Section entière : titre, description, couverture, actions, métriques | En-tête compact (`.pro-dash-header` area) + sidebar |
| **Métriques détaillées** : Objectif (pages/mots cible), Progression (%), Ressources (notes + personnages + sections avant/après) | `.project-hero__metrics` (dans `.project-hero`) | ⚠️ Remplacé partiellement par `.pro-project-progression` (pages actuelles, %, mots uniquement — moins de données) |
| **Boutons d'action du hero** | `.project-hero__actions` (dans `.project-hero`) | ✅ Compensé par la sidebar (Mindmap, Lecture, Relecture, Export, Collaborateurs, Activité) |

### Sur le tableau de bord (liste des projets)

Fichier CSS : `src/public/css/pro/pro-components.css`

| Fonctionnalité | Élément masqué | Impact |
|---|---|---|
| **Stats des cartes projet** | `.project-card__stats` (mots, progression) | ❌ Ces stats (objectif en mots, etc.) n'apparaissent pas sur les cartes en Pro |

### Dans les pages globales

| Fonctionnalité | Élément masqué | Remplacé par en Pro |
|---|---|---|
| **Sélecteur de thème** (dans le dashboard) | `.theme-selector` et `.theme-selector-widget` | ✅ Remplacé par le user dropdown en topbar |
| **Breadcrumb classique** | `.breadcrumb` | ✅ Remplacé par le breadcrumb Pro en topbar |
| **Toolbar de navigation** | `.navigation-toolbar`, `.dashboard-toolbar` | ✅ Remplacé par la sidebar |
| **En-tête dashboard classique** | `.dashboard-header` | ✅ Remplacé par `.pro-dash-header` |

---

## Fonctionnalités EXCLUSIVES au mode Pro

### Navigation & accès contextuel (depuis toutes les pages d'un projet)

En mode classique, ces liens ne sont disponibles que sur la page de vue d'ensemble (`/project/{id}`). La sidebar Pro les expose **en permanence** :

| Fonctionnalité | Classique | Pro |
|---|---|---|
| Carte mentale | Depuis la page projet uniquement | ✅ Sidebar permanente |
| Mode lecture | Depuis la page projet uniquement | ✅ Sidebar permanente |
| Mode relecture | Depuis la page projet uniquement | ✅ Sidebar permanente |
| Collaborateurs (propriétaire) | Depuis la page projet uniquement | ✅ Sidebar permanente |
| Journal d'activité (propriétaire) | Depuis la page projet uniquement | ✅ Sidebar permanente |
| Modules de contenu du template | Depuis la page projet uniquement | ✅ Sidebar dynamique |
| **Modal d'export** | Depuis la page projet uniquement | ✅ Depuis toutes les pages via sidebar |

### Fonctionnalité IA supplémentaire

| Fonctionnalité | Classique | Pro |
|---|---|---|
| **Assistant Narratif IA global** (`proAiAssistantModal`) | Accessible uniquement depuis l'éditeur de chapitre | ✅ Depuis n'importe quelle page projet via la sidebar |

### UX / Interface

| Fonctionnalité | Classique | Pro |
|---|---|---|
| Sidebar persistante avec navigation contextuelle projet | ❌ | ✅ |
| Breadcrumb en topbar (Projets › Projet › Chapitre) | ❌ | ✅ |
| User menu dropdown (avatar, profil, config IA, thème, déconnexion) | ❌ | ✅ |
| Sélecteur de thème accessible depuis partout | Dashboard uniquement | ✅ User dropdown |
| Flash messages layout (success/error bandeaux) | ❌ | ✅ |
| Slide panels (`data-panel-open`) | ❌ | ✅ |
| Skip link accessibilité clavier | ❌ | ✅ |
| Mobile sidebar toggle (hamburger) | ❌ | ✅ |
| Header éditeur sticky sous la topbar | ❌ | ✅ (`position: sticky; top: 48px`) |

---

## Fonctionnalités identiques dans les deux interfaces

Toutes les pages/fonctionnalités ci-dessous sont accessibles et fonctionnelles dans les deux modes.

### Pages / modules accessibles

| Module | Pages | Classique | Pro |
|---|---|---|---|
| **Dashboard** | Liste des projets, création | ✅ | ✅ |
| **Projet** | Vue d'ensemble, fichiers, mindmap, activité | ✅ | ✅ |
| **Chapitres** | Liste, créer, éditeur complet, historique versions | ✅ | ✅ |
| **Actes** | Liste, créer, modifier, timeline | ✅ | ✅ |
| **Sections** | Créer, modifier (avant/après) | ✅ | ✅ |
| **Personnages** | Liste, créer, modifier, graphe relations | ✅ | ✅ |
| **Notes** | Liste, créer, modifier | ✅ | ✅ |
| **Éléments personnalisés** | Liste, créer, modifier | ✅ | ✅ |
| **Synopsis** | Éditer, exporter | ✅ | ✅ (via sidebar si template l'active) |
| **Glossaire** | Index, modifier | ✅ | ✅ |
| **Scénariste** | Liste, nouveau, modifier | ✅ | ✅ |
| **Mode lecture** | Lecture, rapport | ✅ | ✅ |
| **Mode relecture** | Revue, rapport | ✅ | ✅ |
| **Statistiques** | Tableau de bord d'écriture | ✅ | ✅ |
| **Partages** | Gérer, créer ; vues publiques | ✅ | ✅ |
| **Templates** | Liste, créer, modifier, importer | ✅ | ✅ |
| **Collaborations** | Invitations, demandes (propriétaire et collaborateur) | ✅ | ✅ |
| **Recherche** | Résultats globaux (Ctrl+K) | ✅ | ✅ |
| **Auth** | Login, register, profil, tokens API, reset | ✅ | ✅ |

### Fonctionnalités IA dans l'éditeur de chapitre

Présentes dans `chapter/views/editor/edit.html` — fonctionnent dans les deux modes.

| Fonctionnalité | Endpoint | Classique | Pro |
|---|---|---|---|
| Résumer le chapitre avec l'IA | `POST /ai/summarize-chapter` | ✅ | ✅ |
| Suggestions d'ouverture (continuité) | `POST /ai/suggest-continuity` | ✅ | ✅ |
| Détecter les incohérences | `POST /ai/detect-inconsistencies` | ✅ | ✅ |
| Assistant narratif (chat projet) | `POST /ai/ask` | ✅ | ✅ |
| Synonymes Quill | `GET /ai/synonyms/@word` | ✅ | ✅ |
| Mode focus plein écran (F11) | — | ✅ | ✅ |
| Sauvegarde automatique | — | ✅ | ✅ |
| Objectif de session (mots) | — | ✅ | ✅ |
| Vérification grammaticale | — | ✅ | ✅ |
| Historique des versions | `/chapter/{id}/versions` | ✅ | ✅ |
| Lexique du projet (bouton glossaire) | — | ✅ | ✅ |
| Annotations / commentaires | — | ✅ (visible) | ⚠️ Masqué (fonctionne mais section cachée) |

### Fonctionnalités IA dans la vue d'ensemble projet

| Fonctionnalité | Endpoint | Classique | Pro |
|---|---|---|---|
| Résumé auto de chapitre | `POST /ai/summarize-chapter` | ✅ | ✅ |
| Résumé auto d'acte | `POST /ai/summarize-act` | ✅ | ✅ |
| Résumé auto d'élément | `POST /ai/summarize-element` | ✅ | ✅ |

### Fonctionnalités IA — Personnages

| Fonctionnalité | Endpoint | Classique | Pro |
|---|---|---|---|
| Enrichissement de personnage | `POST /ai/enrich-character` | ✅ | ✅ |
| Suggestions de relations | `POST /ai/suggest-relations` | ✅ | ✅ |

### Fonctionnalités IA — Synopsis

| Fonctionnalité | Endpoint | Classique | Pro |
|---|---|---|---|
| Générer synopsis depuis une idée | `POST /ai/synopsis/generate-from-idea` | ✅ | ✅ |
| Générer synopsis depuis le projet | `POST /ai/synopsis/generate-from-project` | ✅ | ✅ |
| Générer un beat | `POST /ai/synopsis/generate-beat` | ✅ | ✅ |
| Suggérer une logline | `POST /ai/synopsis/suggest-logline` | ✅ | ✅ |
| Évaluer le synopsis | `POST /ai/synopsis/evaluate` | ✅ | ✅ |
| Enrichir un beat | `POST /ai/synopsis/enrich-beat` | ✅ | ✅ |

### Demande IA générique

| Fonctionnalité | Classique | Pro |
|---|---|---|
| Modal `#aiRequestModal` | ✅ (lien texte nav) | ✅ (icône topbar) |
| Prompt système configurable + prompts enregistrés | ✅ | ✅ |
| Joindre un fichier (max 1 Mo) | ✅ | ✅ |
| Bouton "Effacer le prompt système" | ❌ | ✅ (ajout Pro) |

### Export (formats identiques, accessibilité différente)

| Format | Classique | Pro |
|---|---|---|
| Texte brut `.txt`, Markdown `.md`, HTML `.html` | ✅ (page projet) | ✅ (sidebar partout) |
| EPUB `.epub`, OpenDocument `.odt` | ✅ | ✅ |
| JSON vectorisation `.json`, Texte brut minuscules `.txt`, Résumés `.txt` | ✅ | ✅ |

---

## Récapitulatif des lacunes à combler

### Lacunes Pro → fonctionnalités du Classique absentes ou dégradées

| Lacune | Localisation CSS | Priorité |
|---|---|---|
| **Changer l'acte d'un chapitre** pendant l'édition | `pro-overrides.css:99` — `.editor-grid-row { display:none }` | 🔴 Haute |
| **Changer le chapitre parent** pendant l'édition | même règle | 🔴 Haute |
| **Glossaire inline** dans l'éditeur | `pro-overrides.css:109` — `#glossaryFoundSection { display:none }` | 🟡 Moyenne |
| **Annotations inline** dans l'éditeur | `pro-overrides.css:110` — `#annotationsSection { display:none }` | 🟡 Moyenne |
| **Compteur de mots inline** | `pro-overrides.css:125` | 🟢 Faible |
| **Métriques détaillées** (ressources, notes, personnages) dans la vue projet | `.project-hero { display:none }` — partiellement remplacé | 🟡 Moyenne |
| **Stats sur les cartes projet** | `.project-card__stats { display:none }` | 🟢 Faible |

### Lacunes Classique → fonctionnalités du Pro absentes

| Lacune | Impact | Priorité |
|---|---|---|
| Navigation projet permanente (Lecture, Relecture, Mindmap depuis l'éditeur) | Doit revenir à la page projet pour accéder à ces liens | 🔴 Haute |
| Assistant IA global (hors éditeur) | Non accessible depuis les pages personnages, notes, synopsis, etc. | 🔴 Haute |
| Export depuis n'importe quelle page | Doit revenir à la page projet | 🟡 Moyenne |
| Flash messages dans le layout | Aucun feedback de succès/erreur visuel global | 🟡 Moyenne |
| **Bouton "Effacer le prompt système"** dans le modal IA | Absent dans le layout classique (`main.html`) | 🟢 Faible |
| **User menu dropdown** (avatar, profil, config IA, thème, déconnexion) | Accès dispersé, pas de point d'entrée unique | 🟡 Moyenne |
| **Breadcrumb en topbar** (Projets › Projet › Chapitre) | Pas de fil d'Ariane contextuel en classique | 🟢 Faible |
| **Slide panels** (`data-panel-open`) | Aucun panneau latéral contextuel disponible | 🟢 Faible |
| **Skip link accessibilité clavier** | Navigation clavier dégradée | 🟢 Faible |
| **Mobile sidebar toggle** (hamburger) | Interface mobile non optimisée | 🟡 Moyenne |
| **Header éditeur sticky** (`position: sticky; top: 48px`) | Le header de l'éditeur défile avec le contenu | 🟢 Faible |

---

*Sources : `src/app/modules/**/views/**/*.html` · `src/public/css/pro/*.css` · `src/app/controllers/Controller.php`*
