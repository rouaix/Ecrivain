# Doublons CSS - Analyse Détaillée

## 1. Doublons de `background: var(--card-bg)`
**Occurrences** : 21 fois dans 15 fichiers différents.

### Fichiers concernés :
- `public/css/ai/ai-config.css` (ligne 12)
- `public/css/ai/ai-modal.css` (lignes 27, 198)
- `public/css/ai/ai-panels.css` (ligne 9)
- `public/css/ai/ai-usage.css` (lignes 33, 82)
- `public/css/auth/auth.css` (ligne 15)
- `public/css/auth/profile.css` (ligne 68)
- `public/css/components/cards.css` (lignes 9, 17, 39)
- `public/css/components/panels.css` (ligne 7)
- `public/css/components/tables.css` (ligne 94)
- `public/css/components/theme-selector.css` (ligne 11)
- `public/css/editor/quill-overrides.css` (ligne 48)
- `public/css/modules/acts.css` (ligne 10)
- `public/css/modules/characters.css` (ligne 27)
- `public/css/modules/project.css` (lignes 53, 133)
- `public/css/utilities/helpers.css` (lignes 28, 149)

**Proposition** : Centraliser dans `public/css/components/cards.css` ou créer une classe utilitaire `.card-bg`.

---

## 2. Doublons de `border: 1px solid var(--border-color)`
**Occurrences** : 23 fois dans 16 fichiers différents.

### Fichiers concernés :
- `public/css/ai/ai-config.css` (ligne 13)
- `public/css/ai/ai-modal.css` (lignes 10, 28, 104, 128, 300)
- `public/css/ai/ai-panels.css` (ligne 10)
- `public/css/ai/ai-usage.css` (lignes 34, 83)
- `public/css/auth/auth.css` (ligne 16)
- `public/css/auth/profile.css` (ligne 69)
- `public/css/auth/tokens.css` (ligne 10)
- `public/css/components/buttons.css` (ligne 125)
- `public/css/components/cards.css` (ligne 10)
- `public/css/components/panels.css` (ligne 8)
- `public/css/components/tables.css` (lignes 95, 102, 117)
- `public/css/modules/characters.css` (ligne 28)
- `public/css/modules/project.css` (lignes 54, 424)
- `public/css/utilities/helpers.css` (lignes 29, 150)

**Proposition** : Créer une classe utilitaire `.border-standard` dans `public/css/utilities/helpers.css`.

---

## 3. Doublons de `color: var(--text-main)`
**Occurrences** : 28 fois dans 18 fichiers différents.

### Fichiers concernés :
- `public/css/ai/ai-config.css` (lignes 25, 59)
- `public/css/ai/ai-modal.css` (lignes 12, 32, 38, 284)
- `public/css/ai/ai-panels.css` (ligne 31)
- `public/css/ai/ai-usage.css` (lignes 18, 47, 71, 92)
- `public/css/auth/auth.css` (lignes 32, 50)
- `public/css/auth/profile.css` (lignes 83, 105, 119)
- `public/css/auth/tokens.css` (ligne 30)
- `public/css/components/tables.css` (ligne 104)
- `public/css/core/reset.css` (ligne 10)
- `public/css/modules/characters.css` (ligne 51)
- `public/css/modules/project.css` (lignes 35, 82, 189, 388)
- `public/css/utilities/helpers.css` (lignes 41, 126, 164, 179)

**Proposition** : Créer une classe utilitaire `.text-main` dans `public/css/utilities/text.css`.

---

## 4. Couleurs en dur (non variables)
**Occurrences** : 30 fois dans 15 fichiers différents.

### Exemples notables :
- `public/css/components/buttons.css` :
  - `.button-ai-purple` (ligne 98) : `#673AB7` → `--button-primary-bg`
  - `.button-ai-green` (ligne 106) : `#4CAF50` → `--button-bg`
  - `.button-ai-orange` (ligne 114) : `#FF9800` → `--button-secondary-bg`
  - `.btn-purple` (ligne 140) : `#7c4dff` → `--button-primary-bg`

- `public/css/auth/auth.css` :
  - `.error` (ligne 114) : `#fee` et `#c33` → Variables à définir.
  - `.success` (ligne 120) : `#efe` et `#3c3` → Variables à définir.

- `public/css/ai/ai-config.css` :
  - `.ai-config-item:hover` (ligne 34) : `#f9f9f9` → `--table-row-hover-bg`

**Proposition** : Remplacer toutes les couleurs en dur par des variables définies dans `public/css/core/variables.css`.

---

## 5. Doublons de styles de boutons
**Fichiers concernés** :
- `public/css/components/buttons.css` (principal)
- `public/css/ai/ai-config.css` (`.ai-config-actions button`)
- `public/css/ai/ai-modal.css` (`button.modal-close`, `button#closeAiModal`)
- `public/css/ai/ai-panels.css` (`.grammar-close-btn`, `.suggestion-button`)
- `public/css/ai/ai-usage.css` (`.back-button`)
- `public/css/dictation.css` (`#dictation-btn`)

**Proposition** : Centraliser tous les styles de boutons dans `public/css/components/buttons.css` et utiliser des classes modifiables (ex: `.button--ai-purple`).

---

## 6. Doublons de styles de cartes
**Fichiers concernés** :
- `public/css/components/cards.css` (principal)
- `public/css/ai/ai-modal.css` (`.ai-modal`)
- `public/css/auth/auth.css` (`.auth-card`)
- `public/css/auth/profile.css` (`.profile-card`)

**Proposition** : Utiliser des classes communes comme `.card` et `.card--modal` pour éviter la duplication.

---

## Recommandations Générales

### 1. Centralisation
- **Boutons** : Tout dans `public/css/components/buttons.css`.
- **Cartes** : Tout dans `public/css/components/cards.css`.
- **Texte** : Créer `public/css/components/text.css`.
- **Couleurs** : Utiliser uniquement les variables de `public/css/core/variables.css`.

### 2. Classes Utilitaires
Créer des classes réutilisables dans `public/css/utilities/` :
- `.text-main`, `.text-muted`
- `.bg-card`, `.bg-header`
- `.border-standard`, `.border-light`

### 3. Variables Manquantes
Ajouter dans `public/css/core/variables.css` :
```css
--success-bg: #efe;
--success-text: #3c3;
--error-bg: #fee;
--error-text: #c33;
--warning-bg: #fff8f8;
--warning-text: #d32f2f;
```

### 4. Structure Optimisée
```css
/* Exemple pour les boutons */
.button {
    /* Styles de base */
}

.button--primary {
    background: var(--button-primary-bg);
}

.button--secondary {
    background: var(--button-secondary-bg);
}

.button--delete {
    background: var(--button-delete-bg);
}
```

---

## Prochaines Étapes
1. **Centraliser les styles** : Regrouper les doublons dans des fichiers dédiés.
2. **Standardiser les couleurs** : Remplacer les couleurs en dur par des variables.
3. **Créer des classes utilitaires** : Pour les styles répétés comme `.text-main`.
4. **Tester** : Vérifier la cohérence visuelle après les changements.
