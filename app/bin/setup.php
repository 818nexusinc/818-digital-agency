<?php
declare(strict_types=1);
/*
 * First-time setup / upgrade:
 *   php app/bin/setup.php                       → creates app/.env (if missing) with a fresh APP_KEY, migrates, seeds
 *   php app/bin/setup.php --owner=you@x.ca --name="Your Name"   → also creates the owner account (password prompted or printed once)
 * Re-running is safe: migrations and seeds are idempotent.
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$app = dirname(__DIR__);
$env = $app . '/.env';
if (!is_file($env)) {
    copy($app . '/.env.example', $env);
    $c = file_get_contents($env);
    file_put_contents($env, str_replace('APP_KEY=', 'APP_KEY=base64:' . base64_encode(random_bytes(32)), $c));
    chmod($env, 0600);
    echo "Created app/.env with a new APP_KEY. Edit it to add your database credentials, then re-run this script.\n";
}
require $app . '/bootstrap.php';
foreach (['storage/logs', 'storage/uploads', 'storage/cache', 'storage/backups', 'storage/locks', 'storage/sessions'] as $d) if (!is_dir("{$app}/{$d}")) mkdir("{$app}/{$d}", 0750, true);

$applied = App\Db::migrate();
echo $applied ? 'Applied migrations: ' . implode(', ', $applied) . "\n" : "Database is up to date.\n";
App\Seed::run();
echo "Seed complete (roles, agents, workflows, catalog, knowledge base, integrations).\n";

$opts = getopt('', ['owner:', 'name:', 'password:']);
if (!empty($opts['owner'])) {
    $email = strtolower(trim($opts['owner']));
    $pw = $opts['password'] ?? bin2hex(random_bytes(8)) . 'A1';
    if ($p = App\Auth::passwordProblem($pw)) exit("Password problem: {$p}\n");
    if ($id = App\Db::val('SELECT id FROM users WHERE email = ?', [$email])) {
        App\Db::update('users', ['role' => 'owner', 'password_hash' => App\Auth::hash($pw), 'status' => 'active', 'updated_at' => now()], 'id = ?', [$id]);
    } else {
        App\Db::insert('users', ['email' => $email, 'name' => $opts['name'] ?? 'Owner', 'password_hash' => App\Auth::hash($pw), 'role' => 'owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }
    App\Settings::set('owner.notify_email', $email);
    echo "Owner account ready: {$email}\n" . (isset($opts['password']) ? '' : "Temporary password (shown once): {$pw}\nSign in at /login, then enable MFA under Admin → Settings.\n");
}
