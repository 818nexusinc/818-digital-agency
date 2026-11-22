# 818 Digital Agency — Phase 0 Audit

Audit date: 2026-11-22 · Source: `nx818-cpanel-deploy_.zip` (35 files, 6.2 MB) · Version string: 2.6.0

## 1. What was shipped

| Path | What it is |
|---|---|
| `index.php` | Front controller. Shows browsers the SPA, but bots get a separate minimal HTML page. That's UA-based cloaking. Sets a nonce CSP. |
| `index.html` + `assets/index-c867c37c.js` | A compiled React SPA, 1.45 MB in one file. **No source code is in the package.** |
| `api/*.php` (12 files) | Thin cURL proxies to Anthropic, Stripe, Resend, 20i, Porkbun and IMAP, plus JSON-file CMS and config stores. |
| `client-docs/*.pdf` | 8 demo order PDFs (`ORD-DEMO-00x-*.pdf`). |
| `private/` | `Deny from all`. Meant for CMS JSON. |
| `fonts/` | Tabler icon font. |
| `sitemap.xml` | Lists only `/`. |
| `llms.txt` | Only `# 818 Nexus — v2.6.0`. |
| `robots.txt` | Allows everything and points to the sitemap. |
| `content.json` | `{"name":"818 Nexus","version":"2.6.0"}`. |

**Hosting signals:** LiteSpeed, `php85` handler, 20i/cPanel. PHP extensions in use are cURL, IMAP (optional) and `mail()`. There is no database. Persistence is browser `localStorage` plus JSON files written into the web root.

## 2. Architecture as found

```
Browser (React SPA)
  ├─ localStorage: users, passwords (hashed client-side), orders, tickets, pricing,
  │   CMS, SEO, workflows, audit log, API config, email log, leads … (≈60 keys)
  ├─ fetch /api/anthropic-proxy.php  (system prompt built in the browser)
  ├─ fetch /api/stripe-intent.php    (amount supplied by the browser)
  ├─ fetch /api/twentyi.php, porkbun.php, webmail.php, email-send.php
  └─ fetch /api/admin-config.php, stripe-key.php, cms-content.php, content-overrides.php
PHP proxies → api/config.json (all secrets, inside the web root)
```

### Consequences
1. **There is no shared database.** An order placed in a customer's browser goes into that browser's `localStorage`. The admin dashboard only sees orders that were created in the admin's own browser. Real order capture depends on emails and Stripe receipts.
2. **Authentication runs only in the browser.** The seeded admin account (`admin@818nexus.ca` with a plaintext password) is in the public bundle. Anyone can also give themselves admin by editing `localStorage`.
3. **"Workflows" and "agents" in the SPA are simulated.** For example, `setTimeout(500+Math.random()*400)` animates a "⚙ Running" label. Nothing executes on a server.
4. **Unverified marketing claims** appear in the UI: "4.9 / 5 Client Rating" and "500+". Demo users and demo orders are seeded.

## 3. Feature inventory & preservation matrix

Legend: ✅ preserved and rebuilt on the server · 🔒 preserved, hardened, admin-only · 🧩 integration pathway (works once credentials are added) · 📦 archived legacy UI (admin-only `/classic/`)

| # | Existing capability | As found | New implementation |
|---|---|---|---|
| A1 | Sole proprietorship registration (10 provinces) | SPA form, browser order | ✅ Catalog service `sole_prop`, per-province pricing from the DB, server order + project workflow, Registration Specialist agent (drafts only, government filing needs owner approval) |
| A2 | Partnership registration | same | ✅ `partnership` |
| A3 | Corporation (numbered/named, provincial/federal) | same | ✅ `corp_numbered`, `corp_named` with province fees |
| A4 | Co-operative | crawler page only | ✅ `cooperative` (quote-based, $349+) |
| A5 | NUANS name search | $42.50 | ✅ `nuans` |
| A6 | Business name registration | `biz_name` | ✅ `biz_name` |
| A7 | Registration document delivery | public PDFs | ✅ Private documents store (outside the web root, signed download, ownership check) |
| B1–B7 | Annual returns, director/office changes, document orders | `bm_plans` | ✅ `bm_annual`, `bm_changes`, `bm_documents` |
| C1 | Domain search | Porkbun/20i from the browser plus a **hard-coded "available" fallback** | ✅ Server domain search through the 20i/Porkbun adapters. With no credentials it returns "unknown" and never fakes availability. |
| C2–C4 | Domain register / renew / transfer | 20i proxy, **unauthenticated** | 🔒 + ✅ Catalog SKUs. Provisioning runs as an agent task that requires approval (transfers always do). |
| C5 | DNS management | none | 🧩 Approval-gated pathway (documented) |
| C6–C8 | Business email, hosting, mailboxes | 20i proxy, unauthenticated | 🔒 Admin-only endpoint + ✅ catalog plans + provisioning task |
| C9–C16 | Website design/redesign/maintenance, web apps, booking, portals, CRM, e-commerce, custom software | plans + consult form in `localStorage` | ✅ Catalog, quote requests → leads/proposals, Website Dev pipeline tasks |
| D1–D14 | Packages, bundles, discounts, add-ons, subscriptions, one-time, cart, checkout, Stripe, confirmation, tracking, webhooks, receipts | Browser-priced PaymentIntent, **no payment webhook** | ✅ Server cart, server pricing, coupons table, Stripe Checkout Sessions (payment and subscription), signed and idempotent `checkout.session.completed` webhook, order timeline, receipt email |
| D15 | BNPL (Afterpay) | `stripe-bnpl.php` | 🔒 Kept as Stripe Checkout payment-method config. The legacy endpoint is now admin-only. |
| E1–E13 | Customer registration, login, dashboard, orders, services, projects, docs, messages, notifications, billing, requests, support | `localStorage` | ✅ Real accounts (Argon2id/bcrypt), sessions, portal pages |
| F1–F9 | Transactional email, Resend, logs, delivery tracking, webmail, IMAP/SMTP, mailbox config, provisioning | Resend proxy (open relay), webmail (world-readable) | ✅ `Mailer` service (Resend → `mail()` fallback, DB log, dedupe keys, unsubscribe). Resend webhook kept at the same URL, now writes to the DB. 🔒 webmail admin-only. |
| G1–G15 | Admin dashboard, CMS, overrides, API config, integrations, settings | `localStorage` + unauthenticated JSON writes | ✅ Admin Control Tower, Integration Hub (encrypted secrets), Settings, content blocks in the DB. 📦 old SPA at `/classic/` for reference. |
| H1–H19 | Home, services, about, contact, blog, registration pages, domain, email, website, web app, business mgmt, bundles, funding, FAQs, testimonials, privacy, terms, refund, cookies | SPA views + cloaked crawler HTML | ✅ Server-rendered pages, same `?view=` URLs redirected to clean URLs (301). Testimonials show only verified reviews, so none appear yet. |

Nothing has been removed. Any capability that can't run until credentials are added shows an explicit "Not connected" state in the Integration Hub.

## 4. Risk register

| ID | Severity | Finding | Fix |
|---|---|---|---|
| R1 | **Critical** | `api/config.json` (Stripe secret, Resend, Anthropic, 20i, Porkbun, mailbox password) is web-accessible. `.htaccess` passes `/api/*` through untouched. | Secrets move to `app/.env` and an encrypted DB table outside the web root. `.htaccess` denies `*.json` and other non-PHP files under `/api`. **Rotate every key after go-live.** |
| R2 | **Critical** | `stripe-key.php` accepts writes with no auth. An attacker can swap in their own Stripe key and receive your customers' payments. | Endpoint deleted (returns 410). |
| R3 | **Critical** | `admin-config.php` writes are allowed when `ADMIN_SECRET_TOKEN` is unset, which is the default. | Now requires an admin session and CSRF token. |
| R4 | **Critical** | `webmail.php` has `CORS: *` and no auth. Anyone can read, delete or send company mail. | Admin session required. |
| R5 | **Critical** | `twentyi.php` has no auth. Anyone can register or transfer domains, create or delete mailboxes, or change passwords on the reseller account. | Admin session required. Customer-facing provisioning goes through approval-gated agent tasks. |
| R6 | **Critical** | The admin password is in plaintext in the public JS bundle. All auth runs client-side. | Server auth. The legacy bundle sits behind an admin session. **Change that password anywhere it's reused.** |
| R7 | High | `email-send.php` is an open relay (any recipient and content, with the company as sender), which risks spam and domain reputation. | Admin-only. System email goes through templated `Mailer`. |
| R8 | High | `anthropic-proxy.php` is open, so anyone can run arbitrary prompts on the company key. | Removed from public use. Chat runs server-side with fixed prompts, rate limits and budgets. |
| R9 | High | `stripe-intent.php` lets the browser set the amount, so prices can be tampered with. | Amounts computed on the server from the DB catalog. The legacy endpoint is now admin-only. |
| R10 | High | `cms-content.php` treats a Referer header containing "818nexus.ca" (easily spoofed) or any token longer than 10 chars as admin. | Admin session required. |
| R11 | High | `client-docs/*.pdf` is public with guessable names. | Documents served by the app after an ownership check. The folder is denied. |
| R12 | High | Payments have no webhook, so an order exists only if the browser finishes the flow. | Signed Stripe webhook, idempotent on event ID. |
| R13 | Medium | Crawlers get different content than users (cloaking under Google's spam policies). | A single server-rendered page for everyone. |
| R14 | Medium | Unverified claims ("4.9/5", "500+") and seeded demo records. | Removed. Reviews appear only after consent and verification. |
| R15 | Medium | Errors leak debug information (paths, config existence). | Generic errors plus server logs with correlation IDs. |
| R16 | Medium | Inconsistent prices (website $127 vs $149, email $8.49 vs $9.99, NUANS $42.50 vs $45). | One DB catalog that all pages and the AI read. Bundle defaults are the seed (website $149, email $9.99, NUANS $42.50). **The owner should confirm these.** |
| R17 | Low | `sitemap.xml` and `llms.txt` are essentially empty. | Generated from the catalog. |

## 5. Hosting assessment (20i / cPanel, LiteSpeed)

| Capability | Status | Design response |
|---|---|---|
| PHP 8.x | Yes (8.5 configured) | Code targets PHP ≥ 8.1 |
| MySQL/MariaDB | Standard on cPanel | Primary DB (PDO). SQLite supported for local/dev/tests. |
| Cron | Standard on cPanel | `app/cron.php` every minute: queue worker, workflows, follow-ups, reports |
| Persistent workers / Node | Not assumed | None required. No build step. |
| Composer / SSH | Optional | Zero third-party dependencies |
| Webhooks | Yes | Stripe and Resend with signature verification |
| Directory above `public_html` | Normally writable | `app/` lives there. A fallback layout keeps `public_html/_app` with deny-all. |
| IMAP ext | Optional | Webmail degrades gracefully |
| `sodium` | PHP core | Secret encryption at rest |

## 6. Highest-value AI capabilities (ranked)
1. **Concierge/Sales chat with real tools.** It quotes from the DB catalog, builds a cart, captures the lead and opens a ticket, which turns visitors into orders 24/7.
2. **Order → project automation.** On payment it creates the project, milestones, requirements request and agent tasks.
3. **Owner action center + Owner Assistant.** One queue for approvals and exceptions, and plain-language answers from live data.
4. **Proposal engine.** Approved pricing only. Custom work becomes a quote request.
5. **Support first response with escalation.**
6. **Content/SEO drafting with approval.**
