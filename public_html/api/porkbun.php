<?php
require __DIR__ . '/_guard.php'; // v3.0: admin session + same-origin required
ini_set('display_errors', 0);
error_reporting(E_ALL);
/**
 * 818 Nexus — Porkbun Domain Registrar Proxy
 * Proxies domain availability checks and registrations to the Porkbun API.
 *
 * Deploy to: public_html/api/porkbun.php
 * Docs: https://porkbun.com/api/json/v3/documentation
 */

header('Content-Type: application/json');
$allowed_origins = ['https://818nexus.ca', 'https://www.818nexus.ca', 'http://localhost:3000'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

/* ── API Keys ──────────────────────────────────────────────────────── */
$apiKey    = getenv('PORKBUN_API_KEY');
$apiSecret = getenv('PORKBUN_SECRET_KEY');
if (!$apiKey) {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $cfg       = json_decode(file_get_contents($configFile), true);
        $apiKey    = $cfg['porkbun']['apiKey']    ?? '';
        $apiSecret = $cfg['porkbun']['secretKey'] ?? '';
    }
}

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => 'Porkbun API key not configured. Set it in Admin → API Integrations.']);
    exit;
}

/* ── Endpoint routing ──────────────────────────────────────────────── */
$endpoint = $_GET['endpoint'] ?? 'domain/checkDomain';
$allowed  = ['domain/checkDomain', 'domain/create', 'domain/listAll', 'pricing/get'];
if (!in_array($endpoint, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not allowed: ' . $endpoint]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$body['apikey']       = $apiKey;
$body['secretapikey'] = $apiSecret;

$ch = curl_init('https://api.porkbun.com/api/json/v3/' . $endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($body),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['status' => 'ERROR', 'message' => 'cURL error: ' . $curlErr]);
    exit;
}

http_response_code($httpCode);
echo $response;
