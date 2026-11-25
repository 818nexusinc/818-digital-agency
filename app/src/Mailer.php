<?php
declare(strict_types=1);
namespace App;

/**
 * Transactional + lifecycle email. Every send is templated, logged, de-duplicated (dedupe_key),
 * respects suppressions/opt-outs for marketing mail, and carries an unsubscribe link.
 */
final class Mailer
{
    /** Template registry: subject + body (plain text with {placeholders}). */
    public const TEMPLATES = [
        'lead_confirmation' => ['marketing' => false, 'subject' => 'We received your request — 818 Digital Agency',
            'body' => "Hi {name},\n\nThanks for reaching out. We've logged your request and our team (with help from our AI assistant) will follow up within one business day.\n\nSummary: {summary}\n\nYou can reply to this email any time."],
        'proposal' => ['marketing' => false, 'subject' => 'Your proposal {number} from 818 Digital Agency',
            'body' => "Hi {name},\n\nYour proposal \"{title}\" is ready.\n\nOne-time: {one_time}\nRecurring: {recurring}\nValid until: {expires}\n\nReview and accept it here: {link}"],
        'proposal_reminder' => ['marketing' => true, 'subject' => 'Reminder: proposal {number} expires soon',
            'body' => "Hi {name},\n\nJust a reminder that your proposal \"{title}\" is valid until {expires}. Questions? Reply to this email.\n\n{link}"],
        'order_confirmation' => ['marketing' => false, 'subject' => 'Order {number} confirmed — payment received',
            'body' => "Hi {name},\n\nThank you! We received your payment of {total} for order {number}.\n\n{items}\n\nWhat happens next: we've opened project(s) for your order. Track progress any time in your portal: {link}"],
        'onboarding' => ['marketing' => false, 'subject' => 'Next step for {project}: tell us about your business',
            'body' => "Hi {name},\n\nTo start \"{project}\" we need a few details. It takes about 5 minutes:\n\n{link}\n\nNothing is filed or published without your confirmation."],
        'project_update' => ['marketing' => false, 'subject' => 'Update on {project}',
            'body' => "Hi {name},\n\n{update}\n\nSee the full timeline: {link}"],
        'approval_request' => ['marketing' => false, 'subject' => 'Your approval is needed: {project}',
            'body' => "Hi {name},\n\n{update}\n\nReview and approve: {link}"],
        'ticket_update' => ['marketing' => false, 'subject' => '[{number}] {subject}',
            'body' => "Hi {name},\n\n{update}\n\nView the conversation: {link}"],
        'feedback_request' => ['marketing' => false, 'subject' => 'How did we do on {project}?',
            'body' => "Hi {name},\n\n{project} is complete. Could you rate your experience? It takes 30 seconds and helps us improve.\n\n{link}"],
        'renewal_reminder' => ['marketing' => false, 'subject' => 'Upcoming renewal: {service}',
            'body' => "Hi {name},\n\n{service} renews on {date}. No action is needed if you want to keep it. Manage it here: {link}"],
        'follow_up' => ['marketing' => false, 'unsub' => true, 'subject' => '{subject}', 'body' => "{body}"],
        'password_reset' => ['marketing' => false, 'subject' => 'Reset your 818 Digital Agency password',
            'body' => "Hi,\n\nUse this link within one hour to choose a new password:\n\n{link}\n\nIf you didn't request this, ignore this email."],
        'signin_link' => ['marketing' => false, 'subject' => 'Your one-time sign-in link — 818 Digital Agency',
            'body' => "Hi,\n\nSomeone (hopefully you) asked to sign in to the Control Tower without a passkey.\n\nThis link works once, for 15 minutes:\n\n{link}\n\nRequested from {ip} at {at} UTC. If this wasn't you, ignore this email: nobody can sign in without the link, and you can see every attempt in the Control Tower's audit log."],
        'owner_alert' => ['marketing' => false, 'subject' => '[818 Ops] {title}', 'body' => "{body}\n\nOpen the Control Tower: {link}"],
        'owner_report' => ['marketing' => false, 'subject' => '[818 Ops] {title}', 'body' => "{body}"],
    ];

    public static function unsubscribeToken(string $email): string
    {
        return substr(hash_hmac('sha256', strtolower($email), (string)Env::get('APP_KEY', 'dev')), 0, 32);
    }

    public static function suppressed(string $email): bool
    {
        return (bool)Db::val('SELECT 1 FROM email_suppressions WHERE email = ?', [strtolower($email)]);
    }

    /**
     * @return array{ok:bool,status:string,id?:int}
     */
    /** When true (agent simulator), nothing is delivered. */
    public static bool $dryRun = false;
    /** Emails that would have been sent while $dryRun was on (the simulator reports them). */
    public static array $outbox = [];

    public static function send(string $template, string $to, array $vars = [], ?string $dedupeKey = null, ?string $relatedType = null, ?int $relatedId = null): array
    {
        if (self::$dryRun) { self::$outbox[] = ['template' => $template, 'to' => $to, 'subject' => preg_replace_callback('/\{(\w+)\}/', fn($m) => is_scalar($vars[$m[1]] ?? null) ? (string)$vars[$m[1]] : $m[0], (string)(self::TEMPLATES[$template]['subject'] ?? $template))]; return ['ok' => true, 'status' => 'simulated']; }
        $def = self::TEMPLATES[$template] ?? throw new \InvalidArgumentException('Unknown template ' . $template);
        $to = strtolower(trim($to));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'status' => 'invalid'];
        if ($dedupeKey && Db::val('SELECT 1 FROM email_log WHERE dedupe_key = ?', [$dedupeKey])) return ['ok' => true, 'status' => 'duplicate'];
        if ($def['marketing']) {
            $consent = Db::val('SELECT consent_marketing FROM customers WHERE email = ?', [$to]);
            if (self::suppressed($to) || (int)$consent !== 1) return ['ok' => false, 'status' => 'no_consent'];
        } elseif (!empty($def['unsub']) ? self::suppressed($to) : (bool)Db::val("SELECT 1 FROM email_suppressions WHERE email = ? AND reason IN ('bounced','complained')", [$to])) {
            return ['ok' => false, 'status' => 'suppressed'];
        }
        $vars += ['name' => 'there'];
        $fill = fn(string $s) => preg_replace_callback('/\{([a-z_]+)\}/', fn($m) => (string)($vars[$m[1]] ?? ''), $s);
        $subject = mb_substr(str_replace(["\r", "\n"], ' ', $fill($def['subject'])), 0, 200);
        $text = $fill($def['body']);
        $unsub = url('/unsubscribe?e=' . rawurlencode($to) . '&t=' . self::unsubscribeToken($to));
        $text .= "\n\n—\n" . Settings::get('company.name') . ' · ' . Settings::get('company.city') . "\n" . (($def['marketing'] || !empty($def['unsub'])) ? "Unsubscribe: {$unsub}\n" : '');

        $logId = Db::insert('email_log', ['dedupe_key' => $dedupeKey, 'to_email' => $to, 'subject' => $subject, 'template' => $template, 'status' => 'queued',
            'related_type' => $relatedType, 'related_id' => $relatedId, 'created_at' => now(), 'updated_at' => now()]);

        [$ok, $provider, $pid, $err] = self::deliver($to, $subject, $text, ($def['marketing'] || !empty($def['unsub'])) ? $unsub : null);
        Db::update('email_log', ['status' => $ok ? 'sent' : 'failed', 'provider' => $provider, 'provider_id' => $pid, 'error' => $err, 'updated_at' => now()], 'id = ?', [$logId]);
        if (!$ok) Log::warn('email.failed', ['template' => $template, 'error' => $err]);
        return ['ok' => $ok, 'status' => $ok ? 'sent' : 'failed', 'id' => $logId];
    }

    private static function deliver(string $to, string $subject, string $text, ?string $unsub): array
    {
        $driver = Env::get('MAIL_DRIVER', 'auto');
        if ($driver === 'log') { Log::info('email.logged', ['to' => $to, 'subject' => $subject]); return [true, 'log', null, null]; }
        $from = Settings::get('company.name') . ' <' . (Integrations::setting('resend', 'from_email') ?: Env::get('MAIL_FROM', 'hello@818nexus.ca')) . '>';
        $html = self::html($subject, $text);
        if ($key = Integrations::secret('resend', 'api_key')) {
            $payload = ['from' => $from, 'to' => [$to], 'subject' => $subject, 'text' => $text, 'html' => $html,
                'reply_to' => Settings::get('company.email')];
            if ($unsub) $payload['headers'] = ['List-Unsubscribe' => '<' . $unsub . '>'];
            $r = Integrations::http('POST', 'https://api.resend.com/emails', ['Authorization: Bearer ' . $key, 'Content-Type: application/json'], jenc($payload), 15);
            if ($r['status'] >= 200 && $r['status'] < 300) return [true, 'resend', $r['json']['id'] ?? null, null];
            $err = 'resend ' . $r['status'] . ' ' . ($r['error'] ?? mb_substr($r['body'], 0, 200));
            if ($driver === 'resend') return [false, 'resend', null, $err];
        }
        if ($driver === 'resend') return [false, 'resend', null, 'Resend not configured'];
        $headers = "From: {$from}\r\nReply-To: " . Settings::get('company.email') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        if ($unsub) $headers .= "List-Unsubscribe: <{$unsub}>\r\n";
        $ok = @mail($to, $subject, $html, $headers);
        return [$ok, 'php_mail', null, $ok ? null : 'mail() failed and Resend is not configured'];
    }

    public static function html(string $subject, string $text): string
    {
        $body = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#1d4ed8">$1</a>', nl2br(e($text)));
        return '<!doctype html><html><body style="margin:0;background:#f4f5f7;font-family:Segoe UI,Arial,sans-serif">'
            . '<div style="max-width:600px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">'
            . '<div style="background:#0b1530;padding:20px 28px;color:#fff;font-weight:700;font-size:18px">818 <span style="color:#7aa2ff">Digital Agency</span></div>'
            . '<div style="padding:28px;font-size:15px;line-height:1.7;color:#1f2937"><h2 style="margin-top:0;font-size:18px">' . e($subject) . '</h2>' . $body . '</div></div></body></html>';
    }

    public static function notifyOwner(string $title, string $body, string $link = '/admin'): void
    {
        $to = Settings::get('owner.notify_email');
        if ($to) self::send('owner_alert', $to, ['title' => $title, 'body' => $body, 'link' => url($link)], 'owner:' . sha1($title . $body . gmdate('Y-m-d-H')));
    }
}
