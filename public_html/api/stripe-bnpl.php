<?php
require __DIR__ . '/_guard.php'; // v3.0: admin session + same-origin required
ini_set('display_errors', 0);
error_reporting(E_ALL);
/**
 * 818 Nexus — Stripe BNPL (Afterpay/Clearpay) Proxy
 * Creates a PaymentIntent with Afterpay/Clearpay as the payment method.
 *
 * Deploy to: public_html/api/stripe-bnpl.php
 */

header('Content-Type: application/json');
$allowed_origins = ['https://818nexus.ca', 'https://www.818nexus.ca', 'http://localhost:3000'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$body      = json_decode(file_get_contents('php://input'), true);
$amount    = isset($body['amount']) ? (int)round((float)$body['amount'] * 100) : 0;
$returnUrl = htmlspecialchars($body['return_url'] ?? 'https://818nexus.ca/cart', ENT_QUOTES);

$stripeKey = getenv('STRIPE_SECRET_KEY');
if (!$stripeKey) {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $cfg = json_decode(file_get_contents($configFile), true);
        $stripeKey = $cfg['stripe']['secretKey'] ?? '';
    }
}

if (!$stripeKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe key not configured.']);
    exit;
}

$postData = http_build_query([
    'amount'                             => $amount,
    'currency'                           => 'cad',
    'payment_method_types[]'             => 'afterpay_clearpay',
    'confirm'                            => 'false',
    'return_url'                         => $returnUrl,
]);

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_USERPWD        => $stripeKey . ':',
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
