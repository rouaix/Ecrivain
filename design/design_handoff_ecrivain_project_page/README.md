# Handoff — Redesign de la page projet Écrivain

## Aperçu

Redesign de la **page projet** de l'app **Écrivain** (assistant d'écriture pour romanciers, rouaix.com/ecrivain). L'écran cible est la vue d'un livre en cours, par exemple `/ecrivain/project/9` (HAINDAL) : sidebar gauche avec les sections du livre, colonne centrale "Actes & chapitres" avec arborescence repliable, sidebar droite avec progression + outils.

Quatre directions visuelles sont fournies, **toutes à conserver** et à intégrer comme **thèmes interchangeables** dans l'app :

- **A · Manuscrit** — papier crème, EB Garamond éditorial, mini-couverture du livre dans la rail, ambiance bureau d'éditeur.
- **B · Bibliothèque moderne** — fond ivoire, accent encre profonde, Lora italique pour les titres + Inter UI, table dense avec colonnes, stats en bandeau. Outil pro "Linear-meets-Notion" littéraire.
- **C · Atelier** — crème chaud + accent terre cuite, Cormorant Garamond, hero avec couverture du livre en grand, badges chapitres ronds.
- **D · Studio nuit** — mode sombre slate + ambre chaud, Source Serif 4 italique, pour les sessions tardives.

## À propos des fichiers de design

Les fichiers dans `source/` sont des **références de design en HTML/JSX** — des prototypes qui montrent le rendu visuel et le comportement attendus, pas du code de production à copier tel quel. Le travail consiste à **recréer ces designs dans l'environnement du codebase Écrivain** (probablement Laravel + Blade/Livewire/Alpine/Vue/React selon la stack actuelle), en utilisant les composants, conventions et système de routing déjà en place. Si la stack n'est pas encore choisie, prendre le framework le plus adapté au reste du projet.

Les 4 thèmes doivent être implémentés comme **un sélecteur de thème utilisateur** (ex. dans Paramètres) — pas comme 4 pages séparées. Mêmes données, mêmes interactions, seul le styling change.

## Fidélité

**Hi-fi** — couleurs, typographies, espacements et hiérarchie sont définitifs. Les valeurs dans la section "Design tokens" sont à reprendre exactement. Quelques éléments sont des **enrichissements proposés** par rapport au design actuel (cf. screenshot original) ; ils sont marqués **[ajout]** ci-dessous et restent optionnels.

## Écrans

Une seule vue : **Page projet — Vue d'ensemble (Actes & chapitres)**.

### Layout général (commun aux 4 directions)

```
┌─────────────────────────────────────────────────────────────────┐
│ Top bar  (52–56 px)                                             │
├──────────┬───────────────────────────────────┬──────────────────┤
│          │                                   │                  │
│ Left     │      Centre                       │  Right rail      │
│ rail     │      (scrollable)                 │  (scrollable)    │
│ 220–248  │      flex: 1                      │  270–290 px      │
│ px       │                                   │                  │
│          │                                   │                  │
└──────────┴───────────────────────────────────┴──────────────────┘
```

### Top bar
- Marque "Écrivain" + monogramme É (carré 26–30 px, italique serif blanc sur fond foncé selon le thème)
- Fil d'Ariane : `Projets › HAINDAL`
- À droite : champ de recherche (⌘K), bouton "Partager", bouton primaire "Reprendre l'écriture →" **[ajout]**, avatar utilisateur

### Left rail
- **Bloc Espace** : Tableau de bord, Statistiques, Partages, Templates, Collaborations
- **Bloc Manuscrit** (titre du livre) : Vue d'ensemble *(actif)*, Couverture, Préface, Introduction, Prologue, Actes (1), Chapitres (67), Postface, Annexes, Quatrième de couverture, Structure narrative, À ajouter au livre
- Direction A et C affichent en plus une **mini-couverture du livre** (rapport 2/3, fond bordeaux/marron, cadre intérieur, titre HAINDAL en italique serif)
- Direction B affiche un **chip projet** (tranche de livre 22×32 px + nom + meta "Roman · 162 080 mots")

### Centre
- **Header de page** : eyebrow "HAINDAL · Vue d'ensemble", H1 "Actes & chapitres", sous-titre "1 acte · 67 chapitres · 764 pages · dernière modif il y a 12 minutes"
- Direction B ajoute un **bandeau de stats** (Mots / Pages / Chapitres / Série) en 4 colonnes
- Direction C ajoute un **hero** avec couverture du livre en grand + lede + 3 stats + barre de progression
- **Toolbar** : champ filtre, boutons Trier / + Nouveau chapitre. Direction B ajoute des **onglets** : Structure *(actif)*, Manuscrit, Personnages, Notes, Historique
- **Groupe "Sections avant les chapitres"** (replié, 0 items)
- **Groupe "Acte 1"** (déplié) avec liste des chapitres :
  - Chaque chapitre : numéro, titre italique serif, date, badge "scènes", colonne mots monospace
  - Le chapitre 5 (Marseille) est **actif** — surligné par un trait gauche couleur d'accent + fond légèrement teinté + actions (✎ ⌖ ⋯)
  - Chapitres 5 et 6 sont dépliés et montrent leurs scènes (indentées, italiques, mots à droite)

### Right rail
- **Carte Progression** : "162 080 mots", barre 100%, "772 / 650 pages · 100%", "122 pages au-delà de l'objectif"
- **Carte Aujourd'hui** **[ajout]** : "1 284 mots", "Objectif 1 000 dépassé", série "23 j. d'affilée"
- **Carte Outils** : Carte mentale, Mode lecture, Mode relecture, Assistant IA, Exporter
- **Carte Activité** **[ajout, B et C]** : 3 dernières actions
- **Carte Espace** : Collaborateurs, Activité, Paramètres
- Direction A ajoute une citation décorative en pied de rail

## Interactions & comportement

- **Clic sur un chapitre** → toggle le dépliage des scènes (chevron `›` ↔ `⌄`).
- **Clic sur une section du rail gauche** → met cette section active (surlignage + trait gauche couleur d'accent).
- **Clic sur "+ Nouveau chapitre"** → ouvre la création d'un chapitre (à câbler sur l'endpoint existant).
- **Clic sur ✎ d'un chapitre actif** → ouvre l'éditeur du chapitre.
- **Hover sur une ligne de chapitre** → fond légèrement plus marqué (subtle).
- **Sélecteur de thème** (à ajouter dans Paramètres) → applique A/B/C/D au niveau du document. Les variables CSS de chaque thème sont indépendantes — un seul thème actif à la fois. Persister le choix utilisateur en BDD ou cookie.

## State management

État côté serveur (déjà en place vraisemblablement) :
- `project` : { id, title, author, totalWords, pagesWritten, pagesGoal, … }
- `acts[]` : actes
- `chapters[]` : chapitres avec `{ n, title, date, scenes, words, children[] }`

État côté client à gérer :
- `expandedChapters: Set<number>` — chapitres dont les scènes sont visibles
- `activeSection: string` — section du rail gauche active
- `theme: 'manuscrit' | 'bibliotheque' | 'atelier' | 'studio-nuit'` — préférence utilisateur

## Design tokens

### A · Manuscrit
| Token | Valeur |
|---|---|
| Bg page | `#fbf7ee` |
| Bg rail | `#efe9dc` / `#f4efe6` |
| Borders | `#e3dccd`, `#ece5d4` |
| Texte | `#2a2520` corps, `#7a6e5a` muted |
| Accent (encre) | `#1a1612` |
| Accent (sépia) | `#6b3f2a` |
| Couverture livre | linear-gradient `#2a1f1a → #4a382c` |
| Serif principal | `EB Garamond` |
| UI sans-serif | `ui-sans-serif, system-ui` |
| Mono | `ui-monospace, "SF Mono", Menlo` |
| Tailles | H1 38, groupTitle 17 italic, chTitle 16, body 15, mono 12 |
| Border radius | 3–4 px |

### B · Bibliothèque moderne
| Token | Valeur |
|---|---|
| Bg page | `#f7f6f2` |
| Bg cartes | `#fff`, `#fbfaf6` |
| Borders | `#e7e3d8`, `#f0ede4` |
| Texte | `#0f172a`, `#5a6781` muted, `#94a0b8` faint |
| Accent encre | `#1e2a4a` (boutons, surlignage actif) |
| Succès | `#0a6e3a` |
| Display | `Lora` (italique pour les titres) |
| UI | `Inter` |
| Mono | `ui-monospace, "SF Mono"` |
| Tailles | H1 32, statValue 22, chTitle 15, body 13.5 |
| Border radius | 5–8 px |

### C · Atelier
| Token | Valeur |
|---|---|
| Bg page | `#f6efe2` |
| Bg cartes | `#fdf8ec` |
| Bg rail | `#ede4d2` |
| Bg topbar | `#3a2418` |
| Borders | `#ddd0b8`, `#ebe0c8` |
| Texte | `#2a2218`, `#8a7558` muted |
| Accent terre cuite | `#c4623c` |
| Accent doré | `#c4a575` |
| Display | `Cormorant Garamond` (italique) |
| UI | `Inter` |
| Tailles | heroTitle 48, H2 28, chTitle 17, badge 38px rond |
| Border radius | 4–6 px |

### D · Studio nuit
| Token | Valeur |
|---|---|
| Bg page | `#161413` |
| Bg cartes | `#1c1a18`, `#0e0d0c` |
| Borders | `#2a2624` |
| Texte | `#f4ecd8` titres, `#e8e2d4` corps, `#a89c87` muted, `#5a514a` faint |
| Accent ambre | `#d4a04a` |
| Display | `Source Serif 4` (italique) |
| UI | `Inter` |
| Tailles | H1 34, groupTitle 17, chTitle 16, body 13.5 |
| Border radius | 4–6 px |

### Espacements communs
- Top bar : 52–56 px
- Left rail : 220–248 px
- Right rail : 270–290 px
- Padding centre : 24–32 px H, 32 px haut, 48–64 px bas
- Padding cartes : 14–18 px

## Assets

- **Polices Google** à charger : EB Garamond, Cormorant Garamond, Lora, Source Serif 4, Inter (poids 400/500/600 + italiques 400/500/600).
- **Couverture du livre HAINDAL** : actuellement reproduite en CSS (gradient marron + cadre intérieur + titre italique). À remplacer par l'image réelle si dispo dans le projet.
- **Iconographie** : caractères Unicode utilisés volontairement (✎ ⌖ ⋯ ↗ ⌕ › ⌄ ❦) pour rester sobres. À remplacer par les icônes du système (Lucide/Heroicons/etc.) si le codebase en a déjà un.
- **Aucune image bitmap** dans les designs.

## Fichiers

Dans `source/` :
- `Ecrivain - Redesign Page Projet.html` — point d'entrée, charge les 4 directions dans un canvas
- `data.jsx` — données partagées HAINDAL (chapitres, scènes, stats) — sert de référence pour la structure de données attendue
- `design-canvas.jsx` — wrapper du canvas (à ignorer côté implémentation)
- `direction-a-manuscrit.jsx` — implémentation A
- `direction-b-bibliotheque.jsx` — implémentation B
- `direction-c-atelier.jsx` — implémentation C
- `direction-d-studio-nuit.jsx` — implémentation D

Chaque direction est un **composant React autonome** qui prend les données via `window.PROJECT`. À porter vers le framework cible en gardant la même structure DOM et les mêmes valeurs de tokens.

## Recommandation d'implémentation

1. Définir les tokens dans 4 fichiers CSS (`theme-manuscrit.css`, etc.) ou en variables CSS scopées sur `[data-theme="..."]`.
2. Construire **un seul** ensemble de composants (TopBar, LeftRail, ChapterRow, ProgressCard…) qui consomment ces tokens.
3. Ajouter un sélecteur de thème dans Paramètres ; persister le choix utilisateur.
4. Charger les 4 polices conditionnellement selon le thème actif (font-display: swap) pour éviter de tout charger d'office.
