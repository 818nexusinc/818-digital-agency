# Deploying 818 Digital Agency v3.0 on cPanel / 20i

Plan on about 30 minutes. You need cPanel access, a MySQL database and PHP 8.1 or newer (the current site already runs 8.5).

## 0. Before you start (security)
The v2.6 site exposed `api/config.json` publicly, along with an admin password in its JavaScript. **Treat every key as compromised and rotate it:**
- Stripe: Developers → API keys → roll the secret key. Create a new webhook signing secret.
- Resend: create a new API key and delete the old one.
- Anthropic: create a new key and revoke the old one.
- 20i and Porkbun: regenerate the API keys.
- Change the `weborders@818nexus.ca` mailbox password, and any account that reused `Admin@NX818$Change!`.

## 1. Back up the current site
In cPanel File Manager, compress `public_html` and download it. If you need a record of the old `api/config.json`, keep it locally, then **delete it from the server**.

## 2. Create the database
cPanel → MySQL® Databases: create a database and a user, and grant the user ALL PRIVILEGES on it.

## 3. Upload the files
The zip contains two folders:
```
app/           → upload to /home/<user>/app   (NEXT TO public_html, not inside it)
public_html/   → upload its contents into /home/<user>/public_html
```
Delete the leftover v2.6 files from `public_html`: `index.html`, `assets/index-*.js`, `content.json`, `sitemap.xml`, `robots.txt`, `llms.txt`, `client-docs/`, `private/` and `api/config.json` (plus `api/overrides.json` and `api/email-log.json` if they exist). The new app generates the SEO files dynamically, and the archived SPA is served from `app/legacy` to admins only.

*If your host does not allow folders outside `public_html`,* upload `app/` as `public_html/_app/`. `index.php` detects it, and `.htaccess` blocks web access to it.

## 4. Configure and install
cPanel → Terminal (or SSH):
```bash
cd ~/app
php bin/setup.php                       # creates app/.env with a random APP_KEY
nano .env                               # set APP_URL, DB_HOST/DB_NAME/DB_USER/DB_PASS
php bin/setup.php --owner=hello@818nexus.ca --name="Your Name"
```
The second run migrates the database, seeds the catalog, agents, workflows and knowledge base, and prints a one-time owner password. **Back up `app/.env`** somewhere safe. Losing `APP_KEY` means re-entering every API key.

No terminal? Run the same commands as one-off cron jobs, e.g. `php /home/<user>/app/bin/setup.php --owner=...` and read the output from the cron email.

## 5. Cron (required)
cPanel → Cron Jobs → Common settings "Once per minute":
```
/usr/local/bin/php /home/<user>/app/cron.php >/dev/null 2>&1
```
This runs agents, workflows, follow-ups, reports, housekeeping and the 06:30 UTC nightly backup. Check **Admin → System health**: "last cron run" should update every minute.

## 6. Connect integrations (Admin → Integrations)
| Integration | What to enter | Then |
|---|---|---|
| Anthropic | API key (+ `ANTHROPIC_WORKSPACE_ID` in .env if your key needs it) | Test connection |
| Stripe | Secret key, webhook signing secret | Add webhook `https://<domain>/webhooks/stripe` with the events listed on the page |
| Resend | API key, webhook secret, verified From address | Webhook URL is unchanged: `/api/resend-webhook.php` |
| 20i / Porkbun | API keys | Used by the archived admin tools and provisioning |
| Company mailbox | Password + IMAP host + mailbox | Used by the archived webmail screen |

## 7. First-day checklist
1. Sign in at `/login` and enable MFA (Admin → Settings).
2. Review **Services & pricing**. The audit found inconsistent legacy prices, so confirm them.
3. Review the policy pages (`/legal/*`) with your advisor.
4. Decide on sales tax (Settings `tax.enabled`, `tax.default_rate_bp`) or use Stripe Tax.
5. Place a $0.50 test order in Stripe test mode and check that the order, projects and emails appear.
6. In Google Search Console, resubmit `https://<domain>/sitemap.xml`. Old `/?view=` URLs 301-redirect automatically.

## Domain note
`.env.example` defaults `APP_URL` to `https://818digitalagency.com`. Set it to whichever domain serves this site (the v2.6 build used `818nexus.ca`).
