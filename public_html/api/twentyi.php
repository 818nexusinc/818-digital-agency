<?php
require __DIR__ . '/_guard.php'; // v3.0: admin session + same-origin required
/**
 * 818 Nexus — 20i Reseller API Proxy (v2)
 * Handles Domain Registration + Business Email via 20i REST API
 *
 * 20i API docs: https://my.20i.com/reseller/apiDoc
 * Base URL: https://api.20i.com
 * Auth: Bearer {API_KEY}  (combined key format: user+password OR OAuth token)
 *
 * Endpoints implemented:
 *   Domain:  searchDomain, registerDomain, listDomains, getDomainPricing, renewDomain
 *   Email:   listPackages, createMailbox, listMailboxes, deleteMailbox,
 *            updateMailboxPassword, getMailboxInfo, createEmailPackage
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ── CORS ─────────────────────────────────────────────────────────── */
header('Content-Type: application/json');
$allowed_origins = ['https://818nexus.ca', 'https://www.818nexus.ca', 'http://localhost:3000', 'http://localhost:5173'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

/* ── API Key ───────────────────────────────────────────────────────── */
$apiKey = getenv('TWENTYI_API_KEY');
if (!$apiKey) {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $cfg    = json_decode(file_get_contents($configFile), true);
        $apiKey = $cfg['twentyi']['apiKey'] ?? '';
    }
}
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => '20i API key not configured. Set TWENTYI_API_KEY env var or config.json.']);
    exit;
}

/* ── Request ───────────────────────────────────────────────────────── */
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

/* ── Allowed actions whitelist ─────────────────────────────────────── */
$allowed = [
    /* Domain */
    'domain_search', 'domain_register', 'domain_list', 'domain_pricing',
    'domain_renew', 'domain_info', 'domain_transfer',
    /* Email */
    'email_list_packages', 'email_create_package', 'email_list_mailboxes',
    'email_create_mailbox', 'email_delete_mailbox', 'email_update_password',
    'email_get_info',
    /* Auth test */
    'ping', 'list_packages',
];

if ($action && !in_array($action, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => "Action not permitted: $action"]);
    exit;
}

/* ── 20i API helper ────────────────────────────────────────────────── */
$API_BASE = 'https://api.20i.com';

function ti_request(string $path, string $method = 'GET', ?array $payload = null): array {
    global $apiKey, $API_BASE;
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $ch = curl_init($API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['_error' => "cURL: $err", '_code' => 0];
    $decoded = json_decode($raw, true);
    if ($decoded === null) $decoded = ['_raw' => $raw];
    $decoded['_code'] = $httpCode;
    return $decoded;
}

/* ── Route ─────────────────────────────────────────────────────────── */
switch ($action) {

    /* ────────────────── AUTH / PING ─────────────────────────────────── */
    case 'ping':
    case 'list_packages':
        $r = ti_request('/package');
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;

    /* ────────────────── DOMAIN ACTIONS ──────────────────────────────── */
    case 'domain_pricing': {
        /* GET /reseller/{ref}/domain/pricing — list all TLD prices */
        $r = ti_request('/reseller/tld-pricing');
        /* Fallback: try the package domain pricing endpoint */
        if (($r['_code'] ?? 0) >= 400) {
            $r = ti_request('/reseller/domain-pricing');
        }
        http_response_code(200);
        echo json_encode($r);
        break;
    }

    case 'domain_search': {
        /*
         * 20i domain search: GET /domain/search?domainName=example.ca
         * Returns: {available: bool, price: {register, renew, transfer}}
         * Also check via WHOIS endpoint as fallback.
         */
        $domainName = trim($body['domain'] ?? '');
        if (!$domainName) {
            http_response_code(400);
            echo json_encode(['error' => 'domain required']);
            break;
        }
        /* Primary: 20i domain availability check */
        $r = ti_request('/domain/search?domainName=' . urlencode($domainName));
        if (($r['_code'] ?? 0) >= 400) {
            /* Fallback: reseller domain check */
            $r = ti_request('/reseller/domain/search?domainName=' . urlencode($domainName));
        }
        http_response_code(200);
        echo json_encode($r);
        break;
    }

    case 'domain_register': {
        /*
         * POST /reseller/{ref}/addDomain
         * Body: {domainName, years, registrant: {name, email, phone, address, city, state, postcode, country}}
         * OR POST /package with type=domain
         */
        $domainName  = trim($body['domain'] ?? '');
        $years       = intval($body['years'] ?? 1);
        $registrant  = $body['registrant'] ?? [];
        if (!$domainName) {
            http_response_code(400);
            echo json_encode(['error' => 'domain required']);
            break;
        }
        /* Build 20i registration payload */
        $payload = [
            'domainName' => $domainName,
            'years'      => $years,
            'registrant' => array_merge([
                'name'     => '818 Nexus Inc.',
                'email'    => 'domains@818nexus.ca',
                'phone'    => '+1.5068556626',
                'address'  => '26 Westview Terrace',
                'city'     => 'Riverview',
                'state'    => 'NB',
                'postcode' => 'E1B 0E8',
                'country'  => 'CA',
            ], $registrant),
            'privacy'    => $body['privacy'] ?? true,
            'autorenew'  => $body['autorenew'] ?? false,
        ];
        /* Try primary reseller domain registration endpoint */
        $r = ti_request('/reseller/domain/register', 'POST', $payload);
        if (($r['_code'] ?? 0) >= 400) {
            /* Fallback: package-based domain provisioning */
            $r = ti_request('/package', 'POST', [
                'type'    => 'domain',
                'names'   => [['name' => $domainName, 'years' => $years]],
            ]);
        }
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;
    }

    case 'domain_list': {
        /* GET /reseller/{ref}/domains — list all domains on reseller account */
        $r = ti_request('/reseller/domain');
        if (($r['_code'] ?? 0) >= 400) {
            $r = ti_request('/domain');
        }
        http_response_code(200);
        echo json_encode($r);
        break;
    }

    case 'domain_info': {
        $domainName = trim($body['domain'] ?? '');
        if (!$domainName) { http_response_code(400); echo json_encode(['error'=>'domain required']); break; }
        $r = ti_request('/domain/' . urlencode($domainName));
        http_response_code(200);
        echo json_encode($r);
        break;
    }

    case 'domain_renew': {
        $domainName = trim($body['domain'] ?? '');
        $years      = intval($body['years'] ?? 1);
        if (!$domainName) { http_response_code(400); echo json_encode(['error'=>'domain required']); break; }
        $r = ti_request('/domain/' . urlencode($domainName) . '/renew', 'POST', ['years' => $years]);
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;
    }

    case 'domain_transfer': {
        $domainName = trim($body['domain'] ?? '');
        $authCode   = trim($body['authCode'] ?? '');
        if (!$domainName) { http_response_code(400); echo json_encode(['error'=>'domain required']); break; }
        $r = ti_request('/reseller/domain/transfer', 'POST', [
            'domainName' => $domainName,
            'authCode'   => $authCode,
        ]);
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;
    }

    /* ────────────────── EMAIL ACTIONS ───────────────────────────────── */
    case 'email_list_packages': {
        /* GET /package — returns all hosting/email packages */
        $r = ti_request('/package');
        http_response_code(200);
        echo json_encode($r);
        break;
    }

    case 'email_create_package': {
        /*
         * Create a new hosting/email package for a domain.
         * 20i: POST /reseller/{ref}/addWeb with type details
         * OR:  POST /package to create a new managed package
         */
        $domain      = trim($body['domain'] ?? '');
        $plan        = $body['plan'] ?? 'starter';   /* starter|business|team */
        $label       = $body['label'] ?? "818nexus-email-$domain";

        $planMap = [
            'starter'  => 'Email Starter',
            'business' => 'Email Business',
            'team'     => 'Email Team',
        ];

        $r = ti_request('/package', 'POST', [
            'type'    => 'email',
            'label'   => $label,
            'names'   => [['name' => $domain]],
            'stackUser' => [
                'username' => preg_replace('/[^a-z0-9]/', '', strtolower($domain)),
            ],
        ]);
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;
    }

    case 'email_list_mailboxes': {
        $pkgId = trim($body['packageId'] ?? '');
        if (!$pkgId) { http_response_code(400); echo json_encode(['error'=>'packageId required']); break; }
        $r = ti_request('/package/' . urlencode($pkgId) . '/email/mailboxes');
        http_response_code(200);
        echo json_encode($r);
        break;
    }

    case 'email_create_mailbox': {
        $pkgId    = trim($body['packageId'] ?? '');
        $name     = trim($body['name'] ?? '');      /* local part e.g. "info" */
        $password = $body['password'] ?? '';
        $quota    = intval($body['quota'] ?? 2048); /* MB */
        if (!$pkgId || !$name) {
            http_response_code(400);
            echo json_encode(['error' => 'packageId and name required']);
            break;
        }
        $r = ti_request('/package/' . urlencode($pkgId) . '/email/mailboxes', 'POST', [
            'name'     => $name,
            'password' => $password,
            'quota'    => $quota,
        ]);
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;
    }

    case 'email_delete_mailbox': {
        $pkgId   = trim($body['packageId'] ?? '');
        $mailbox = trim($body['mailbox'] ?? '');
        if (!$pkgId || !$mailbox) {
            http_response_code(400);
            echo json_encode(['error' => 'packageId and mailbox required']);
            break;
        }
        $r = ti_request('/package/' . urlencode($pkgId) . '/email/mailboxes/' . urlencode($mailbox), 'DELETE');
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;
    }

    case 'email_update_password': {
        $pkgId    = trim($body['packageId'] ?? '');
        $mailbox  = trim($body['mailbox'] ?? '');
        $password = $body['password'] ?? '';
        if (!$pkgId || !$mailbox) {
            http_response_code(400);
            echo json_encode(['error' => 'packageId and mailbox required']);
            break;
        }
        $r = ti_request(
            '/package/' . urlencode($pkgId) . '/email/mailboxes/' . urlencode($mailbox) . '/password',
            'POST',
            ['password' => $password]
        );
        http_response_code($r['_code'] ?? 200);
        echo json_encode($r);
        break;
    }

    case 'email_get_info': {
        $pkgId = trim($body['packageId'] ?? '');
        if (!$pkgId) { http_response_code(400); echo json_encode(['error'=>'packageId required']); break; }
        $r = ti_request('/package/' . urlencode($pkgId));
        http_response_code(200);
        echo json_encode($r);
        break;
    }

    default: {
        /* No action → return API status for health checks */
        $r = ti_request('/package');
        $healthy = ($r['_code'] ?? 0) < 400;
        http_response_code(200);
        echo json_encode([
            'status'  => $healthy ? 'ok' : 'error',
            'message' => '818 Nexus 20i API Proxy v2',
            'code'    => $r['_code'] ?? 0,
        ]);
        break;
    }
}
