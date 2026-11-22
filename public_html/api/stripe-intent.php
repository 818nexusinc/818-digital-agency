<?php
require __DIR__ . '/_guard.php'; // v3.0: admin session + same-origin required
ini_set('display_errors', 0);
error_reporting(E_ALL);
/**
 * 818 Nexus — Stripe PaymentIntent Proxy
 * Creates a Stripe PaymentIntent (CAD) and returns the client_secret
 * to the browser for confirmation via Stripe.js.
 *
 * Key resolution order:
 *  1. STRIPE_SECRET_KEY environment variable (cPanel → PHP INI)
 *  2. api/config.json (written by admin-config.php)
 *  3. stripe_secret_key in POST body (sent from admin localStorage)
 */

header('Content-Type: application/json');
$allowed_origins = ['https://818nexus.ca', 'https://www.818nexus.ca', 'http://localhost:3000', 'http://localhost:5173'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

/* Read body ONCE — php://input is a read-once stream */
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?: [];

/* ── Stripe secret key resolution (3-tier) ───────────────────────────── */
$stripeKey = '';

/* Tier 1: server env var */
$stripeKey = getenv('STRIPE_SECRET_KEY') ?: '';

/* Tier 2: config.json written by admin-config.php */
if (!$stripeKey) {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $cfg       = json_decode(file_get_contents($configFile), true) ?: [];
        $stripeKey = $cfg['stripe']['secretKey'] ?? '';
    }
}

/* v3.0: Tier 3 (secret key sent from the browser) removed. */

/* Validate key was found */
if (!$stripeKey || strlen($stripeKey) < 20) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe secret key not configured. Set it in Admin → API Integrations → Stripe → Secret Key.']);
    exit;
}

/* ── Validate amount ─────────────────────────────────────────────────── */
$amount = isset($body['amount']) ? (int)$body['amount'] : 0;
if ($amount < 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Amount too small (minimum $0.50 CAD).']);
    exit;
}

/* ── Build PaymentIntent ─────────────────────────────────────────────── */
$desc     = htmlspecialchars($body['description']     ?? '818 Nexus Order', ENT_QUOTES);
$custEmail= filter_var($body['customer_email']        ?? '', FILTER_SANITIZE_EMAIL);

$postData = http_build_query([
    'amount'                              => $amount,
    'currency'                            => 'cad',
    'description'                         => $desc,
    'receipt_email'                       => $custEmail,
    'automatic_payment_methods[enabled]'  => 'true',
]);

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_USERPWD        => $stripeKey . ':',
    CURLOPT_HTTPHEADER     => ['Stripe-Version: 2023-10-16'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(500);
    echo json_encode(['error' => 'Network error contacting Stripe: ' . $curlErr]);
    exit;
}

http_response_code($httpCode);
echo $response;
