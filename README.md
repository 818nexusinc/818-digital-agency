# 818 Digital Agency — AI business platform (v3.0)

A dependency-free PHP 8.1+ application for cPanel/20i hosting. It runs the agency's public website, AI assistant, 18-agent workforce, workflows, commerce, customer portal and the owner's Control Tower.

```
app/             application (OUTSIDE the web root): src/, views/, migrations/, storage/, cron.php, bin/setup.php, .env
public_html/     web root: index.php, .htaccess, assets/, fonts/, api/ (hardened legacy endpoints)
docs/            audit, ADR, roadmap, deploy, backup & recovery
tests/           php tests/run.php  (48 tests) · dev-router.php for local `php -S`
```

**Local dev:** `php app/bin/setup.php --owner=you@x.ca --password=ChangeMe2026` then `php -S 127.0.0.1:8000 -t public_html tests/dev-router.php` (set `APP_URL`, `SESSION_SECURE=false` and `MAIL_DRIVER=log` in `app/.env`).

**Deploy:** see `docs/DEPLOY.md`. Rotate all v2.6 API keys first.

**Vercel:** see `docs/VERCEL.md` (serverless mode, DB sessions/files, Vercel Cron, one-time `/setup`).
