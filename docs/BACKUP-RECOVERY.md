# Backup & recovery

## What is backed up
| Asset | How | Where | Retention |
|---|---|---|---|
| Database (all tables) | `App\Backup` logical dump, verified after writing | `app/storage/backups/backup-*.sql.gz` | 14 most recent |
| Uploaded documents | File copy | `app/storage/uploads/` | Include in cPanel backups |
| Configuration | `app/.env` (contains APP_KEY) | Off-site password manager | Permanent |

Nightly at 06:30 UTC via cron. On demand: Admin → System health → **Back up now**, or `php -r 'require "app/bootstrap.php"; echo App\Backup::run();'`.

**Off-site copy:** turn on cPanel / 20i automated backups (Timeline / JetBackup) for the whole home directory, and download a backup each week.

## Restore the database
```bash
cd ~/app
gunzip -c storage/backups/backup-YYYYMMDD-HHMMSS.sql.gz > /tmp/restore.sql
# Recreate the schema first if the database is empty:
php bin/setup.php
mysql -u DB_USER -p DB_NAME < /tmp/restore.sql     # each table is cleared then re-inserted
rm /tmp/restore.sql
```
Then sign in and check the Control Tower totals.

## Restore secrets
Integration secrets are encrypted with `APP_KEY`. Restore the original `app/.env` **before** restoring the DB. If APP_KEY is lost, re-enter the keys in Admin → Integrations.

## Disaster recovery (new server)
1. Install the zip (docs/DEPLOY.md steps 3–4) using the saved `.env`.
2. Restore the DB, then copy `storage/uploads`.
3. Re-add the cron job. Re-point DNS. Check that Stripe and Resend webhooks reach the new host.

## Rules
- Never run a destructive migration without a verified backup from the last hour.
- Test a restore into a scratch database once a quarter.
