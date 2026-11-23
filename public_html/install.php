<?php
declare(strict_types=1);
/*
 * One-time browser installer for hosts without a terminal (20i, cPanel).
 * Works only until installation finishes; afterwards it answers 404. Proves ownership by requiring the database password.
 */
$appRoot = is_file(dirname(__DIR__) . '/app/bootstrap.php') ? dirname(__DIR__) . '/app' : __DIR__ . '/_app';
$envFile = $appRoot . '/.env';
$cur = is_file($envFile) ? (string)file_get_contents($envFile) : '';
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store'); header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");
if (preg_match('/^APP_INSTALLED=1/m', $cur)) { http_response_code(404); exit('Not found'); }

$val = fn(string $k, string $d = '') => preg_match('/^' . $k . '=(.*)$/m', $cur, $m) ? trim($m[1], " \t\"'") : $d;
$in = fn(string $k, string $d = '') => trim((string)($_POST[$k] ?? $d));
$err = ''; $done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $in('db_host', 'localhost'); $port = (int)$in('db_port', '3306'); $name = $in('db_name'); $user = $in('db_user'); $pass = (string)($_POST['db_pass'] ?? '');
    $email = strtolower($in('owner_email')); $pw = (string)($_POST['owner_pass'] ?? ''); $url = rtrim($in('app_url'), '/');
    try {
        if (!$name || !$user || $pass === '') throw new RuntimeException('Enter the database name, user and password.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid owner email.');
        if (strlen($pw) < 12 || $pw !== ($_POST['owner_pass2'] ?? '')) throw new RuntimeException('Owner password must be at least 12 characters and both entries must match.');
        if (!preg_match('#^https://[a-z0-9.-]+$#i', $url)) throw new RuntimeException('Site address must look like https://818digitalagency.com');
        try { new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]); }
        catch (PDOException $e) { throw new RuntimeException('Could not connect to the database: ' . preg_replace('/using password: \w+/', '', $e->getMessage())); }

        $q = fn($v) => '"' . str_replace(['\\', '"'], ['\\\\', '\"'], $v) . '"';
        $env = "# 818 Digital Agency — production settings. Written by install.php on " . gmdate('Y-m-d H:i') . " UTC. Keep private; back up APP_KEY.\n"
            . "APP_URL={$url}\nAPP_DEBUG=false\nAPP_KEY=" . ($val('APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32))) . "\n\n"
            . "DB_HOST={$host}\nDB_PORT={$port}\nDB_NAME={$name}\nDB_USER={$user}\nDB_PASS=" . $q($pass) . "\nDB_SSL=off\n\n"
            . "SESSION_SECURE=true\nMAIL_DRIVER=" . ($val('MAIL_DRIVER') ?: 'auto') . "\nMAIL_FROM=" . ($val('MAIL_FROM') ?: 'hello@818nexus.ca') . "\nOWNER_EMAIL={$email}\n"
            . "CRON_SECRET=" . bin2hex(random_bytes(24)) . "\nAPP_INSTALLED=1\n";
        foreach (['ANTHROPIC_API_KEY', 'OPENAI_API_KEY', 'STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET', 'RESEND_API_KEY', 'RESEND_WEBHOOK_SECRET', 'TWENTYI_API_KEY'] as $k) if ($val($k) !== '') $env .= "{$k}=" . $q($val($k)) . "\n";
        if (@file_put_contents($envFile, $env, LOCK_EX) === false) throw new RuntimeException('Could not write ' . $envFile . '. Make the _app folder writable (755) and try again.');
        @chmod($envFile, 0600);

        require $appRoot . '/bootstrap.php';
        foreach (['storage/logs', 'storage/uploads', 'storage/cache', 'storage/backups', 'storage/locks', 'storage/sessions'] as $d) if (!is_dir("{$appRoot}/{$d}")) @mkdir("{$appRoot}/{$d}", 0750, true);
        $applied = App\Db::migrate();
        App\Seed::run();
        if ($p = App\Auth::passwordProblem($pw)) throw new RuntimeException('Owner password: ' . $p);
        $now = gmdate('Y-m-d H:i:s');
        if ($id = App\Db::val('SELECT id FROM users WHERE email = ?', [$email])) App\Db::update('users', ['role' => 'owner', 'password_hash' => App\Auth::hash($pw), 'status' => 'active', 'updated_at' => $now], 'id = ?', [$id]);
        else App\Db::insert('users', ['email' => $email, 'name' => 'Owner', 'password_hash' => App\Auth::hash($pw), 'role' => 'owner', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        App\Settings::set('owner.notify_email', $email);
        App\Audit::log('system.installed', 'system', null, ['migrations' => $applied]);
        $done = ['migrations' => $applied, 'cron' => (PHP_BINARY && !str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : '/usr/bin/php') . ' ' . $appRoot . '/cron.php', 'email' => $email];
    } catch (Throwable $e) {
        $err = $e->getMessage();
        if ($done === null && preg_match('/^APP_INSTALLED=1/m', (string)@file_get_contents($envFile))) { // roll the flag back so the form can be used again
            @file_put_contents($envFile, preg_replace('/^APP_INSTALLED=1\n/m', '', (string)file_get_contents($envFile)));
        }
    }
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install 818 Digital Agency</title>
<style>body{font:15px/1.5 system-ui,sans-serif;background:#f4f5f8;color:#0f1729;margin:0;padding:40px 16px}main{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e3e6ee;border-radius:14px;padding:28px}
h1{font-size:22px;margin:0 0 6px}p{color:#5f6b85}label{display:block;font-size:13px;font-weight:600;margin:14px 0 4px}input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d5dae5;border-radius:10px;font:inherit}
.row{display:grid;grid-template-columns:2fr 1fr;gap:10px}button{margin-top:20px;background:#0f1729;color:#fff;border:0;border-radius:10px;padding:12px 18px;font:600 15px system-ui;cursor:pointer;width:100%}
.err{background:#fef2f2;color:#b42318;border:1px solid #fecaca;padding:12px;border-radius:10px}.ok{background:#ecfdf5;border:1px solid #a7f3d0;padding:14px;border-radius:10px}code{background:#f4f5f8;padding:2px 6px;border-radius:6px;word-break:break-all}h2{font-size:15px;margin:20px 0 4px}</style>
<main>
<?php if ($done): ?>
  <h1>Installed</h1><div class="ok">The database is ready and the owner account <b><?= $h($done['email']) ?></b> is set up. This installer is now switched off.</div>
  <h2>1. Add the background job</h2><p>My20i → your package → Web Tools → Scheduled Tasks (Cron). Every 5 minutes, command:</p><p><code><?= $h($done['cron']) ?></code></p>
  <h2>2. Sign in</h2><p><a href="/login">Go to the sign-in page</a>, then turn on two-step verification under Settings &amp; team.</p>
  <h2>3. Check</h2><p>Open System → Security check and fix anything red.</p>
<?php else: ?>
  <h1>Install 818 Digital Agency</h1><p>Connects the site to its MySQL database, creates the tables and your owner account. Works once; afterwards this page is disabled.</p>
  <?php if ($err): ?><div class="err"><?= $h($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <div class="row"><div><label for="h">Database host</label><input id="h" name="db_host" value="<?= $h($_POST['db_host'] ?? 'localhost') ?>"></div><div><label for="p">Port</label><input id="p" name="db_port" value="<?= $h($_POST['db_port'] ?? '3306') ?>"></div></div>
    <label for="n">Database name</label><input id="n" name="db_name" value="<?= $h($_POST['db_name'] ?? $val('DB_NAME')) ?>" required>
    <label for="u">Database user</label><input id="u" name="db_user" value="<?= $h($_POST['db_user'] ?? $val('DB_USER')) ?>" required>
    <label for="pw">Database password</label><input id="pw" name="db_pass" type="password" required>
    <label for="url">Site address</label><input id="url" name="app_url" value="<?= $h($_POST['app_url'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? '818digitalagency.com'))) ?>" required>
    <label for="e">Owner email</label><input id="e" name="owner_email" type="email" value="<?= $h($_POST['owner_email'] ?? 'hello@818nexus.ca') ?>" required>
    <label for="o1">Owner password (12+ characters)</label><input id="o1" name="owner_pass" type="password" minlength="12" required>
    <label for="o2">Owner password again</label><input id="o2" name="owner_pass2" type="password" minlength="12" required>
    <button>Install</button>
  </form>
<?php endif; ?>
</main></html>
