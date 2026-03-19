# Module Synopsis — Conception complète

> Fichier d'analyse et de planification — à lire avant toute création de fichier.
> Date de rédaction : 2026-03-19

---

## 1. Compétence métier : qu'est-ce qu'un synopsis de roman ?

### 1.1 Définition

Le synopsis est un document de travail qui résume un roman de façon structurée, du début à la fin, en révélant les retournements de situation et la résolution. Il ne s'agit **pas** d'un texte promotionnel (quatrième de couverture) : un synopsis dévoile tout, y compris la fin.

Il sert à :
- permettre à l'auteur de valider la cohérence globale avant ou pendant l'écriture ;
- soumettre le projet à un éditeur ou un agent littéraire ;
- servir de boussole narrative en cours de rédaction.

### 1.2 Les composants d'un synopsis professionnel

Un synopsis complet comporte plusieurs couches, du plus court au plus détaillé :

| Composant | Description | Longueur cible |
|---|---|---|
| **Ligne directrice (logline)** | Une phrase : personnage + désir + obstacle + enjeu | 1–2 phrases |
| **Pitch / accroche** | Résumé séduisant pour convaincre (quatrième de couverture intérieure) | 1–2 paragraphes |
| **Situation initiale** | Présentation du monde, du héros, de son manque ou déséquilibre | ½ page |
| **Élément déclencheur** | L'événement qui rompt l'équilibre et force le protagoniste à agir | court |
| **Premier tournant (plot point 1)** | Le héros bascule dans l'aventure / s'engage | court |
| **Développement (acte II)** | Enchaînement des obstacles, évolution intérieure, sous-intrigues | 1–3 pages |
| **Point médian (midpoint)** | Révélation ou renversement à mi-parcours | court |
| **Crise / nœud dramatique** | Moment le plus sombre — le héros semble perdre | court |
| **Climax / résolution** | Affrontement final et dénouement | ½ page |
| **Situation finale** | Nouveau monde stable, thème explicite | court |

### 1.3 Informations complémentaires (fiche projet)

Ces métadonnées accompagnent souvent un synopsis soumis à un éditeur :

- **Genre** (romance, thriller, fantasy, SF, littérature blanche…)
- **Sous-genre** (dark romance, cozy mystery, space opera…)
- **Public cible** (adulte, YA, MG, enfant)
- **Ton** (sombre, léger, humoristique, épique…)
- **Thèmes principaux** (rédemption, identité, vengeance, amour interdit…)
- **Nombre de mots estimé / réel** (lié au projet)
- **Comparables (comps)** : « dans l'esprit de [Titre] de [Auteur] »
- **Statut du manuscrit** (en cours, premier jet, révisé, prêt à soumettre)
- **Biographie auteur courte** (optionnel)

### 1.4 Méthodes narratives de référence pour structurer le synopsis

L'auteur peut vouloir suivre un modèle de structure. Les plus courants :

| Méthode | Principe |
|---|---|
| **Freytag (pyramide)** | Introduction → Action montante → Climax → Action descendante → Dénouement |
| **Schéma actanciel (Greimas)** | Sujet / Objet / Destinateur / Destinataire / Adjuvant / Opposant |
| **Voyage du héros (Campbell)** | 12 étapes archétypales (appel, seuil, épreuves, apothéose, retour…) |
| **Save the Cat (Snyder)** | 15 beats sur 3 actes, minutage précis |
| **Méthode des 7 points** | Hook → Plot turn 1 → Pinch 1 → Midpoint → Pinch 2 → Plot turn 2 → Resolution |
| **Snowflake Method** | Expansion progressive : 1 phrase → 1 para → 1 page → fiche perso → synopsis complet |

---

## 2. Conception du module `synopsis`

### 2.1 Positionnement dans l'application

Le synopsis est attaché à un **projet** (table `projects`). Un projet a **un seul synopsis** (relation 1-1). Il vit dans une page dédiée accessible depuis la fiche projet, comme les sections (`SectionController`) mais avec une structure riche à champs multiples.

**Navigation** : accessible via un onglet ou un bouton depuis `GET /project/@id` (vue show du projet).

### 2.2 Modèle de données

#### Table `synopsis` (migration `026_synopsis.sql`)

```sql
CREATE TABLE IF NOT EXISTS synopsis (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id   INT UNSIGNED NOT NULL UNIQUE,
    -- Méta-informations
    genre        VARCHAR(100)  NULL,
    subgenre     VARCHAR(100)  NULL,
    audience     VARCHAR(100)  NULL,
    tone         VARCHAR(100)  NULL,
    themes       TEXT          NULL,   -- liste CSV ou JSON simple
    comps        TEXT          NULL,   -- comparables éditoriaux
    status       VARCHAR(50)   NULL DEFAULT 'en_cours',
    -- Couches narratives (champs Quill HTML)
    logline      TEXT          NULL,
    pitch        TEXT          NULL,
    situation    TEXT          NULL,   -- situation initiale
    trigger_evt  TEXT          NULL,   -- élément déclencheur
    plot_point1  TEXT          NULL,
    development  TEXT          NULL,   -- acte II (le plus long)
    midpoint     TEXT          NULL,
    crisis       TEXT          NULL,
    climax       TEXT          NULL,
    resolution   TEXT          NULL,
    -- Structure choisie
    structure_method VARCHAR(50) NULL DEFAULT 'libre',
    -- Timestamps
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

**Valeurs de `status`** : `en_cours`, `premier_jet`, `revise`, `pret_soumission`

**Valeurs de `structure_method`** : `libre`, `freytag`, `voyage_heros`, `save_the_cat`, `sept_points`, `snowflake`, `actanciel`

#### Pas de table séparée pour les beats

Les beats narratifs (situation, trigger, plot_point1…) sont des colonnes directes dans `synopsis`. Cela simplifie les requêtes et suffit pour un document 1-1 par projet.

### 2.3 Modèle PHP

**Fichier** : `src/app/modules/synopsis/models/Synopsis.php`

```
class Synopsis extends KS\Mapper
    const TABLE = 'synopsis'

    + getByProject(int $projectId): ?array
    + createForProject(int $projectId): int
    + updateFields(int $projectId, array $fields): bool
```

> Ne pas nommer `SynopsisBase` ni `BaseSynopsis` — éviter les conflits F3. Nom exact : `Synopsis`.

### 2.4 Contrôleur

**Fichier** : `src/app/modules/synopsis/controllers/SynopsisController.php`

```
class SynopsisController extends Controller

    beforeRoute()       → auth check + CSRF standard
    show($f3)           → GET /project/@pid/synopsis
                          → charge ou crée le synopsis, affiche la vue
    save($f3)           → POST /project/@pid/synopsis/save
                          → valide + persiste tous les champs, redirige
    export($f3)         → GET /project/@pid/synopsis/export
                          → génère un HTML/texte propre exportable
```

**Logique `show`** :
1. Charger le projet, vérifier `hasProjectAccess($pid)` (propriétaire + collabs).
2. Charger le synopsis via `getByProject()`.
3. Si null → créer une ligne vide via `createForProject()`.
4. Passer à la vue.

**Logique `save`** :
- Propriétaire uniquement (`isOwner($pid)`) pour écrire.
- Nettoyer les champs Quill avec `$this->cleanQuillHtml()`.
- Logguer l'activité avec `$this->logActivity($pid, 'update', 'synopsis', $synopsisId, 'Synopsis mis à jour')`.

### 2.5 Routes à ajouter dans `config.ini`

```ini
; Synopsis
GET  /project/@pid/synopsis          = SynopsisController->show
POST /project/@pid/synopsis/save     = SynopsisController->save
GET  /project/@pid/synopsis/export   = SynopsisController->export
```

### 2.6 Vues

**Répertoire** : `src/app/modules/synopsis/views/synopsis/`

#### `edit.html` — page principale

Structure de la page :

```
[En-tête : titre projet + breadcrumb]

[Onglets ou sections accordéon]
  ├── 1. Fiche éditeur (méta)
  │       genre / sous-genre / audience / ton
  │       thèmes (tags input)
  │       comparables
  │       statut (select)
  │       méthode structurelle choisie (select → affiche aide contextuelle)
  │
  ├── 2. Accroche
  │       logline (textarea simple, 1–2 phrases, compteur de caractères)
  │       pitch   (Quill léger, 1–2 paragraphes)
  │
  ├── 3. Structure narrative (beats)
  │       → chaque beat = label + aide contextuelle + Quill editor
  │       Situation initiale
  │       Élément déclencheur
  │       Premier tournant
  │       Développement (acte II)
  │       Point médian
  │       Crise
  │       Climax / Résolution
  │       Situation finale
  │
  └── [Boutons : Enregistrer | Exporter | Retour au projet]
```

**Aide contextuelle** : lorsque l'utilisateur survole le label d'un beat, une infobulle (tooltip CSS) affiche la définition et un exemple du type de contenu attendu.

**Méthode structurelle** : le select « méthode » affiche un encadré de référence (texte statique) qui décrit les étapes de la méthode choisie. Cela guide l'auteur sans imposer.

**Compteur logline** : affichage live `XX / 200 caractères` en JS vanilla.

#### `export.html` — vue d'export

Rendu propre, sans formulaire, sans UI. Contient le synopsis formaté en texte lisible, imprimable. Lien « Imprimer » + lien « Télécharger en .txt ».

### 2.7 CSS

**Fichier** : `src/public/css/modules/synopsis.css`

Importé dans `src/public/style.css` avec `@import 'css/modules/synopsis.css';`

Classes prévues :
- `.synopsis-layout` — grille générale
- `.synopsis-meta-grid` — grille 2 colonnes pour les méta-champs
- `.synopsis-beat` — section d'un beat narratif
- `.synopsis-beat__label` — titre du beat avec badge numéroté
- `.synopsis-beat__hint` — infobulle contextuelle
- `.synopsis-logline-counter` — compteur de caractères

### 2.8 Intégration dans la fiche projet

Dans `src/app/modules/project/views/project/show.html` (ou équivalent), ajouter une carte ou un bouton dans la section des outils :

```html
<a href="{{ @base }}/project/{{ @project.id }}/synopsis" class="btn btn-outline">
    Synopsis
</a>
```

### 2.9 Intégration dans la navigation latérale

Si une sidebar de projet existe, ajouter l'entrée après « Sections » ou dans un groupe « Outils ».

### 2.10 API (optionnel — phase 2)

Endpoints à ajouter dans `ApiController` une fois le module stable :

```
GET  /api/v1/project/@pid/synopsis   → getSynopsis
PUT  /api/v1/project/@pid/synopsis   → updateSynopsis
```

### 2.11 Accès collaborateurs

| Action | Accès |
|---|---|
| Lire le synopsis | `hasProjectAccess()` (propriétaire + collabs acceptés) |
| Modifier le synopsis | `isOwner()` uniquement |
| Exporter | `hasProjectAccess()` |

---

## 3. Fonctions IA du module Synopsis

### 3.1 Vue d'ensemble des fonctions IA

Six fonctions IA sont prévues, réparties en deux catégories :

**Génération** (produire un synopsis de zéro ou depuis le contenu existant) :
1. `generateFromIdea` — synopsis complet depuis quelques lignes d'idée
2. `generateFromProject` — synopsis complet depuis les données du projet existant
3. `generateBeat` — générer ou regénérer un beat individuel

**Amélioration** (travailler sur un synopsis déjà partiellement rempli) :
4. `suggestLogline` — proposer 3 loglines alternatives
5. `evaluateSynopsis` — analyser et critiquer la cohérence narrative
6. `enrichBeat` — développer / approfondir un beat rédigé

---

### 3.2 Localisation du code IA

**Choix architectural** : les fonctions IA synopsis vivent dans `AiController` (comme `summarizeChapter`, `summarizeAct`), **pas** dans `SynopsisController`. Raisons :
- le provider/modèle/config est centralisé dans `AiController`
- le rate limit `'ai_gen'` est partagé avec toutes les autres fonctions IA
- `logAiUsage()` et `getUserConfig()` sont déjà disponibles là

Routes à ajouter dans `config.ini` :

```ini
POST /ai/synopsis/generate-from-idea    = AiController->synopsisFromIdea
POST /ai/synopsis/generate-from-project = AiController->synopsisFromProject
POST /ai/synopsis/generate-beat         = AiController->synopsisGenerateBeat
POST /ai/synopsis/suggest-logline       = AiController->synopsisSuggestLogline
POST /ai/synopsis/evaluate              = AiController->synopsisEvaluate
POST /ai/synopsis/enrich-beat           = AiController->synopsisEnrichBeat
```

---

### 3.3 Fonction 1 — `synopsisFromIdea` : générer depuis une idée

**Cas d'usage** : l'auteur a une idée en quelques lignes ("une détective rurale découvre que son frère est un tueur en série") et veut un synopsis structuré complet pour démarrer.

**Payload JSON** :
```json
{
  "idea": "texte libre de l'idée, 2 à 20 lignes",
  "genre": "thriller",
  "audience": "adulte",
  "structure_method": "save_the_cat",
  "project_id": 42
}
```

**Logique PHP** :
1. Rate limit `'ai_gen', 10, 60`
2. Valider que `project_id` appartient à l'utilisateur (guard IDOR)
3. Construire un prompt système expert en écriture de synopsis
4. Construire un prompt utilisateur avec l'idée + genre + méthode
5. Demander à l'IA un JSON structuré avec tous les beats
6. Valider la réponse JSON, renvoyer au client
7. `logAiUsage($model, $prompt_tokens, $completion_tokens, 'synopsis_from_idea')`

**Format de réponse IA demandé (JSON)** :
```json
{
  "logline": "...",
  "pitch": "...",
  "situation": "...",
  "trigger_evt": "...",
  "plot_point1": "...",
  "development": "...",
  "midpoint": "...",
  "crisis": "...",
  "climax": "...",
  "resolution": "..."
}
```

**Prompt système** (base, à adapter dans `ai_prompts.json`) :
```
Tu es un expert en dramaturgie et en écriture de romans. Tu maîtrises les structures narratives
(Save the Cat, Voyage du Héros, Freytag, etc.). Tu génères des synopsis professionnels
complets, révélant l'intrigue du début à la fin. Tu réponds UNIQUEMENT en JSON valide,
sans markdown, sans texte en dehors du JSON.
```

**Prompt utilisateur** :
```
Génère un synopsis complet de roman à partir de cette idée :
[IDÉE]
{idea}

Genre : {genre}
Public cible : {audience}
Structure narrative à suivre : {structure_method}

Remplis chaque section du synopsis. Sois précis, révèle la fin.
Réponds en JSON avec les clés : logline, pitch, situation, trigger_evt,
plot_point1, development, midpoint, crisis, climax, resolution.
```

**maxTokens** : 1800 (synopsis complet = long)
**temperature** : 0.8 (créativité souhaitée)

**Comportement côté client** :
- Modal avec une `<textarea>` pour l'idée + selects genre/audience/méthode
- Bouton « Générer »
- Spinner pendant la requête
- Les champs générés **pré-remplissent** les zones de la page sans écraser automatiquement — l'utilisateur voit une prévisualisation et clique « Appliquer » ou « Ignorer » champ par champ
- Alternative plus simple : remplissage direct avec confirmation globale

---

### 3.4 Fonction 2 — `synopsisFromProject` : générer depuis le projet existant

**Cas d'usage** : l'auteur a déjà écrit des chapitres, des fiches personnages, des actes. Il veut que l'IA lise tout ça et rédige un synopsis cohérent avec ce qui existe.

**Payload JSON** :
```json
{
  "project_id": 42,
  "structure_method": "libre"
}
```

**Logique PHP** :
1. Rate limit `'ai_gen', 10, 60`
2. Vérifier `isOwner($pid)` (données sensibles = écriture)
3. **Collecter le contexte du projet** depuis la DB :
   - Titre + description du projet
   - Actes (titres + résumés si disponibles)
   - Chapitres : titre + `resume` (si présent), sinon 300 premiers chars de `content` stripped
   - Personnages : nom + description (200 chars max chacun)
   - Sections utiles : synopsis existant partiel, résumé, thème
4. Tronquer intelligemment : limiter à ~6000 chars de contexte total (budget tokens)
5. Appel IA → JSON structuré des beats
6. `logAiUsage(..., 'synopsis_from_project')`

**Stratégie de construction du contexte** (crucial pour la qualité) :
```
Priorité décroissante pour le remplissage du budget tokens :
  1. Résumés de chapitres existants (champ `resume`) — le plus dense
  2. Descriptions des personnages principaux (top 5 par order_index)
  3. Titres + 300 chars de contenu des chapitres sans résumé
  4. Titres des actes
  5. Description du projet
```

**Prompt utilisateur** :
```
Tu dois écrire un synopsis complet de ce roman à partir du contenu existant.
Analyse les informations ci-dessous et rédige un synopsis structuré qui reflète
fidèlement l'histoire déjà écrite. Révèle l'intrigue du début à la fin.

[PROJET]
Titre : {project.title}
Description : {project.description}

[PERSONNAGES PRINCIPAUX]
{characters_list}

[STRUCTURE (actes et chapitres)]
{chapters_with_summaries}

Structure narrative demandée : {structure_method}

Réponds en JSON avec les clés : logline, pitch, situation, trigger_evt,
plot_point1, development, midpoint, crisis, climax, resolution.
```

**maxTokens** : 2000
**temperature** : 0.6 (fidélité au contenu existant, moins d'invention)

**Point d'attention** : si le projet a peu de contenu (< 3 chapitres), afficher un avertissement côté client "Votre projet contient peu de contenu. Le synopsis généré sera approximatif."

---

### 3.5 Fonction 3 — `synopsisGenerateBeat` : générer un beat individuel

**Cas d'usage** : l'auteur a rempli plusieurs beats mais bloque sur le "développement" ou la "crise". Il veut de l'aide sur un seul champ.

**Payload JSON** :
```json
{
  "project_id": 42,
  "beat": "development",
  "synopsis_context": {
    "logline": "...",
    "situation": "...",
    "trigger_evt": "..."
  }
}
```

**Logique PHP** :
- Valider que `beat` est une valeur autorisée : `logline|pitch|situation|trigger_evt|plot_point1|development|midpoint|crisis|climax|resolution`
- Passer le contexte des autres beats déjà remplis pour assurer la cohérence
- Réponse : `{ "beat": "development", "content": "..." }` (texte seul, pas JSON multi-clés)

**maxTokens** : 600
**temperature** : 0.75

---

### 3.6 Fonction 4 — `synopsisSuggestLogline` : proposer des loglines

**Cas d'usage** : l'auteur a rédigé son synopsis mais sa logline est faible ou absente. Il veut 3 propositions percutantes.

**Payload JSON** :
```json
{
  "project_id": 42,
  "synopsis_context": "pitch + situation + climax concaténés (3000 chars max)"
}
```

**Logique PHP** :
- Appel IA demandant 3 loglines au format : `["logline 1", "logline 2", "logline 3"]`
- Retourner le tableau JSON

**Prompt système** :
```
Tu es spécialiste de la logline cinématographique et romanesque.
Une logline = 1 phrase : personnage + objectif + obstacle + enjeu.
Tu génères des loglines percutantes, sans clichés, qui accrochent un éditeur.
Réponds UNIQUEMENT par un tableau JSON de 3 chaînes, sans autre texte.
```

**maxTokens** : 300
**temperature** : 0.9 (créativité maximale)

**Comportement côté client** : bouton "Proposer des loglines" sous le champ logline → modal avec 3 propositions → clic sur l'une l'insère dans le champ.

---

### 3.7 Fonction 5 — `synopsisEvaluate` : évaluer la cohérence narrative

**Cas d'usage** : l'auteur a rédigé tous ses beats et veut un regard critique avant de soumettre à un éditeur.

**Payload JSON** :
```json
{
  "project_id": 42,
  "synopsis": {
    "logline": "...",
    "pitch": "...",
    "situation": "...",
    ... (tous les beats)
  }
}
```

**Logique PHP** :
- Rate limit plus strict : `'ai_eval', 3, 60` (analyse consomme plus de tokens)
- Construire le synopsis complet en texte
- Demander à l'IA une évaluation structurée

**Format de réponse IA demandé** :
```json
{
  "score_global": 7,
  "points_forts": ["...", "..."],
  "points_faibles": ["...", "..."],
  "incoherences": ["...", "..."],
  "suggestions": ["...", "..."],
  "logline_evaluation": "...",
  "verdict": "..."
}
```

**Prompt système** :
```
Tu es un éditeur littéraire expérimenté et un lecteur bêta exigeant.
Tu évalues des synopsis de romans avec un regard professionnel et bienveillant.
Tu identifies les incohérences narratives, les arcs incomplets, les personnages
sous-développés, et les problèmes de rythme. Tu donnes des conseils actionnables.
Réponds UNIQUEMENT en JSON valide.
```

**maxTokens** : 1000
**temperature** : 0.4 (analyse = précision > créativité)

**Comportement côté client** : bouton "Évaluer le synopsis" → loader → affichage dans un panneau latéral ou modal avec score visuel (étoiles ou barre de progression), listes à puces par catégorie.

---

### 3.8 Fonction 6 — `synopsisEnrichBeat` : enrichir / développer un beat

**Cas d'usage** : l'auteur a une version courte d'un beat ("Le héros retrouve son père") et veut que l'IA le développe narrativement avec plus de détails, de tension, de nuance.

**Payload JSON** :
```json
{
  "project_id": 42,
  "beat": "crisis",
  "current_content": "Le héros retrouve son père...",
  "synopsis_context": "logline + situation (résumé court)"
}
```

**Logique PHP** :
- Similaire à `generateBeat` mais avec le texte existant comme base
- Instruction : enrichir sans dénaturer, garder les faits existants

**Prompt utilisateur** :
```
Voici le contenu actuel de la section "{beat_label}" d'un synopsis :

[CONTENU ACTUEL]
{current_content}

[CONTEXTE DU ROMAN]
{synopsis_context}

Développe ce passage en le rendant plus précis, plus tendu et plus vivant.
Conserve tous les éléments narratifs existants. Ajoute des détails concrets,
des enjeux émotionnels et de la tension dramatique. 2 à 4 paragraphes maximum.
```

**maxTokens** : 500
**temperature** : 0.7

---

### 3.9 Prompts à ajouter dans `src/app/ai_prompts.json`

```json
{
  "synopsis_system": "Tu es un expert en dramaturgie et en écriture de synopsis de romans...",
  "synopsis_from_idea": "Génère un synopsis complet de roman à partir de cette idée...",
  "synopsis_from_project": "Écris un synopsis fidèle à partir du contenu du projet...",
  "synopsis_generate_beat": "Génère la section {beat} du synopsis en cohérence avec les autres éléments...",
  "synopsis_suggest_logline": "Propose 3 loglines percutantes pour ce roman...",
  "synopsis_evaluate": "Évalue ce synopsis de façon professionnelle et bienveillante...",
  "synopsis_enrich_beat": "Enrichis ce passage de synopsis en conservant les éléments existants..."
}
```

Ces clés sont surchargeable par l'utilisateur via sa config IA (`data/{email}/ai_config.json`), exactement comme `summarize_chapter`.

---

### 3.10 Interface : boutons IA dans la vue `edit.html`

Chaque zone IA est matérialisée par un bouton discret adjacent au champ concerné, suivant le style existant dans les vues chapitre/acte.

```
[En-tête de la page]
  ├── Bouton principal : ✨ Générer depuis une idée       → modal idée → synopsisFromIdea
  └── Bouton principal : ✨ Générer depuis le projet      → confirm → synopsisFromProject

[Section Accroche]
  ├── Champ logline  [💡 Proposer des loglines]            → synopsisSuggestLogline
  └── Champ pitch    [✨ Générer]                          → synopsisGenerateBeat beat=pitch

[Chaque beat narratif]
  └── [✨ Générer ce beat] [🔧 Enrichir]                  → synopsisGenerateBeat / synopsisEnrichBeat

[Pied de page du formulaire]
  └── [📊 Évaluer le synopsis]                            → synopsisEvaluate → panneau résultat
```

**Règle UX** : les boutons IA sont désactivés si l'utilisateur n'a pas configuré son provider IA (vérifier `@aiSystemPrompt` injecté par le contrôleur de base).

---

### 3.11 Points de vigilance spécifiques à l'IA

| Risque | Mitigation |
|---|---|
| Réponse IA pas du JSON valide | `json_decode()` + vérification ; si échec → retourner `success: false` avec le texte brut pour debug |
| Hallucination (inventions incohérentes avec le projet) | Pour `fromProject`, limiter la température à 0.6 et formuler "reste fidèle au contenu fourni" |
| Trop de tokens (projet long) | Tronquer le contexte chapitres à 300 chars/chapitre, résumés à 600 chars, total ≤ 6000 chars |
| Rate limit partagé | Les fonctions synopsis partagent le bucket `'ai_gen'` (10 req/60s) — sauf `evaluate` qui a son propre bucket `'ai_eval'` (3/60s) |
| Écrasement accidentel | La génération complète (`fromIdea`, `fromProject`) ne doit **jamais** sauvegarder directement — elle pré-remplit avec confirmation utilisateur |
| Budget tokens `synopsisFromProject` | Logguer séparément pour permettre un suivi de la consommation de cette fonction gourmande |

---

## 4. Plan d'implémentation (ordre recommandé)

```
Étape 1 — Migration DB
  → Créer src/data/migrations/026_synopsis.sql

Étape 2 — Modèle
  → src/app/modules/synopsis/models/Synopsis.php

Étape 3 — Contrôleur CRUD
  → src/app/modules/synopsis/controllers/SynopsisController.php
  → méthodes : show(), save(), export()

Étape 4 — Fonctions IA dans AiController
  → Ajouter dans src/app/modules/ai/controllers/AiController.php :
    synopsisFromIdea(), synopsisFromProject(), synopsisGenerateBeat(),
    synopsisSuggestLogline(), synopsisEvaluate(), synopsisEnrichBeat()
  → Ajouter prompts dans src/app/ai_prompts.json

Étape 5 — Vue principale
  → src/app/modules/synopsis/views/synopsis/edit.html
  → Intégrer les boutons IA et les appels JS fetch()

Étape 6 — Vue export
  → src/app/modules/synopsis/views/synopsis/export.html

Étape 7 — CSS
  → src/public/css/modules/synopsis.css
  → ajouter @import dans src/public/style.css

Étape 8 — Config
  → Ajouter routes CRUD synopsis + 6 routes IA dans src/app/config.ini
  → Ajouter UI path + AUTOLOAD path dans src/app/config.ini

Étape 9 — Intégration
  → Ajouter bouton dans la fiche projet (show.html)
  → Incrémenter ?v= dans main.html
```

---

## 5. Points de vigilance techniques

| Risque | Mitigation |
|---|---|
| Quill instancié N fois (1 par beat) | Utiliser `QuillTools.getToolbarOptions()` pour chaque instance, jamais l'objet partagé |
| CRLF sur Windows | Vérifier les LF après création des fichiers JS/PHP |
| Nommage du modèle | `Synopsis` est sûr (non réservé par F3) |
| Champs TEXT vides vs NULL | Toujours traiter `null` et `''` de façon identique côté PHP et JS |
| Multiple Quill + sauvegarde | Collecter `.root.innerHTML` de chaque instance avant submit, l'injecter dans des `<input type="hidden">` |
| Auto-save | Non prévu en v1 pour rester simple — ajouter en v2 si besoin |
| Longueur logline | Limiter à 500 chars côté SQL (VARCHAR) et afficher compteur côté JS |
| Collab : write guard | `isOwner()` dans `save()`, pas `hasProjectAccess()` |
| Réponse IA pas du JSON valide | `json_decode()` + vérification ; retourner le texte brut si échec |
| Écrasement accidentel par l'IA | `fromIdea` et `fromProject` pré-remplissent sans sauvegarder — confirmation utilisateur obligatoire |
| Budget tokens `fromProject` | Tronquer : 300 chars/chapitre, 600 chars/résumé, total ≤ 6000 chars |

---

## 6. Intégration export, mindmap, lecture et relecture

### 6.1 Colonne `is_exported` dans la table synopsis

Ajouter à la migration `026_synopsis.sql` :

```sql
is_exported  TINYINT(1) NOT NULL DEFAULT 1,
```

**Valeur par défaut `1`** : le synopsis est coché pour export dès sa création.

Toggle via un bouton dans l'interface du synopsis (case à cocher ou switch), enregistré via :

```ini
POST /project/@pid/synopsis/toggle-export = SynopsisController->toggleExport
```

La méthode `toggleExport` fait un `UPDATE synopsis SET is_exported = ? WHERE project_id = ?` et répond en JSON `{"is_exported": 0|1}` — même pattern que `ProjectContentController->toggleExport` pour les chapitres.

---

### 6.2 Export global — `ProjectExportController::generateExportContent()`

Le synopsis s'insère **en tête de document**, juste après l'en-tête du projet (titre + auteur), avant les sections "before" et les chapitres. Cela correspond à la position naturelle d'un synopsis dans un manuscrit soumis à un éditeur.

**Condition d'inclusion** : `$synopsis['is_exported'] == 1` ET le synopsis a au minimum `logline` ou `pitch` non vide.

**Modifications à apporter dans `generateExportContent()`** :

1. Charger le synopsis après le chargement des sections :
```php
$synopsisModel = new Synopsis();
$synopsis = $synopsisModel->getByProject($pid);
```

2. Insérer le bloc synopsis dans la boucle de génération, **avant** `$templateElements` :

#### Format TXT (`txt`)
```
SYNOPSIS

[LOGLINE]
{logline}

[ACCROCHE]
{pitch — strip_tags}

[STRUCTURE NARRATIVE]
Situation initiale : {situation}
Élément déclencheur : {trigger_evt}
Premier tournant : {plot_point1}
Développement : {development}
Point médian : {midpoint}
Crise : {crisis}
Climax : {climax}
Résolution : {resolution}

---
```

#### Format HTML (`html`)
```html
<div class="page-break"></div>
<h2>Synopsis</h2>
<div class="synopsis-export">
  <p class="synopsis-logline"><em>{logline}</em></p>
  <h3>Accroche</h3>
  <div class="section-content">{pitch}</div>
  <h3>Structure narrative</h3>
  <dl>
    <dt>Situation initiale</dt><dd>{situation}</dd>
    <dt>Élément déclencheur</dt><dd>{trigger_evt}</dd>
    <!-- … tous les beats … -->
  </dl>
</div>
```

#### Format Markdown (`markdown`)
```markdown
## Synopsis

> {logline}

### Accroche
{pitch — htmlToMarkdown()}

### Structure narrative

**Situation initiale**
{situation — htmlToMarkdown()}

**Élément déclencheur**
{trigger_evt}
…
```

#### Format EPUB (`generateEpub()`)
Le synopsis forme un **chapitre EPUB dédié** intitulé "Synopsis", inséré comme premier élément du `<spine>` après la couverture. Utiliser le même mécanisme que les sections "before".

#### Format `summaries`
Inclure uniquement logline + pitch (les plus synthétiques) :
```
SYNOPSIS
{logline}
{pitch — strip_tags}
```

#### Format `vector` / `clean`
Inclure logline + development en texte brut normalisé (lowercase, strip_tags), comme pour les autres blocs.

---

### 6.3 Mindmap — `ProjectMindmapController::mindmap()`

Le synopsis apparaît comme un **nœud unique** rattaché directement à `'project'`, sans groupe intermédiaire (contrairement aux notes ou sections qui ont un groupe parent).

**Position dans le graphe** : à droite du nœud projet, ajouté en premier dans la boucle droite, avant les sections et chapitres.

**Condition** : `$synopsis !== null && $synopsis['is_exported'] == 1 && (!empty($synopsis['logline']) || !empty($synopsis['pitch']))`.

**Nœud à ajouter** :
```php
$nodes[] = [
    'id'          => 'synopsis',
    'name'        => 'Synopsis',
    'type'        => 'synopsis',
    'description' => strip_tags($synopsis['logline'] ?? ''),
    'content'     => strip_tags($synopsis['pitch'] ?? $synopsis['development'] ?? '')
];
$links[] = ['source' => 'project', 'target' => 'synopsis'];
```

**Type de nœud `'synopsis'`** : ajouter un style dédié dans le JS/CSS de la mindmap. Le nœud doit avoir une couleur et une icône distinctes (ex : parchemin ou ✦). La vue mindmap est dans `src/app/modules/project/views/project/mindmap.html` — ajouter le style du nœud `type === 'synopsis'` dans le code de rendu D3/force graph.

---

### 6.4 Mode lecture — `LectureController::read()`

Le synopsis apparaît comme **premier élément de `$readingContent`**, avant les sections "before" et les chapitres, présenté dans un cadre visuellement distinct (pas une page de roman, mais un document de travail).

**Condition** : `$synopsis !== null && $synopsis['is_exported'] == 1`.

**Bloc à insérer en tête de `$readingContent`** :
```php
$synopsisModel = new Synopsis();
$synopsis = $synopsisModel->getByProject($pid);

if ($synopsis && ($synopsis['is_exported'] ?? 0)) {
    $readingContent[] = [
        'type'    => 'synopsis',
        'title'   => 'Synopsis',
        'logline' => $prepareContent($synopsis['logline'] ?? ''),
        'pitch'   => $prepareContent($synopsis['pitch'] ?? ''),
        'beats'   => [
            'Situation initiale'  => $prepareContent($synopsis['situation'] ?? ''),
            'Élément déclencheur' => $prepareContent($synopsis['trigger_evt'] ?? ''),
            'Premier tournant'    => $prepareContent($synopsis['plot_point1'] ?? ''),
            'Développement'       => $prepareContent($synopsis['development'] ?? ''),
            'Point médian'        => $prepareContent($synopsis['midpoint'] ?? ''),
            'Crise'               => $prepareContent($synopsis['crisis'] ?? ''),
            'Climax'              => $prepareContent($synopsis['climax'] ?? ''),
            'Résolution'          => $prepareContent($synopsis['resolution'] ?? ''),
        ]
    ];
}
```

**Rendu dans la vue lecture** (`src/app/modules/lecture/views/lecture/read.html`) :

Ajouter un bloc conditionnel en tête de la boucle `$readingContent` :
```html
<check if="{{ @item.type == 'synopsis' }}">
  <section class="reading-synopsis">
    <h2 class="synopsis-reading-title">Synopsis</h2>
    <p class="synopsis-logline-reading">{{ @item.logline | raw }}</p>
    <div class="synopsis-pitch-reading">{{ @item.pitch | raw }}</div>
    <details class="synopsis-beats-reading">
      <summary>Structure narrative complète</summary>
      <!-- itérer sur @item.beats -->
    </details>
  </section>
</check>
```

Le synopsis dans la lecture utilise `<details>/<summary>` pour masquer les beats détaillés par défaut — l'auteur peut déplier s'il le souhaite, sans casser l'immersion de la lecture linéaire.

---

### 6.5 Mode relecture — `ReviewController`

Même principe que la lecture. Charger le synopsis et l'insérer en tête du contenu de relecture.

Dans la vue relecture, le synopsis peut être affiché en mode **lecture seule compacte** (logline + pitch uniquement, sans les beats détaillés), avec un lien "Modifier le synopsis" pointant vers `/project/{pid}/synopsis`.

**Modification requise** : ajouter le chargement de `Synopsis` dans `ReviewController::review()`, même pattern que pour `LectureController`.

---

### 6.6 Partage public — `SharePublicController`

Si le projet est partagé via un token (`/s/@token/@pid/lecture`), le synopsis suit le flag `is_exported` exactement comme en mode lecture authentifié.

Aucune modification spécifique — `SharePublicController->lecture()` appelle le même rendu que `LectureController->read()` ou partage la même logique de construction du contenu. Vérifier si `SharePublicController` construit sa propre liste `$readingContent` (auquel cas ajouter le bloc synopsis) ou délègue à `LectureController`.

---

### 6.7 Résumé des points d'intégration

| Fonctionnalité | Fichier à modifier | Ce qui change |
|---|---|---|
| Toggle export synopsis | `SynopsisController` (nouvelle méthode) | `UPDATE synopsis SET is_exported` |
| Export TXT/HTML/MD/EPUB | `ProjectExportController` (+ base `ProjectBaseController`) | Insérer bloc synopsis en tête |
| Export EPUB | `ProjectExportController::generateEpub()` | Chapitre EPUB dédié en premier |
| Mindmap | `ProjectMindmapController::mindmap()` | Nœud `type=synopsis` lié à `project` |
| Rendu mindmap | `project/mindmap.html` (JS) | Style nœud `synopsis` |
| Mode lecture | `LectureController::read()` | Insérer en tête de `$readingContent` |
| Vue lecture | `lecture/views/read.html` | Bloc `<check if type==synopsis>` |
| Mode relecture | `ReviewController::review()` | Même ajout que lecture |
| Vue relecture | `relecture/views/...` | Affichage compact logline+pitch |
| Partage public | `SharePublicController` | Vérifier si délégation ou copie |

---

## 7. Questions ouvertes à trancher avant implémentation

1. **Interface beats** : onglets horizontaux ou accordéon vertical ? (accordéon préférable pour les longs textes).
2. **Quill sur tous les beats ?** Beats courts (logline, déclencheur) = `<textarea>` simple ; Quill uniquement sur `development`, `pitch`, `climax`, `resolution`.
3. **Aide par méthode** : JSON statique avec les étapes de chaque méthode pour adapter dynamiquement les labels des beats ?
4. **Versioning** : historique des versions du synopsis comme pour les chapitres ?
5. **IA : confirmation beat par beat ou globale ?** Pour `fromIdea`/`fromProject`, proposer un aperçu complet avec "Appliquer tout" ou permettre d'accepter beat par beat.
6. **IA désactivée sans config** : bloquer les boutons IA ou afficher un message de redirection vers `/ai/config` ?
7. **Position dans l'export EPUB** : avant ou après la couverture ? (recommandé : après la couverture, avant le premier chapitre).
8. **Synopsis dans la TOC lecture** : apparaître dans la table des matières du mode lecture avec un style différent des chapitres ?
9. **`SharePublicController`** : construit-il sa propre liste de contenu ou délègue-t-il à `LectureController` ? (À vérifier dans le code avant implémentation).

---

## 8. Estimation des fichiers à créer/modifier

### Fichiers à créer (6)

| Fichier | Contenu |
|---|---|
| `src/data/migrations/026_synopsis.sql` | Table `synopsis` avec `is_exported` |
| `src/app/modules/synopsis/models/Synopsis.php` | Modèle KS\Mapper |
| `src/app/modules/synopsis/controllers/SynopsisController.php` | CRUD + toggleExport |
| `src/app/modules/synopsis/views/synopsis/edit.html` | Formulaire beats + boutons IA |
| `src/app/modules/synopsis/views/synopsis/export.html` | Vue export dédiée |
| `src/public/css/modules/synopsis.css` | Styles synopsis + lecture |

### Fichiers à modifier (11)

| Fichier | Modification |
|---|---|
| `src/app/modules/ai/controllers/AiController.php` | + 6 méthodes IA synopsis |
| `src/app/ai_prompts.json` | + 7 clés de prompts |
| `src/app/modules/project/controllers/ProjectExportController.php` | Insérer bloc synopsis dans `generateExportContent()` + EPUB |
| `src/app/modules/project/controllers/ProjectMindmapController.php` | Nœud synopsis dans le graphe |
| `src/app/modules/project/views/project/mindmap.html` | Style nœud `type=synopsis` (JS) |
| `src/app/modules/lecture/controllers/LectureController.php` | Charger synopsis + insérer en tête de `$readingContent` |
| `src/app/modules/lecture/views/lecture/read.html` | Bloc rendu synopsis `<details>` |
| `src/app/modules/stats/controllers/ReviewController.php` | Charger synopsis (ou équivalent relecture) |
| `src/public/style.css` | `@import 'css/modules/synopsis.css'` |
| `src/app/config.ini` | Routes + UI path + AUTOLOAD path |
| `src/app/modules/project/views/layouts/main.html` | Incrémenter `?v=` CSS/JS |

> `SharePublicController` : à vérifier — s'il construit sa propre liste de contenu pour la lecture, ajouter le synopsis ; s'il délègue à `LectureController`, aucun changement nécessaire.

**Total : 6 créations + 11 modifications.**
