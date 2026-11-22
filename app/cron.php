<?php
declare(strict_types=1);
/*
 * cPanel → Cron Jobs → every minute:
 *   * * * * *  /usr/local/bin/php /home/USER/app/cron.php >/dev/null 2>&1
 * Safe to run concurrently (file lock) and resumable (all state is in the database).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/bootstrap.php';
$stats = App\Cron::run(50);
if (in_array('-v', $argv, true)) echo json_encode($stats, JSON_PRETTY_PRINT), "\n";
// Nightly backup at ~06:30 UTC.
if (gmdate('H:i') === '06:30') App\Backup::run();
