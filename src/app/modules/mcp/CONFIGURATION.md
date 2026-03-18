# Configuration MCP — Écrivain

Le serveur MCP d'Écrivain expose 35+ outils pour gérer vos projets d'écriture (chapitres,
personnages, actes, notes, éléments, recherche, export Markdown) depuis n'importe quel
client compatible MCP.

## Deux modes de connexion

| Mode | Fichier | Prérequis |
|------|---------|-----------|
| **HTTP** (distant) | `McpController.php` → `POST /mcp` | Token JWT · PHP + Apache |
| **stdio** (local) | `server.php` | PHP CLI · `curl` · Token JWT |

**Obtenir un token JWT** : connectez-vous à votre instance Écrivain → *Paramètres →
Tokens API → Créer un token*.

---

## Claude Desktop (Anthropic)

> Fichier de config selon l'OS :
> - **macOS** : `~/Library/Application Support/Claude/claude_desktop_config.json`
> - **Windows** : `%APPDATA%\Claude\claude_desktop_config.json`
> - **Linux** : `~/.config/Claude/claude_desktop_config.json`
>
> Accès rapide : barre latérale → *Développeur → Modifier la configuration*

### Mode HTTP (recommandé — serveur distant)

```json
{
  "mcpServers": {
    "ecrivain": {
      "url": "https://votre-site.com/mcp",
      "headers": {
        "Authorization": "Bearer VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

### Mode stdio (serveur local — PHP CLI)

```json
{
  "mcpServers": {
    "ecrivain": {
      "command": "php",
      "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
      "env": {
        "API_URL": "https://votre-site.com",
        "API_TOKEN": "VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

> **Windows** : utilisez des doubles barres obliques inverses dans le chemin :
> `"C:\\Projets\\ecrivain\\src\\app\\modules\\mcp\\server.php"`

Protocoles supportés : `2024-11-05` · `2025-03-26`

---

## ChatGPT (OpenAI)

### Via le fichier `chatgpt-app.json` (OAuth, recommandé)

Le fichier `chatgpt-app.json` fourni avec le projet configure ChatGPT pour utiliser le
flux OAuth 2.0 avec PKCE. Importez-le dans *ChatGPT → Paramètres → Connecteurs → Importer*.

```json
{
  "name": "Ecrivain",
  "description": "Gérez vos projets d'écriture depuis ChatGPT.",
  "version": "1.1",
  "mcp": {
    "server_url": "https://votre-site.com/mcp"
  },
  "auth": {
    "type": "oauth",
    "authorization_url": "https://votre-site.com/oauth/authorize",
    "token_url": "https://votre-site.com/oauth/token",
    "scope": "mcp",
    "pkce_required": true,
    "token_exchange_method": "default_post"
  }
}
```

### Via Bearer token (mode Développeur)

Dans ChatGPT → *Mode Développeur → Serveurs MCP* :

```json
{
  "mcpServers": {
    "ecrivain": {
      "url": "https://votre-site.com/mcp",
      "headers": {
        "Authorization": "Bearer VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

---

## Cursor

> Fichier de config :
> - **Projet** : `.cursor/mcp.json` (racine du projet)
> - **Global** : `~/.cursor/mcp.json`
>
> Accès via : *Cursor Settings → Tools & MCP → New MCP Server*

### Mode HTTP

```json
{
  "mcpServers": {
    "ecrivain": {
      "url": "https://votre-site.com/mcp",
      "headers": {
        "Authorization": "Bearer VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

### Mode stdio

```json
{
  "mcpServers": {
    "ecrivain": {
      "command": "php",
      "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
      "env": {
        "API_URL": "https://votre-site.com",
        "API_TOKEN": "VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

---

## VS Code + GitHub Copilot

> Disponible depuis VS Code 1.99+ · Copilot Agent Mode
>
> Fichier de config :
> - **Projet** : `.vscode/mcp.json`
> - **Global** : `settings.json` utilisateur

### `.vscode/mcp.json`

```json
{
  "servers": {
    "ecrivain": {
      "url": "https://votre-site.com/mcp",
      "headers": {
        "Authorization": "Bearer VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

### Mode stdio dans `.vscode/mcp.json`

```json
{
  "servers": {
    "ecrivain": {
      "type": "stdio",
      "command": "php",
      "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
      "env": {
        "API_URL": "https://votre-site.com",
        "API_TOKEN": "VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

> Activez le mode Agent dans le panneau Copilot Chat (`@workspace` → *Agent*) pour que
> les outils MCP soient disponibles.

---

## Windsurf (Codeium)

> Fichier de config : `~/.codeium/windsurf/mcp_config.json`
>
> Ou via : *Settings → Cascade → MCP Servers → Add Server*

### Mode HTTP

```json
{
  "mcpServers": {
    "ecrivain": {
      "serverUrl": "https://votre-site.com/mcp",
      "headers": {
        "Authorization": "Bearer VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

### Mode stdio

```json
{
  "mcpServers": {
    "ecrivain": {
      "command": "php",
      "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
      "env": {
        "API_URL": "https://votre-site.com",
        "API_TOKEN": "VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

---

## Continue.dev (extension VS Code / JetBrains)

> Fichier de config : `.continue/mcpServers/ecrivain.yaml` (racine du projet)

### Mode HTTP

```yaml
name: ecrivain
version: 1.0.0
schema: v1
mcpServers:
  - name: ecrivain
    transport:
      type: streamableHttp
      url: https://votre-site.com/mcp
      headers:
        Authorization: "Bearer VOTRE_TOKEN_JWT"
```

### Mode stdio

```yaml
name: ecrivain
version: 1.0.0
schema: v1
mcpServers:
  - name: ecrivain
    transport:
      type: stdio
      command: php
      args:
        - /chemin/vers/src/app/modules/mcp/server.php
      env:
        API_URL: https://votre-site.com
        API_TOKEN: VOTRE_TOKEN_JWT
```

> Les outils MCP sont disponibles uniquement en mode **Agent** (pas en mode Chat simple).

---

## Zed

> Fichier de config : `~/.config/zed/settings.json`

```json
{
  "context_servers": {
    "ecrivain": {
      "command": {
        "path": "php",
        "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
        "env": {
          "API_URL": "https://votre-site.com",
          "API_TOKEN": "VOTRE_TOKEN_JWT"
        }
      }
    }
  }
}
```

> Zed supporte uniquement le mode stdio pour les serveurs MCP.
> Vérifiez que `php` est dans votre `$PATH`.

---

## Cline (extension VS Code)

> Interface : *Cline → MCP Servers → Configure MCP Servers*

```json
{
  "mcpServers": {
    "ecrivain": {
      "command": "php",
      "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
      "env": {
        "API_URL": "https://votre-site.com",
        "API_TOKEN": "VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

Le mode HTTP est également supporté (même format que Cursor).

---

## Roo Code (extension VS Code)

> Interface : *Roo Code → MCP Servers*

Même format JSON que Cline :

```json
{
  "mcpServers": {
    "ecrivain": {
      "command": "php",
      "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
      "env": {
        "API_URL": "https://votre-site.com",
        "API_TOKEN": "VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

---

## JetBrains (IntelliJ IDEA, PhpStorm, PyCharm…)

> Disponible depuis la version **2025.1** via le plugin *AI Assistant*
>
> Accès : *Settings → AI Assistant → Model Context Protocol (MCP) → Add*

### Mode HTTP

```json
{
  "mcpServers": {
    "ecrivain": {
      "url": "https://votre-site.com/mcp",
      "headers": {
        "Authorization": "Bearer VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

### Mode stdio

```json
{
  "mcpServers": {
    "ecrivain": {
      "command": "php",
      "args": ["/chemin/vers/src/app/modules/mcp/server.php"],
      "env": {
        "API_URL": "https://votre-site.com",
        "API_TOKEN": "VOTRE_TOKEN_JWT"
      }
    }
  }
}
```

---

## Claude Code (CLI Anthropic)

> Le CLI Claude Code supporte MCP en ajoutant le serveur via la commande :

```bash
# Mode HTTP
claude mcp add ecrivain --transport http \
  "https://votre-site.com/mcp" \
  --header "Authorization: Bearer VOTRE_TOKEN_JWT"

# Mode stdio
claude mcp add ecrivain \
  php /chemin/vers/src/app/modules/mcp/server.php \
  --env API_URL=https://votre-site.com \
  --env API_TOKEN=VOTRE_TOKEN_JWT
```

Vérification :

```bash
claude mcp list
claude mcp get ecrivain
```

---

## Récapitulatif de compatibilité

| Client | stdio | HTTP | Version protocole | Auth |
|--------|:-----:|:----:|-------------------|------|
| Claude Desktop | ✅ | ✅ | 2024-11-05 · 2025-03-26 | Bearer JWT · OAuth |
| ChatGPT | — | ✅ | 2025-03-26 | Bearer JWT · OAuth PKCE |
| Cursor | ✅ | ✅ | 2025-03-26 | Bearer JWT |
| VS Code + Copilot | ✅ | ✅ | 2025-03-26 | Bearer JWT |
| Windsurf | ✅ | ✅ | 2025-03-26 | Bearer JWT |
| Continue.dev | ✅ | ✅ | 2025-03-26 | Bearer JWT |
| Zed | ✅ | — | 2024-11-05 | — |
| Cline | ✅ | ✅ | 2025-03-26 | Bearer JWT |
| Roo Code | ✅ | ✅ | 2025-03-26 | Bearer JWT |
| JetBrains | ✅ | ✅ | 2025-03-26 | Bearer JWT |
| Claude Code CLI | ✅ | ✅ | 2025-03-26 | Bearer JWT |

---

## Outils disponibles (35)

| Catégorie | Outils |
|-----------|--------|
| Projets | `list_projects` `get_project` `create_project` `update_project` `delete_project` |
| Actes | `list_acts` `get_act` `create_act` `update_act` `delete_act` |
| Chapitres | `list_chapters` `get_chapter` `create_chapter` `update_chapter` `delete_chapter` |
| Sections | `list_sections` `get_section` `create_section` `update_section` `delete_section` |
| Notes | `list_notes` `get_note` `create_note` `update_note` `delete_note` |
| Personnages | `list_characters` `get_character` `create_character` `update_character` `delete_character` |
| Éléments | `list_element_types` `list_elements` `get_element` `create_element` `update_element` `delete_element` |
| Images | `list_images` `delete_image` *(+ `upload_image` en mode stdio)* |
| Export | `export_markdown` |
| Recherche | `search` |

---

## Dépannage

**`php` introuvable** — ajoutez le chemin complet : `/usr/bin/php` (Linux/macOS) ou
`C:\php\php.exe` (Windows).

**Erreur 401** — le token est invalide ou expiré. Régénérez-le dans *Paramètres → Tokens API*.

**Outils non visibles** — rechargez la configuration du client (ex. : redémarrer Claude Desktop
après modification du JSON).

**Erreur CORS (mode HTTP)** — le client web doit provenir d'une origine autorisée
(`chatgpt.com`, `chat.openai.com`, `claude.ai`). Les clients desktop ne sont pas concernés.
