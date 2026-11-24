<?php
declare(strict_types=1);
namespace App;

/**
 * Install a release package from the Control Tower. The package is the same zip used for a fresh install
 * (public_html/… with public_html/_app/…). Settings (.env), uploads, logs and backups are never touched.
 * Before anything is replaced, the current code is saved so one click can roll it back.
 */
final class Updater
{
    private const KEEP = ['#^_app/\.env$#', '#^_app/storage/#', '#^\.well-known/#', '#^_app/\.env\.#'];

    public static function supported(): ?string
    {
        if (ON_VERCEL) return 'This site runs on Vercel, which updates automatically from GitHub.';
        if (!class_exists(\ZipArchive::class)) return 'The PHP zip extension is not available on this server.';
        if (!is_writable(APP_ROOT) || !is_writable(self::webRoot())) return 'The site folders are not writable by PHP on this server.';
        return null;
    }

    /** Folder that holds index.php. */
    public static function webRoot(): string
    {
        $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($doc && is_file($doc . '/index.php')) return $doc;
        return basename(APP_ROOT) === '_app' ? dirname(APP_ROOT) : dirname(APP_ROOT) . '/public_html';
    }

    /** Map a path inside the package to where it lives on this server. Returns null for anything we must not write. */
    private static function target(string $rel): ?string
    {
        if (!str_starts_with($rel, 'public_html/')) return null;
        $rel = substr($rel, strlen('public_html/'));
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) return null;
        foreach (self::KEEP as $re) if (preg_match($re, $rel)) return null;
        if (str_starts_with($rel, '_app/')) return APP_ROOT . '/' . substr($rel, 5);
        return self::webRoot() . '/' . $rel;
    }

    /** Validate and apply an uploaded package. Returns a summary. */
    public static function apply(string $zipPath, string $by): array
    {
        if ($why = self::supported()) throw new UserError($why);
        $z = new \ZipArchive();
        if ($z->open($zipPath) !== true) throw new UserError('That file is not a valid zip package.');
        $names = []; for ($i = 0; $i < $z->numFiles; $i++) $names[] = (string)$z->getNameIndex($i);
        if (!in_array('public_html/index.php', $names, true) || !in_array('public_html/_app/bootstrap.php', $names, true))
            throw new UserError('This is not an 818 Digital Agency release package (public_html/index.php and public_html/_app/bootstrap.php are missing).');
        $boot = (string)$z->getFromName('public_html/_app/bootstrap.php');
        $newVer = preg_match("/APP_VERSION', '([0-9.]+)'/", $boot, $m) ? $m[1] : '?';

        // 1. Save the current code (only files the package will replace) for rollback.
        $dir = STORAGE_ROOT . '/backups'; if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $bk = $dir . '/code-' . APP_VERSION . '-' . gmdate('Ymd-His') . '.zip';
        $b = new \ZipArchive();
        if ($b->open($bk, \ZipArchive::CREATE) !== true) throw new UserError('Could not create the rollback copy in storage/backups.');
        $plan = [];
        foreach ($names as $n) {
            if (str_ends_with($n, '/')) continue;
            $dest = self::target($n);
            if ($dest === null) continue;
            $plan[$n] = $dest;
            if (is_file($dest)) $b->addFile($dest, $n);
        }
        $b->setArchiveComment(jenc(['from' => APP_VERSION, 'to' => $newVer, 'at' => now(), 'by' => $by, 'created' => array_keys(array_filter($plan, fn($d) => !is_file($d)))]));
        $b->close();

        // 2. Write the new files.
        $written = 0;
        foreach ($plan as $n => $dest) {
            $data = $z->getFromName($n);
            if ($data === false) continue;
            if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) throw new UserError('Could not create ' . dirname($dest));
            $tmp = $dest . '.upd-' . getmypid();
            if (@file_put_contents($tmp, $data) === false || !@rename($tmp, $dest)) { @unlink($tmp); throw new UserError('Could not write ' . $n . '. Some files may be updated; use Roll back.'); }
            $written++;
        }
        $z->close();
        if (function_exists('opcache_reset')) @opcache_reset();
        Settings::set('system.last_update', jenc(['from' => APP_VERSION, 'to' => $newVer, 'at' => now(), 'by' => $by, 'files' => $written, 'backup' => basename($bk)]));
        Audit::log('system.update', 'system', $newVer, ['from' => APP_VERSION, 'files' => $written, 'by' => $by]);
        return ['from' => APP_VERSION, 'to' => $newVer, 'files' => $written, 'backup' => basename($bk)];
    }

    /** Restore the code saved before an update. */
    public static function rollback(string $backup, string $by): array
    {
        if ($why = self::supported()) throw new UserError($why);
        $f = STORAGE_ROOT . '/backups/' . basename($backup);
        if (!preg_match('/^code-[0-9.]+-\d{8}-\d{6}\.zip$/', basename($backup)) || !is_file($f)) throw new UserError('That rollback copy was not found.');
        $z = new \ZipArchive(); if ($z->open($f) !== true) throw new UserError('The rollback copy could not be opened.');
        $meta = jdec((string)$z->getArchiveComment()) ?: [];
        $n = 0;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = (string)$z->getNameIndex($i); $dest = self::target($name);
            if ($dest === null || str_ends_with($name, '/')) continue;
            @file_put_contents($dest, (string)$z->getFromIndex($i)); $n++;
        }
        foreach ((array)($meta['created'] ?? []) as $name) { $dest = self::target((string)$name); if ($dest && is_file($dest)) @unlink($dest); }
        $z->close();
        if (function_exists('opcache_reset')) @opcache_reset();
        Audit::log('system.rollback', 'system', basename($backup), ['by' => $by, 'files' => $n]);
        return ['files' => $n, 'version' => $meta['from'] ?? '?'];
    }

    public static function backups(): array
    {
        $out = [];
        foreach (glob(STORAGE_ROOT . '/backups/code-*.zip') ?: [] as $f) $out[] = ['file' => basename($f), 'size' => filesize($f), 'at' => gmdate('Y-m-d H:i', filemtime($f))];
        usort($out, fn($a, $b) => strcmp($b['file'], $a['file']));
        return array_slice($out, 0, 10);
    }
}
