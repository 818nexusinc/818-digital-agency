<?php
declare(strict_types=1);
namespace App;

/** Website + portal AI conversations (Sales for visitors, Concierge for signed-in customers). */
final class ChatService
{
    public const SUGGESTIONS = [
        'default' => ['I just started a business — what do I need?', 'How much does incorporation cost in NB?', 'Is my domain name available?', 'I need a website for my company'],
        'registration' => ['Sole proprietorship or corporation?', 'What does a NUANS search do?', 'Federal vs provincial incorporation?'],
        'websites' => ['Which website plan fits me?', 'Can you redesign my current site?', 'Do you build online stores?'],
        'domains' => ['Check if mybusiness.ca is available', '.ca or .com?', 'Can I transfer my domain?'],
        'email' => ['How many mailboxes do I need?', 'Can I keep my current email?'],
        'portal' => ['What is the status of my project?', 'Show my orders', 'I need help with something'],
    ];

    public static function handle(?string $publicId, string $message, array $page = []): array
    {
        $message = trim(mb_substr($message, 0, 2000));
        if ($message === '') throw new UserError('Type a message first.');
        $ipLimit = max(5, Settings::int('ai.chat_rate_per_hour') ?: 40);
        if (!RateLimit::hit('chat:' . client_ip(), $ipLimit, 3600)) throw new UserError('You have sent a lot of messages. Please wait a little, or email ' . Settings::get('company.email') . '.');

        $user = Auth::user();
        $customer = $user ? Auth::customer() : null;
        $conv = $publicId ? Db::one('SELECT * FROM conversations WHERE public_id = ?', [$publicId]) : null;
        // A conversation belongs to whoever started it: block access to someone else's thread.
        if ($conv && $conv['user_id'] && (!$user || (int)$conv['user_id'] !== (int)$user['id'])) $conv = null;
        if ($conv && !$conv['user_id'] && (($_SESSION['chat_ids'] ?? null) === null || !in_array($conv['public_id'], $_SESSION['chat_ids'], true))) $conv = null;
        if (!$conv) {
            $agentSlug = $customer ? 'concierge' : 'sales';
            $pid = rid(16);
            $id = Db::insert('conversations', ['public_id' => $pid, 'customer_id' => $customer['id'] ?? null, 'user_id' => $user['id'] ?? null, 'channel' => $customer ? 'portal' : 'web',
                'agent_slug' => $agentSlug, 'page_context' => mb_substr((string)($page['path'] ?? ''), 0, 190), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $conv = Db::one('SELECT * FROM conversations WHERE id = ?', [$id]);
            Session::start(); $_SESSION['chat_ids'][] = $pid;
        }
        if ((int)Db::val('SELECT COUNT(*) FROM messages WHERE conversation_id = ?', [$conv['id']]) > 80) throw new UserError('This conversation is very long — please start a new one.');

        Db::insert('messages', ['conversation_id' => $conv['id'], 'role' => 'user', 'content' => $message, 'meta' => jenc(['page' => $page['path'] ?? null]), 'created_at' => now()]);
        // Contact details shared in chat become a lead, whether or not an AI provider is connected.
        try { if (ChatLeads::fromMessage($conv, $message)) $conv['customer_id'] = Db::val('SELECT customer_id FROM conversations WHERE id = ?', [$conv['id']]); } catch (\Throwable $e) { Log::error('chat.lead_failed', ['error' => $e->getMessage()]); }
        $history = array_reverse(Db::all('SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 14', [$conv['id']]));
        $msgs = [];
        foreach ($history as $h) {
            $msgs[] = $h['role'] === 'user' ? ['role' => 'user', 'content' => "<untrusted>\n" . $h['content'] . "\n</untrusted>"] : ['role' => 'assistant', 'content' => $h['content']];
        }
        // Offline fallback needs the raw text.
        if ($msgs) $msgs[count($msgs) - 1]['raw'] = $message;

        $ctx = ['conversation_id' => (int)$conv['id'], 'customer_id' => $conv['customer_id'] ? (int)$conv['customer_id'] : ($customer['id'] ?? null),
            'user_id' => $user['id'] ?? null, 'channel' => 'web'];
        $cart = Cart::totals();
        $extra = 'Visitor is ' . ($customer ? "a signed-in customer ({$customer['name']})" : 'not signed in') . ".\nCurrent page: " . mb_substr((string)($page['path'] ?? '/'), 0, 120)
            . (isset($page['service']) ? "\nViewing service: " . preg_replace('/[^a-z0-9_]/', '', (string)$page['service']) : '')
            . "\nCart: " . ($cart['lines'] ? count($cart['lines']) . ' item(s), due today ' . money($cart['due_today']) : 'empty')
            . "\nUseful links: /services, /cart, /start (guided intake), /portal (signed-in area), /contact.\n"
            . 'Keep replies under 120 words unless asked for detail. Use plain text with short lists. Offer one clear next step.'
            . "\n" . Memory::contextFor($ctx['customer_id'] && $user ? (int)$ctx['customer_id'] : null);

        // Offline brain reads last user content; give it plain text.
        $offlineMsgs = $msgs; $offlineMsgs[count($offlineMsgs) - 1]['content'] = $message;
        $useMsgs = Ai::available(Ai::providerFor((string)(Agents::get($conv['agent_slug'])['model'] ?? ''))) ? $msgs : $offlineMsgs;
        try {
            $r = AgentRunner::run($conv['agent_slug'], array_map(fn($m) => array_diff_key($m, ['raw' => 1]), $useMsgs), $ctx, $extra);
        } catch (AgentUnavailable $e) {
            $r = ['text' => 'Our AI assistant is resting right now. Leave your question and email via the contact form, or email ' . Settings::get('company.email') . " and we'll get back to you within one business day.", 'actions' => [], 'offline' => true];
        }
        $reply = trim($r['text']) ?: "Sorry — I couldn't produce an answer. You can reach the team at " . Settings::get('company.email') . '.';
        Db::insert('messages', ['conversation_id' => $conv['id'], 'role' => 'assistant', 'content' => $reply, 'meta' => jenc(['actions' => $r['actions'] ?? [], 'offline' => $r['offline'] ?? false]), 'created_at' => now()]);
        Db::update('conversations', ['updated_at' => now(), 'customer_id' => $ctx['customer_id']], 'id = ?', [$conv['id']]);
        $area = self::area((string)($page['path'] ?? ''));
        return ['conversation' => $conv['public_id'], 'reply' => $reply, 'actions' => $r['actions'] ?? [], 'offline' => (bool)($r['offline'] ?? false),
            'suggestions' => self::SUGGESTIONS[$area] ?? self::SUGGESTIONS['default'], 'cart_count' => count(Cart::lines())];
    }

    public static function area(string $path): string
    {
        return match (true) {
            str_starts_with($path, '/portal') => 'portal',
            (bool)preg_match('#sole_prop|partnership|corp|nuans|cooperative|biz_name|registration#', $path) => 'registration',
            (bool)preg_match('#website|maintenance#', $path) => 'websites',
            str_contains($path, 'domain') => 'domains',
            str_contains($path, 'email') => 'email',
            default => 'default',
        };
    }

    public static function history(?string $publicId): array
    {
        if (!$publicId) return [];
        Session::start();
        $user = Auth::user();
        $conv = Db::one('SELECT * FROM conversations WHERE public_id = ?', [$publicId]);
        if (!$conv) return [];
        $mine = ($conv['user_id'] && $user && (int)$conv['user_id'] === (int)$user['id']) || in_array($publicId, $_SESSION['chat_ids'] ?? [], true);
        if (!$mine) return [];
        return Db::all('SELECT role, content, created_at FROM messages WHERE conversation_id = ? ORDER BY id LIMIT 80', [$conv['id']]);
    }

    /** Owner Assistant: answers from live data; can create tasks (delegation is permission-bound). */
    public static function owner(string $q, array $user): array
    {
        if (!RateLimit::hit('owner_ai:' . $user['id'], 120, 3600)) throw new UserError('Rate limit reached.');
        Session::start();
        $hist = $_SESSION['owner_chat'] ?? [];
        $hist[] = ['role' => 'user', 'content' => mb_substr($q, 0, 3000)];
        $hist = array_slice($hist, -10);
        $r = AgentRunner::run('owner_assistant', $hist, ['channel' => 'owner', 'user_id' => $user['id']],
            "You are speaking with {$user['name']}, the business owner. Use business_metrics, list_leads, list_open_tasks before answering questions about the business. Quote exact figures. Before creating tasks, confirm with the owner unless they explicitly asked.");
        $hist[] = ['role' => 'assistant', 'content' => $r['text']];
        $_SESSION['owner_chat'] = $hist;
        Audit::log('owner_assistant.ask', 'assistant', null, ['q' => str_limit($q, 200)]);
        return ['reply' => $r['text'], 'actions' => $r['actions'], 'offline' => $r['offline']];
    }
}
