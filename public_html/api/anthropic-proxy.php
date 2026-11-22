<?php
require __DIR__ . '/_guard.php'; // v3.0: admin session + same-origin required
/**
 * 818 Nexus — Anthropic Claude API Proxy
 * RISK-002: CORS restricted to own domain.
 * RISK-009: No unauthenticated writes accepted.
 * RISK-018: PHP errors suppressed.
 * FIX-2026-09: Added anthropic-workspace-id header for identity-linked API keys.
 *
 * Deploy to: public_html/api/anthropic-proxy.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ── CORS ───────────────────────────────────────────────────────────── */
$allowed_origins = ['https://818nexus.ca', 'https://www.818nexus.ca', 'http://localhost:3000'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    
}
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Method not allowed']]);
    exit;
}

/* ── KEY RESOLUTION ─────────────────────────────────────────────────── */
$HARDCODED_KEY        = "";   /* v3.0: never hard-code keys; use Admin -> Integrations */
$HARDCODED_WORKSPACE  = "";   /* ← paste your workspace ID (wrkspc_...) here if needed */

$apiKey     = '';
$workspaceId = '';

/* Priority 1: hardcoded values in this file */
if (!empty(trim($HARDCODED_KEY)))       $apiKey      = trim($HARDCODED_KEY);
if (!empty(trim($HARDCODED_WORKSPACE))) $workspaceId = trim($HARDCODED_WORKSPACE);

/* Priority 2: cPanel environment variables */
if (empty($apiKey)) {
    $envKey = getenv('ANTHROPIC_API_KEY');
    if (!empty(trim((string)$envKey))) $apiKey = trim($envKey);
}
if (empty($workspaceId)) {
    $envWs = getenv('ANTHROPIC_WORKSPACE_ID');
    if (!empty(trim((string)$envWs))) $workspaceId = trim($envWs);
}

/* Priority 3: config.json (same directory, then parent directory) */
foreach ([__DIR__ . '/config.json', dirname(__DIR__) . '/config.json'] as $configFile) {
    if (!file_exists($configFile)) continue;
    $config = json_decode(file_get_contents($configFile), true) ?? [];
    if (empty($apiKey)) {
        $candidate = trim($config['anthropic']['apiKey'] ?? '');
        if (!empty($candidate) && $candidate !== '""') $apiKey = $candidate;
    }
    if (empty($workspaceId)) {
        $candidate = trim($config['anthropic']['workspaceId'] ?? '');
        if (!empty($candidate)) $workspaceId = $candidate;
    }
    if (!empty($apiKey)) break;
}

if (empty($apiKey) || strlen($apiKey) < 20) {
    http_response_code(500);
    echo json_encode(['error' => [
        'type'    => 'configuration_error',
        'message' => 'Anthropic API key not configured. Add it in Admin -> Integrations.',
            ]]);
    exit;
}

/* ── REQUEST BODY ───────────────────────────────────────────────────── */
$body    = file_get_contents('php://input');
$decoded = json_decode($body, true);
if (!$decoded) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid JSON body']]);
    exit;
}

/* Safety: enforce model and cap tokens */
$decoded['model']      = getenv('NX818_LEGACY_MODEL') ?: 'claude-sonnet-5-5';
$decoded['max_tokens'] = min((int)($decoded['max_tokens'] ?? 1000), 4096);

/* ── OUTBOUND HEADERS ───────────────────────────────────────────────── */
$httpHeaders = [
    'Content-Type: application/json',
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01',
];

/*
 * Identity-linked API keys (issued inside an Anthropic workspace) REQUIRE
 * the anthropic-workspace-id header. Set ANTHROPIC_WORKSPACE_ID in cPanel
 * environment variables, or paste the wrkspc_... value into $HARDCODED_WORKSPACE.
 * If you are using a classic (non-identity-linked) key this header is ignored.
 */
if (!empty($workspaceId)) {
    $httpHeaders[] = 'anthropic-workspace-id: ' . $workspaceId;
}

/* ── CURL REQUEST ───────────────────────────────────────────────────── */
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($decoded),
    CURLOPT_HTTPHEADER     => $httpHeaders,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => ['message' => 'cURL error: ' . $curlError]]);
    exit;
}

http_response_code($httpCode);
echo $response;
