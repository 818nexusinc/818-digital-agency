<?php
require __DIR__ . '/_guard.php'; // v3.0: admin session + same-origin required
/**
 * 818 Nexus — Webmail PHP Proxy (v2)
 * Bridges IMAP/SMTP for the in-app email client.
 * Inspired by oxi (github.com/c0h1b4/oxi) architecture.
 *
 * Config resolution order (first non-empty wins):
 *   1. Environment variables  (NX818_IMAP_HOST etc.)
 *   2. api/config.json        (written by admin-config.php — SAME directory)
 *   3. ../config.json         (legacy path, site root)
 *   4. POST body credentials  (sent from JS when testing before server-save)
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_GET['action'] ?? '';

/* ── Config resolution ────────────────────────────────────────────── */
$IMAP_HOST = getenv('NX818_IMAP_HOST') ?: '';
$IMAP_PORT = getenv('NX818_IMAP_PORT') ?: '993';
$SMTP_HOST = getenv('NX818_SMTP_HOST') ?: '';
$SMTP_PORT = getenv('NX818_SMTP_PORT') ?: '587';
$EMAIL     = getenv('NX818_EMAIL')     ?: '';
$PASSWORD  = getenv('NX818_EMAIL_PASS') ?: '';

/* Path 1: api/config.json (same dir as this file — written by admin-config.php) */
$api_cfg_path  = __DIR__ . '/config.json';
/* Path 2: ../config.json (site root — legacy) */
$root_cfg_path = __DIR__ . '/../config.json';

foreach ([$api_cfg_path, $root_cfg_path] as $cfg_path) {
    if (!$PASSWORD && file_exists($cfg_path)) {
        $cfg = json_decode(file_get_contents($cfg_path), true) ?? [];
        $wm  = $cfg['webmail'] ?? [];
        if (!empty($wm['password'])) {
            $IMAP_HOST = !empty($wm['imapHost']) ? $wm['imapHost'] : ($IMAP_HOST ?: 'mail.818nexus.ca');
            $IMAP_PORT = !empty($wm['imapPort']) ? $wm['imapPort'] : $IMAP_PORT;
            $SMTP_HOST = !empty($wm['smtpHost']) ? $wm['smtpHost'] : ($SMTP_HOST ?: 'mail.818nexus.ca');
            $SMTP_PORT = !empty($wm['smtpPort']) ? $wm['smtpPort'] : $SMTP_PORT;
            $EMAIL     = !empty($wm['email'])    ? $wm['email']    : ($EMAIL ?: 'weborders@818nexus.ca');
            $PASSWORD  = $wm['password'];
            break;
        }
    }
}

/* Path 3: POST body override (JS sends creds for live test before server save) */
if (!$PASSWORD && !empty($body['email']) && !empty($body['password'])) {
    $EMAIL     = $body['email'];
    $PASSWORD  = $body['password'];
    $IMAP_HOST = !empty($body['imapHost']) ? $body['imapHost'] : ($IMAP_HOST ?: 'mail.818nexus.ca');
    $IMAP_PORT = !empty($body['imapPort']) ? $body['imapPort'] : $IMAP_PORT;
    $SMTP_HOST = !empty($body['smtpHost']) ? $body['smtpHost'] : ($SMTP_HOST ?: 'mail.818nexus.ca');
    $SMTP_PORT = !empty($body['smtpPort']) ? $body['smtpPort'] : $SMTP_PORT;
}

/* Defaults if still empty */
if (!$IMAP_HOST) $IMAP_HOST = 'mail.818nexus.ca';
if (!$SMTP_HOST) $SMTP_HOST = 'mail.818nexus.ca';
if (!$EMAIL)     $EMAIL     = 'weborders@818nexus.ca';

function nx_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
function nx_ok($data = []) {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

/* ── IMAP connection helper ──────────────────────────────────────── */
function imap_conn($host, $port, $email, $pass, $folder = 'INBOX') {
    if (!function_exists('imap_open')) return null;
    $mbox = '{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}' . $folder;
    $c = @imap_open($mbox, $email, $pass, 0, 1);
    return $c ?: null;
}

/* ── Demo mode when IMAP ext absent or no credentials ──────────────── */
function no_imap($action) {
    $demo_msgs = [
        ['uid'=>1001,'msgno'=>1,'subject'=>'Welcome to 818 Nexus Webmail','from'=>'system@818nexus.ca','to'=>'weborders@818nexus.ca','date'=>date('r',strtotime('-2 hours')),'read'=>true,'flagged'=>false,'size'=>1024,'preview'=>'Your webmail client is set up and ready.'],
        ['uid'=>1002,'msgno'=>2,'subject'=>'New Order: NUANS Name Search — Client','from'=>'noreply@818nexus.ca','to'=>'weborders@818nexus.ca','date'=>date('r',strtotime('-1 hour')),'read'=>false,'flagged'=>true,'size'=>2048,'preview'=>'A new NUANS order has been placed. Order ID: ORD-001'],
        ['uid'=>1003,'msgno'=>3,'subject'=>'Domain Registration Confirmation','from'=>'no-reply@20i.com','to'=>'weborders@818nexus.ca','date'=>date('r',strtotime('-30 minutes')),'read'=>false,'flagged'=>false,'size'=>1536,'preview'=>'Your domain has been successfully registered.'],
        ['uid'=>1004,'msgno'=>4,'subject'=>'Re: Business Incorporation Quote','from'=>'client@example.ca','to'=>'weborders@818nexus.ca','date'=>date('r',strtotime('-15 minutes')),'read'=>false,'flagged'=>false,'size'=>876,'preview'=>"Thank you for the quote. We'd like to proceed."],
    ];
    if ($action === 'list_messages') nx_ok(['messages' => $demo_msgs, 'total' => count($demo_msgs), 'page' => 1, 'demo' => true]);
    if ($action === 'get_message')   nx_ok(['uid'=>1001,'msgno'=>1,'subject'=>'[Demo] Configure webmail to load real emails','from'=>'system@818nexus.ca','to'=>'weborders@818nexus.ca','date'=>date('r'),'body'=>"Configure your IMAP credentials:\n\n1. Go to Admin → API Integrations\n2. Find Webmail (IMAP/SMTP) → Configure\n3. Enter your email, password, IMAP/SMTP host\n4. Click Save Credentials\n5. Click Test Connection\n\nIMAP Host: mail.818nexus.ca (port 993)\nSMTP Host: mail.818nexus.ca (port 587)","html"=>false,'attachments'=>[],'demo'=>true]);
    if ($action === 'list_folders')  nx_ok(['folders' => ['INBOX', 'Sent', 'Drafts', 'Spam', 'Trash'], 'demo' => true]);
    nx_ok(['demo' => true, 'message' => 'IMAP not configured']);
}

/* ── Route actions ───────────────────────────────────────────────── */
switch ($action) {

  case 'config':
    nx_err('Webmail credentials are managed in Admin -> Integrations -> Company mailbox (encrypted).', 410);

  case 'test_connection':
    if (!$PASSWORD) {
        nx_err('Webmail credentials not found. Save them in Admin → API Integrations → Webmail (IMAP/SMTP) → Configure → Save Credentials.');
    }
    if (!function_exists('imap_open')) {
        /* IMAP extension not available — report config as found but IMAP unavailable */
        nx_ok(['connected' => false, 'host' => $IMAP_HOST, 'email' => $EMAIL,
               'messages' => 0, 'imap_available' => false,
               'note' => 'Credentials found. PHP IMAP extension not enabled on this server. Contact hosting to enable php_imap.']);
    }
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD);
    if (!$c) {
        $err = imap_last_error() ?: 'Connection refused';
        nx_err('IMAP connection failed: ' . $err . ' — Host: ' . $IMAP_HOST . ':' . $IMAP_PORT . ', Email: ' . $EMAIL, 502);
    }
    $n = imap_num_msg($c);
    imap_close($c);
    nx_ok(['connected' => true, 'host' => $IMAP_HOST, 'email' => $EMAIL, 'messages' => $n, 'imap_available' => true]);

  case 'list_folders':
    if (!$PASSWORD) { no_imap($action); }
    if (!function_exists('imap_open')) { no_imap($action); }
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD);
    if (!$c) { no_imap($action); }
    $raw = imap_list($c, '{' . $IMAP_HOST . ':' . $IMAP_PORT . '/imap/ssl/novalidate-cert}', '*') ?: [];
    $folders = array_map(function($f) use ($IMAP_HOST, $IMAP_PORT) {
        $name = str_replace('{' . $IMAP_HOST . ':' . $IMAP_PORT . '/imap/ssl/novalidate-cert}', '', $f);
        return imap_utf7_decode($name);
    }, $raw);
    imap_close($c);
    nx_ok(['folders' => $folders ?: ['INBOX', 'Sent', 'Drafts', 'Spam', 'Trash']]);

  case 'list_messages':
    if (!$PASSWORD || !function_exists('imap_open')) { no_imap($action); }
    $folder = $body['folder'] ?? 'INBOX';
    $page   = max(1, (int)($body['page'] ?? 1));
    $limit  = min(50, max(1, (int)($body['limit'] ?? 25)));
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD, $folder);
    if (!$c) { no_imap($action); }
    $total = imap_num_msg($c);
    $msgs  = [];
    if ($total > 0) {
        $end   = max(1, $total - ($page - 1) * $limit);
        $start = max(1, $end - $limit + 1);
        $overview = imap_fetch_overview($c, "$start:$end", 0) ?: [];
        foreach (array_reverse($overview) as $ov) {
            $msgs[] = [
                'uid'     => $ov->uid ?? $ov->msgno,
                'msgno'   => $ov->msgno,
                'subject' => isset($ov->subject) ? imap_utf8($ov->subject) : '(no subject)',
                'from'    => isset($ov->from) ? imap_utf8($ov->from) : '',
                'to'      => isset($ov->to) ? imap_utf8($ov->to) : '',
                'date'    => $ov->date ?? '',
                'read'    => (bool)($ov->seen ?? false),
                'flagged' => (bool)($ov->flagged ?? false),
                'size'    => $ov->size ?? 0,
                'preview' => '',
            ];
        }
    }
    imap_close($c);
    nx_ok(['messages' => $msgs, 'total' => $total, 'page' => $page, 'folder' => $folder]);

  case 'get_message':
    if (!$PASSWORD || !function_exists('imap_open')) { no_imap($action); }
    $folder = $body['folder'] ?? 'INBOX';
    $msgno  = (int)($body['msgno'] ?? 1);
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD, $folder);
    if (!$c) { no_imap($action); }
    $header = imap_headerinfo($c, $msgno);
    $struct = imap_fetchstructure($c, $msgno);
    $html_body = ''; $text_body = '';
    if ($struct->type === 0) {
        $raw = imap_body($c, $msgno, FT_PEEK);
        $enc = $struct->encoding ?? 0;
        if ($enc === 3) $raw = base64_decode($raw);
        elseif ($enc === 4) $raw = quoted_printable_decode($raw);
        if (strtolower($struct->subtype ?? '') === 'html') $html_body = $raw;
        else $text_body = $raw;
    } elseif ($struct->type === 1 && isset($struct->parts)) {
        foreach ($struct->parts as $pi => $part) {
            $raw = imap_fetchbody($c, $msgno, (string)($pi + 1), FT_PEEK);
            $enc = $part->encoding ?? 0;
            if ($enc === 3) $raw = base64_decode($raw);
            elseif ($enc === 4) $raw = quoted_printable_decode($raw);
            if (strtolower($part->subtype ?? '') === 'html') $html_body = $raw;
            elseif (strtolower($part->subtype ?? '') === 'plain') $text_body = $raw;
        }
    }
    imap_setflag_full($c, (string)$msgno, '\\Seen');
    $from_addr = '';
    if (isset($header->from[0])) {
        $fn = isset($header->from[0]->personal) ? imap_utf8($header->from[0]->personal) . ' ' : '';
        $from_addr = $fn . '<' . ($header->from[0]->mailbox ?? '') . '@' . ($header->from[0]->host ?? '') . '>';
    }
    imap_close($c);
    nx_ok([
        'msgno'       => $msgno,
        'subject'     => imap_utf8($header->subject ?? '(no subject)'),
        'from'        => $from_addr,
        'to'          => isset($header->to[0]) ? ($header->to[0]->mailbox ?? '') . '@' . ($header->to[0]->host ?? '') : '',
        'date'        => $header->date ?? '',
        'body'        => $html_body ?: nl2br(htmlspecialchars($text_body, ENT_QUOTES, 'UTF-8')),
        'html'        => !empty($html_body),
        'attachments' => [],
    ]);

  case 'mark_read':
    if (!$PASSWORD || !function_exists('imap_open')) nx_err('IMAP not configured');
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD, $body['folder'] ?? 'INBOX');
    if (!$c) nx_err('Connection failed', 502);
    $msgno = (string)((int)($body['msgno'] ?? 0));
    if ($body['read'] ?? true) imap_setflag_full($c, $msgno, '\\Seen');
    else                       imap_clearflag_full($c, $msgno, '\\Seen');
    imap_close($c);
    nx_ok(['marked' => true]);

  case 'delete':
    if (!$PASSWORD || !function_exists('imap_open')) nx_err('IMAP not configured');
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD, $body['folder'] ?? 'INBOX');
    if (!$c) nx_err('Connection failed', 502);
    imap_delete($c, (string)((int)($body['msgno'] ?? 0)));
    imap_expunge($c);
    imap_close($c);
    nx_ok(['deleted' => true]);

  case 'move':
    if (!$PASSWORD || !function_exists('imap_open')) nx_err('IMAP not configured');
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD, $body['folder'] ?? 'INBOX');
    if (!$c) nx_err('Connection failed', 502);
    imap_mail_move($c, (string)((int)($body['msgno'] ?? 0)), $body['dest'] ?? 'Trash');
    imap_expunge($c);
    imap_close($c);
    nx_ok(['moved' => true]);

  case 'search':
    if (!$PASSWORD || !function_exists('imap_open')) { no_imap($action); }
    $c = imap_conn($IMAP_HOST, $IMAP_PORT, $EMAIL, $PASSWORD, $body['folder'] ?? 'INBOX');
    if (!$c) { no_imap($action); }
    $q = addslashes(trim($body['query'] ?? ''));
    $results = $q ? (imap_search($c, 'TEXT "' . $q . '"', SE_UID) ?: []) : [];
    $msgs = [];
    foreach (array_slice(array_reverse($results), 0, 50) as $uid) {
        $ov = imap_fetch_overview($c, (string)$uid, FT_UID);
        if ($ov) $msgs[] = ['uid'=>$uid,'msgno'=>$ov[0]->msgno,'subject'=>imap_utf8($ov[0]->subject??''),'from'=>imap_utf8($ov[0]->from??''),'date'=>$ov[0]->date??'','read'=>(bool)($ov[0]->seen??false),'flagged'=>false,'size'=>$ov[0]->size??0,'preview'=>''];
    }
    imap_close($c);
    nx_ok(['messages' => $msgs, 'total' => count($msgs), 'query' => $body['query'] ?? '']);

  case 'send':
    $to      = trim($body['to']      ?? '');
    $subject = trim($body['subject'] ?? '');
    $message = trim($body['body']    ?? '');
    $cc      = trim($body['cc']      ?? '');
    if (!$to || !$subject || !$message) nx_err('to, subject, and body are required');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) nx_err('Invalid "to" address');

    /* Try Resend API first (most reliable on cPanel) */
    $resend_key = '';
    foreach ([$api_cfg_path, $root_cfg_path] as $cp) {
        if (file_exists($cp)) {
            $c2 = json_decode(file_get_contents($cp), true) ?? [];
            $resend_key = $c2['resend']['apiKey'] ?? '';
            if ($resend_key) break;
        }
    }
    if ($resend_key) {
        $from_email = !empty($EMAIL) ? $EMAIL : 'weborders@818nexus.ca';
        $payload = ['from' => '818 Nexus <' . $from_email . '>', 'to' => [$to],
            'subject' => $subject, 'text' => strip_tags($message),
            'html' => nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))];
        if ($cc) $payload['cc'] = [$cc];
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $resend_key, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 15]);
        $res = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $json = json_decode($res, true) ?? [];
        if ($http === 200 || $http === 201) nx_ok(['sent' => true, 'provider' => 'resend', 'id' => $json['id'] ?? '']);
        nx_err('Resend failed (' . $http . '): ' . ($json['message'] ?? $res), 502);
    }

    /* Fallback: PHP mail() */
    $headers  = "From: weborders@818nexus.ca\r\nReply-To: weborders@818nexus.ca\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\nX-Mailer: 818NexusWebmail/2.0\r\n";
    if ($cc) $headers .= "Cc: $cc\r\n";
    if (@mail($to, $subject, $message, $headers)) nx_ok(['sent' => true, 'provider' => 'php_mail']);
    nx_err('Send failed. Configure Resend API key for reliable delivery.', 502);

  default:
    nx_err('Unknown action: ' . htmlspecialchars($action), 400);
}
