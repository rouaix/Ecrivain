# Suivi — Simplification CSS/HTML
Référence : design/analyse-simplification.md
Démarré : 2026-04-29

---

## Phase A — Nettoyage immédiat

### A.1 — Supprimer les fichiers HTML morts
- [ ] Identifier et supprimer les vues projet non référencées
- [ ] Sortir le CSS inline de `_project_overview_panel_css.html`
- [ ] Supprimer les imports inutiles dans `style.css`

### A.2 — CSS inline → fichier CSS
- [ ] Déplacer le contenu de `_project_overview_panel_css.html` dans un vrai fichier CSS

### A.3 — Imports style.css inutiles
- [ ] Auditer et retirer les @import de fichiers non utilisés

---

## Phase B — Fusion des couches CSS
- [x] B.1 — Remplacer les couleurs hardcodées de pro-nav.css + pro-layout.css par des variables CSS, puis supprimer les overrides redondants dans theme-bibliotheque.css
- [x] B.2 — Réduire theme-bibliotheque.css à ses seuls styles spécifiques
- [x] B.3 — Nettoyer pro-features.css des doublons avec css/ai/, css/auth/, css/modules/

---

## Phase C — Architecture cible
- [ ] Restructuration finale des fichiers CSS (~20 fichiers)
- [ ] Suppression de main.html (layout classique mort)

---

## Log

| Date | Étape | Statut | Notes |
|------|-------|--------|-------|
| 2026-04-29 | Démarrage | ✅ | Fichier de suivi créé |
| 2026-04-29 | A.1 — Fichiers HTML morts | ✅ | Supprimé : body.html, dynamic_body.html, header.html, _project_overview_aside.html. Retiré l'include dans new_body.html |
| 2026-04-29 | A.2 — CSS inline → head | ✅ | `_project_overview_panel_css.html` supprimé. Le `<style>` dynamique déplacé dans le `<head>` de main-pro.html |
| 2026-04-29 | A.3 — Imports CSS inutiles | ✅ | Supprimé : editor-tools.css (vide), components/search.css (fusionné dans modules/search.css), components/text.css (orphelin non importé). style.css v68 |
| 2026-04-29 | B.1 — Variables CSS + purge topbar theme | ✅ | pro-nav.css + pro-layout.css : toutes les couleurs hardcodées → variables CSS. Supprimé bloc TOPBAR (69 lignes) + swatches multi-layout dans theme-bibliotheque.css. theme-bibliotheque.css v22 |
| 2026-04-29 | B.2 — Dédupliquer theme-bibliotheque.css | ✅ | Remplacé 238 var(--tm-*) par équivalents :root. Supprimé bloc --tm-* (26 lignes). Fusionné 2 blocs .button.small. Corrigé bug ms-sub-link hover. 1507 → 1459 lignes. theme-bibliotheque.css v23 |
| 2026-04-29 | B.3 — Doublons pro-features.css | ✅ | Les surcharges body.ui-pro sur les modules sont intentionnelles (Pro compact). Vrai doublon interne : .stats-kpi-* défini 2× (cascade hack). Fusionné en 1 bloc, retiré 2e instance. 2280 → 2244 lignes. pro.css v50 |
