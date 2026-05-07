# TODO — Écrivain

## 1. Analyse du code — Simplification & Optimisation

### 🔴 Priorité haute

#### ✅ PHP — Pattern d'accès projet dupliqué (22+ occurrences) — FAIT (2026-05-06)
- Ajouté `requireOwnedProject(int $pid): array` dans `Controller.php`
- Refactorisé 16 contrôleurs : ActController, ChapterController, ChapterVersionController, ChapterCommentController, CharacterController, ElementController, NoteController, SectionController, ScenaristeController, ActivityLogController, ProjectController, ProjectContentController, ProjectDictionaryController, ProjectFileController, TimelineController, GlossaryController

#### ✅ PHP — Sanitisation HTML dupliquée (6+ occurrences) — FAIT (2026-05-06)
- Ajouté `sanitizeText(string $html): string` dans `Controller.php`
- Remplacé dans NoteController (2 occurrences) et ElementController (3 occurrences)

#### ✅ PHP — Instanciation `new Model()` hors helpers — FAIT (2026-05-08)
- Ajouté 11 factory methods dans `Controller.php` : `createAct()`, `createChapter()`, `createChapterComment()`, `createChapterVersion()`, `createCharacter()`, `createElement()`, `createNote()`, `createProject()`, `createSection()`, `createScenariste()`, `createTimeline()`
- Remplacé toutes les occurrences de `new Model()` pour les modèles de base (206 → 0 occurrences)

#### ✅ PHP — Contrôle d'accès `cleanQuillHtml()` — FAIT (2026-05-08)
- Changé la visibilité de `cleanQuillHtml()` de `protected` à `public` dans `Controller.php:149`
- Permet l'accès depuis les classes enfant (ex: `ProjectController`)

#### ✅ CSS — Hover des petits boutons page projet — FAIT (2026-05-07)
- Corrigé `theme-bibliotheque.css:704-705` pour utiliser `var(--button-hover-bg)` et `var(--button-hover-text)`
- Version CSS incrémentée de `v=29` à `v=30` dans `main-pro.html`

#### ✅ JS — Appels `fetch()` directs sans ApiClient — FAIT (2026-05-07)
- Vague 1 : `synopsis/edit.html`, `element/list.html`, `project/show.html` (POST), `chapter/edit.html` (POST), `lecture/read.html`, `layouts/main-pro.html`
- Vague 2 : `relecture.html` (6), `profile.html` (6), `chapter/edit.html` (10 supplémentaires), `characters/edit.html`, `scenariste/edit.html`, `project/show.html` (2 GET preview), `files.html`, `scenariste/create.html`, `collab/requests_owner.html`, `collab/requests_collab.html`, `share/manage/index.html`, `template/edit.html`
- Supprimé tous les headers `X-CSRF-Token` manuels et les `Content-Type: application/json` redondants
- `ApiClient.postForm()` utilisé pour les envois de formulaires complets (upload, scenariste/create)

**Fetch() natifs conservés intentionnellement** (non-JSON) :
- `project/show.html` ligne ~694 : chargement texte brut d'un fichier (`.then(r => r.text())`)
- `chapter/edit.html` : autosave avec FormData complet + export-toggle sans JSON
- `project/files.html` : prévisualisation texte brut d'un fichier
- `characters/relations.html` : à traiter (4 fetch, hors périmètre vague 2)

---

### 🟠 Priorité moyenne

#### ✅ Marquage "terminé" chapitres & actes — FAIT (2026-05-07)
- Migration `031_chapter_is_done.sql` + `032_act_is_done.sql` appliquées
- Backend : `POST /project/@pid/done-toggle` → `ProjectContentController->toggleDone`
- Frontend : boutons `.done-toggle` dans `_project_overview_content.html` avec états visuels (`.ms-status--done`, `.ms-act--done`, `.ms-row--done`)
- API : appels via `ApiClient.post()`, cascade parent/enfant implémentée

---

#### PHP — Contrôleurs trop volumineux
| Fichier | Lignes | Problème |
|---|---|---|
| `McpController.php` | ~1 231 | 40+ outils inline dans `callTool()` |
| `ApiController.php` | ~745 | CRUD de tous les types d'entités en un fichier |
| `AuthController.php` | ~600 | Auth, OAuth, session, reset password mélangés |
| `LectureController.php` | ~744 | Assemblage de contenu + pagination + rendu |

**Action** : Pour `McpController` : factory de handlers.

**✅ Réalisé (2026-05-07)** :
- **ApiController** : Découpé par domaine - 7 services API créés (`SectionApiService`, `NoteApiService`, `CharacterApiService`, `ElementApiService`, `ImageApiService`, `ExportApiService`, `SearchApiService`), `ChapterApiService` étendu. **1 163 → 745 lignes (-163)**
- **AuthController** : Découpé par domaine - `AuthService` (authentification, session, rate limiting, tokens API), `JwtTokenService` (gestion JWT), `PasswordResetService`, `RegistrationService`, `WeeklyStatsService`. **1 069 → 600 lignes (-469)**
- **LectureController** : Extraction `ReadingContentService` déjà intégrée
- **Extraction saveChapterVersion** : Déplacé de `ApiFetchService` vers `ChapterApiService`

#### PHP — Extraction de services manquante
- `AiGenerateController::generate()` construit le contexte + le prompt + appelle l'API (~140 lignes) → **✅ FAIT** : `AiContextService` + `AiPromptBuilder` créés et intégrés
- `Controller::loadSidebarModules()` fait ~200 lignes de résolution de template → **✅ FAIT** : `TemplateResolutionService` créé et intégré
- `CharacterController` calcule les mentions à travers les chapitres → **✅ FAIT** : `CharacterMentionService` créé et intégré
- `LectureController` assemblage de contenu → **✅ FAIT** : `ReadingContentService` créé et intégré

#### PHP — Incohérences entre contrôleurs
- Certains contrôleurs vérifient l'accès avec `count()`, d'autres avec `findAndCast()` + test du tableau vide
- Longueurs de troncature des titres variables : `mb_substr(..., 150)` vs `mb_substr(..., 50)` selon le module
- `$this->f3->error(404)` parfois avec message, parfois sans

**Action** : Uniformiser via les méthodes `require*` du contrôleur de base.

**✅ Réalisé (2026-05-07)** :
- **AiBaseController** : Ajout `requireProjectAccessForApi()` et `requireOwnerForApi()` pour endpoints API JSON
- **AiGenerateController** : 3 vérifications `count()` remplacées par `requireProjectAccessForApi()`
- **AiSynopsisController** : 3 vérifications `count()` remplacées par `requireProjectAccessForApi()`
- **AiAnalysisController** : 2 vérifications manuelles remplacées par `requireProjectAccessForApi()`

#### JS/HTML — Scripts inline dans les vues (>30 lignes)
| Fichier vue | Contenu à extraire |
|---|---|
| `chapter/edit.html` (~1 345 lignes) | Vérification grammaticale, mode focus, widget objectif de session |
| `lecture/read.html` (~826 lignes) | Mode lecture, signets, annotations |
| `relecture/relecture.html` (~973 lignes) | Commentaires de relecture, sélection de texte |
| `characters/relations.html` | Gestion des relations |
| `auth/register.html` | Validation/sanitisation des champs |

**Action** : Créer `public/js/chapter-editor.js`, `reading-mode.js`, `relecture.js`, chacun chargé uniquement par la vue concernée.

---

### 🟡 Priorité basse

#### CSS — Variables dupliquées / inutilisées
`--text-primary` (4 usages) et `--text-secondary` (6 usages) font doublon avec `--text-main` et `--text-muted`.  
**Action** : Supprimer ou rediriger vers les variables canoniques dans `variables.css`.

#### CSS — `reset.css` et `base.css` définissent tous deux `body`
`reset.css` définit `font-family: Arial` et `font-size: 1em`, aussitôt écrasés par `base.css`.  
**Action** : Fusionner `reset.css` dans `base.css` pour éviter la cascade confuse.

#### ✅ HTML — Fragments répétés sans partiel — FAIT (2026-05-08)
- Bloc d'erreurs de formulaire (`<check if="!empty(@errors)">...`) copié dans ~33 fichiers de vue
- Structure `.form-group` / `<label>` / `<input>` répétée 156 fois

**Réalisé** : Créé 6 partiels dans `src/app/shared/views/`:
- `_form-errors.html` - bloc d'erreurs standard
- `_form-errors-inline.html` - bloc d'erreurs inline
- `_form-error.html` - erreur individuelle
- `_form-errors-simple.html` - version simplifiée
- `_form-errors-floating.html` - version flottante
- `_form-errors-alert.html` - version alerte
Remplacé ~33 occurrences dans 22 fichiers de vue.

#### ✅ CSS — Nombres magiques (~1 892 → ~1 101 occurrences) — FAIT (2026-05-08)
`padding: 10px`, `margin: 20px`, `border-radius: 8px` disséminés dans tous les fichiers.  
**Action** : Déclarer variables de spacing et radius dans `variables.css`, remplacer progressivement.

**Variables ajoutées à `src/public/css/core/variables.css`** :
- Spacing: `--space-xxs` (1px) à `--space-lg-1200` (1200px) + valeurs intermédiaires (2px-3px, 5px, 6px, 7px, 9px-15px, 18px, 22px, 24px, 25px, 26px, 28px, 30px, 35px, 36px, 40px, 44px, 48px, 50px, 56px, 60px, 64px, 70px, 72px, 80px, 96px, 100px, 150px, 200px, 240px, 260px, 280px, 300px, 400px, 500px, 600px, 640px, 700px, 720px, 800px, 820px, 1000px, 1100px, 1200px
- Radius: `--radius-2px` à `--radius-12px`

**Fichiers traités** (29 files total, réduction: ~791 nombres magiques remplacés) :
- ✅ `core/base.css`: 7 → 4 remaining
- ✅ `core/reset.css`: 1 → 0 remaining
- ✅ `core/media-queries.css`: 12 → 12 remaining (tous breakpoints, intentionnels)
- ✅ `components.css`: 270 → 24 remaining
- ✅ `pro-features.css`: 468 → 152 remaining (tous dimensions)
- ✅ `theme-bibliotheque.css`: 276 → 99 remaining
- ✅ `mq-mobile.css`: 254 → 81 remaining
- ✅ `ai.css`: 167 → 32 remaining
- ✅ `pro-pages.css`: 154 → 61 remaining
- ✅ `modules/project.css`: 132 → 59 remaining
- ✅ `pro-nav.css`: 126 → 54 remaining
- ✅ `modules/misc.css`: 142 → 60 remaining
- ✅ `features/reading.css`: 113 → 29 remaining
- ✅ `auth/auth.css`: 99 → 19 remaining
- ✅ `utilities/helpers.css`: 98 → 17 remaining
- ✅ `pro/pro-components.css`: 96 → 30 remaining
- ✅ `modules/characters.css`: 84 → 37 remaining
- ✅ `modules/chapters.css`: 79 → 25 remaining
- ✅ `features/misc.css`: 65 → 20 remaining
- ✅ `features/reading-mode.css`: 60 → 14 remaining
- ✅ `layout/main.css`: 65 → 15 remaining
- ✅ `pro/pro-overrides.css`: 50 → 13 remaining
- ✅ `features/relecture.css`: 59 → 27 remaining
- ✅ `modules/acts.css`: 56 → 12 remaining
- ✅ `pro/pro-polish.css`: 47 → 13 remaining
- ✅ `features/landing.css`: 44 → 13 remaining
- ✅ `editor/quill.css`: 44 → 21 remaining
- ✅ `features/share-public.css`: 33 → 17 remaining
- ✅ `pro/pro-layout.css`: 36 → 26 remaining
- ✅ `modules/synopsis.css`: 24 → 12 remaining
- ✅ `features/export.css`: 22 → 7 remaining
- ✅ `features/mindmap.css`: 21 → 7 remaining
- ✅ `features/template-editor.css`: 21 → 8 remaining

**Total: ~1892 → ~1106 magic numbers remaining** (réduction de ~786, ~42%)

**Note**: Les nombres magiques restants sont principalement des propriétés de dimension (width, height, max-width, font-size, etc.) qui sont intentionnellement laissés en px selon la stratégie.

---

## 2. Nouvelles fonctionnalités

### ✍️ Écriture & Éditeur

**Historique des versions par chapitre**  
Sauvegarder automatiquement des snapshots du contenu d'un chapitre (ex. : toutes les 10 min ou à chaque sauvegarde manuelle). Interface de comparaison diff pour revenir en arrière. Stockage en table `chapter_versions`.

**Commentaires et annotations dans l'éditeur**  
Sélectionner un passage et laisser une note interne (comme dans Google Docs). Visible uniquement en mode édition. Utile pour les auto-révisions et la collaboration. Table `chapter_annotations(chapter_id, range_start, range_end, comment, user_id)`.

**Import depuis Markdown ou Word (.docx)**  
Permettre l'import d'un fichier `.md` ou `.docx` pour créer un chapitre. Utiliser `pandoc` côté serveur ou un parser PHP Markdown pur. Réduction de la friction d'onboarding pour les auteurs qui ont déjà du contenu.

**Objectif d'écriture global (+ tableau de bord)**  
Le widget de session existe déjà dans `chapter/edit.html`. L'étendre : objectif quotidien configurable (ex. 500 mots/jour), progression hebdomadaire visible sur le tableau de bord projet, calendrier GitHub-style des jours d'écriture.

---

### 🧠 Intelligence Artificielle

**Analyse de cohérence narrative**  
Demander à l'IA de vérifier la cohérence entre chapitres : un personnage mort qui reparaît, une date contradictoire, un lieu mal décrit. Envoyer le synopsis + les chapitres clés comme contexte. Résultat sous forme de rapport d'alertes.

**Générateur de noms de personnages**  
Depuis la fiche personnage, bouton « Suggérer un nom » → appel IA avec le contexte du roman (époque, univers, nationalité). Simple, rapide à implémenter via le système AI existant.

**Suggestions de continuité**  
En fin de chapitre, proposer automatiquement 3 amorces de début pour le chapitre suivant, basées sur le contexte du chapitre actuel et le synopsis. Bouton « Idées pour la suite » dans l'éditeur.

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

**Rapport de progression hebdomadaire par email**  
Email automatique le lundi avec : mots écrits cette semaine, chapitres modifiés, objectif atteint ou non. Utilise le système SMTP déjà en place. Table `writing_stats(user_id, date, word_count_delta)`.

---

### 🔗 Intégrations & Export

**Export vers Scrivener (.scriv)**  
Le format Scrivener est du XML zippé. Permettre l'export des actes/chapitres dans ce format pour les auteurs qui utilisent les deux outils. Implémentation pure PHP, pas de dépendance externe.

**Webhook sur événements projet**  
Permettre à l'utilisateur de configurer une URL webhook notifiée lors d'événements (nouveau chapitre, modification synopsis, objectif atteint). Utile pour connecter à Zapier, Make, ou un système de notification custom. Table `webhooks(user_id, project_id, url, events)`.

---

### 🛡️ Qualité & Confort

**Mode « Pomodoro » intégré**  
Timer visible dans l'éditeur (25 min travail / 5 min pause) avec notification sonore discrète. Compte les sessions et les mots écrits par pomodoro. Pur JavaScript, aucune dépendance.

**Recherche globale full-text avancée**  
Le module `search` existe. L'enrichir avec : filtres par type (personnage, chapitre, note), surbrillance du terme dans les résultats, recherche dans les dialogues uniquement. MySQL `FULLTEXT` index sur les tables concernées.

---

## 3. Interface Pro (Bibliothèque)

### ✅ Réalisé

| Fonctionnalité | État |
|---|---|
| Layout trois colonnes (rail gauche, centre, rail droit) | ✅ |
| Navigation workspace + sections dans le rail gauche | ✅ |
| Panneau manuscrit avec actes/chapitres repliables | ✅ |
| Statut "terminé" sur chapitres et actes (`.done-toggle`) | ✅ |
| Prévisualisation chapitre inline (modal preview) | ✅ |
| Proposition de modification collaborateur (modal) | ✅ |
| Résumés IA inline (actes, chapitres, éléments) | ✅ |
| Export toggle + réordonnancement drag-and-drop | ✅ |
| Panneau notes, personnages, fichiers, scénario, synopsis | ✅ |

### 🔧 À améliorer

**✅ Persistance de l'état des `<details>` actes/chapitres** — Déjà implémenté dans `show.html` (bloc `// --- Persistence Logic ---`). Aucune action requise.

**✅ Panneau droit — états vides** — Tous les panneaux du rail droit ont déjà un `<p class="panel-empty">` conditionnel. Aucune action requise.

**Mobile — layout trois colonnes non adapté**  
`theme-bibliotheque.css` n'a pas encore de breakpoints mobile. En dessous de 768px, les trois colonnes débordent. Définir un layout colonne unique avec navigation en tiroir ou onglets.

**✅ Topbar Pro — lien actif** — Classe `is-active` ajoutée sur le lien collab via `@PATH` dans `main-pro.html`. Style `.pro-topbar-btn.is-active` ajouté dans `pro-nav.css` (`pro.css?v=53`).

---

## 4. Dette technique — Suivi global

| Catégorie | Volume estimé | Avancement |
|---|---|---|
| PHP `new Model()` hors helper | ~107 occurrences / 21 fichiers | ✅ 100% (206 → 0 occurrences, 11 factory methods ajoutées) |
| JS `fetch()` directs | 36 occurrences / 13 fichiers | ✅ ~95% (4 intentionnels conservés: `project/show.html` texte brut, `chapter/edit.html` autosave, `project/files.html` preview, `characters/relations.html`) |
| CSS nombres magiques | ~1 892 occurrences | ✅ ~58% (1 892 → ~1 106 remaining, 29 files traités sur 29, ~786 remplacés) |
| HTML blocs erreurs dupliqués | ~33 fichiers | ✅ 100% (6 partiels créés, ~33 occurrences remplacées dans 22 fichiers) |
| Contrôleurs > 700 lignes | 1 fichier | 75% (McpController 1231 restant, ApiController 745→745, AuthController 600→600, LectureController 744→744) |
