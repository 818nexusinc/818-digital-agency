<?php
declare(strict_types=1);
namespace App;

/** Structured JSON-lines logger with correlation IDs and secret redaction. */
final class Log
{
    private static ?string $cid = null;

    public static function correlationId(): string
    {
        return self::$cid ??= substr(rid(8), 0, 12);
    }

    public static function redact(mixed $v): mixed
    {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $x) {
                $out[$k] = preg_match('/(pass|secret|token|key|authorization|card|cvc|mfa)/i', (string)$k) ? '[redacted]' : self::redact($x);
            }
            return $out;
        }
        if (is_string($v)) {
            $v = preg_replace('/\b(sk|rk|pk)_(live|test)_[A-Za-z0-9]{6,}/', '$1_$2_[redacted]', $v);
            $v = preg_replace('/\b(sk-ant-|sk-|re_|whsec_)[A-Za-z0-9_\-]{8,}/', '$1[redacted]', $v);
        }
        return $v;
    }

    public static function write(string $level, string $msg, array $ctx = []): void
    {
        $line = jenc(['ts' => gmdate('c'), 'level' => $level, 'cid' => self::correlationId(), 'msg' => $msg, 'ctx' => self::redact($ctx)]);
        $dir = STORAGE_ROOT . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        @file_put_contents($dir . '/app-' . gmdate('Y-m-d') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
        if (ON_VERCEL) error_log($line); // surfaces in Vercel's runtime logs
    }

    public static function info(string $m, array $c = []): void { self::write('info', $m, $c); }
    public static function warn(string $m, array $c = []): void { self::write('warn', $m, $c); }
    public static function error(string $m, array $c = []): void { self::write('error', $m, $c); }
}
