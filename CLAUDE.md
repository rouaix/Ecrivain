# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- **Backend**: PHP 8.4 + Fat-Free Framework (F3) v3.6.5
- **Database**: MySQL 8.0+ (auto-migrated on startup)
- **Frontend**: Vanilla JS + Quill.js 1.3.6 (no build step, no Node.js)
- **Dependencies**: Composer only (`cd src && composer install`)
- **Entry point**: `src/www/index.php`

## Development Setup

No build commands — this is a pure PHP/Apache project.

```bash
# Install dependencies
cd src && composer install

# Create database (tables auto-created on first run)
mysql -e "CREATE DATABASE ecrivain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Configure environment
cp src/.env.example src/.env.local
# Edit DB_HOST, DB_NAME, DB_USER, DB_PASS, JWT_SECRET
```

Apache must point to the project root (using symlinks `index.php`, `.htaccess`, `public` → `src/www/`) or directly to `src/www/`. Requires `mod_rewrite` and `Options FollowSymLinks`.

Writable directories: `src/tmp/` (F3 cache), `src/logs/`, `src/public/uploads/`, `src/data/` (per-user AI configs, JWT tokens, OAuth store)

**No test suite exists in this project.**

## Architecture

### Module Structure

Each module under `src/app/modules/` follows MVC:
```
modules/{name}/
├── controllers/   # Extend base Controller
├── models/        # Extend KS\Mapper (ActiveRecord)
└── views/         # F3 template files
```

Modules: `acts`, `ai`, `api`, `auth`, `chapter`, `characters`, `collab`, `element`, `glossary`, `lecture`, `mcp`, `note`, `project`, `scenariste`, `search`, `section`, `share`, `stats`, `synopsis`, `template`

The `ai` module is split into: `AiBaseController` (shared helpers), `AiGenerateController` (text generation), `AiConfigController` (provider/API key config), `AiAnalysisController` (analysis features), `AiSynopsisController` (synopsis generation).

### Routing & Autoloading

All routes and autoload paths are declared in `src/app/config.ini`. F3 scans the declared `AUTOLOAD` paths automatically — no registration needed beyond naming the class correctly.

**Naming constraint**: Never name a model `Template`, `Base`, `View`, or `Auth` — these are reserved by F3.

### Database Access Patterns

**In controllers**: use `$this->db->exec()` — the base `Controller` exposes `$this->db` as `DB\SQL`.

**In models**: use `$this->db->exec()` — available internally via `KS\Mapper`.

**Never** access the DB via a model instance from outside: `$modelInstance->db` triggers F3's `__get()` which looks for a column named `db` and throws "champ non défini".

### Database Migrations

Migrations in `src/data/migrations/` run automatically on every app load (only unexecuted ones). Naming format: `NNN_description.sql` (alphabetical = execution order). Current highest: `030_`.

> Always check `ls src/data/migrations/ | sort | tail -3` before creating a new migration to get the correct next number.

Migration rules:
- Always use `CREATE TABLE IF NOT EXISTS`
- No `ALTER TABLE ADD COLUMN IF NOT EXISTS` (MySQL < 8.0.17 incompatible) — one column per migration
- No SQL variables (`SET @var`) — each statement runs via `PDO::exec()` independently
- To undo: create a new migration with the reverse operation

### Key Files

| File | Purpose |
|------|---------|
| `src/www/index.php` | Bootstrap: env loading, DB init, session config, CSP headers, nonce generation |
| `src/app/config.ini` | F3 routes, UI paths, autoload paths |
| `src/app/core/Migrations.php` | Auto-migration system |
| `src/app/core/ContentTransformer.php` | Centralised HTML-to-plain-text conversion (used by API, MCP, export) |
| `src/app/core/Logger.php` | Structured logging with severity levels |
| `src/app/core/TokenService.php` | Unified JWT/bearer token validation and decryption |
| `src/app/controllers/Controller.php` | Base controller (CSRF, auth checks, render, rate limiting) |
| `src/app/controllers/ApiBaseController.php` | Base for JSON endpoints — Bearer auth in `beforeRoute`, `jsonOut()`, `jsonError()`, `getBody()` |
| `src/app/services/AiPricingService.php` | AI cost calculations |
| `src/app/services/ProjectService.php` | Shared project business logic |
| `src/app/services/ProjectShowService.php` | Loads all data for `project/show.html` (word counts, panel config, section groups) |
| `src/app/services/ExportContentService.php` | Generates export content for all formats (delegated from `ProjectExportController`) |
| `src/app/services/ImageUploadService.php` | `validate()` + `move()` + `deleteOld()` — used via `Controller::validateImageUpload()` |
| `src/app/core/OrderableTrait.php` | `reorderItems()` + `getNextOrderBy()` — used by Act, Section models |
| `src/app/modules/project/controllers/ProjectBaseController.php` | Shared helpers for all project sub-controllers: `supHtml()`, `getUserFullName()`, `loadProjectTemplateElements()`, `buildPanelOrderCss()` |
| `src/app/shared/views/` | Reusable F3 partials: `_page-header.html`, `_empty-state.html` |
| `src/public/js/quill-adapter.js` | Quill editor integration (singleton QuillTools) |
| `src/public/js/offline-reader.js` | Offline reading support |
| `src/app/modules/project/views/layouts/main-pro.html` | Single unified layout — includes `pro-ui.js`, `pro/pro.css`, and `theme-bibliotheque.css` |
| `src/app/controllers/UiModeController.php` | Handles `POST /ui-mode` — currently unused in routing, layout is always `main-pro.html` |
| `src/app/modules/project/controllers/ProjectLayoutController.php` | Handles `POST /project-layout` — persists `data-layout` attribute on `<body>` (e.g. `bibliotheque`) |

### Base Controller Helpers

Available in all controllers (no import needed):

| Method | Purpose |
|--------|---------|
| `$this->render($view, $data)` | Renders view inside `layouts/main-pro.html` (the single layout); auto-injects `@base`, `@csrfToken`, `@currentUser`, `@aiSystemPrompt`, `@aiUserPrompts`, `@pendingCollabCount` |
| `$this->currentUser()` | Returns current user array or null |
| `$this->requireAuth()` | Redirect to `/login` if not authenticated |
| `$this->requireOwner(int $pid)` | Abort 403 if current user is not the project owner |
| `$this->requireProjectAccess(int $pid)` | Abort 403 if not owner and not accepted collaborator |
| `$this->isOwner(int $pid)` | True if current user owns the project |
| `$this->isCollaborator(int $pid)` | True if current user is an accepted collaborator |
| `$this->hasProjectAccess(int $pid)` | `isOwner()` OR `isCollaborator()` — use this for read/export guards |
| `$this->pendingCollabCount()` | Count of pending collaboration requests across all owned projects |
| `$this->logActivity($action, $contentType, $contentId, $details)` | Writes to `project_activity_logs` (non-blocking, inside try-catch) |
| `$this->cleanQuillHtml($html)` | Strips spurious empty `<p>` tags from Quill output |
| `$this->checkRateLimit($key, $max, $secs)` | Session-based sliding-window rate limiter |
| `$this->validateImageUpload($file, $maxMB)` | MIME + extension + magic-bytes validation |
| `$this->encryptData($data)` / `$this->decryptData($enc)` | AES-256-GCM encryption (key from `JWT_SECRET`) |
| `$this->getUserDataDir($email)` | Returns `data/{sanitized_email}/` — safe against path traversal |
| `$this->f3->get('AJAX')` | Detect AJAX request (send JSON response and `exit`) |

CSRF is validated automatically on every POST by `beforeRoute`. All POST forms must include:
```html
<input type="hidden" name="csrf_token" value="{{ @csrfToken }}">
```

### Environment Detection

Dev is auto-detected when the project path contains `Projets`. Dev loads `src/.env.local`; production loads `src/.env`. Key variables: `DB_*`, `JWT_SECRET`, `SESSION_DOMAIN`, `DEBUG` (0–3).

### AI Configuration

Per-user config stored in `src/data/{email}/ai_config.json` (AES-256-GCM encrypted). Providers: `openai`, `gemini`, `anthropic`, `mistral`. Default prompts in `src/app/ai_prompts.json`.

### Collaborative Access (collab module)

The `collab` module has **no model classes** — both controllers (`CollabInviteController`, `CollabRequestController`) use `$this->db->exec()` directly. Two DB tables: `project_collaborators` (invitations) and `collaboration_requests` (change proposals).

Access control pattern for existing controllers:
- Read / export actions: guard with `hasProjectAccess($pid)`
- Write / edit / delete actions: guard with `isOwner($pid)` (collaborators are blocked)
- Direct-write actions (e.g., `addComment()` in LectureController): remain owner-only even if reads are open to collaborators

Valid `request_type` values: `add`, `modify`, `correct`, `delete`
Valid `content_type` values: `chapter`, `act`, `section`, `note`, `element`, `character`

### API & MCP Integration

**REST API** (`src/app/modules/api/controllers/ApiController.php`): Full CRUD for all content types under `/api/...`. API controllers extend `ApiBaseController` (not `Controller`) — Bearer JWT auth is handled in its `beforeRoute`, bypassing CSRF/session. Add `api/controllers/` to `AUTOLOAD` when adding new API controllers.

**MCP server** — two modes:
- **HTTP** (`POST /mcp` via `McpController`): Same Bearer JWT auth. Configure in Claude Desktop with `"url"` + `"headers": {"Authorization": "Bearer TOKEN"}`.
- **stdio** (`src/app/modules/mcp/server.php`): Standalone PHP CLI script, reads `API_URL` and `API_TOKEN` from env. Configure in Claude Desktop with `"command": "php"` + `"args"` + `"env"`.

**JWT tokens**: Users generate personal API tokens at `/auth/tokens`. Tokens are JWT-signed (`JWT_SECRET`) and stored encrypted in `src/data/{email}/tokens.json`. Revocation deletes from that file.

### Share Module (public routes)

`SharePublicController` serves read-only project views at `/s/@token/...` with **no authentication required**. These routes must never call `$this->currentUser()` as a guard. The `share` module has its own standalone layout. Use `$this->renderPublic($view, $data)` instead of `$this->render()` — it skips auth injection and CSRF.

### OAuth2 Authorization Server (auth module)

`OAuthController` implements a full OAuth2 authorization code flow with PKCE for third-party integrations (e.g., ChatGPT, Claude plugins):
- Endpoints: `GET /oauth/authorize`, `POST /oauth/token`, `POST /oauth/register`
- Discovery: `GET /.well-known/oauth-authorization-server`, `GET /.well-known/oauth-protected-resource`
- Token lifetimes: auth code 300s, access token 3600s, refresh token 2592000s
- CORS enabled on token/register endpoints for cross-origin integrator use
- This is separate from the JWT bearer tokens used by the REST API and MCP.

### Project Overview Layout (Bibliothèque)

The project overview page (`/project/{id}`) uses a three-column layout defined in `src/app/modules/project/views/project/inc/layout-bibliotheque.html`:
- **Left rail** (`.tm-left-rail`): workspace switcher, navigation, project chip, sections nav
- **Centre** (`.tm-center`): page header with stats, toolbar row (search + action buttons), content
- **Right rail** (`.tm-right-rail`): metadata panels

The centre content is assembled by `new_body.html`, which includes all `_project_overview_*.html` partials in order.

**Panel visibility** is controlled by `@panelConfig` (built by `ProjectShowService`):
- `@panelConfig.content` — show the acts/chapters panel (also gates the toolbar title and create buttons)
- `@panelConfig.has_acts` — template has acts; show `+ Acte` button and use act-based chapter creation
- `@panelConfig.note`, `.character`, `.file`, `.scenario`, `.synopsis` — show/hide respective panels

The `data-layout` attribute on `<body>` is set from `$currentUser['theme']` (default: `bibliotheque`). `theme-bibliotheque.css` uses `body[data-layout="bibliotheque"]` selectors. Users change layout via `POST /project-layout`.

## Critical Rules

### Line Endings (Windows)

`.gitattributes` enforces LF. Files saved with CRLF cause `NS_ERROR_CORRUPTED_CONTENT` in browsers. Always verify:
```bash
git config core.autocrlf false
```
Fix CRLF files with Python if needed (see project memory notes).

### CSS Architecture

`src/public/style.css` is the single CSS entry point — it imports everything via `@import`. Never add `<link>` tags for internal CSS in views except for feature-specific overrides (e.g. `reading.css` in the lecture view).

Subdirectory layout under `src/public/css/`:
- `core/` — variables, reset, base; `media-queries.css` (tablet/desktop ≥481px), `mq-mobile.css` (≤480px, ≤767px, ≤380px)
- `layout/` — header, footer, grid
- `components/` — buttons, forms, modals, tables, cards, panels…
- `modules/` — feature styles (chapters, characters, notes…)
- `editor/` — Quill overrides
- `ai/`, `auth/` — section-specific styles
- `features/` — standalone features: `reading.css`, `reading-mode.css`, `relecture.css`, `export.css`, `mindmap.css`, `template-editor.css`, `dictation.css`
- `utilities/` — atomic helpers: `helpers.css`, `spacing.css`, `text.css`, `visibility.css`
- `themes/` — `theme-default.css`, `theme-dark.css`, `theme-blue.css`, `theme-forest.css`, `theme-moderne.css`, `theme-bibliotheque.css`
- `pro/` — Pro UI styles: `pro.css` (aggregator importing all 7 files), `pro-layout.css`, `pro-nav.css`, `pro-components.css` (base components), `pro-pages.css` (dashboard + project page), `pro-features.css` (edit pages, AI, collab, etc.), `pro-overrides.css`, `pro-polish.css`

**Modal visibility**: modals use `.is-visible` (adds `display: flex`) — never toggle `.is-hidden` on a `.modal-overlay`. Open: `modal.classList.add('is-visible')`, close: `modal.classList.remove('is-visible')`.

### JS/CSS Cache Busting

After modifying any JS or CSS file, increment the `?v=` parameter in `main-pro.html`. Current versions:
```html
<link rel="stylesheet" href="{{ @base }}/public/style.css?v=81">
<link rel="stylesheet" href="{{ @base }}/public/css/pro/pro.css?v=52">
<link rel="stylesheet" href="{{ @base }}/public/css/themes/theme-bibliotheque.css?v=29">
<script src="{{ @base }}/public/js/quill-adapter.js?v=26"></script>
<script src="{{ @base }}/public/js/api-client.js?v=3"></script>
<script src="{{ @base }}/public/js/notifications.js?v=1"></script>
<script src="{{ @base }}/public/js/pro-ui.js?v=5"></script>
```

Standalone layouts that embed their own `style.css` reference (update separately if modified):
- `src/app/modules/lecture/views/lecture/read.html` — `style.css?v=58`
- `src/app/modules/share/views/share/public/*.html` — `style.css?v=67`

### Shared View Partials

`src/app/shared/views/` contains reusable F3 fragments. Set variables before `<include>`:

```html
<!-- Page header with optional create button -->
<set pageIcon="book" />
<set pageTitle="{{ @project.title }}" />
<set backUrl="{{ @base.'/project/'.@project.id }}" />
<set createUrl="{{ @base.'/project/'.@project.id.'/chapter/create' }}" />
<set createTitle="Nouveau chapitre" />
<include href="_page-header.html" />

<!-- Empty state (wrap in <check if="...count == 0"> ) -->
<set emptyIcon="book" />
<set emptyMessage="Aucun chapitre pour l'instant." />
<set emptyCreateUrl="{{ @createUrl }}" />
<set emptyCreateLabel="Créer le premier" />
<include href="_empty-state.html" />
```

Both partials guard the create button with `@isOwner` — controllers **must** pass `'isOwner' => $this->isOwner($pid)` in `render()`.

### ApiClient (JavaScript)

`api-client.js` exports a global `ApiClient` namespace for all fetch operations. Auto-injects the CSRF token from `<meta name="csrf-token">` into every request header.

- `ApiClient.get(url, params?)` → `Promise<data>`
- `ApiClient.post(url, data)` / `ApiClient.put(url, data)` / `ApiClient.delete(url)` → `Promise<data>`
- `ApiClient.postForm(url, formElement)` → `Promise<data>` — serializes a form automatically
- Returns `{ status, message }` shape on HTTP errors; always returns a Promise.

### AppUI (JavaScript)

`api-client.js` exports a global `AppUI` namespace:

- `AppUI.confirm(message, title?)` → `Promise<boolean>` — styled modal replacing `window.confirm()`
- `AppUI.notify(message, type?, duration?)` — toast notification
- `AppUI.openModal(id)` / `AppUI.closeModal(id)` — open/close by element ID
- `AppUI.initConfirmLinks()` — auto-binds all `.js-confirm` elements (called on `DOMContentLoaded`)

**Do not add per-view `.js-confirm` handlers** — `initConfirmLinks()` already handles them globally. Adding your own creates double-binding (native confirm + modal both fire).

### Quill Instances

Multiple Quill instances must NOT share the same toolbar config object reference. Use `QuillTools.getToolbarOptions()` (returns a fresh copy) rather than `QuillTools.toolbarOptions` directly.
