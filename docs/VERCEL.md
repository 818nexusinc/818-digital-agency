# Deploying 818 Digital Agency on Vercel

The app runs on Vercel with the community PHP runtime (`vercel-php`). Serverless mode switches on automatically when Vercel sets `VERCEL=1`:
- **Sessions** are stored in the database, so sign-ins survive across instances.
- **Uploaded files** are stored in the database (`document_blobs`), so they're private and never lost.
- **Scratch storage** uses `/tmp`, because the rest of the filesystem is read-only.
- **Background work** (agents, workflows, filings, follow-ups) runs through Vercel Cron calling `/cron`.

Only `public_html/` is served as static files. The `app/` folder, including the archived v2.6 bundle, is never exposed.

## Quick preview (no database yet)
If `DB_NAME` / `DB_DSN` are not set, the app runs in **preview mode** on Vercel. Each instance builds a temporary SQLite database in `/tmp` on its first request, seeded from the real catalog, agents and workflows. Every page works, including the portal and Control Tower.
- Set `APP_KEY`, `OWNER_EMAIL` and `PREVIEW_OWNER_PASSWORD` (12+ characters) to get an owner login. Without `PREVIEW_OWNER_PASSWORD`, no owner account is created.
- Nothing is durable. Data resets when Vercel recycles an instance, and separate instances don't share data, so you may occasionally be signed out. Don't take real orders in preview mode.
- Set the MySQL variables (step 1) to switch to the real, durable mode.

## 1. Database (MySQL)
Vercel doesn't host MySQL. Use one of these:
- **Your existing 20i/cPanel MySQL** (simplest): cPanel → Remote MySQL → allow `%`, with a long random password.
- A managed MySQL such as PlanetScale, Aiven or Railway.

## 2. Put the code in GitHub
Push this folder to a private repo, e.g. `818nexusinc/818-digital-agency`. `app/.env` and `app/storage` are git-ignored.

## 3. Import into Vercel
New Project → import the repo → Framework preset **Other**, no build command. `vercel.json` already sets the PHP runtime, routes, output directory and cron.

## 4. Environment variables (Project → Settings → Environment Variables)
| Name | Value |
|---|---|
| `APP_URL` | `https://<your-project>.vercel.app` (later your domain) |
| `APP_KEY` | `base64:` + 32 random bytes (`openssl rand -base64 32`). **Never change it.** It encrypts stored API keys. |
| `DB_HOST` `DB_NAME` `DB_USER` `DB_PASS` | from step 1 |
| `SESSION_SECURE` | `true` |
| `CRON_SECRET` | long random string (Vercel sends it to `/cron` automatically) |
| `SETUP_TOKEN` | long random string (32+ characters). Remove it after setup. |
| `OWNER_EMAIL` | `hello@818nexus.ca` |
| Optional | `ANTHROPIC_API_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `RESEND_API_KEY`, `RESEND_WEBHOOK_SECRET`, `BC_REGISTRY_API_KEY`, `BC_REGISTRY_ACCOUNT_ID`; or add them later in Admin → Integrations |

## 5. Install (once)
```bash
curl -X POST -H "X-Setup-Token: <SETUP_TOKEN>" https://<your-project>.vercel.app/setup
```
This creates the tables, seeds the catalog, agents and workflows, and returns a one-time owner password. Sign in at `/login`, change the password, enable MFA, then **delete `SETUP_TOKEN`** in Vercel and redeploy. Re-run the same call after future upgrades that add migrations.

## 6. Webhooks
- Stripe: `https://<domain>/webhooks/stripe`
- Resend: `https://<domain>/api/resend-webhook.php`

## Vercel-specific limits
- **Cron frequency:** `vercel.json` runs once a day (09:00 UTC) so it deploys on the Hobby plan. On Pro, change the schedule to `*/5 * * * *`. On Hobby, use an external scheduler (cron-job.org) to call `GET /cron` with header `Authorization: Bearer <CRON_SECRET>` every few minutes.
- **Upload size:** Vercel caps request bodies at about 4.5 MB, so customer uploads are limited to that on Vercel (10 MB on cPanel).
- **Backups:** the in-app backup writes to `/tmp` and isn't durable. Use your database provider's automated backups.
- **Archived admin tools** (`/classic` webmail and the 20i panel) only run on cPanel.
- Runtime: `vercel-php@0.7.3` (PHP 8.3). If Vercel reports a newer required version, bump it in `vercel.json`.
