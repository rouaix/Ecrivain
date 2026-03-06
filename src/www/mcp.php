<?php
declare(strict_types=1);
// MCP HTTP endpoint for Ecrivain — lightweight wrapper of server.php
// Expects Authorization: Bearer <API_TOKEN> header from the client (ChatGPT Desktop)

// Determine API_URL: prefer env API_URL, else infer from request path (strip /mcp)
$apiUrlEnv = trim((string) getenv('API_URL'));
if ($apiUrlEnv) {
    $API_URL = rtrim($apiUrlEnv, '/');
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // script name may be /ecrivain/mcp or /ecrivain/mcp.php
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $base = rtrim(dirname($script), '/');
    $API_URL = $scheme . '://' . $host . ($base === '' ? '' : $base);
}

// Get bearer token from Authorization header
$headers = [];
foreach (getallheaders() as $k => $v) $headers[strtolower($k)] = $v;
$auth = $headers['authorization'] ?? $headers['Authorization'] ?? '';
if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
$API_TOKEN = '';
if ($auth) {
    if (preg_match('/^Bearer\s+(.*)$/i', $auth, $m)) {
        $API_TOKEN = trim($m[1]);
    } else {
        // Accept raw token (no 'Bearer ' prefix) — some clients send token directly
        $API_TOKEN = trim($auth);
    }
}

if (!$API_TOKEN) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing Authorization Bearer token']);
    exit(0);
}

// Reuse helper functions from server.php, simplified and adapted to HTTP
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
    if ($body !== null && strtoupper($method) !== 'GET') {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) throw new RuntimeException("Erreur réseau : $error");
    if ($response === false) throw new RuntimeException("Réponse vide du serveur.");
    $data = json_decode($response, true);
    if ($status >= 400) {
        $msg = is_array($data) && isset($data['error']) ? $data['error'] : "Erreur HTTP $status";
        throw new RuntimeException((string)$msg);
    }
    return $data;
}

function apiGet(string $path, array $params = []): mixed
{
    $query = $params ? '?' . http_build_query($params) : '';
    return apiRequest('GET', $path . $query);
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
    if ($status >= 400) throw new RuntimeException("Erreur export markdown ($status).");
    return (string)$response;
}

function mdOk(string $msg): string { return "✓ $msg"; }
function fmtDate(?string $iso): string { return $iso ? substr($iso,0,10) : '?'; }

function mdProjects(array $data): string
{
    $projects = $data['projects'] ?? [];
    if (!$projects) return '_Aucun projet trouvé._';
    $out = "# Mes projets (" . count($projects) . ")\n\n";
    foreach ($projects as $p) {
        $out .= "## {$p['title']} *(id: {$p['id']})*\n";
        if (!empty($p['description'])) $out .= $p['description'] . "\n";
        $out .= "Mis à jour le " . fmtDate($p['updated_at']) . "\n\n";
    }
    return $out;
}

// buildTools: minimal subset used by ChatGPT Desktop tests (list_projects + ping + tools/list + tools/call)
function buildTools(): array
{
    return [
        'list_projects' => [
            'description' => "Liste tous les projets de l'utilisateur.",
            'inputSchema' => (object)[],
            'handler' => function($a){ return mdProjects(apiGet('/api/projects')); },
        ],
    ];
}

function mcpRespond($id, $result)
{
    echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
}

function mcpError($id, $code, $message)
{
    echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]], JSON_UNESCAPED_UNICODE) . "\n";
}

function mcpToolResult(string $text): array { return ['content'=>[['type'=>'text','text'=>$text]]]; }

function handle(array $message, array $tools)
{
    $method = $message['method'] ?? '';
    $id = $message['id'] ?? null;
    $params = $message['params'] ?? [];
    switch ($method) {
        case 'initialize':
            mcpRespond($id, ['protocolVersion'=>'2025-03-26','capabilities'=>['tools'=>new stdClass()],'serverInfo'=>['name'=>'ecrivain','version'=>'1.0.0']]);
            break;
        case 'ping':
            mcpRespond($id, new stdClass());
            break;
        case 'tools/list':
            $list = [];
            foreach ($tools as $name => $def) {
                $list[] = ['name'=>$name,'description'=>$def['description'],'inputSchema'=>$def['inputSchema']];
            }
            mcpRespond($id, ['tools'=>$list]);
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
                mcpRespond($id, mcpToolResult((string)$text));
            } catch (Throwable $e) {
                mcpRespond($id, mcpToolResult("Erreur : " . $e->getMessage()));
            }
            break;
        default:
            if ($id !== null) mcpError($id, -32601, "Method not found: $method");
            break;
    }
}

// Only accept POST with JSON body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Use POST with JSON-RPC payload']);
    exit(0);
}

$raw = file_get_contents('php://input');
$msg = json_decode($raw, true);
if (!is_array($msg)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid JSON']);
    exit(0);
}

// Handle
try {
    $tools = buildTools();
    // capture output and return single JSON-RPC response (handler echoes JSON followed by newline)
    ob_start();
    handle($msg, $tools);
    $out = trim(ob_get_clean());
    header('Content-Type: application/json; charset=utf-8');
    echo $out;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()]);
}
