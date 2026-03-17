# Normalisation CSS/HTML — Écrivain

> Analyse effectuée le 2026-03-17 · 42 fichiers CSS · 25+ vues HTML analysées

---

## Résumé exécutif

Le projet a une architecture CSS correctement découpée (variables, components, utilities, modules) mais souffre de **quatre problèmes structurels** :

1. **Styles inline omniprésents** — `display:none`, `float:right`, `margin-right:10px` répétés partout au lieu de classes utilitaires
2. **Boutons fragmentés** — 7 classes alternatives pour "un bouton" (`.button`, `.button-ai-purple`, `.btn-purple`, `.preview-btn`, `.button-ai-green`, `.button-ai-orange`, `.token-button`) avec comportements incohérents
3. **Variables CSS incomplètes** — `--button-hover-bg` utilisée mais **non définie** → rendu imprévisible ; couleurs sémantiques (info, success, danger) inexistantes
4. **Utilitaires manquants** — `visibility.css` a `.is-hidden` mais les vues utilisent `style="display:none"` ; `spacing.css` n'a que 4 classes alors qu'on en aurait besoin de 20+

---

## 1. Variables CSS manquantes et incohérentes

### 1.1 Variable critique non définie

`buttons.css` ligne 34 utilise `var(--button-hover-bg)` → **jamais définie** dans `variables.css`. Le hover de tous les boutons verts est cassé silencieusement.

```css
/* buttons.css — LIGNE 34 (bug actuel) */
background-color: var(--button-hover-bg);  /* ← valeur computée : vide = transparent */
```

### 1.2 Variables manquantes à ajouter dans `variables.css`

```css
/* À ajouter dans :root { } */

/* Hover des boutons (correction du bug) */
--button-hover-bg: #43a047;          /* vert foncé, hover de --button-bg */
--button-primary-hover-bg: #303f9f;  /* indigo foncé */
--button-secondary-hover-bg: #757575;/* gris foncé */
--button-delete-hover-bg: #c62828;   /* rouge foncé — remplace "darkred" hardcodé */

/* Couleurs sémantiques (absentes, hardcodées en inline) */
--color-success: #22c55e;
--color-info:    #3b82f6;
--color-warning: #f59e0b;
--color-danger:  #ef4444;

/* Couleurs sémantiques — fonds et textes */
--success-bg:   #f0fdf4;  /* remplace #efe (trop court, lisibilité nulle) */
--success-text: #166534;  /* remplace #3c3 */
--error-bg:     #fef2f2;  /* remplace #fee */
--error-text:   #991b1b;  /* remplace #c33 */
--info-bg:      #eff6ff;
--info-text:    #1e40af;
--warning-bg:   #fffbeb;  /* remplace #fff8f8 (qui est rouge, pas orange) */
--warning-text: #92400e;  /* remplace #d32f2f (qui est rouge) */

/* Shadows (actuellement en dur dans .edit-card, .project-card, etc.) */
--shadow-sm: 0 2px 4px rgba(0,0,0,0.08);
--shadow-md: 0 4px 12px rgba(0,0,0,0.1);
--shadow-lg: 0 10px 30px rgba(0,0,0,0.05);

/* Transitions (actuellement 0.2s en dur dans buttons, forms, etc.) */
--transition: 0.2s ease;

/* Border-radius (4px, 8px, 12px en dur partout) */
--radius-sm: 4px;
--radius-md: 8px;
--radius-lg: 12px;

/* Focus ring (rgba(63,81,181,0.1) hardcodé dans forms.css:112) */
--focus-ring: 0 0 0 3px rgba(63, 81, 181, 0.1);
```

### 1.3 Variables existantes à corriger

| Variable | Valeur actuelle | Problème | Correction |
|---|---|---|---|
| `--success-bg` | `#efe` | Format court illisible, pas compatible dark mode | `#f0fdf4` |
| `--success-text` | `#3c3` | Format court | `#166534` |
| `--error-bg` | `#fee` | Format court | `#fef2f2` |
| `--error-text` | `#c33` | Format court | `#991b1b` |
| `--warning-bg` | `#fff8f8` | C'est du rouge, pas du warning orange | `#fffbeb` |
| `--warning-text` | `#d32f2f` | C'est du rouge danger, pas du warning | `#92400e` |
| `--primary-color` | `#000` | Nom trompeur (noir), jamais utilisé | Supprimer ou renommer |
| `--secondary-color` | `#fff` | Nom trompeur (blanc), jamais utilisé | Supprimer ou renommer |
| `--rouge` | `#d65151` | Nom en français, doublon de `--button-delete-bg` | Supprimer, utiliser `--color-danger` |

---

## 2. Boutons — fragmentation et duplication

### 2.1 Inventaire des classes actuelles

| Classe | Rendu | Problème |
|---|---|---|
| `.button` | Vert (#4caf50), 24px | Base — OK |
| `.button.primary` | Indigo (#3f51b5) | Alias de `.button.secondary.btn-purple` ? |
| `.button.secondary` | Gris (#9e9e9e) mais **CSS commenté** → rendu identique à `.button` ! | Bug : la couleur secondaire n'est plus appliquée |
| `.button.small` | 28px (plus grand que `.button` 24px !) | Incohérence : small devrait être plus petit |
| `.button.delete` | Rouge (#f44336) | OK |
| `.button-ai-purple` | Indigo, padding 4px 8px, sans border, sans border-radius | Pas un `.button`, pas de base commune |
| `.button-ai-green` | Vert, idem | Idem |
| `.button-ai-orange` | Gris (#9e9e9e nommé "orange"), idem | Nommé orange, rendu gris |
| `.btn-purple` | Indigo, sans border | Doublon de `.button.primary` et `.button-ai-purple` |
| `.preview-btn` | Gris, padding 2px 8px, `margin-right:5px` | Pas un `.button`, dimensions différentes |

### 2.2 Problèmes critiques

**`.button.secondary` ne s'applique pas** — La règle est commentée dans `buttons.css` :
```css
/* buttons.css lignes 64-69 */
.button.secondary {
    /*
    background-color: var(--button-secondary-bg);
    color: white;
    */
}
```
→ Tous les boutons `.button.secondary` sont donc **verts** comme les boutons primaires.

**`.button.small` est paradoxalement plus grand** — height 28px vs 24px pour `.button` de base.

**`.button-ai-*` n'héritent pas de la base `.button`** — Ils définissent leur propre `border`, `padding`, `cursor` séparément.

### 2.3 Normalisation proposée

```css
/* buttons.css — version normalisée */

/* Base */
input[type="submit"],
button:not(.ql-toolbar button),
.button:not(.ql-toolbar button) {
    /* … identique à l'actuel … */
    background: var(--button-bg);
    transition: var(--transition);
}

/* Hover (correction du bug --button-hover-bg) */
button:not(.ql-toolbar button):hover,
.button:not(.ql-toolbar button):hover,
input[type="submit"]:hover {
    background-color: var(--button-hover-bg);  /* maintenant définie */
}

/* Variantes couleur */
.button.primary   { background: var(--button-primary-bg); }
.button.primary:hover { background: var(--button-primary-hover-bg); }

.button.secondary { background: var(--button-secondary-bg); }  /* décommenter */
.button.secondary:hover { background: var(--button-secondary-hover-bg); }

.button.delete    { background: var(--button-delete-bg); }
.button.delete:hover { background: var(--button-delete-hover-bg); }  /* plus "darkred" */

/* Tailles */
.button.small { height: 20px; font-size: 11px; padding: 0 8px; }  /* corriger : 20 < 24 */
.button.large { height: 32px; font-size: 13px; padding: 0 16px; }

/* Supprimer — fusionner dans .button.primary */
/* .button-ai-purple → .button.primary */
/* .btn-purple       → .button.primary */
/* .button-ai-green  → .button (déjà vert) */
/* .button-ai-orange → .button.secondary */
/* .preview-btn      → .button.secondary.small */
```

### 2.4 Boutons IA — pattern unifié

Actuellement dans les vues IA :
```html
<button class="button-ai-purple">Générer</button>
<button class="button-ai-green">Accepter</button>
<button class="button-ai-orange">Options</button>
```

À remplacer par :
```html
<button class="button primary">Générer</button>
<button class="button">Accepter</button>
<button class="button secondary">Options</button>
```

---

## 3. Styles inline — inventaire et remplacement

### 3.1 `display:none` — le cas le plus fréquent (~40 occurrences)

**Actuel** (dans chapter/edit.html, glossary, modals, annotations…) :
```html
<div id="ai-panel" style="display:none;">
<div class="annotation-form" style="display:none;">
<span class="diff-hidden" style="display:none;">
```

**Solution** — `.is-hidden` existe déjà dans `visibility.css` :
```html
<div id="ai-panel" class="is-hidden">
<div class="annotation-form is-hidden">
<span class="diff-hidden is-hidden">
```

Les JS qui font `element.style.display = 'block'` doivent utiliser :
```js
// Avant
el.style.display = 'none';
el.style.display = 'block';

// Après (utiliser les classes)
el.classList.add('is-hidden');
el.classList.remove('is-hidden');
```

### 3.2 `float:right` — technique obsolète (~3 occurrences)

**Actuel** :
```html
<span style="float:right;">{{ wordCount }} mots</span>
<div style="float: right;">actions</div>
```

**Solution** — ajouter dans `utilities/text.css` :
```css
.float-right { float: right; }  /* tolérance de migration */
/* Ou mieux, restructurer en flexbox le conteneur parent */
```

En pratique : restructurer le parent en flexbox `justify-content: space-between`.

### 3.3 `margin-right: 10px` sur icônes (~8 occurrences)

**Actuel** :
```html
<i class="fas fa-spell-check" style="margin-right: 10px;"></i>
<i class="fas fa-magic" style="margin-right: 6px;"></i>
```

**Solution** — la règle `span.button i, a.button i { margin: 0 5px; }` existe déjà dans `buttons.css`. Pour les icônes **hors** bouton, ajouter dans `utilities/helpers.css` :
```css
.icon-mr { margin-right: 6px; }   /* icône suivie de texte */
.icon-ml { margin-left: 6px; }
```

### 3.4 Styles inline les plus récurrents à transformer en classes

| Style inline | Occurrences | Classe à créer/utiliser |
|---|---|---|
| `style="display:none"` | ~40 | `.is-hidden` (existe) |
| `style="display:flex; flex-direction:column"` | ~15 | `.d-flex-col` |
| `style="margin-right:10px"` | ~8 | `.icon-mr` |
| `style="float:right"` | ~3 | Restructurer en flexbox |
| `style="text-align:center"` | ~5 | `.text-center` (existe dans text.css ?) |
| `style="color:#ef4444"` | ~2 | `.text-danger` |
| `style="padding-top:0"` | ~3 | `.pt-0` |
| `style="visibility:hidden"` | ~2 | `.invisible` |
| `style="max-width:860px; max-height:85vh; display:flex; flex-direction:column"` | modals | `.modal-content.modal-lg` |
| `style="font-size:.8em;opacity:.6;margin-right:6px"` | ~2 | `.text-xs.text-muted.icon-mr` |

---

## 4. Utilitaires manquants

### 4.1 `spacing.css` — à compléter

Actuellement seulement 4 classes. À ajouter :
```css
/* Margins top */
.mt-0  { margin-top: 0; }
.mt-5  { margin-top: 5px; }
.mt-15 { margin-top: 15px; }    /* remplace .form-group--mt-15 */
.mt-30 { margin-top: 30px; }

/* Margins bottom */
.mb-0  { margin-bottom: 0; }
.mb-5  { margin-bottom: 5px; }
.mb-15 { margin-bottom: 15px; }
.mb-30 { margin-bottom: 30px; }

/* Padding */
.pt-0  { padding-top: 0; }
.pb-0  { padding-bottom: 0; }
.p-0   { padding: 0; }
```

### 4.2 `text.css` / `utilities` — à compléter

```css
.text-center  { text-align: center; }
.text-right   { text-align: right; }
.text-muted   { color: var(--text-muted); }
.text-danger  { color: var(--color-danger); }
.text-success { color: var(--color-success); }
.text-info    { color: var(--color-info); }
.text-xs      { font-size: 0.8em; }
.text-sm      { font-size: 0.9em; }
.fw-bold      { font-weight: bold; }
.opacity-60   { opacity: 0.6; }
```

### 4.3 `visibility.css` — à compléter

```css
.is-hidden    { display: none !important; }        /* existe */
.is-visible   { display: block !important; }       /* existe */
.invisible    { visibility: hidden; }              /* à ajouter — préserve l'espace */
.d-flex       { display: flex; }
.d-flex-col   { display: flex; flex-direction: column; }
.d-inline     { display: inline; }
.d-inline-flex { display: inline-flex; }
```

### 4.4 Modals — classes de taille

`modals.css` est actuellement **vide** (renvoie vers `ai-modal.css`). Toutes les modales utilisent des styles inline pour la taille. À créer dans `modals.css` :

```css
/* Overlay */
.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.is-visible { display: flex; }

/* Conteneur */
.modal-content {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    padding: 24px;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    width: 560px;  /* défaut */
}

/* Tailles */
.modal-content.modal-sm { width: 400px; }
.modal-content.modal-md { width: 560px; }   /* défaut */
.modal-content.modal-lg { width: 860px; }
.modal-content.modal-xl { width: min(95vw, 1100px); }

/* Hauteur */
.modal-content.modal-tall {
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}
.modal-content.modal-tall .modal-body { flex: 1; overflow-y: auto; }
```

---

## 5. Formulaires — corrections mineures

### 5.1 `.form-group` sans margin de base

```css
/* forms.css ligne 6 */
.form-group { margin: 0; }  /* ← aucun espacement entre champs */
```

Correction :
```css
.form-group { margin-bottom: 16px; }
.form-group:last-child { margin-bottom: 0; }
```

### 5.2 Focus ring avec couleur hardcodée

```css
/* forms.css ligne 112 */
box-shadow: 0 0 0 3px rgba(63, 81, 181, 0.1);  /* indigo hardcodé */
```

Correction :
```css
box-shadow: var(--focus-ring);  /* définie dans variables.css */
```

### 5.3 Classes spacing ad-hoc

`.form-group--mt-15` et `.form-group--mt-20` (forms.css lignes 70-75) font doublon avec les utilitaires `.mt-15`, `.mt-20` à créer dans `spacing.css`. Une fois les utilitaires créés, supprimer ces classes.

---

## 6. Liens — comportement uniforme

Les liens simples `<a href>` sans classe sont correctement gérés via `base.css` et `var(--link-color)`. Pas de normalisation nécessaire.

Les liens-boutons `<a class="button">` fonctionnent correctement.

**Seul problème** : certains liens de navigation utilisent `class="button secondary"` alors que `.button.secondary` n'applique aucune couleur (CSS commenté, cf. §2.2). Correction liée à la résolution du bug `.button.secondary`.

---

## 7. Icônes `<i>` FontAwesome

### 7.1 Icônes avec margin inline

Environ 8 occurrences de `<i ... style="margin-right:Xpx">`. Toutes dans des boutons ou à côté de texte. Solution : utiliser `.icon-mr` (§4.3) ou laisser le parent en `display:flex; gap:6px`.

### 7.2 Accessibilité

Les icônes dans les boutons texte/icône n'ont pas `aria-hidden="true"`. Non bloquant mais à corriger progressivement :
```html
<!-- Avant -->
<a class="button secondary"><i class="fas fa-arrow-left"></i> Retour</a>

<!-- Après -->
<a class="button secondary"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour</a>
```

---

## Plan de modifications — par priorité

### P1 — Bugs (à faire en premier, impact immédiat)

| # | Modification | Fichier | Effort |
|---|---|---|---|
| 1.1 | Définir `--button-hover-bg` et `--button-delete-hover-bg` | `variables.css` | 5 min |
| 1.2 | Décommenter `.button.secondary { background: var(--button-secondary-bg); }` | `buttons.css` | 2 min |
| 1.3 | Corriger `.button.small` height (28→20px pour qu'il soit vraiment smaller) | `buttons.css` | 2 min |
| 1.4 | Remplacer `darkred` par `var(--button-delete-hover-bg)` | `buttons.css` | 2 min |
| 1.5 | Corriger `.warning-bg` (`#fff8f8` rouge → `#fffbeb` orange) | `variables.css` | 2 min |

### P2 — Variables manquantes

| # | Modification | Fichier | Effort |
|---|---|---|---|
| 2.1 | Ajouter variables hover, couleurs sémantiques, shadows, radius, transitions | `variables.css` | 20 min |
| 2.2 | Corriger les valeurs courtes (`#efe` → `#f0fdf4`, etc.) | `variables.css` | 5 min |
| 2.3 | Corriger `box-shadow` focus hardcodé → `var(--focus-ring)` | `forms.css` | 2 min |
| 2.4 | Remplacer `0.2s ease` hardcodés → `var(--transition)` | `buttons.css`, `forms.css` | 10 min |

### P3 — Compléter les utilitaires

| # | Modification | Fichier | Effort |
|---|---|---|---|
| 3.1 | Ajouter `.mt-*`, `.mb-*`, `.pt-0`, `.pb-0` | `spacing.css` | 10 min |
| 3.2 | Ajouter `.text-center`, `.text-right`, `.text-danger`, `.text-success`, `.text-xs`, `.text-sm`, `.fw-bold`, `.opacity-60` | `text.css` | 10 min |
| 3.3 | Ajouter `.invisible`, `.d-flex`, `.d-flex-col`, `.d-inline-flex` | `visibility.css` | 5 min |
| 3.4 | Ajouter `.icon-mr`, `.icon-ml` | `helpers.css` | 5 min |
| 3.5 | Créer `.modal-content` + variantes taille dans `modals.css` | `modals.css` | 20 min |
| 3.6 | Corriger `.form-group { margin-bottom: 16px; }` | `forms.css` | 2 min |

### P4 — Consolider les boutons

| # | Modification | Fichier | Effort |
|---|---|---|---|
| 4.1 | Supprimer `.button-ai-purple`, `.button-ai-green`, `.button-ai-orange` | `buttons.css` | 5 min |
| 4.2 | Supprimer `.btn-purple` | `buttons.css` | 2 min |
| 4.3 | Intégrer `.preview-btn` comme `.button.secondary.small` | `buttons.css` | 5 min |
| 4.4 | Remplacer dans les vues IA : `button-ai-purple` → `button primary`, etc. | vues `ai/` | 30 min |

### P5 — Supprimer les styles inline dans les vues

| # | Modification | Fichiers touchés | Effort |
|---|---|---|---|
| 5.1 | `style="display:none"` → `class="is-hidden"` | 15+ vues | 45 min |
| 5.2 | `style="display:flex;flex-direction:column"` → `class="d-flex-col"` | 5+ vues | 15 min |
| 5.3 | `style="margin-right:Xpx"` sur `<i>` → `class="icon-mr"` | 8 vues | 15 min |
| 5.4 | `style="text-align:center"` → `class="text-center"` | 5 vues | 10 min |
| 5.5 | `style="color:#ef4444"` → `class="text-danger"` | 2 vues | 5 min |
| 5.6 | `style="float:right"` → restructurer en flexbox | 3 vues | 20 min |
| 5.7 | Styles inline modals → classes `.modal-content.modal-lg` etc. | 5+ vues | 30 min |
| 5.8 | `style="padding-top:0"` etc. → classes utilitaires `.pt-0` | 3 vues | 10 min |
| 5.9 | JS : `el.style.display = 'none'` → `el.classList.add('is-hidden')` | JS dans vues | 30 min |

### P6 — Nettoyage final

| # | Modification | Fichier | Effort |
|---|---|---|---|
| 6.1 | Supprimer `.form-group--mt-15`, `.form-group--mt-20` | `forms.css` + vues | 10 min |
| 6.2 | Supprimer `--rouge`, `--primary-color`, `--secondary-color` (non utilisés) | `variables.css` | 5 min |
| 6.3 | Ajouter `aria-hidden="true"` sur les `<i>` dans les boutons | toutes les vues | 30 min |

---

## Avancement

> Dernière mise à jour : 2026-03-17

| Priorité | Tâche | Statut |
|---|---|---|
| 🔴 P1 | Bugs boutons (hover, secondary, small, darkred, warning-bg) | ✅ |
| 🟠 P2 | Variables manquantes / valeurs incorrectes | ✅ |
| 🟡 P3 | Compléter utilitaires (spacing, text, visibility, modals, forms) | ✅ |
| 🟢 P4 | Consolider classes boutons (supprimer doublons) | ✅ |
| 🔵 P5 | Supprimer styles inline dans les vues | ✅ |
| ⚪ P6 | Nettoyage final (variables inutilisées, aria) | ✅ |

### Session 2 — P6 (2026-03-17)

**CSS modifiés :**
- `forms.css` : suppression `.form-group--mt-15/20` (non utilisés), `transition: all 0.2s` → `var(--transition)`
- `visibility.css` : ajout `.align-end`, `.justify-center`, `.ml-10`, `.gap-8`, `.gap-16`, `.clearfix`, `.d-block`
- `helpers.css` : ajout `.empty-state-block`, `.empty-state-icon`, `.empty-state-icon--success`
- `characters.css` : ajout `.relation-dot` + 8 variantes couleur (neutral/danger/orange/warning/success/pink/info/purple)

**Vues modifiées :**
- 431× `<i class="fas...">` → `aria-hidden="true"` ajouté (65 fichiers)
- 26× patterns flex/spacing → classes utilitaires (12 fichiers)
- 13× couleurs de badges/état → classes sémantiques `.relation-dot--*`, `.text-success`, `.text-danger.fw-bold` (4 fichiers)
- 4× empty-state inline → `.empty-state-block` + `.empty-state-icon` (4 fichiers)
- 30× patterns flex/margin/text restants → classes utilitaires (19 fichiers)
- **Styles inline restants : 17** (tous légitimes — 4 dynamiques JS `.color`, reste = contraintes layout spécifiques)

### Session 1 — détail (2026-03-17)

**CSS modifiés :**
- `variables.css` : +20 variables (hover boutons, sémantiques, shadows, radius, transitions, focus ring) ; correction `--warning-bg/text` ; suppression `--rouge`, `--primary-color`, `--secondary-color`
- `buttons.css` : réécriture complète — bug `--button-hover-bg` corrigé, `.button.secondary` décommenté, `.button.small` height 28→20px, `darkred` → variable, classes AI doublons supprimées (`.button-ai-*`, `.btn-purple`), `.preview-btn` conservé comme hook JS + style secondaire
- `forms.css` : `.form-group` margin 0→16px, focus ring hardcodé → `var(--focus-ring)`
- `modals.css` : rempli (était vide) — `.modal-overlay`, `.modal-content`, variantes taille (`modal-sm/md/lg/xl`), `.modal-tall`, `.modal-header/footer/close`
- `spacing.css` : 4→30+ classes (mt/mb 0-30, pt/pb, ml/mr auto, p/m-0)
- `text.css` : refonte — `.text-danger/success/warning/info`, `.text-xs/sm/lg`, `.fw-bold`, `.opacity-60`, `.text-truncate`
- `visibility.css` : ajout `.invisible`, `.d-flex`, `.d-flex-col`, `.d-inline-flex`, `.flex-1`, `.align-center`, `.justify-between`, `.gap-*`
- `helpers.css` : ajout `.icon-mr/ml`, `.flex-spacer`, `.inline-flex-center`

**Vues modifiées :**
- 30× `style="display:none"` → `class="is-hidden"` (11 fichiers)
- 91× JS `el.style.display='none/block/flex'` → `el.classList.add/remove('is-hidden')` (9 fichiers)
- 7× `<i style="margin-right:Xpx">` → `<i class="icon-mr">` (4 fichiers)
- 7× modals `style="max-width/max-height/flex"` → classes `.modal-md/lg modal-tall` (4 fichiers)
- 3× `style="float:right"` → `.ml-auto` / flexbox (3 fichiers)
- 2× `style="color:#ef4444"` → `class="text-danger"` (1 fichier)
- 1× `style="visibility:hidden"` → `class="invisible"` (1 fichier)
- 1× `class="button secondary btn-purple"` → `class="button primary"` (dashboard.html)
- 1× inline 5-props sur `.character-actions` supprimé (CSS existant suffisant)
