<?php
require __DIR__ . '/_guard.php'; // v3.0: admin session + same-origin required
/**
 * 818 Nexus — Transactional Email via Resend
 * 
 * Actions:
 *   POST { __action: "test_connection", resend_api_key }  — test API key, list domains
 *   POST { __action: "save_key", resend_api_key, from_email, from_name, reply_to }  — persist to config.json
 *   POST { to, subject, body, html?, from?, reply_to?, tags?, resend_api_key? }  — send email
 *
 * Resend API: https://api.resend.com
 * Key format: re_xxxxxxxxxxxxxxxxxxxx
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ── CORS ── */
$allowed = ['https://818nexus.ca','https://www.818nexus.ca','http://localhost:3000','http://localhost:5173'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Admin-Token');
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

/* ── Resend API key: env var > request body > config.json ── */
$resendKey = getenv('RESEND_API_KEY') ?: '';
if (!$resendKey) {
    $cfgFile = __DIR__ . '/config.json';
    if (file_exists($cfgFile)) {
        $cfg = json_decode(file_get_contents($cfgFile), true) ?: [];
        $resendKey = $cfg['resend_api_key'] ?? $cfg['resend']['apiKey'] ?? '';
    }
}
/* Request body key overrides stored key (used for testing before saving) */
if (!empty($body['resend_api_key'])) {
    $resendKey = trim($body['resend_api_key']);
}

/* ─────────────────────────────────────────────────────────────────────
   ACTION: test_connection
   Calls GET /domains to verify the key and list verified domains.
───────────────────────────────────────────────────────────────────── */
if (($body['__action'] ?? '') === 'test_connection') {
    if (!$resendKey) {
        echo json_encode(['ok'=>false,'error'=>'No API key provided.']); exit;
    }
    if (!str_starts_with($resendKey, 're_')) {
        echo json_encode(['ok'=>false,'error'=>'Invalid key format. Resend keys start with re_']); exit;
    }

    $ch = curl_init('https://api.resend.com/domains');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $resendKey,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['ok'=>false,'error'=>"cURL error: $curlErr"]); exit;
    }
    $data = json_decode($resp, true) ?: [];

    if ($httpCode === 200) {
        $domains = $data['data'] ?? [];
        $domainCount = count($domains);
        $verifiedDomains = array_filter($domains, fn($d) => ($d['status'] ?? '') === 'verified');
        $msg = $domainCount === 0
            ? 'Connected — no domains verified yet. Add a domain at resend.com/domains.'
            : "Connected — $domainCount domain(s): " . implode(', ', array_column($domains, 'name'));
        echo json_encode([
            'ok'             => true,
            'message'        => $msg,
            'domains'        => $domains,
            'verified_count' => count($verifiedDomains),
        ]);
    } elseif ($httpCode === 401 || $httpCode === 403) {
        $errMsg = $data['message'] ?? $data['name'] ?? 'Unauthorized';
        echo json_encode(['ok'=>false,'error'=>"Auth failed: $errMsg. Check your API key at resend.com/api-keys."]);
    } else {
        $errMsg = $data['message'] ?? "HTTP $httpCode";
        echo json_encode(['ok'=>false,'error'=>"Resend error: $errMsg"]);
    }
    exit;
}

/* ─────────────────────────────────────────────────────────────────────
   ACTION: fetch_log
   Fetches recent emails from Resend /emails endpoint for the Email Log.
───────────────────────────────────────────────────────────────────── */
if (($body['__action'] ?? '') === 'fetch_log') {
    if (!$resendKey) {
        echo json_encode(['ok'=>false,'error'=>'No Resend API key configured.']); exit;
    }
    $limit = min(intval($body['limit'] ?? 100), 100);
    $ch = curl_init("https://api.resend.com/emails?limit={$limit}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $resendKey,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $err = json_decode($resp, true);
        echo json_encode(['ok'=>false,'error'=>$err['message'] ?? "HTTP {$httpCode}"]); exit;
    }
    $data   = json_decode($resp, true) ?: [];
    $emails = $data['data'] ?? [];
    echo json_encode(['ok'=>true,'emails'=>$emails,'count'=>count($emails)]);
    exit;
}

/* ─────────────────────────────────────────────────────────────────────
   ACTION: save_key
   Persists Resend config to config.json (server-side storage).
───────────────────────────────────────────────────────────────────── */
if (($body['__action'] ?? '') === 'save_key') {
    http_response_code(410); echo json_encode(['ok'=>false,'error'=>'Resend settings moved to Admin -> Integrations.']); exit;
}

/* ─────────────────────────────────────────────────────────────────────
   SEND EMAIL
───────────────────────────────────────────────────────────────────── */
$to         = trim($body['to']       ?? '');
$subject    = trim($body['subject']  ?? '');
$text       = trim($body['body']     ?? '');
$htmlBody   = $body['html']          ?? null;
$from       = trim($body['from']     ?? '');
$replyTo    = trim($body['reply_to'] ?? '');
$cc         = $body['cc']            ?? null;
$tags       = $body['tags']          ?? [];

/* Load sender settings from config.json if not in request */
$cfgFile = __DIR__ . '/config.json';
$cfg = file_exists($cfgFile) ? (json_decode(file_get_contents($cfgFile), true) ?: []) : [];
$rcfg = $cfg['resend'] ?? [];
if (!$from)    $from    = $rcfg['fromEmail'] ?? 'hello@818nexus.ca';
if (!$replyTo) $replyTo = $rcfg['replyTo']  ?? 'hello@818nexus.ca';
$fromName = $rcfg['fromName'] ?? '818 Nexus';

if (!$to || !$subject || (!$text && !$htmlBody)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'to, subject, and body are required']); exit;
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid recipient email address']); exit;
}

$fromFull = "{$fromName} <{$from}>";
if (!$htmlBody) $htmlBody = buildEmailHtml($subject, $text);

/* Send via Resend */
if ($resendKey && str_starts_with($resendKey, 're_')) {
    $payload = [
        'from'    => $fromFull,
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $htmlBody,
        'text'    => $text,
    ];
    if ($replyTo) $payload['reply_to'] = $replyTo;
    if ($cc)      $payload['cc']       = is_array($cc) ? $cc : [$cc];
    if (!empty($tags)) {
        $payload['tags'] = array_map(
            fn($t) => is_string($t) ? ['name'=>$t,'value'=>'true'] : $t,
            $tags
        );
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $resendKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if (!$curlErr && ($httpCode === 200 || $httpCode === 201)) {
        $data = json_decode($resp, true);
        echo json_encode([
            'ok'         => true,
            'provider'   => 'resend',
            'message_id' => $data['id'] ?? null,
            'message'    => "Email sent via Resend to {$to}",
        ]);
        exit;
    }
    if ($httpCode === 401 || $httpCode === 403) {
        http_response_code(502);
        $err = json_decode($resp, true);
        echo json_encode(['ok'=>false,'provider'=>'resend','error'=>'Resend auth failed: ' . ($err['message'] ?? 'check API key')]);
        exit;
    }
    /* Other Resend errors: fall through to PHP mail() */
    error_log("[818Nexus] Resend {$httpCode}: " . substr($resp, 0, 200));
}

/* Fallback: PHP mail() */
$headers  = "From: {$fromName} <{$from}>\r\n";
$headers .= "Reply-To: {$replyTo}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";
$headers .= "X-818Nexus-Provider: php-mail\r\n";

$sent = mail($to, $subject, $htmlBody, $headers);
if ($sent) {
    echo json_encode([
        'ok'       => true,
        'provider' => 'php-mail',
        'message'  => "Email sent via PHP mail() to {$to}. Add RESEND_API_KEY for better deliverability.",
    ]);
} else {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'All send methods failed.']);
}

/* ─────────────────────────────────────────────────────────────────────
   HTML EMAIL TEMPLATE — 818 Nexus branded
───────────────────────────────────────────────────────────────────── */
function buildEmailHtml(string $subject, string $text): string {
    $textHtml = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$subject}</title>
  <style>
    body { margin:0; padding:0; background:#F4F6F9; font-family:'Segoe UI',Arial,sans-serif; }
    .wrapper { max-width:600px; margin:32px auto; border-radius:12px; overflow:hidden; box-shadow:0 2px 16px rgba(0,0,0,.08); }
    .header  { background:#1748C2; padding:24px 32px; }
    .logo    { color:#fff; font-size:20px; font-weight:800; letter-spacing:-0.5px; text-decoration:none; }
    .logo-blue { color:#93C5FD; }
    .body    { background:#fff; padding:32px; }
    .badge   { display:inline-block; background:#EBF1FF; color:#1748C2; border-radius:6px; padding:3px 10px; font-size:11px; font-weight:700; margin-bottom:20px; }
    .body p  { font-size:15px; line-height:1.75; color:#374151; margin:0 0 16px; }
    .footer  { background:#F9FAFB; border-top:1px solid #E5E7EB; padding:20px 32px; font-size:12px; color:#9CA3AF; text-align:center; }
    .footer a { color:#6B7280; text-decoration:none; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <a class="logo" href="https://818nexus.ca">818 <span class="logo-blue">NEXUS</span></a>
    </div>
    <div class="body">
      <div class="badge">818 Nexus</div>
      <p>{$textHtml}</p>
    </div>
    <div class="footer">
      <strong>818 Nexus Inc.</strong> &middot; Moncton, New Brunswick, Canada<br>
      <a href="mailto:hello@818nexus.ca">hello@818nexus.ca</a> &middot;
      <a href="https://818nexus.ca">818nexus.ca</a><br><br>
      Automated notification &mdash; please do not reply &middot; &copy; {$year} 818 Nexus Inc.
    </div>
  </div>
</body>
</html>
HTML;
}
