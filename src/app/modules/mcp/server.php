#!/usr/bin/env php
<?php
/**
 * Écrivain — Serveur MCP en PHP CLI
 *
 * Protocole : MCP stdio (JSON-RPC 2.0, newline-delimited)
 * Usage     : php server.php
 *
 * Aucune dépendance externe — PHP CLI + extension curl uniquement.
 */

declare(strict_types=1);

// ──────────────────────────────────────────────────────────────────────────────
// 1. Configuration
// ──────────────────────────────────────────────────────────────────────────────

// API_URL et API_TOKEN sont passés par Claude Desktop via "env" dans la config MCP.
// Exemple de configuration Claude Desktop :
//   {
//     "mcpServers": {
//       "ecrivain": {
//         "command": "php",
//         "args": ["/chemin/vers/server.php"],
//         "env": {
//           "API_URL": "https://monsite.com",
//           "API_TOKEN": "token_jwt_utilisateur"
//         }
//       }
//     }
//   }
$API_URL   = rtrim((string) getenv('API_URL'), '/');
$API_TOKEN = trim((string) getenv('API_TOKEN'));

if (!$API_URL || !$API_TOKEN) {
    fwrite(STDERR, "Erreur : API_URL et API_TOKEN doivent être définis dans .env\n");
    exit(1);
}

// ──────────────────────────────────────────────────────────────────────────────
// 2. Client HTTP (curl)
// ──────────────────────────────────────────────────────────────────────────────

function apiRequest(string $method, string $path, mixed $body = null, array $extraHeaders = []): mixed
{
    global $API_URL, $API_TOKEN;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $API_URL . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Authorization: Bearer ' . $API_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders),
    ]);

    if ($body !== null && $method !== 'GET') {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error)
        throw new RuntimeException("Erreur réseau : $error");
    if ($response === false)
        throw new RuntimeException("Réponse vide du serveur.");

    $data = json_decode($response, true);
    if ($status >= 400) {
        $msg = $data['error'] ?? "Erreur HTTP $status";
        throw new RuntimeException("Erreur $status : $msg");
    }
    return $data;
}

function apiGet(string $path, array $params = []): mixed
{
    $query = $params ? '?' . http_build_query($params) : '';
    return apiRequest('GET', $path . $query);
}

function apiPost(string $path, array $body = []): mixed
{
    return apiRequest('POST', $path, $body);
}
function apiPut(string $path, array $body = []): mixed
{
    return apiRequest('PUT', $path, $body);
}
function apiDelete(string $path): mixed
{
    return apiRequest('DELETE', $path);
}

function apiGetMarkdown(string $path): string
{
    global $API_URL, $API_TOKEN;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $API_URL . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $API_TOKEN,
            'Accept: text/markdown, text/plain',
        ],
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400)
        throw new RuntimeException("Erreur export markdown ($status).");
    return (string) $response;
}

function apiUploadImage(string $path, string $filePath): mixed
{
    global $API_URL, $API_TOKEN;
    if (!file_exists($filePath))
        throw new RuntimeException("Fichier introuvable : $filePath");

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $API_URL . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['file' => new CURLFile($filePath)],
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $API_TOKEN],
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $response, true);
    if ($status >= 400)
        throw new RuntimeException($data['error'] ?? "Erreur upload ($status).");
    return $data;
}

// ──────────────────────────────────────────────────────────────────────────────
// 3. Formatters JSON → Markdown
// ──────────────────────────────────────────────────────────────────────────────

function fmtDate(?string $iso): string
{
    return $iso ? substr($iso, 0, 10) : '?';
}
function mdOk(string $msg): string
{
    return "✓ $msg";
}

function mdProjects(array $data): string
{
    $projects = $data['projects'] ?? [];
    if (!$projects)
        return '_Aucun projet trouvé._';
    $out = "# Mes projets (" . count($projects) . ")\n\n";
    foreach ($projects as $p) {
        $out .= "## {$p['title']} *(id: {$p['id']})*\n";
        if (!empty($p['description']))
            $out .= $p['description'] . "\n";
        $out .= "Mis à jour le " . fmtDate($p['updated_at']) . "\n\n";
    }
    return $out;
}

function mdProject(array $p): string
{
    $out = "# {$p['title']} *(id: {$p['id']})*\n";
    if (!empty($p['description']))
        $out .= "\n{$p['description']}\n";
    $out .= "\n**Créé le** : " . fmtDate($p['created_at']) . " | **Mis à jour** : " . fmtDate($p['updated_at']) . "\n";
    $out .= "**Personnages** : {$p['characters_count']} | **Notes** : {$p['notes_count']} | **Éléments** : {$p['elements_count']}\n";

    if (!empty($p['acts'])) {
        $out .= "\n## Structure\n\n";
        foreach ($p['acts'] as $act) {
            $out .= "### {$act['title']} *(acte id: {$act['id']})*\n";
            if (!empty($act['description']))
                $out .= "> {$act['description']}\n";
            foreach ($act['chapters'] ?? [] as $ch) {
                $wc = $ch['word_count'] ? " — {$ch['word_count']} mots" : '';
                $out .= "- **{$ch['title']}** *(id: {$ch['id']})*{$wc}\n";
                if (!empty($ch['resume']))
                    $out .= "  _{$ch['resume']}_\n";
            }
            $out .= "\n";
        }
    }
    if (!empty($p['chapters_without_act'])) {
        $out .= "### Chapitres sans acte\n\n";
        foreach ($p['chapters_without_act'] as $ch) {
            $wc = $ch['word_count'] ? " — {$ch['word_count']} mots" : '';
            $out .= "- **{$ch['title']}** *(id: {$ch['id']})*{$wc}\n";
        }
        $out .= "\n";
    }
    if (!empty($p['sections'])) {
        $out .= "## Sections\n\n";
        foreach ($p['sections'] as $s) {
            $label = $s['type_label'] ?? $s['type'];
            $title = $s['title'] ?? '(sans titre)';
            $out .= "- {$label} : **{$title}** *(id: {$s['id']})*\n";
        }
    }
    return $out;
}

function mdActs(array $data): string
{
    $acts = $data['acts'] ?? [];
    if (!$acts)
        return '_Aucun acte dans ce projet._';
    $out = "# Actes (" . count($acts) . ")\n\n";
    foreach ($acts as $a) {
        $out .= "## {$a['title']} *(id: {$a['id']})*\n";
        if (!empty($a['description']))
            $out .= $a['description'] . "\n";
        $out .= "**Chapitres** : " . ($a['chapters_count'] ?? 0) . "\n\n";
    }
    return $out;
}

function mdAct(array $a): string
{
    $out = "# {$a['title']} *(id: {$a['id']})*\n";
    if (!empty($a['description']))
        $out .= "\n{$a['description']}\n";
    if (!empty($a['resume']))
        $out .= "\n**Résumé** : {$a['resume']}\n";
    $out .= "\n## Chapitres\n\n";
    foreach ($a['chapters'] ?? [] as $ch) {
        $wc = $ch['word_count'] ? " — {$ch['word_count']} mots" : '';
        $out .= "- **{$ch['title']}** *(id: {$ch['id']})*{$wc}\n";
        if (!empty($ch['resume']))
            $out .= "  _{$ch['resume']}_\n";
    }
    return $out;
}

function mdChapter(array $ch): string
{
    $out = "# {$ch['title']} *(id: {$ch['id']})*\n";
    if (!empty($ch['act_id']))
        $out .= "**Acte id** : {$ch['act_id']}\n";
    $out .= "**Mots** : {$ch['word_count']} | **Mis à jour** : " . fmtDate($ch['updated_at']) . "\n";
    if (!empty($ch['resume']))
        $out .= "\n## Résumé\n{$ch['resume']}\n";
    $out .= "\n## Contenu\n" . ($ch['content_text'] ?: '_Contenu vide._') . "\n";
    return $out;
}

function mdSections(array $data): string
{
    $sections = $data['sections'] ?? [];
    if (!$sections)
        return '_Aucune section dans ce projet._';
    $out = "# Sections (" . count($sections) . ")\n\n";
    foreach ($sections as $s) {
        $label = $s['type_label'] ?? $s['type'];
        $title = $s['title'] ?? '(sans titre)';
        $out .= "## {$label} — {$title} *(id: {$s['id']})*\n";
        if (!empty($s['has_image']))
            $out .= "_Contient une image_\n";
        $out .= "\n";
    }
    return $out;
}

function mdSection(array $s): string
{
    $label = $s['type_label'] ?? $s['type'];
    $title = $s['title'] ?? '(sans titre)';
    $out = "# {$label} — {$title} *(id: {$s['id']})*\n";
    if (!empty($s['comment']))
        $out .= "\n**Note** : {$s['comment']}\n";
    if (!empty($s['image_url']))
        $out .= "\n**Image** : {$s['image_url']}\n";
    $out .= "\n## Contenu\n" . ($s['content_text'] ?: '_Contenu vide._') . "\n";
    return $out;
}

function mdNotes(array $data): string
{
    $notes = $data['notes'] ?? [];
    if (!$notes)
        return '_Aucune note dans ce projet._';
    $out = "# Notes (" . count($notes) . ")\n\n";
    foreach ($notes as $n) {
        $title = $n['title'] ?? '(sans titre)';
        $out .= "## {$title} *(id: {$n['id']})*\n";
        $out .= "Mis à jour le " . fmtDate($n['updated_at']) . "\n\n";
    }
    return $out;
}

function mdNote(array $n): string
{
    $title = $n['title'] ?? '(sans titre)';
    $out = "# {$title} *(id: {$n['id']})*\n";
    if (!empty($n['comment']))
        $out .= "\n**Note** : {$n['comment']}\n";
    $out .= "\n## Contenu\n" . ($n['content_text'] ?: '_Contenu vide._') . "\n";
    return $out;
}

function mdCharacters(array $data): string
{
    $characters = $data['characters'] ?? [];
    if (!$characters)
        return '_Aucun personnage dans ce projet._';
    $out = "# Personnages (" . count($characters) . ")\n\n";
    foreach ($characters as $c) {
        $out .= "## {$c['name']} *(id: {$c['id']})*\n";
        if (!empty($c['description']))
            $out .= substr($c['description'], 0, 100) . (strlen($c['description']) > 100 ? '…' : '') . "\n";
        $out .= "\n";
    }
    return $out;
}

function mdCharacter(array $c): string
{
    $out = "# {$c['name']} *(id: {$c['id']})*\n";
    if (!empty($c['description']))
        $out .= "\n## Description\n{$c['description']}\n";
    if (!empty($c['comment']))
        $out .= "\n## Notes\n{$c['comment']}\n";
    return $out;
}

function mdElements(array $data): string
{
    $elements = $data['elements'] ?? [];
    if (!$elements)
        return '_Aucun élément dans ce projet._';
    $out = "# Éléments (" . count($elements) . ")\n\n";
    $byType = [];
    foreach ($elements as $e) {
        $k = $e['type_label'] ?? $e['element_type'] ?? 'Autre';
        $byType[$k][] = $e;
    }
    foreach ($byType as $type => $items) {
        $out .= "## {$type}\n\n";
        foreach ($items as $e) {
            $indent = $e['parent_id'] ? '  - ' : '- ';
            $out .= "{$indent}**{$e['title']}** *(id: {$e['id']})*\n";
        }
        $out .= "\n";
    }
    return $out;
}

function mdElement(array $e): string
{
    $type = $e['type_label'] ?? $e['element_type'] ?? '';
    $out = "# {$e['title']} *(id: {$e['id']})*\n";
    if ($type)
        $out .= "**Type** : {$type}\n";
    if (!empty($e['resume']))
        $out .= "\n**Résumé** : {$e['resume']}\n";
    $out .= "\n## Contenu\n" . ($e['content_text'] ?: '_Contenu vide._') . "\n";
    if (!empty($e['sub_elements'])) {
        $out .= "\n## Sous-éléments\n\n";
        foreach ($e['sub_elements'] as $s)
            $out .= "- **{$s['title']}** *(id: {$s['id']})*\n";
    }
    return $out;
}

function mdImages(array $data): string
{
    $images = $data['images'] ?? [];
    if (!$images)
        return '_Aucune image dans ce projet._';
    $out = "# Images (" . count($images) . ")\n\n";
    foreach ($images as $img) {
        $out .= "- **{$img['filename']}** *(id: {$img['id']})* — {$img['size_kb']} Ko\n";
        $out .= "  {$img['url']}\n";
    }
    return $out;
}

function mdSearch(array $data): string
{
    $q = $data['query'] ?? '';
    $results = $data['results'] ?? [];
    if (!$results)
        return "_Aucun résultat pour « {$q} »._";
    $out = "# Résultats pour « {$q} » (" . count($results) . ")\n\n";
    foreach ($results as $r) {
        $out .= "## {$r['title']} *({$r['type']}, id: {$r['id']})*\n";
        if (!empty($r['excerpt']))
            $out .= "> …{$r['excerpt']}…\n";
        $out .= "\n";
    }
    return $out;
}

// ──────────────────────────────────────────────────────────────────────────────
// 4. Définition des outils MCP
// ──────────────────────────────────────────────────────────────────────────────

function buildTools(): array
{
    $int = ['type' => 'integer'];
    $str = ['type' => 'string'];

    $prop = fn(string $type, string $desc) => ['type' => $type, 'description' => $desc];
    $req = fn(array $props, array $required = []) => ['type' => 'object', 'properties' => $props, 'required' => $required];

    return [

        // ── Projects ──────────────────────────────────────────────────────────
        'list_projects' => [
            'description' => "Liste tous les projets de l'utilisateur.",
            'inputSchema' => $req([]),
            'handler' => fn($a) => mdProjects(apiGet('/api/projects')),
        ],
        'get_project' => [
            'description' => "Détails et structure complète d'un projet (actes, chapitres, sections…).",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du projet')], ['id']),
            'handler' => fn($a) => mdProject(apiGet("/api/project/{$a['id']}")),
        ],
        'create_project' => [
            'description' => "Crée un nouveau projet.",
            'inputSchema' => $req(['title' => $prop('string', 'Titre'), 'description' => $prop('string', 'Description')], ['title']),
            'handler' => fn($a) => mdProject(apiPost('/api/projects', ['title' => $a['title'], 'description' => $a['description'] ?? null])),
        ],
        'update_project' => [
            'description' => "Modifie le titre ou la description d'un projet.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du projet'), 'title' => $prop('string', 'Nouveau titre'), 'description' => $prop('string', 'Nouvelle description')], ['id']),
            'handler' => fn($a) => mdProject(apiPut("/api/project/{$a['id']}", array_filter(['title' => $a['title'] ?? null, 'description' => $a['description'] ?? null]))),
        ],
        'delete_project' => [
            'description' => "Supprime définitivement un projet et tout son contenu.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du projet')], ['id']),
            'handler' => function ($a) {
                apiDelete("/api/project/{$a['id']}");
                return mdOk("Projet {$a['id']} supprimé."); },
        ],

        // ── Acts ──────────────────────────────────────────────────────────────
        'list_acts' => [
            'description' => "Liste les actes d'un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet')], ['project_id']),
            'handler' => fn($a) => mdActs(apiGet("/api/project/{$a['project_id']}/acts")),
        ],
        'get_act' => [
            'description' => "Acte avec la liste de ses chapitres.",
            'inputSchema' => $req(['id' => $prop('integer', "ID de l'acte")], ['id']),
            'handler' => fn($a) => mdAct(apiGet("/api/act/{$a['id']}")),
        ],
        'create_act' => [
            'description' => "Crée un acte dans un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet'), 'title' => $prop('string', 'Titre'), 'description' => $prop('string', 'Description')], ['project_id', 'title']),
            'handler' => fn($a) => mdAct(apiPost("/api/project/{$a['project_id']}/acts", ['title' => $a['title'], 'description' => $a['description'] ?? null])),
        ],
        'update_act' => [
            'description' => "Modifie un acte (titre, description, résumé).",
            'inputSchema' => $req(['id' => $prop('integer', "ID de l'acte"), 'title' => $prop('string', 'Titre'), 'description' => $prop('string', 'Description'), 'resume' => $prop('string', 'Résumé narratif')], ['id']),
            'handler' => fn($a) => mdAct(apiPut("/api/act/{$a['id']}", array_filter(['title' => $a['title'] ?? null, 'description' => $a['description'] ?? null, 'resume' => $a['resume'] ?? null]))),
        ],
        'delete_act' => [
            'description' => "Supprime un acte.",
            'inputSchema' => $req(['id' => $prop('integer', "ID de l'acte")], ['id']),
            'handler' => function ($a) {
                apiDelete("/api/act/{$a['id']}");
                return mdOk("Acte {$a['id']} supprimé."); },
        ],

        // ── Chapters ──────────────────────────────────────────────────────────
        'get_chapter' => [
            'description' => "Lit le contenu complet d'un chapitre (texte brut + résumé).",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du chapitre')], ['id']),
            'handler' => fn($a) => mdChapter(apiGet("/api/chapter/{$a['id']}")),
        ],
        'create_chapter' => [
            'description' => "Crée un chapitre dans un projet.",
            'inputSchema' => $req([
                'project_id' => $prop('integer', 'ID du projet'),
                'title' => $prop('string', 'Titre'),
                'act_id' => $prop('integer', "ID de l'acte parent (optionnel)"),
                'content' => $prop('string', 'Contenu HTML ou texte'),
                'resume' => $prop('string', 'Résumé du chapitre'),
            ], ['project_id', 'title']),
            'handler' => fn($a) => mdChapter(apiPost("/api/project/{$a['project_id']}/chapters", array_filter([
                'title' => $a['title'],
                'act_id' => $a['act_id'] ?? null,
                'content' => $a['content'] ?? null,
                'resume' => $a['resume'] ?? null,
            ]))),
        ],
        'update_chapter' => [
            'description' => "Met à jour le contenu, le titre ou le résumé d'un chapitre. Crée automatiquement une version historique.",
            'inputSchema' => $req([
                'id' => $prop('integer', 'ID du chapitre'),
                'title' => $prop('string', 'Nouveau titre'),
                'content' => $prop('string', 'Nouveau contenu HTML'),
                'resume' => $prop('string', 'Nouveau résumé'),
            ], ['id']),
            'handler' => fn($a) => mdChapter(apiPut("/api/chapter/{$a['id']}", array_filter([
                'title' => $a['title'] ?? null,
                'content' => $a['content'] ?? null,
                'resume' => $a['resume'] ?? null,
            ]))),
        ],
        'delete_chapter' => [
            'description' => "Supprime un chapitre et son historique de versions.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du chapitre')], ['id']),
            'handler' => function ($a) {
                apiDelete("/api/chapter/{$a['id']}");
                return mdOk("Chapitre {$a['id']} supprimé."); },
        ],

        // ── Sections ──────────────────────────────────────────────────────────
        'list_sections' => [
            'description' => "Liste les sections d'un projet (préface, annexes, couverture…).",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet')], ['project_id']),
            'handler' => fn($a) => mdSections(apiGet("/api/project/{$a['project_id']}/sections")),
        ],
        'get_section' => [
            'description' => "Contenu complet d'une section.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID de la section')], ['id']),
            'handler' => fn($a) => mdSection(apiGet("/api/section/{$a['id']}")),
        ],
        'create_section' => [
            'description' => "Crée une section dans un projet. Types valides : cover, preface, introduction, prologue, postface, appendices, back_cover.",
            'inputSchema' => $req([
                'project_id' => $prop('integer', 'ID du projet'),
                'type' => $prop('string', 'Type : cover | preface | introduction | prologue | postface | appendices | back_cover'),
                'title' => $prop('string', 'Titre'),
                'content' => $prop('string', 'Contenu HTML'),
                'comment' => $prop('string', 'Commentaire interne'),
            ], ['project_id', 'type']),
            'handler' => fn($a) => mdSection(apiPost("/api/project/{$a['project_id']}/sections", array_filter([
                'type' => $a['type'],
                'title' => $a['title'] ?? null,
                'content' => $a['content'] ?? null,
                'comment' => $a['comment'] ?? null,
            ]))),
        ],
        'update_section' => [
            'description' => "Met à jour une section.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID de la section'), 'title' => $prop('string', 'Titre'), 'content' => $prop('string', 'Contenu'), 'comment' => $prop('string', 'Commentaire')], ['id']),
            'handler' => fn($a) => mdSection(apiPut("/api/section/{$a['id']}", array_filter(['title' => $a['title'] ?? null, 'content' => $a['content'] ?? null, 'comment' => $a['comment'] ?? null]))),
        ],
        'delete_section' => [
            'description' => "Supprime une section.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID de la section')], ['id']),
            'handler' => function ($a) {
                apiDelete("/api/section/{$a['id']}");
                return mdOk("Section {$a['id']} supprimée."); },
        ],

        // ── Notes ─────────────────────────────────────────────────────────────
        'list_notes' => [
            'description' => "Liste les notes d'un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet')], ['project_id']),
            'handler' => fn($a) => mdNotes(apiGet("/api/project/{$a['project_id']}/notes")),
        ],
        'get_note' => [
            'description' => "Contenu complet d'une note.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID de la note')], ['id']),
            'handler' => fn($a) => mdNote(apiGet("/api/note/{$a['id']}")),
        ],
        'create_note' => [
            'description' => "Crée une note dans un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet'), 'title' => $prop('string', 'Titre'), 'content' => $prop('string', 'Contenu HTML'), 'comment' => $prop('string', 'Commentaire')], ['project_id']),
            'handler' => fn($a) => mdNote(apiPost("/api/project/{$a['project_id']}/notes", array_filter(['title' => $a['title'] ?? null, 'content' => $a['content'] ?? null, 'comment' => $a['comment'] ?? null]))),
        ],
        'update_note' => [
            'description' => "Met à jour une note.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID de la note'), 'title' => $prop('string', 'Titre'), 'content' => $prop('string', 'Contenu'), 'comment' => $prop('string', 'Commentaire')], ['id']),
            'handler' => fn($a) => mdNote(apiPut("/api/note/{$a['id']}", array_filter(['title' => $a['title'] ?? null, 'content' => $a['content'] ?? null, 'comment' => $a['comment'] ?? null]))),
        ],
        'delete_note' => [
            'description' => "Supprime une note.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID de la note')], ['id']),
            'handler' => function ($a) {
                apiDelete("/api/note/{$a['id']}");
                return mdOk("Note {$a['id']} supprimée."); },
        ],

        // ── Characters ────────────────────────────────────────────────────────
        'list_characters' => [
            'description' => "Liste les personnages d'un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet')], ['project_id']),
            'handler' => fn($a) => mdCharacters(apiGet("/api/project/{$a['project_id']}/characters")),
        ],
        'get_character' => [
            'description' => "Fiche complète d'un personnage.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du personnage')], ['id']),
            'handler' => fn($a) => mdCharacter(apiGet("/api/character/{$a['id']}")),
        ],
        'create_character' => [
            'description' => "Crée un personnage dans un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet'), 'name' => $prop('string', 'Nom'), 'description' => $prop('string', 'Biographie'), 'comment' => $prop('string', 'Notes internes')], ['project_id', 'name']),
            'handler' => fn($a) => mdCharacter(apiPost("/api/project/{$a['project_id']}/characters", array_filter(['name' => $a['name'], 'description' => $a['description'] ?? null, 'comment' => $a['comment'] ?? null]))),
        ],
        'update_character' => [
            'description' => "Met à jour la fiche d'un personnage.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du personnage'), 'name' => $prop('string', 'Nom'), 'description' => $prop('string', 'Biographie'), 'comment' => $prop('string', 'Notes')], ['id']),
            'handler' => fn($a) => mdCharacter(apiPut("/api/character/{$a['id']}", array_filter(['name' => $a['name'] ?? null, 'description' => $a['description'] ?? null, 'comment' => $a['comment'] ?? null]))),
        ],
        'delete_character' => [
            'description' => "Supprime un personnage.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du personnage')], ['id']),
            'handler' => function ($a) {
                apiDelete("/api/character/{$a['id']}");
                return mdOk("Personnage {$a['id']} supprimé."); },
        ],

        // ── Elements ──────────────────────────────────────────────────────────
        'list_element_types' => [
            'description' => "Liste les types d'éléments disponibles pour un projet (avec leur template_element_id). À appeler AVANT create_element pour connaître les IDs valides.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet')], ['project_id']),
            'handler' => function ($a) {
                $data = apiGet("/api/project/{$a['project_id']}/element-types");
                $types = $data['types'] ?? [];
                if (!$types) return '_Aucun type d\'élément configuré pour ce projet._';
                $out = "# Types d'éléments disponibles\n\nUtiliser `template_element_id` dans `create_element`.\n\n";
                foreach ($types as $t) {
                    $out .= "- **{$t['label']}** — template_element_id: **{$t['id']}**\n";
                }
                return $out;
            },
        ],
        'list_elements' => [
            'description' => "Liste les éléments personnalisés d'un projet (lieux, objets, exercices…).",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet')], ['project_id']),
            'handler' => fn($a) => mdElements(apiGet("/api/project/{$a['project_id']}/elements")),
        ],
        'get_element' => [
            'description' => "Fiche complète d'un élément et ses sous-éléments.",
            'inputSchema' => $req(['id' => $prop('integer', "ID de l'élément")], ['id']),
            'handler' => fn($a) => mdElement(apiGet("/api/element/{$a['id']}")),
        ],
        'create_element' => [
            'description' => "Crée un élément dans un projet. Requiert l'ID du template_element correspondant au type souhaité (visible dans list_elements).",
            'inputSchema' => $req([
                'project_id' => $prop('integer', 'ID du projet'),
                'title' => $prop('string', 'Titre'),
                'template_element_id' => $prop('integer', "ID du type d'élément"),
                'parent_id' => $prop('integer', "ID du parent pour un sous-élément"),
            ], ['project_id', 'title', 'template_element_id']),
            'handler' => fn($a) => mdElement(apiPost("/api/project/{$a['project_id']}/elements", array_filter([
                'title' => $a['title'],
                'template_element_id' => $a['template_element_id'],
                'parent_id' => $a['parent_id'] ?? null,
            ]))),
        ],
        'update_element' => [
            'description' => "Met à jour le contenu d'un élément.",
            'inputSchema' => $req(['id' => $prop('integer', "ID de l'élément"), 'title' => $prop('string', 'Titre'), 'content' => $prop('string', 'Contenu HTML'), 'resume' => $prop('string', 'Résumé')], ['id']),
            'handler' => fn($a) => mdElement(apiPut("/api/element/{$a['id']}", array_filter(['title' => $a['title'] ?? null, 'content' => $a['content'] ?? null, 'resume' => $a['resume'] ?? null]))),
        ],
        'delete_element' => [
            'description' => "Supprime un élément et ses sous-éléments.",
            'inputSchema' => $req(['id' => $prop('integer', "ID de l'élément")], ['id']),
            'handler' => function ($a) {
                apiDelete("/api/element/{$a['id']}");
                return mdOk("Élément {$a['id']} supprimé."); },
        ],

        // ── Images ────────────────────────────────────────────────────────────
        'list_images' => [
            'description' => "Liste les images attachées à un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet')], ['project_id']),
            'handler' => fn($a) => mdImages(apiGet("/api/project/{$a['project_id']}/images")),
        ],
        'upload_image' => [
            'description' => "Upload une image depuis le disque local vers un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet'), 'file_path' => $prop('string', 'Chemin absolu du fichier image')], ['project_id', 'file_path']),
            'handler' => function ($a) {
                $r = apiUploadImage("/api/project/{$a['project_id']}/images", $a['file_path']);
                return mdOk("Image « {$r['filename']} » uploadée (id: {$r['id']})\n{$r['url']}");
            },
        ],
        'delete_image' => [
            'description' => "Supprime une image d'un projet.",
            'inputSchema' => $req(['project_id' => $prop('integer', 'ID du projet'), 'image_id' => $prop('integer', "ID de l'image")], ['project_id', 'image_id']),
            'handler' => function ($a) {
                apiDelete("/api/project/{$a['project_id']}/image/{$a['image_id']}");
                return mdOk("Image {$a['image_id']} supprimée."); },
        ],

        // ── Export ────────────────────────────────────────────────────────────
        'export_markdown' => [
            'description' => "Exporte l'intégralité d'un projet en Markdown structuré.",
            'inputSchema' => $req(['id' => $prop('integer', 'ID du projet')], ['id']),
            'handler' => fn($a) => apiGetMarkdown("/api/project/{$a['id']}/export/markdown"),
        ],

        // ── Search ────────────────────────────────────────────────────────────
        'search' => [
            'description' => "Recherche full-text dans les chapitres, personnages, notes et éléments.",
            'inputSchema' => $req([
                'q' => $prop('string', 'Texte à rechercher (min. 2 caractères)'),
                'project_id' => $prop('integer', 'Limiter à un projet (optionnel)'),
            ], ['q']),
            'handler' => function ($a) {
                $params = ['q' => $a['q']];
                if (!empty($a['project_id']))
                    $params['pid'] = $a['project_id'];
                return mdSearch(apiGet('/api/search', $params));
            },
        ],
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// 5. Protocole MCP (JSON-RPC 2.0 sur stdio)
// ──────────────────────────────────────────────────────────────────────────────

function mcpRespond(mixed $id, mixed $result): void
{
    $msg = json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    fwrite(STDOUT, $msg . "\n");
    fflush(STDOUT);
}

function mcpError(mixed $id, int $code, string $message): void
{
    $msg = json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    fwrite(STDOUT, $msg . "\n");
    fflush(STDOUT);
}

function mcpToolResult(string $text): array
{
    return ['content' => [['type' => 'text', 'text' => $text]]];
}

function handle(array $message, array $tools): void
{
    $method = $message['method'] ?? '';
    $id = $message['id'] ?? null;
    $params = $message['params'] ?? [];

    switch ($method) {

        case 'initialize':
            mcpRespond($id, [
                'protocolVersion' => '2025-03-26',
                'capabilities' => ['tools' => new stdClass()],
                'serverInfo' => ['name' => 'ecrivain', 'version' => '1.0.0'],
            ]);
            break;

        case 'notifications/initialized':
        case 'initialized':
            // Notification — pas de réponse
            break;

        case 'ping':
            mcpRespond($id, new stdClass());
            break;

        case 'tools/list':
            $list = [];
            foreach ($tools as $name => $def) {
                $list[] = [
                    'name' => $name,
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ];
            }
            mcpRespond($id, ['tools' => $list]);
            break;

        case 'tools/call':
            $name = $params['name'] ?? '';
            $args = $params['arguments'] ?? [];

            if (!isset($tools[$name])) {
                mcpRespond($id, mcpToolResult("Erreur : outil « {$name} » inconnu."));
                break;
            }

            try {
                $text = ($tools[$name]['handler'])($args);
                mcpRespond($id, mcpToolResult((string) $text));
            } catch (Throwable $e) {
                mcpRespond($id, mcpToolResult("Erreur : " . $e->getMessage()));
            }
            break;

        default:
            if ($id !== null) {
                mcpError($id, -32601, "Method not found: $method");
            }
            break;
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// 6. Boucle principale
// ──────────────────────────────────────────────────────────────────────────────

fwrite(STDERR, "Écrivain MCP server started (PHP CLI)\n");

$tools = buildTools();

while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if ($line === '')
        continue;

    $message = json_decode($line, true);
    if (!is_array($message)) {
        fwrite(STDERR, "Message JSON invalide ignoré.\n");
        continue;
    }

    handle($message, $tools);
}
