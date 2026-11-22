<?php
declare(strict_types=1);
namespace App;

/** Stripe Checkout + signed, idempotent webhooks. Amounts always come from the server-side order. */
final class Stripe
{
    public static function enabled(): bool { return Integrations::configured('stripe'); }

    private static function call(string $method, string $path, array $params = []): array
    {
        $key = Integrations::secret('stripe', 'secret_key') ?? throw new UserError('Online payment is not available yet.');
        $r = Integrations::http($method, 'https://api.stripe.com/v1' . $path, ['Stripe-Version: 2024-06-20', 'Content-Type: application/x-www-form-urlencoded',
            'Idempotency-Key: ' . ($params['_idem'] ?? rid(12))], http_build_query(array_diff_key($params, ['_idem' => 1])), 30, $key . ':');
        if ($r['status'] < 200 || $r['status'] >= 300) {
            Integrations::record('stripe', $path, false, ($r['error'] ?? '') . ' ' . ($r['json']['error']['message'] ?? 'HTTP ' . $r['status']));
            throw new UserError('Payment could not be started. Please try again or choose "pay by invoice".');
        }
        return $r['json'] ?? [];
    }

    /** Build a Checkout Session for a pending order. Subscription mode when any line recurs. */
    public static function checkout(array $order, array $customer): string
    {
        $items = Orders::items((int)$order['id']);
        // Stripe Checkout cannot mix billing intervals: when monthly and yearly items coexist,
        // yearly items (e.g. domains) are charged once for the first year and renewed via renewal reminders.
        $intervals = array_unique(array_map(fn($i) => $i['billing'], array_filter($items, fn($i) => $i['billing'] !== 'one_time')));
        if (count($intervals) > 1) foreach ($items as &$i) if ($i['billing'] === 'yearly') { $i['billing'] = 'one_time'; $i['name'] .= ' (first year)'; }
        unset($i);
        $recurring = array_filter($items, fn($i) => in_array($i['billing'], ['monthly', 'yearly'], true));
        $p = ['mode' => $recurring ? 'subscription' : 'payment', 'customer_email' => $customer['email'], 'client_reference_id' => $order['number'],
            'success_url' => url('/checkout/success?o=' . $order['number'] . '&session_id={CHECKOUT_SESSION_ID}'), 'cancel_url' => url('/cart?cancelled=1'),
            'metadata[order_number]' => $order['number'], 'locale' => 'auto', '_idem' => 'checkout-' . $order['number']];
        if (!$recurring) $p['payment_intent_data[metadata][order_number]'] = $order['number'];
        else $p['subscription_data[metadata][order_number]'] = $order['number'];
        $n = 0;
        foreach ($items as $it) {
            $p["line_items[$n][quantity]"] = $it['qty'];
            $p["line_items[$n][price_data][currency]"] = 'cad';
            $p["line_items[$n][price_data][unit_amount]"] = $it['unit_cents'];
            $p["line_items[$n][price_data][product_data][name]"] = mb_substr($it['name'], 0, 120);
            if ($it['billing'] !== 'one_time') $p["line_items[$n][price_data][recurring][interval]"] = $it['billing'] === 'yearly' ? 'year' : 'month';
            $n++;
            if ($it['setup_cents'] > 0) {
                $p["line_items[$n][quantity]"] = $it['qty'];
                $p["line_items[$n][price_data][currency]"] = 'cad';
                $p["line_items[$n][price_data][unit_amount]"] = $it['setup_cents'];
                $p["line_items[$n][price_data][product_data][name]"] = mb_substr('Setup — ' . $it['name'], 0, 120);
                $n++;
            }
        }
        if ((int)$order['tax_cents'] > 0) {
            $p["line_items[$n][quantity]"] = 1; $p["line_items[$n][price_data][currency]"] = 'cad';
            $p["line_items[$n][price_data][unit_amount]"] = (int)$order['tax_cents']; $p["line_items[$n][price_data][product_data][name]"] = 'Sales tax';
        }
        $s = self::call('POST', '/checkout/sessions', $p);
        Db::update('orders', ['stripe_session_id' => $s['id'] ?? null, 'updated_at' => now()], 'id = ?', [$order['id']]);
        Integrations::record('stripe', 'checkout.create', true, $order['number']);
        return (string)($s['url'] ?? '/cart');
    }

    /** Verify Stripe-Signature (t=..., v1=...) with a 5-minute tolerance. */
    public static function verify(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $kv) { [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, ''); $parts[$k][] = $v; }
        $t = (int)($parts['t'][0] ?? 0);
        if (!$t || abs(time() - $t) > $tolerance) return false;
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($parts['v1'] ?? [] as $sig) if (hash_equals($expected, $sig)) return true;
        return false;
    }

    /** Process a verified event exactly once. Returns HTTP status to send to Stripe. */
    public static function handle(array $evt): int
    {
        $id = (string)($evt['id'] ?? '');
        $type = (string)($evt['type'] ?? '');
        if ($id === '') return 400;
        try {
            Db::insert('webhook_events', ['provider' => 'stripe', 'event_id' => $id, 'type' => $type, 'status' => 'received', 'received_at' => now()]);
        } catch (\PDOException) {
            $st = Db::val("SELECT status FROM webhook_events WHERE provider = 'stripe' AND event_id = ?", [$id]);
            if ($st === 'processed' || $st === 'ignored') return 200; // duplicate delivery — already handled
        }
        try {
            $obj = $evt['data']['object'] ?? [];
            $status = 'processed';
            switch ($type) {
                case 'checkout.session.completed':
                case 'checkout.session.async_payment_succeeded':
                    if (($obj['payment_status'] ?? '') !== 'paid' && ($obj['payment_status'] ?? '') !== 'no_payment_required') { $status = 'ignored'; break; }
                    $num = (string)($obj['metadata']['order_number'] ?? $obj['client_reference_id'] ?? '');
                    $o = Db::one('SELECT * FROM orders WHERE number = ?', [$num]);
                    if (!$o) throw new \RuntimeException('Unknown order ' . $num);
                    Orders::markPaid((int)$o['id'], 'stripe', (string)($obj['payment_intent'] ?? $obj['id']), (int)($obj['amount_total'] ?? 0), $obj['subscription'] ?? null);
                    break;
                case 'checkout.session.async_payment_failed':
                case 'payment_intent.payment_failed':
                case 'invoice.payment_failed':
                    $num = (string)($obj['metadata']['order_number'] ?? '');
                    Approvals::request('payment_exception', 'Payment failed' . ($num ? " for {$num}" : ''), ['what' => 'A customer payment failed.', 'why' => (string)($obj['last_payment_error']['message'] ?? $type),
                        'impact' => 'Service may be interrupted; Finance agent will draft a polite reminder.', 'cost' => money((int)($obj['amount_due'] ?? $obj['amount'] ?? 0))], 'stripe', null, 'finance', 'medium');
                    Events::emit('payment.failed', ['order_number' => $num, 'type' => $type]);
                    break;
                case 'charge.refunded':
                    Db::insert('payments', ['order_id' => Db::val('SELECT order_id FROM payments WHERE provider_ref = ?', [(string)($obj['payment_intent'] ?? '')]),
                        'provider' => 'stripe', 'provider_ref' => 'refund_' . ($obj['id'] ?? rid(4)), 'amount_cents' => -(int)($obj['amount_refunded'] ?? 0), 'status' => 'refunded', 'kind' => 'refund', 'created_at' => now()]);
                    break;
                case 'invoice.paid':
                    if (!empty($obj['subscription']) && ($end = $obj['lines']['data'][0]['period']['end'] ?? null)) {
                        Db::update('subscriptions', ['status' => 'active', 'current_period_end' => gmdate('Y-m-d H:i:s', (int)$end), 'updated_at' => now()], 'provider_ref = ?', [(string)$obj['subscription']]);
                    }
                    break;
                case 'customer.subscription.deleted':
                    Db::update('subscriptions', ['status' => 'cancelled', 'updated_at' => now()], 'provider_ref = ?', [(string)($obj['id'] ?? '')]);
                    Events::emit('subscription.cancelled', ['ref' => $obj['id'] ?? null]);
                    break;
                default:
                    $status = 'ignored';
            }
            Db::update('webhook_events', ['status' => $status, 'processed_at' => now(), 'error' => null], "provider = 'stripe' AND event_id = ?", [$id]);
            return 200;
        } catch (\Throwable $e) {
            Db::update('webhook_events', ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)], "provider = 'stripe' AND event_id = ?", [$id]);
            Log::error('stripe.webhook', ['event' => $id, 'error' => $e->getMessage()]);
            return 500; // Stripe will retry
        }
    }
}
