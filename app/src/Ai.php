<?php
declare(strict_types=1);
namespace App;

/**
 * Provider abstraction. All providers return the same normalized shape:
 *   ['text'=>string,'tool_calls'=>[['id','name','input']], 'tokens_in'=>int,'tokens_out'=>int,'stop'=>string,'provider'=>string,'model'=>string,'assistant_blocks'=>array]
 * Messages use a neutral format: ['role'=>'user'|'assistant','content'=>string] or
 *   ['role'=>'assistant','blocks'=>[...]] / ['role'=>'tool','results'=>[['id','content']]].
 */
final class Ai
{
    /** Estimated USD cost per million tokens [in, out]; editable in Settings (ai.price.<model>). */
    public const PRICES = [
        'claude-haiku-5-5' => [1.0, 5.0], 'claude-sonnet-5-5' => [3.0, 15.0], 'claude-opus-5-5' => [15.0, 75.0],
        'gpt-4.1-mini' => [0.4, 1.6], 'gpt-4.1' => [2.0, 8.0], 'offline' => [0, 0],
    ];

    public const MODELS = ['fast' => 'claude-haiku-5-5', 'standard' => 'claude-sonnet-5-5', 'deep' => 'claude-opus-5-5'];

    /** Cost in micro-USD (1e-6). */
    public static function cost(string $model, int $in, int $out): int
    {
        $override = Settings::get('ai.price.' . $model);
        [$pi, $po] = $override ? array_map('floatval', explode(',', $override)) + [0, 0] : (self::PRICES[$model] ?? [3.0, 15.0]);
        return (int)round($in * $pi + $out * $po); // tokens * $/Mtok == micro-dollars
    }

    public static function providerFor(string $model): string
    {
        if (str_starts_with($model, 'gpt-')) return 'openai';
        if ($model === 'offline') return 'offline';
        return 'anthropic';
    }

    public static function available(string $provider): bool
    {
        return $provider === 'offline' || (Settings::get('ai.enabled') === '1' && Integrations::configured($provider));
    }

    /**
     * Call a model with automatic fallback: primary → fallback model → offline.
     */
    public static function complete(string $system, array $messages, array $tools, string $model, ?string $fallback, int $maxTokens, float $temperature, int $timeout = 60): array
    {
        $chain = array_values(array_unique(array_filter([$model, $fallback, 'offline'])));
        $lastErr = null;
        foreach ($chain as $m) {
            $prov = self::providerFor($m);
            if (!self::available($prov)) continue;
            try {
                $r = match ($prov) {
                    'anthropic' => self::anthropic($system, $messages, $tools, $m, $maxTokens, $temperature, $timeout),
                    'openai' => self::openai($system, $messages, $tools, $m, $maxTokens, $temperature, $timeout),
                    default => OfflineBrain::complete($system, $messages, $tools),
                };
                if ($lastErr) $r['fallback_reason'] = $lastErr;
                return $r;
            } catch (\Throwable $e) {
                $lastErr = $prov . ': ' . $e->getMessage();
                Log::warn('ai.provider_failed', ['provider' => $prov, 'model' => $m, 'error' => $e->getMessage()]);
                Integrations::record($prov, 'complete', false, $e->getMessage());
            }
        }
        return OfflineBrain::complete($system, $messages, $tools) + ['fallback_reason' => $lastErr];
    }

    private static function anthropic(string $system, array $messages, array $tools, string $model, int $maxTokens, float $temp, int $timeout): array
    {
        $msgs = [];
        foreach ($messages as $m) {
            if (isset($m['results'])) {
                $msgs[] = ['role' => 'user', 'content' => array_map(fn($r) => ['type' => 'tool_result', 'tool_use_id' => $r['id'], 'content' => $r['content']], $m['results'])];
            } elseif (isset($m['blocks'])) {
                $msgs[] = ['role' => 'assistant', 'content' => $m['blocks']];
            } else {
                $msgs[] = ['role' => $m['role'], 'content' => (string)$m['content']];
            }
        }
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'temperature' => $temp, 'system' => $system, 'messages' => $msgs];
        if ($tools) $body['tools'] = array_map(fn($t) => ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['schema']], $tools);
        $headers = ['x-api-key: ' . Integrations::secret('anthropic', 'api_key'), 'anthropic-version: 2023-06-01', 'content-type: application/json'];
        if ($ws = Env::get('ANTHROPIC_WORKSPACE_ID')) $headers[] = 'anthropic-workspace-id: ' . $ws;
        $r = Integrations::http('POST', 'https://api.anthropic.com/v1/messages', $headers, jenc($body), $timeout);
        if ($r['status'] !== 200) throw new \RuntimeException(($r['error'] ?? '') . ' HTTP ' . $r['status'] . ' ' . mb_substr((string)($r['json']['error']['message'] ?? ''), 0, 200));
        $j = $r['json'];
        $text = ''; $calls = [];
        foreach ($j['content'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'text') $text .= $b['text'];
            if (($b['type'] ?? '') === 'tool_use') $calls[] = ['id' => $b['id'], 'name' => $b['name'], 'input' => $b['input'] ?? []];
        }
        return ['text' => $text, 'tool_calls' => $calls, 'tokens_in' => (int)($j['usage']['input_tokens'] ?? 0), 'tokens_out' => (int)($j['usage']['output_tokens'] ?? 0),
            'stop' => (string)($j['stop_reason'] ?? ''), 'provider' => 'anthropic', 'model' => $model, 'assistant_blocks' => $j['content'] ?? []];
    }

    private static function openai(string $system, array $messages, array $tools, string $model, int $maxTokens, float $temp, int $timeout): array
    {
        $msgs = [['role' => 'system', 'content' => $system]];
        foreach ($messages as $m) {
            if (isset($m['results'])) foreach ($m['results'] as $r) $msgs[] = ['role' => 'tool', 'tool_call_id' => $r['id'], 'content' => $r['content']];
            elseif (isset($m['blocks'])) {
                $txt = ''; $tc = [];
                foreach ($m['blocks'] as $b) {
                    if ($b['type'] === 'text') $txt .= $b['text'];
                    if ($b['type'] === 'tool_use') $tc[] = ['id' => $b['id'], 'type' => 'function', 'function' => ['name' => $b['name'], 'arguments' => jenc($b['input'] ?: new \stdClass())]];
                }
                $msgs[] = ['role' => 'assistant', 'content' => $txt ?: null] + ($tc ? ['tool_calls' => $tc] : []);
            } else $msgs[] = ['role' => $m['role'], 'content' => (string)$m['content']];
        }
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'temperature' => $temp, 'messages' => $msgs];
        if ($tools) $body['tools'] = array_map(fn($t) => ['type' => 'function', 'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['schema']]], $tools);
        $r = Integrations::http('POST', 'https://api.openai.com/v1/chat/completions', ['Authorization: Bearer ' . Integrations::secret('openai', 'api_key'), 'Content-Type: application/json'], jenc($body), $timeout);
        if ($r['status'] !== 200) throw new \RuntimeException('HTTP ' . $r['status']);
        $c = $r['json']['choices'][0]['message'] ?? [];
        $calls = []; $blocks = [];
        if (!empty($c['content'])) $blocks[] = ['type' => 'text', 'text' => $c['content']];
        foreach ($c['tool_calls'] ?? [] as $tc) {
            $in = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $calls[] = ['id' => $tc['id'], 'name' => $tc['function']['name'], 'input' => $in];
            $blocks[] = ['type' => 'tool_use', 'id' => $tc['id'], 'name' => $tc['function']['name'], 'input' => $in];
        }
        return ['text' => (string)($c['content'] ?? ''), 'tool_calls' => $calls, 'tokens_in' => (int)($r['json']['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int)($r['json']['usage']['completion_tokens'] ?? 0), 'stop' => $calls ? 'tool_use' : 'end_turn', 'provider' => 'openai', 'model' => $model, 'assistant_blocks' => $blocks];
    }
}

/**
 * Deterministic fallback used when no AI provider is configured or all providers fail.
 * It never pretends to be AI-generated: replies say so, and tasks are marked as drafts needing review.
 */
final class OfflineBrain
{
    public static function complete(string $system, array $messages, array $tools): array
    {
        $last = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) { if (($messages[$i]['role'] ?? '') === 'user' && isset($messages[$i]['content'])) { $last = (string)$messages[$i]['content']; break; } }
        $hadToolResults = isset(end($messages)['results']);
        $names = array_column($tools, 'name');
        $base = ['tokens_in' => 0, 'tokens_out' => 0, 'provider' => 'offline', 'model' => 'offline', 'stop' => 'end_turn'];
        if (!$hadToolResults && in_array('search_catalog', $names, true) && trim($last) !== '' && !str_starts_with($last, 'TASK:')) {
            $id = 'off_' . rid(4);
            return $base + ['text' => '', 'tool_calls' => [['id' => $id, 'name' => 'search_catalog', 'input' => ['query' => mb_substr($last, 0, 200)]]],
                'stop' => 'tool_use', 'assistant_blocks' => [['type' => 'tool_use', 'id' => $id, 'name' => 'search_catalog', 'input' => ['query' => mb_substr($last, 0, 200)]]]];
        }
        if ($hadToolResults) {
            $res = json_decode((string)(end($messages)['results'][0]['content'] ?? '[]'), true);
            $list = [];
            foreach (($res['services'] ?? []) as $s) $list[] = "• {$s['name']} — {$s['price']} (/services/{$s['slug']})";
            $text = $list
                ? "Here are services that match what you described:\n" . implode("\n", array_slice($list, 0, 5)) . "\n\nOur AI assistant is temporarily in basic mode, so for a tailored recommendation tap “Talk to a person” or leave your email and we'll follow up within one business day."
                : "I couldn't find a matching service. Our AI assistant is temporarily in basic mode — tap “Talk to a person” or browse /services and we'll help you right away.";
            return $base + ['text' => $text, 'tool_calls' => [], 'assistant_blocks' => [['type' => 'text', 'text' => $text]]];
        }
        $text = 'AI provider unavailable — this task was not processed by a model. A human should review it.';
        return $base + ['text' => $text, 'tool_calls' => [], 'assistant_blocks' => [['type' => 'text', 'text' => $text]], 'offline' => true];
    }
}
