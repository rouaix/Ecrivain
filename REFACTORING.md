# Proposition de refactorisation — Écrivain

> Analyse effectuée le 2026-03-17 sur la base de code courante (branche `dev`).

---

## Résumé exécutif

Le projet est **fonctionnel et globalement bien structuré** (F3, migrations auto, CSRF, AES-256-GCM, prepared statements). Les problèmes identifiés ne sont pas des blockers immédiats, mais ils freineront la maintenabilité à mesure que la base de code grossit.

**Axes principaux** :
1. Validation JWT dupliquée en 3 endroits → extraction en service
2. Accès DB inconsistant (modèles vs. `$this->db->exec()` direct) → DAO pour les modules manquants
3. Logique métier éparpillée dans les contrôleurs → couche service
4. Pas d'erreurs centralisées → handler unifié
5. JS sans couche réseau centralisée

**Fichiers les plus volumineux** (candidats prioritaires) :

| Fichier | Lignes | Problème principal |
|---------|--------|--------------------|
| `ProjectExportController.php` | 1 392 | Trop de formats d'export dans un seul contrôleur |
| `AiController.php` | ~1 621 | Pricing hard-codé (112 lignes de if/else) |
| `AuthController.php` | 1 203 | Token/session éparpillé |
| `McpController.php` | 1 129 | Conversion de format embarquée |
| `ApiController.php` | 1 068 | CRUD répétitif, pas de couche commune |

---

## 1. Sécurité

### 1.1 Triple validation JWT → `TokenService`

**Problème** : `Controller.php` possède trois chemins de validation distincts :
- Nouveau format JWT Firebase
- Ancien format base64+hex (rétrocompatibilité)
- Fallback vers `data/auth_tokens.json` (fichier global non chiffré)

Chaque chemin a sa propre logique d'erreur. Un correctif de sécurité doit être appliqué à trois endroits.

**Solution** : Créer `src/app/core/TokenService.php`.

```php
class TokenService {
    public function validate(string $raw): ?array  // retourne le payload ou null
    public function issue(array $payload, int $ttl = 3600): string
    public function revoke(string $jti, string $userEmail): void
    public function isRevoked(string $jti, string $userEmail): bool
}
```

`checkAutoLogin()` et `authenticateApiRequest()` dans `Controller` deviennent de simples délégations :

```php
protected function authenticateApiRequest(): void {
    $token = /* lire header */;
    $payload = $this->tokenService->validate($token);
    if (!$payload) { $this->jsonError('Unauthorized', 401, 'auth_failed'); }
    $_SESSION['user_id'] = $payload['uid'];
}
```

**Gain** : surface d'attaque réduite de moitié, un seul endroit à maintenir.

---

### 1.2 Token auto-login dans l'URL → POST seulement

**Problème** : `?token=xxx` dans l'URL est logué par Apache, sauvegardé dans l'historique navigateur et envoyé dans les en-têtes `Referer`.

**Solution** : Migrer vers un échange POST (form hidden ou fetch) ou des cookies `HttpOnly ; SameSite=Strict`. Le paramètre GET doit rester accepté temporairement pour les liens déjà envoyés, mais être déprécié.

---

### 1.3 Rate limiting atomique

**Problème** : Le rate limiting est basé sur `$_SESSION` avec un `array_filter` non atomique. Deux requêtes simultanées peuvent toutes deux passer si la session n'est pas encore flushée.

**Solution court terme** : Verrouillage de fichier (ou Redis si disponible) :

```php
// dans checkRateLimit()
$lockFile = sys_get_temp_dir() . '/rl_' . md5($key) . '.lock';
$fp = fopen($lockFile, 'c+');
flock($fp, LOCK_EX);
// ... lecture, incrémentation, écriture ...
flock($fp, LOCK_UN);
```

**Solution long terme** : Redis `INCR` + `EXPIRE`.

---

### 1.4 Unreachable code dans `AuthController::login()`

**Problème** : La ligne 141 (`reroute($redirectAfterLogin)`) n'est jamais atteinte car la ligne 140 (`reroute('/dashboard')`) interrompt l'exécution.

**Fix immédiat** :

```php
// Avant
$this->f3->reroute('/dashboard');          // ligne 140 — toujours exécutée
$this->f3->reroute($redirectAfterLogin ?: '/dashboard'); // ligne 141 — mort

// Après
$target = $_SESSION['post_login_redirect'] ?? '/dashboard';
unset($_SESSION['post_login_redirect']);
$this->f3->reroute($target);
```

---

## 2. Architecture PHP — couche service

### 2.1 Extraire `ProjectService`

`ProjectController::dashboard()` (~110 lignes) charge projets, projets partagés, invitations, tags, stats et objectifs dans une seule méthode. Toute modification du schéma nécessite de lire 110 lignes.

**Découpage proposé** :

```
src/app/services/
├── ProjectService.php        ← requêtes projets
├── CollabService.php         ← invitations, demandes de modification
├── AiPricingService.php      ← calcul coût IA
└── ContentTransformer.php    ← htmlToText, stripTags, sanitizeHtml
```

```php
// ProjectController::dashboard() après refactorisation
public function dashboard(): void {
    $svc = new ProjectService($this->db);
    $this->render('project/dashboard', [
        'projects'    => $svc->getOwnedProjects($user['id']),
        'shared'      => $svc->getCollaborativeProjects($user['id']),
        'invitations' => $svc->getPendingInvitations($user['id']),
    ]);
}
```

---

### 2.2 Pricing IA → fichier de configuration

**Problème** : 112 lignes de `if (strpos($model, 'gpt-4o') !== false)` dans `AiController`. Chaque nouveau modèle ou changement de tarif exige une modification de code.

**Solution** : `src/app/ai_pricing.json`

```json
{
  "openai": {
    "gpt-4o":         { "input": 2.50,  "output": 10.00, "unit": 1000000 },
    "gpt-4o-mini":    { "input": 0.15,  "output": 0.60,  "unit": 1000000 },
    "o1":             { "input": 15.00, "output": 60.00, "unit": 1000000 }
  },
  "anthropic": {
    "claude-sonnet-4-6": { "input": 3.00, "output": 15.00, "unit": 1000000 }
  }
}
```

```php
// AiPricingService.php
public function computeCost(string $provider, string $model, int $inputTokens, int $outputTokens): float {
    $table = $this->loadPricingTable();
    $rates = $this->matchModel($table[$provider] ?? [], $model);
    return ($inputTokens * $rates['input'] + $outputTokens * $rates['output']) / $rates['unit'];
}
```

**Gain** : mise à jour tarifaire sans déploiement de code.

---

### 2.3 DAO pour le module `collab`

**Problème** : Le module `collab` est explicitement documenté comme "sans modèle", mais la logique des invitations et des demandes de modification est complexe (JOINs manuels, statuts, notifications).

**Solution** : Créer des modèles légers qui étendent `KS\Mapper` :

```
src/app/modules/collab/models/
├── CollaboratorInvite.php    ← table project_collaborators
└── CollaborationRequest.php  ← table collaboration_requests
```

Les contrôleurs `CollabInviteController` et `CollabRequestController` restent identiques mais délèguent les requêtes aux modèles.

---

### 2.4 Contrôleur de base API abstrait

`ApiController` et `McpController` réimplémentent tous les deux : lecture du body JSON, authentication, envoi de réponse JSON, gestion d'erreur.

**Solution** : `src/app/controllers/ApiBaseController.php` entre `Controller` et les contrôleurs API.

```php
abstract class ApiBaseController extends Controller {
    protected function getBody(): array { /* ... */ }
    protected function jsonOut(array $data, int $status = 200): void { /* ... */ }
    protected function jsonError(string $msg, int $status, string $code): never { /* ... */ }
    protected function requireField(array $body, string ...$fields): void { /* ... */ }
}
```

---

### 2.5 Middleware de permissions

Actuellement, chaque action répète :

```php
if (!$this->isOwner($pid)) { $this->f3->error(403); return; }
```

Ce bloc apparaît dans ~30 méthodes. Avec F3 on peut déclarer des hooks `beforeRoute` au niveau de chaque contrôleur :

```php
class ChapterController extends Controller {
    public function beforeRoute(): void {
        parent::beforeRoute();
        $pid = (int) $this->f3->get('PARAMS.pid');
        if (!$this->isOwner($pid)) $this->f3->error(403);
    }
}
```

Pour les routes lecture seule (collaborateurs acceptés), utiliser `hasProjectAccess()` dans le `beforeRoute` du contrôleur concerné.

---

## 3. Qualité — réduction de la duplication

### 3.1 `ContentTransformer` (mutualisé)

Trois endroits convertissent du HTML en texte brut (`ApiController`, `ProjectExportController`, `server.php`). Extraire :

```php
// src/app/core/ContentTransformer.php
class ContentTransformer {
    public static function htmlToText(string $html): string { /* ... */ }
    public static function htmlToMarkdown(string $html): string { /* ... */ }
    public static function cleanQuillHtml(string $html): string { /* ... */ }
}
```

### 3.2 Découpage de `ProjectExportController` (1 392 lignes)

Un contrôleur ne devrait pas contenir la logique de rendu de chaque format. Proposition :

```
src/app/modules/project/export/
├── ExportStrategy.php         ← interface
├── PdfExport.php
├── DocxExport.php
├── EpubExport.php
├── MarkdownExport.php
└── HtmlExport.php
```

`ProjectExportController` devient un dispatcher de 50 lignes.

---

## 4. Gestion d'erreurs centralisée

**Problème** : Trois patterns d'erreur coexistent :
- `$this->f3->error(403)` → page HTML
- `jsonError(...)` → JSON API
- `$this->f3->reroute(...)` → redirect

**Solution** : Surcharger le handler F3 `ONERROR` dans `index.php` :

```php
$f3->set('ONERROR', function (Base $f3) {
    if ($f3->get('AJAX') || str_starts_with($f3->get('PATH'), '/api/')) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $f3->get('ERROR.text'),
            'code'  => $f3->get('ERROR.code'),
        ]);
    } else {
        // render error view HTML existante
    }
    exit;
});
```

---

## 5. Logging

**Problème** : `error_log()` dispersé, logs dans 3 fichiers différents, certaines exceptions silencieusement avalées.

**Solution légère** (sans dépendance PSR-3) : un logger maison.

```php
// src/app/core/Logger.php
class Logger {
    const DEBUG = 0, INFO = 1, WARN = 2, ERROR = 3;
    public static function log(int $level, string $module, string $msg, array $ctx = []): void {
        $line = json_encode([
            'ts' => date('c'), 'level' => self::NAMES[$level],
            'module' => $module, 'msg' => $msg, 'ctx' => $ctx
        ]);
        file_put_contents(self::logPath(), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
```

Centraliser dans `src/logs/app.jsonl` (une ligne JSON par événement, exploitable avec `jq`).

---

## 6. JavaScript

### 6.1 Client HTTP centralisé

Actuellement, les `fetch()` sont éparpillés dans les vues. Extraire `src/public/js/api-client.js` :

```javascript
const ApiClient = {
    async request(method, url, body = null) {
        const opts = {
            method,
            headers: { 'X-CSRF-Token': document.querySelector('[name=csrf_token]')?.value },
            credentials: 'same-origin',
        };
        if (body) { opts.body = JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
        const res = await fetch(url, opts);
        if (!res.ok) throw new ApiError(res.status, await res.json());
        return res.json();
    },
    get:    (url)         => ApiClient.request('GET', url),
    post:   (url, body)   => ApiClient.request('POST', url, body),
    put:    (url, body)   => ApiClient.request('PUT', url, body),
    delete: (url)         => ApiClient.request('DELETE', url),
};
```

### 6.2 Isolation des instances Quill

La note de mémoire du projet signale que les instances Quill partagent parfois la même référence d'objet toolbar. Garantir dans `quill-adapter.js` que `init()` appelle **toujours** `getToolbarOptions()` (qui retourne une copie fraîche) et jamais `this.toolbarOptions` directement.

```javascript
// Avant (risque de référence partagée)
const toolbar = QuillTools.toolbarOptions;

// Après (copie fraîche garantie)
const toolbar = QuillTools.getToolbarOptions();
```

---

## 7. Migrations

### 7.1 Migrations réversibles

Ajouter une convention `DOWN` commentée dans chaque fichier SQL :

```sql
-- UP
ALTER TABLE projects ADD COLUMN ai_budget DECIMAL(10,4) DEFAULT 0;

-- DOWN (pour référence — appliquer manuellement si rollback nécessaire)
-- ALTER TABLE projects DROP COLUMN ai_budget;
```

### 7.2 Dry-run CLI

Créer `src/data/migrations/migrate.php` exécutable en CLI avec `--dry-run` pour tester avant déploiement.

---

## 8. API

### 8.1 Pagination

Toutes les listes retournent actuellement l'intégralité des données. Ajouter `?offset=0&limit=50` à toutes les routes liste :

```php
$offset = max(0, (int) ($params['offset'] ?? 0));
$limit  = min(100, max(1, (int) ($params['limit'] ?? 50)));
```

### 8.2 Versionnement

Préfixer les routes en `/api/v1/...` dès maintenant pour conserver la liberté de casser la compatibilité dans une v2 ultérieure.

---

## Avancement — branche `refactoring/structure`

> Dernière mise à jour : 2026-03-17 · commit `f5cb33a` → session P4/P5

### Légende
- ✅ Terminé
- 🚧 En cours
- ⬜ À faire

### Tableau de bord

| Priorité | Tâche | Statut | Commit |
|----------|-------|--------|--------|
| 🔴 P1 | Fix unreachable code `AuthController::authenticate()` | ✅ | `70447b7` |
| 🔴 P1 | Extraire `TokenService` | ✅ | `70447b7` |
| 🟠 P2 | `ApiBaseController` abstrait | ✅ | `70447b7` |
| 🟠 P2 | `AiPricingService` + `ai_pricing.json` | ✅ | `70447b7` |
| 🟠 P2 | `ContentTransformer` partagé | ✅ | `70447b7` |
| 🟡 P3 | Logger centralisé | ✅ | `70447b7` |
| 🟡 P3 | `ProjectService` (découper dashboard) | ✅ | `f5cb33a` |
| 🟡 P3 | DAO `collab` (CollaboratorInvite, CollaborationRequest) | ✅ | `f5cb33a` |
| 🟡 P3 | Guards `requireOwner/requireProjectAccess/requireAuth` | ✅ | `f5cb33a` |
| 🟢 P4 | Handler `ONERROR` unifié | ✅ | `f5cb33a` |
| 🟢 P4 | `ApiClient.js` centralisé | ✅ | session 3 |
| 🟢 P4 | Guards fail-fast `ProjectExportController` | ✅ | session 3 |
| 🟢 P4 | Pagination API (`offset`/`limit`) | ✅ | session 3 |
| 🔵 P5 | Rate limiting atomique | ✅ | N/A — sessions PHP déjà atomiques |
| 🔵 P5 | Versionnement API `/v1/` | ✅ | session 3 |
| 🔵 P5 | Migrations dry-run CLI | ✅ | session 3 |

### Détail du commit `f5cb33a` (2026-03-17)

**9 fichiers touchés — net : −291 lignes**

| Fichier créé | Rôle |
|---|---|
| `src/app/services/ProjectService.php` | Toutes les requêtes dashboard extraites de ProjectController |
| `src/app/modules/collab/models/CollaboratorInvite.php` | DAO `project_collaborators` |
| `src/app/modules/collab/models/CollaborationRequest.php` | DAO `collaboration_requests` |

| Fichier modifié | Ce qui a changé |
|---|---|
| `ProjectController.php` | `dashboard()` : 110 lignes → 25, délègue à `ProjectService` ; logs theme_debug → `Logger` |
| `CollabInviteController.php` | SQL inline → méthodes de `CollaboratorInvite` |
| `CollabRequestController.php` | SQL inline → méthodes de `CollaborationRequest` |
| `Controller.php` | Ajout `requireOwner()`, `requireProjectAccess()`, `requireAuth()` |
| `index.php` | Handler `ONERROR` unifié (JSON pour API, HTML pour browser) |
| `config.ini` | `app/modules/collab/models/` ajouté à `AUTOLOAD` |

---

### Détail session 3 (2026-03-17) — P4/P5

**5 fichiers touchés**

| Fichier créé | Rôle |
|---|---|
| `src/public/js/api-client.js` | Couche réseau JS centralisée : `get`, `post`, `postForm`, `put`, `delete`. CSRF injecté automatiquement depuis `<meta name="csrf-token">`. |
| `src/data/migrations/migrate.php` | CLI dry-run/run : `php src/data/migrations/migrate.php` affiche l'état ; `--run` applique les migrations pendantes sans F3. |

| Fichier modifié | Ce qui a changé |
|---|---|
| `ApiBaseController.php` | Ajout `getPaginationParams()` + `paginatedOut()` — pagination uniforme pour tous les contrôleurs API ; header `X-API-Version: 1` dans `jsonOut()` |
| `ApiController.php` | 6 endpoints `list*` paginés (`offset`/`limit`, méta `{data, meta:{total,offset,limit}}`) |
| `ProjectExportController.php` | Guards `requireProjectAccess($pid)` ajoutés à toutes les méthodes publiques dispatcher (fail-fast avant toute requête DB) |
| `config.ini` | Routes `/api/v1/...` ajoutées (canonical v1) ; routes `/api/...` conservées (rétrocompatibilité) |
| `layouts/main.html` | `api-client.js?v=1` ajouté |

**Note rate limiting** : `checkRateLimit()` est déjà atomique — PHP acquiert un verrou exclusif sur le fichier de session lors de `session_start()`. Deux requêtes concurrentes avec le même `session_id` sont sérialisées par le handler PHP natif.

---

### Détail du commit `70447b7` (2026-03-17)

**7 fichiers créés, 7 modifiés — net : −533 lignes**

| Fichier créé | Rôle |
|---|---|
| `src/app/core/TokenService.php` | JWT + AES-256-GCM + lecture/écriture token files |
| `src/app/core/Logger.php` | Logs JSON structurés → `logs/app.jsonl` |
| `src/app/core/ContentTransformer.php` | `htmlToText()`, `countWords()`, `cleanQuillHtml()` |
| `src/app/services/AiPricingService.php` | Calcul de coût IA délégué au JSON |
| `src/app/controllers/ApiBaseController.php` | Socle JWT pour `ApiController` et `McpController` |
| `src/app/ai_pricing.json` | Table de tarifs IA (éditable sans déploiement PHP) |
| `REFACTORING.md` | Ce fichier |

| Fichier modifié | Ce qui a changé |
|---|---|
| `Controller.php` | `checkAutoLogin` et `authenticateApiRequest` → délèguent à `TokenService` ; `encryptData`/`decryptData` → façades |
| `ApiController.php` | Hérite de `ApiBaseController` ; suppression de 4 méthodes privées |
| `McpController.php` | `htmlToText()` → `ContentTransformer::htmlToText()` |
| `AiController.php` | 112 lignes de pricing → `(new AiPricingService())->computeCost()` |
| `AuthController.php` | Bug `reroute` mort corrigé ; `encodeAutoLoginToken`, opérations fichiers token → `TokenService` |
| `config.ini` | `app/core/` et `app/services/` ajoutés à l'`AUTOLOAD` |
| `index.php` | `Logger::configure()` appelé au démarrage |

---

## Ce qui fonctionne bien (ne pas toucher)

- Système de migrations automatiques → garder tel quel
- Validation uploads images (multi-couche) → solide
- Prepared statements partout → pas de SQL injection
- En-têtes de sécurité (CSP, X-Frame-Options) → garder
- Chiffrement AES-256-GCM des configs IA → garder
- Structure modules MVC → garder le pattern
- Partage public via token (`SharePublicController`) → garder l'isolation
- OAuth2 PKCE → architecture propre
