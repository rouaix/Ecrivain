# Plan MCP — Écrivain

Rendre l'application accessible aux IA via le protocole MCP (Model Context Protocol).

---

## Architecture cible

```
Client IA (Claude Desktop, etc.)
        ↕  MCP Protocol (stdio ou SSE)
   Serveur MCP  [mcp/]  (Node.js + TypeScript)
        ↕  HTTP + Bearer token
   API PHP  [src/app/modules/api/]
        ↕  SQL
      MySQL (base existante)
```

---

## Format des réponses MCP

**Les outils MCP retournent du Markdown**, pas du JSON brut.
Le serveur MCP reçoit du JSON depuis l'API PHP, le convertit en Markdown lisible, et le retourne au client IA.

Exemples :

```markdown
# Mon roman
**Description** : Un thriller psychologique.
**Mis à jour** : 2026-03-01

## Structure
### Acte I — Le commencement
- Chapitre 1 : L'arrivée (1 240 mots)
- Chapitre 2 : La rencontre (890 mots)

### Acte II — Le nœud
- Chapitre 3 : La trahison (2 100 mots)
```

Les erreurs sont retournées sous forme de texte simple :
```
Erreur 404 : Projet introuvable (id=99)
```

---

## Phase 1 — API REST PHP

### 1.1 Authentification Bearer

**Fichier** : `src/app/controllers/Controller.php`

- [ ] Lire le header `Authorization: Bearer <token>` en complément du `?token=` existant
- [ ] Extraire et valider le token via la logique JWT déjà présente
- [ ] Si token valide, initialiser le contexte utilisateur sans démarrer de session PHP
- [ ] Ne pas casser l'authentification session existante

---

### 1.2 Nouveau module API

**Répertoire** : `src/app/modules/api/controllers/`
**Classe** : `ApiController` (étend `Controller`)

Toutes les routes `/api/*` :
- Requièrent un Bearer token valide
- Retournent du JSON (traité en Markdown par le serveur MCP)
- Ne font jamais de redirect
- Utilisent des codes HTTP appropriés (200, 201, 400, 403, 404, 422, 500)

---

### 1.3 Routes API — `src/app/config.ini`

```ini
; ── PROJECTS ──────────────────────────────────────────────
GET  /api/projects                   = ApiController->listProjects
POST /api/projects                   = ApiController->createProject
GET  /api/project/@id                = ApiController->getProject
PUT  /api/project/@id                = ApiController->updateProject
DELETE /api/project/@id              = ApiController->deleteProject

; ── ACTS ──────────────────────────────────────────────────
GET  /api/project/@pid/acts          = ApiController->listActs
POST /api/project/@pid/acts          = ApiController->createAct
GET  /api/act/@id                    = ApiController->getAct
PUT  /api/act/@id                    = ApiController->updateAct
DELETE /api/act/@id                  = ApiController->deleteAct

; ── CHAPTERS ──────────────────────────────────────────────
GET  /api/chapter/@id                = ApiController->getChapter
POST /api/project/@pid/chapters      = ApiController->createChapter
PUT  /api/chapter/@id                = ApiController->updateChapter
DELETE /api/chapter/@id              = ApiController->deleteChapter

; ── SECTIONS ──────────────────────────────────────────────
; Sections : cover, preface, introduction, prologue, postface, appendices, back_cover
GET  /api/project/@pid/sections      = ApiController->listSections
GET  /api/section/@id                = ApiController->getSection
PUT  /api/section/@id                = ApiController->updateSection
POST /api/project/@pid/sections      = ApiController->createSection
DELETE /api/section/@id              = ApiController->deleteSection

; ── NOTES ─────────────────────────────────────────────────
GET  /api/project/@pid/notes         = ApiController->listNotes
GET  /api/note/@id                   = ApiController->getNote
POST /api/project/@pid/notes         = ApiController->createNote
PUT  /api/note/@id                   = ApiController->updateNote
DELETE /api/note/@id                 = ApiController->deleteNote

; ── CHARACTERS ────────────────────────────────────────────
GET  /api/project/@pid/characters    = ApiController->listCharacters
GET  /api/character/@id              = ApiController->getCharacter
POST /api/project/@pid/characters    = ApiController->createCharacter
PUT  /api/character/@id              = ApiController->updateCharacter
DELETE /api/character/@id            = ApiController->deleteCharacter

; ── ELEMENTS (fiches templates) ───────────────────────────
GET  /api/project/@pid/elements      = ApiController->listElements
GET  /api/element/@id                = ApiController->getElement
POST /api/project/@pid/elements      = ApiController->createElement
PUT  /api/element/@id                = ApiController->updateElement
DELETE /api/element/@id              = ApiController->deleteElement

; ── IMAGES ────────────────────────────────────────────────
POST /api/project/@pid/images        = ApiController->uploadImage
GET  /api/project/@pid/images        = ApiController->listImages
DELETE /api/project/@pid/image/@fid  = ApiController->deleteImage

; ── EXPORT ────────────────────────────────────────────────
GET  /api/project/@id/export/markdown = ApiController->exportMarkdown

; ── SEARCH ────────────────────────────────────────────────
GET  /api/search                     = ApiController->search
```

---

### 1.4 Détail des formats JSON internes (API PHP → MCP)

#### Projects

**`GET /api/projects`**
```json
{
  "projects": [
    { "id": 1, "title": "Mon roman", "description": "...", "updated_at": "2026-01-15" }
  ]
}
```

**`GET /api/project/@id`**
```json
{
  "id": 1, "title": "Mon roman", "description": "...", "updated_at": "...",
  "acts": [
    {
      "id": 10, "title": "Acte I", "description": "...",
      "chapters": [
        { "id": 100, "title": "Chapitre 1", "summary": "...", "word_count": 1240 }
      ]
    }
  ],
  "sections": [
    { "id": 5, "type": "preface", "type_label": "Préface", "title": "Avant-propos" }
  ],
  "characters_count": 3,
  "notes_count": 2,
  "elements_count": 7
}
```

**`POST /api/projects`** — body :
```json
{ "title": "Nouveau projet", "description": "..." }
```

**`PUT /api/project/@id`** — body :
```json
{ "title": "...", "description": "..." }
```

#### Acts

**`GET /api/project/@pid/acts`**
```json
{
  "acts": [
    { "id": 10, "title": "Acte I", "description": "...", "order_index": 0, "chapters_count": 3 }
  ]
}
```

**`GET /api/act/@id`**
```json
{
  "id": 10, "title": "Acte I", "description": "...", "order_index": 0,
  "chapters": [
    { "id": 100, "title": "Chapitre 1", "summary": "...", "word_count": 1240 }
  ]
}
```

**`POST /api/project/@pid/acts`** — body :
```json
{ "title": "Acte II", "description": "..." }
```

**`PUT /api/act/@id`** — body :
```json
{ "title": "...", "description": "..." }
```

#### Chapters

**`GET /api/chapter/@id`**
```json
{
  "id": 100, "title": "Chapitre 1",
  "content_html": "<p>...</p>",
  "content_text": "Version texte brut sans balises HTML",
  "summary": "...",
  "word_count": 1240,
  "act_id": 10,
  "updated_at": "..."
}
```

**`POST /api/project/@pid/chapters`** — body :
```json
{ "title": "Nouveau chapitre", "act_id": 10, "content": "<p>...</p>", "summary": "..." }
```

**`PUT /api/chapter/@id`** — body :
```json
{ "title": "...", "content": "<p>...</p>", "summary": "..." }
```

> Note : la sauvegarde crée automatiquement une version (même comportement que l'UI).

#### Sections

Types valides : `cover`, `preface`, `introduction`, `prologue`, `postface`, `appendices`, `back_cover`

**`GET /api/project/@pid/sections`**
```json
{
  "sections": [
    {
      "id": 5, "type": "preface", "type_label": "Préface", "position": "before",
      "title": "Avant-propos", "has_image": false
    }
  ]
}
```

**`GET /api/section/@id`**
```json
{
  "id": 5, "type": "preface", "type_label": "Préface",
  "title": "Avant-propos",
  "content_html": "<p>...</p>",
  "content_text": "...",
  "comment": "...",
  "image_url": null
}
```

**`POST /api/project/@pid/sections`** / **`PUT /api/section/@id`** — body :
```json
{ "type": "preface", "title": "...", "content": "<p>...</p>", "comment": "..." }
```

#### Notes

**`GET /api/project/@pid/notes`**
```json
{
  "notes": [
    { "id": 20, "title": "Notes générales", "updated_at": "..." }
  ]
}
```

**`GET /api/note/@id`**
```json
{
  "id": 20, "title": "Notes générales",
  "content_html": "<p>...</p>",
  "content_text": "...",
  "updated_at": "..."
}
```

**`POST /api/project/@pid/notes`** / **`PUT /api/note/@id`** — body :
```json
{ "title": "...", "content": "<p>...</p>" }
```

#### Characters

**`GET /api/project/@pid/characters`**
```json
{
  "characters": [
    { "id": 5, "name": "Alice", "role": "protagoniste" }
  ]
}
```

**`GET /api/character/@id`**
```json
{
  "id": 5, "name": "Alice", "role": "protagoniste",
  "description": "...",
  "traits": "...",
  "notes": "...",
  "photo_url": null
}
```

**`POST /api/project/@pid/characters`** / **`PUT /api/character/@id`** — body :
```json
{ "name": "Alice", "role": "protagoniste", "description": "...", "traits": "...", "notes": "..." }
```

#### Elements (fiches template)

Les éléments sont des instances de `template_elements`. Chaque élément a un `element_type` (ex. "lieu", "objet", "timeline") défini par son template.

**`GET /api/project/@pid/elements`**
```json
{
  "elements": [
    {
      "id": 30, "title": "La forêt noire",
      "element_type": "lieu", "template_element_id": 3,
      "parent_id": null, "order_index": 0
    }
  ]
}
```

**`GET /api/element/@id`**
```json
{
  "id": 30, "title": "La forêt noire",
  "element_type": "lieu",
  "content_html": "<p>...</p>",
  "content_text": "...",
  "template_element_id": 3,
  "parent_id": null,
  "sub_elements": [
    { "id": 31, "title": "La clairière" }
  ]
}
```

**`POST /api/project/@pid/elements`** — body :
```json
{ "title": "La forêt noire", "template_element_id": 3, "parent_id": null }
```

**`PUT /api/element/@id`** — body :
```json
{ "title": "...", "content": "<p>...</p>" }
```

#### Images

**`GET /api/project/@pid/images`**
```json
{
  "images": [
    { "id": 1, "filename": "carte.jpg", "url": "/public/uploads/1/carte.jpg", "size_kb": 245 }
  ]
}
```

**`POST /api/project/@pid/images`** — multipart/form-data :
- Champ `file` : fichier image (JPEG, PNG, GIF, WebP — max 5 Mo)
- Réutilise la validation de `$this->validateImageUpload()` existante

**`DELETE /api/project/@pid/image/@fid`**
```json
{ "status": "ok" }
```

#### Export Markdown

**`GET /api/project/@id/export/markdown`**
- Retourne le fichier `.md` complet du projet (contenu existant de `ProjectExportController->exportMarkdown`)
- Réponse : texte Markdown brut (Content-Type: text/markdown)
- Le MCP passe ce contenu directement sans conversion supplémentaire

#### Search

**`GET /api/search?q=mot&pid=1`**
```json
{
  "query": "mot",
  "results": [
    { "type": "chapter", "id": 100, "title": "Chapitre 1", "excerpt": "...occurrence du **mot**..." },
    { "type": "character", "id": 5, "title": "Alice", "excerpt": "...mot..." }
  ]
}
```

---

### 1.5 Gestion des erreurs API

Format d'erreur uniforme :
```json
{ "error": "Message lisible", "code": "NOT_FOUND" }
```

| HTTP | Code | Cas |
|---|---|---|
| 401 | `UNAUTHORIZED` | Token manquant ou invalide |
| 403 | `FORBIDDEN` | Pas propriétaire de la ressource |
| 404 | `NOT_FOUND` | Ressource introuvable |
| 422 | `INVALID_INPUT` | Données invalides ou manquantes |
| 500 | `SERVER_ERROR` | Erreur interne |

---

## Phase 2 — Serveur MCP (Node.js)

### 2.1 Structure des fichiers

```
mcp/
├── package.json
├── tsconfig.json
├── .env.example              # API_URL, API_TOKEN
├── .env                      # (non versionné)
└── src/
    ├── index.ts              # Point d'entrée, déclaration serveur MCP
    ├── client.ts             # Wrapper HTTP axios vers l'API PHP
    ├── markdown.ts           # Convertisseurs JSON → Markdown
    └── tools/
        ├── projects.ts       # list_projects, get_project, create_project, update_project, delete_project
        ├── acts.ts           # list_acts, get_act, create_act, update_act, delete_act
        ├── chapters.ts       # get_chapter, create_chapter, update_chapter, delete_chapter
        ├── sections.ts       # list_sections, get_section, create_section, update_section, delete_section
        ├── notes.ts          # list_notes, get_note, create_note, update_note, delete_note
        ├── characters.ts     # list_characters, get_character, create_character, update_character, delete_character
        ├── elements.ts       # list_elements, get_element, create_element, update_element, delete_element
        ├── images.ts         # list_images, upload_image, delete_image
        ├── export.ts         # export_markdown
        └── search.ts         # search
```

### 2.2 Dépendances

```json
{
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.x",
    "axios": "^1.x",
    "dotenv": "^16.x",
    "form-data": "^4.x"
  },
  "devDependencies": {
    "typescript": "^5.x",
    "@types/node": "^20.x"
  }
}
```

### 2.3 Outils MCP exposés

#### Projets
| Outil | Description | Méthode PHP |
|---|---|---|
| `list_projects` | Liste tous les projets | `listProjects` |
| `get_project` | Détails + structure complète | `getProject` |
| `create_project` | Crée un projet | `createProject` |
| `update_project` | Modifie titre/description | `updateProject` |
| `delete_project` | Supprime un projet | `deleteProject` |

#### Actes
| Outil | Description | Méthode PHP |
|---|---|---|
| `list_acts` | Liste les actes d'un projet | `listActs` |
| `get_act` | Acte + liste de ses chapitres | `getAct` |
| `create_act` | Crée un acte | `createAct` |
| `update_act` | Modifie titre/description | `updateAct` |
| `delete_act` | Supprime un acte | `deleteAct` |

#### Chapitres
| Outil | Description | Méthode PHP |
|---|---|---|
| `get_chapter` | Contenu complet (HTML + texte brut) | `getChapter` |
| `create_chapter` | Crée un chapitre dans un acte | `createChapter` |
| `update_chapter` | Met à jour contenu/résumé (crée une version) | `updateChapter` |
| `delete_chapter` | Supprime un chapitre | `deleteChapter` |

#### Sections
| Outil | Description | Méthode PHP |
|---|---|---|
| `list_sections` | Liste les sections (préface, annexes…) | `listSections` |
| `get_section` | Contenu d'une section | `getSection` |
| `create_section` | Crée une section (type requis) | `createSection` |
| `update_section` | Met à jour une section | `updateSection` |
| `delete_section` | Supprime une section | `deleteSection` |

#### Notes
| Outil | Description | Méthode PHP |
|---|---|---|
| `list_notes` | Liste les notes d'un projet | `listNotes` |
| `get_note` | Contenu d'une note | `getNote` |
| `create_note` | Crée une note | `createNote` |
| `update_note` | Met à jour une note | `updateNote` |
| `delete_note` | Supprime une note | `deleteNote` |

#### Personnages
| Outil | Description | Méthode PHP |
|---|---|---|
| `list_characters` | Liste les personnages | `listCharacters` |
| `get_character` | Fiche complète | `getCharacter` |
| `create_character` | Crée un personnage | `createCharacter` |
| `update_character` | Met à jour une fiche | `updateCharacter` |
| `delete_character` | Supprime un personnage | `deleteCharacter` |

#### Éléments (fiches template)
| Outil | Description | Méthode PHP |
|---|---|---|
| `list_elements` | Liste les éléments (lieux, objets…) | `listElements` |
| `get_element` | Fiche complète + sous-éléments | `getElement` |
| `create_element` | Crée un élément | `createElement` |
| `update_element` | Met à jour le contenu | `updateElement` |
| `delete_element` | Supprime un élément | `deleteElement` |

#### Images
| Outil | Description | Méthode PHP |
|---|---|---|
| `list_images` | Liste les images uploadées | `listImages` |
| `upload_image` | Upload une image (chemin local) | `uploadImage` |
| `delete_image` | Supprime une image | `deleteImage` |

#### Export & Recherche
| Outil | Description | Méthode PHP |
|---|---|---|
| `export_markdown` | Exporte le projet complet en Markdown | `exportMarkdown` |
| `search` | Recherche full-text dans un projet | `search` |

### 2.4 Exemple de sortie Markdown par outil

**`list_projects`** :
```markdown
# Mes projets (2)

## 1. Mon roman *(id: 1)*
Mis à jour le 15 janvier 2026

## 2. Essai philosophique *(id: 2)*
Mis à jour le 28 février 2026
```

**`get_chapter`** :
```markdown
# Chapitre 1 — L'arrivée *(id: 100)*
**Acte** : Acte I (id: 10)
**Mots** : 1 240 | **Mis à jour** : 2026-01-20

## Résumé
Alice arrive en train dans une ville inconnue...

## Contenu
Il pleuvait ce soir-là. Alice posa sa valise...
```

**`list_characters`** :
```markdown
# Personnages du projet "Mon roman" (3)

## Alice *(id: 5)*
**Rôle** : Protagoniste

## Viktor *(id: 6)*
**Rôle** : Antagoniste

## La voisine *(id: 7)*
**Rôle** : Secondaire
```

**`export_markdown`** :
```markdown
Retourne directement le fichier Markdown complet généré par l'export existant.
```

### 2.5 Configuration Claude Desktop

```json
{
  "mcpServers": {
    "ecrivain": {
      "command": "node",
      "args": ["/chemin/vers/mcp/dist/index.js"],
      "env": {
        "API_URL": "http://localhost/ecrivain",
        "API_TOKEN": "<token généré via /auth/token/generate>"
      }
    }
  }
}
```

---

## Phase 3 — Tests & Documentation

- [ ] Tester avec `npx @modelcontextprotocol/inspector` en local
- [ ] Documenter la procédure de génération de token (via l'UI `/auth/tokens`)
- [ ] Documenter les permissions : owner vs collaborateur
- [ ] README dans `mcp/` avec exemples d'utilisation
- [ ] Vérifier que l'export Markdown existant est exploitable tel quel

---

## Points à décider

1. **Permissions d'écriture** — Un collaborateur peut-il créer/modifier du contenu via l'API ?
   - Proposition : lecture seule pour collaborateurs, écriture réservée au propriétaire

2. **Transport MCP** — `stdio` (Claude Desktop local) ou `SSE` (usage distant/serveur) ?
   - Proposition : `stdio` pour commencer, `SSE` en option future

3. **Images — source** — `upload_image` accepte-t-il un chemin local (stdio) ou une URL ?
   - Proposition : chemin local uniquement (usage stdio), l'IA lit depuis son filesystem

4. **Sections — types multiples** — `appendices` autorise plusieurs entrées ; les autres sont uniques par projet. À expliciter dans la description des outils MCP.

5. **Périmètre Phase 1** — commencer par les GET uniquement pour valider l'approche avant d'exposer les écritures ?
   - Proposition : oui, GET d'abord

---

## Ordre d'implémentation suggéré

1. `Controller.php` — support du header `Authorization: Bearer`
2. `config.ini` — déclaration des routes `/api/*`
3. `ApiController.php` — GET uniquement : `listProjects`, `getProject`, `listActs`, `getAct`, `getChapter`, `listSections`, `getSection`, `listNotes`, `getNote`, `listCharacters`, `getCharacter`, `listElements`, `getElement`, `listImages`, `exportMarkdown`, `search`
4. `mcp/` — serveur MCP minimal avec tous les outils de lecture, sorties Markdown
5. Test end-to-end avec MCP Inspector
6. `ApiController.php` — POST/PUT/DELETE pour tous les types de contenu
7. `mcp/` — outils d'écriture + `upload_image`
8. Documentation utilisateur + README `mcp/`
