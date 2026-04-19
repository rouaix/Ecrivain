# Plan de refactorisation — Écrivain

> **À lire avant de commencer.** Ce document est un audit + plan d'action priorisé.  
> Chaque phase peut être validée/ajustée indépendamment. Aucune phase ne casse la précédente.

---

## État des lieux

| Métrique | Valeur | Statut |
|---|---|---|
| Contrôleurs > 200 lignes | 26 sur 36 | 🔴 Critique |
| Contrôleur le plus long | AiController — 2 136 lignes | 🔴 Critique |
| Fichiers CSS thèmes dupliqués | 5 fichiers en double | 🟠 Facile à corriger |
| `editor-tools.js` | 560 lignes — aucune référence trouvée | 🟠 Code mort |
| Logique `reorder()` | Copiée dans Act, Chapter, Section, Element | 🟡 Duplication |
| Gestion upload image | Copiée dans 4+ contrôleurs | 🟡 Duplication |
| Blocs HTML répétés dans les vues | ~30 fragments identiques | 🟡 Duplication |

---

## Phase 1 — Suppressions sans risque (2–4 h)

Actions purement destructives : aucune logique ne change, aucun test à écrire.

### 1.1 Supprimer les fichiers CSS thèmes dupliqués

**Problème** : les thèmes existent en double.

```
src/public/theme-blue.css       ← DOUBLON
src/public/theme-dark.css       ← DOUBLON
src/public/theme-default.css    ← DOUBLON
src/public/theme-forest.css     ← DOUBLON
src/public/theme-moderne.css    ← DOUBLON

src/public/css/themes/theme-blue.css    ← SOURCE UNIQUE à conserver
src/public/css/themes/theme-dark.css
src/public/css/themes/theme-default.css
src/public/css/themes/theme-forest.css
src/public/css/themes/theme-moderne.css
```

**Attention** : `src/public/offline.html` charge `theme-{name}.css` (chemin racine) via JS.  
→ Corriger ce JS pour pointer vers `css/themes/theme-{name}.css` avant de supprimer.

**Fichiers à toucher** :
- `src/public/offline.html` (ligne 203) — corriger le chemin JS
- Supprimer les 5 fichiers `src/public/theme-*.css`

---

### 1.2 Supprimer `editor-tools.js`

**Problème** : fichier de 560 lignes pour TinyMCE. Aucune vue ni contrôleur ne l'inclut.  
Quill (`quill-adapter.js`) est l'éditeur actif.

**Action** : supprimer `src/public/js/editor-tools.js`.  
Vérifier au préalable avec `grep -r "editor-tools" src/` qu'il n'existe aucune référence cachée.

---

## Phase 2 — Mutualisation des modèles (3–5 h)

Les modèles Act, Chapter, Section partagent exactement la même structure pour `reorder()` et `getNextOrder()`. Créer un trait pour éviter la maintenance triplée.

### 2.1 Créer `src/app/core/OrderableTrait.php`

```php
trait OrderableTrait
{
    /**
     * Retourne le prochain order_index disponible dans la table.
     * $criteria : ['column=?', $value] ou null pour tout le projet.
     */
    public function getNextOrderBy(string $table, string $col, $val): int
    {
        $res = $this->db->exec(
            "SELECT MAX(order_index) as m FROM {$table} WHERE {$col} = ?", [$val]
        );
        return (int)($res[0]['m'] ?? 0) + 1;
    }

    /**
     * Réordonne les lignes de la table selon le tableau d'IDs fourni.
     * Sécurisé : vérifie project_id pour empêcher la manipulation.
     */
    public function reorderItems(string $table, int $projectId, array $orderedIds): bool
    {
        $this->db->begin();
        foreach ($orderedIds as $i => $id) {
            $this->db->exec(
                "UPDATE {$table} SET order_index = ? WHERE id = ? AND project_id = ?",
                [$i + 1, (int)$id, $projectId]
            );
        }
        $this->db->commit();
        return true;
    }
}
```

**Modèles à migrer** :
- `Act::reorder()` → déléguer à `reorderItems('acts', …)`
- `Chapter::reorder()` → déléguer (conserver la logique hiérarchique spécifique)
- `Section::reorder()` → déléguer à `reorderItems('sections', …)`

> **Note** : Chapter a une logique de tri hiérarchique (parent_id, act_id) qui dépasse  
> le trait générique. Ne pas forcer la mutualisation là où la logique diverge.

---

## Phase 3 — Mutualisation des vues (4–6 h)

F3 supporte les includes avec variables via `{{ include('path.html') }}`. Créer des partials partagés.

### 3.1 Créer le répertoire `src/app/modules/_shared/views/`

Ce répertoire contiendra les fragments réutilisables entre modules.  
L'ajouter à `AUTOLOAD` n'est pas nécessaire (c'est des vues, pas du PHP).

### 3.2 Partial : état vide

**Problème** : ~15 pages répètent ce bloc :

```html
<check if="{{ count(@items) == 0 }}">
    <div class="edit-card mt-20">
        <p class="text-muted-para empty-state-block">
            <i class="fas fa-{icon}" style="font-size:2rem;opacity:.3"></i>
            Aucun {item} créé pour ce projet.
            <check if="{{ @isOwner }}">
                <a href="..." class="button mt-10">Créer le premier {item}</a>
            </check>
        </p>
    </div>
</check>
```

**Solution** : créer `_shared/views/_empty-state.html` avec variables `@emptyIcon`, `@emptyMessage`, `@emptyCreateUrl`, `@emptyCreateLabel`.

Pages à migrer (priorité) : `acts/list.html`, `chapter/list.html`, `note/list.html`, `characters/index.html`, `element/list.html`, `glossary/index.html`.

### 3.3 Partial : en-tête de page avec actions

**Problème** : ~20 vues répètent le même en-tête :

```html
<div class="edit-header">
    <h2><i class="fas fa-{icon}"></i> Titre — {{ @project.title }}</h2>
    <div class="header-actions">
        <a href="..." class="button secondary">Retour</a>
        <check if="{{ @isOwner }}">
            <a href="..." class="button">Créer</a>
        </check>
    </div>
</div>
```

**Solution** : créer `_shared/views/_page-header.html` avec variables `@pageIcon`, `@pageTitle`, `@backUrl`, `@createUrl`, `@createLabel`.

### 3.4 Partial : dialogue de confirmation de suppression

**Problème** : chaque liste utilise `.js-confirm` avec `window.confirm()` natif du navigateur (expérience médiocre, pas stylisable).

**Solution** :
1. Créer `_shared/views/_confirm-modal.html` — modal HTML réutilisable
2. Ajouter dans `api-client.js` une fonction `AppUI.confirm(message)` → `Promise<boolean>`
3. Remplacer les `href` de suppression par des boutons avec `data-delete-url`

---

## Phase 4 — Découpage des contrôleurs (1–2 semaines)

C'est la phase la plus impactante. Procéder module par module pour limiter les risques.

### Règle générale

Un contrôleur ne doit pas dépasser ~200 lignes.  
Si une méthode dépasse 30 lignes, c'est le signal qu'elle appartient à un service.

### 4.1 AiController (2 136 lignes) → 4 contrôleurs + 1 service

Actuellement, `AiController` mélange :
- Génération de texte (suggestions, complétion, reformulation…)
- Synonymes et dictionnaire
- Configuration des providers IA
- Comptage/facturation des tokens
- Appels vers plusieurs APIs externes (OpenAI, Gemini, Anthropic, Mistral)

**Découpage proposé** :

```
AiController.php          ← entrée : routing + délégation uniquement (~80 lignes)
AiGenerateController.php  ← génération de texte (suggest, complete, rewrite…)
AiConfigController.php    ← configuration provider/clé API utilisateur
AiDictController.php      ← synonymes, correction, grammaire
AiService.php             ← logique métier commune (appels API, gestion erreurs, cache)
```

`AiService` existe déjà partiellement — le consolider.

### 4.2 ProjectExportController (1 467 lignes) → 2 contrôleurs

Actuellement mélange :
- Export PDF, EPUB, DOCX, Markdown, TXT
- Gestion des options d'export (couverture, styles, inclusions)
- Transformation HTML→texte (qui existe déjà dans `ContentTransformer`)

**Découpage proposé** :

```
ProjectExportController.php   ← routing + options (~120 lignes)
ExportRendererService.php     ← logique de rendu par format (PDF, EPUB, etc.)
```

> `ContentTransformer` est déjà bien isolé — vérifier qu'ExportController l'utilise  
> systématiquement plutôt que de recoder la même transformation.

### 4.3 ChapterController (922 lignes) → 2 contrôleurs

Actuellement mélange :
- CRUD chapitres
- Réordonnancement drag & drop
- Commentaires/annotations
- Export du chapitre seul

**Découpage proposé** :

```
ChapterController.php         ← CRUD + éditeur (~250 lignes)
ChapterOrderController.php    ← réordonnancement AJAX (~60 lignes)
```

Les commentaires sont déjà dans `LectureController` — vérifier les doublons.

### 4.4 ProjectController (1 081 lignes) → déléguer vers ProjectBaseController

`ProjectBaseController` existe déjà (205 lignes). Vérifier ce qui peut y être remonté :
- Méthodes de chargement du projet commun à tous les sous-contrôleurs
- Vérification d'accès (`hasProjectAccess`, `isOwner`)
- Récupération des paramètres de la requête

### 4.5 Controller.php base (906 lignes)

Trop grand pour une classe de base. Candidats à extraire :
- `validateImageUpload()` + déplacement du fichier → `ImageUploadService`
- `encryptData()` / `decryptData()` → déjà dans `TokenService`? Vérifier les doublons
- `checkRateLimit()` → peut rester en base (utilisé partout)

**Créer `src/app/services/ImageUploadService.php`** :

```php
class ImageUploadService
{
    public function validate(array $file, int $maxMB = 5): array { … }
    public function move(array $file, string $destDir, string $prefix = ''): string { … }
    public function delete(string $path): void { … }
}
```

Contrôleurs concernés : `ProjectController`, `CharacterController`, `SectionController`, `ApiController`, `ProfileController`.

---

## Phase 5 — Mutualisation JS (3–5 h)

### 5.1 Centraliser les handlers modaux dans `api-client.js`

**Ajouter un namespace `AppUI`** dans `api-client.js` (ou un fichier `app-ui.js` dédié) :

```javascript
const AppUI = {
    // Remplace window.confirm() — retourne une Promise
    confirm(message, title = 'Confirmation') { … },

    // Affiche une notification temporaire (success/error/info)
    notify(message, type = 'success', durationMs = 3000) { … },

    // Ouvre/ferme un modal par ID
    openModal(id) { … },
    closeModal(id) { … },
};
```

**Vues à migrer** : tous les `js-confirm` en suppriment la duplication inline.

### 5.2 Retirer le code de style inline dans `notifications.js`

`notifications.js` construit du CSS via des chaînes JavaScript (fragile, non maintenable).  
→ Déplacer ces styles dans `src/public/css/components/` et retirer le JS de construction CSS.

### 5.3 Vérifier `pro-ui.js` vs `quill-adapter.js`

S'assurer qu'il n'y a pas de double initialisation de Quill entre les deux fichiers.

---

## Phase 6 — CSS (2–3 h)

### 6.1 Réduire `pro-components.css`

Ce fichier fait 2 900 lignes car il répète les mêmes sélecteurs pour chaque combinaison thème × mode Pro. Utiliser des variables CSS pour remplacer les duplications :

```css
/* Avant (répété 5 fois pour chaque thème) */
body.theme-default.ui-pro .button { background: #3f51b5; color: #fff; }
body.theme-blue.ui-pro .button { background: #1976d2; color: #fff; }

/* Après */
body.ui-pro .button { background: var(--color-primary); color: #fff; }
/* Les thèmes définissent --color-primary dans core/variables.css */
```

### 6.2 Réorganiser `media-queries.css` (1 516 lignes)

Ce fichier centralise tous les breakpoints de l'application. C'est difficile à maintenir car il n'y a pas de lien visuel avec les composants qu'il modifie.

**Option pragmatique (sans build step)** : déplacer les breakpoints dans le fichier CSS de chaque composant correspondant, et supprimer `media-queries.css`.

**Condition** : ne pas introduire de régression sur mobile — faire relire après migration.

---

## Ordre d'exécution recommandé

| Priorité | Phase | Durée estimée | Risque |
|---|---|---|---|
| 1 | 1.1 — Supprimer CSS dupliqués | 30 min | Très faible |
| 2 | 1.2 — Supprimer editor-tools.js | 15 min | Très faible |
| 3 | 3.1–3.3 — Partials vues | 4–6 h | Faible |
| 4 | 2.1 — OrderableTrait | 3–4 h | Faible |
| 5 | 4.5 — ImageUploadService | 2–3 h | Moyen |
| 6 | 4.1 — Découper AiController | 1 jour | Moyen |
| 7 | 4.2 — Découper ProjectExportController | 4–6 h | Moyen |
| 8 | 4.3 — Découper ChapterController | 3–4 h | Moyen |
| 9 | 5.1–5.3 — JS AppUI | 3–5 h | Moyen |
| 10 | 6.1 — Réduire pro-components.css | 2–3 h | Moyen |
| 11 | 6.2 — Réorganiser media-queries.css | 2–3 h | Faible |

---

## Ce qu'on ne touche pas (pour l'instant)

- `McpController` et `ApiController` : longs mais cohérents — chaque méthode est un endpoint distinct, peu de duplication réelle.
- `OAuthController` : implémentation d'un protocole standard, sa longueur est justifiée.
- La structure des routes `config.ini` : propre, ne pas sur-ingénierer.
- Les doublons `/api/*` vs `/api/v1/*` : prévoir une dépréciation séparée, pas dans ce chantier.

---

## Contraintes à respecter pendant la refactorisation

1. **Fins de ligne LF** — tout fichier créé/modifié sur Windows doit être vérifié (`file path/to/file` doit afficher "UTF-8 text", pas "CRLF").
2. **Versionnement CSS/JS** — incrémenter `?v=` dans `main.html` et `main-pro.html` après chaque modification.
3. **Migrations DB** — aucun changement de schéma dans ce chantier.
4. **Pas de framework JS** — rester en vanilla JS, pas d'Alpine/htmx/React.
5. **Pas de build step** — rester sans bundler (Vite, Webpack, etc.).
6. **F3 templates** — les includes F3 s'écrivent `{{ include('/chemin/relatif/_partial.html') }}` ; les variables du contexte sont automatiquement héritées.
