# Implementation roadmap & progress log

## v3.1.0 (2026-11-22): Filing Agent + redesign
- Corporate Registry Filing Agent with automatic submission (BC API) and assisted one-click filing for FED and the other provinces. See `docs/04-FILING-AGENT.md`.
- "Registry" visual redesign: self-hosted Libre Caslon + Hanken Grotesk, live certificate hero, ledger catalog and filing track.
- Tests: 59 passing.

## Status at hand-off (v3.0.0, 2026-11-22)

| Phase | Scope | Status |
|---|---|---|
| 0 Audit | Inventory, preservation matrix, risk register, hosting assessment, ADR | ✅ `docs/01-AUDIT.md`, `docs/02-ARCHITECTURE-ADR.md` |
| 1 Foundation | MySQL/SQLite data layer (50 tables, migrations), auth (Argon2id, lockout, TOTP MFA), RBAC, CSRF, rate limits, audit + security logs, encrypted secrets, structured logging with correlation IDs, settings, integration framework | ✅ |
| 2 Website & CX | Server-rendered redesign, all legacy pages and `?view=` redirects, catalog pages, AI chat across the site, guided intake, domain checker (real RDAP), cart, customer portal | ✅ |
| 3 Agent platform | 18-agent registry (versioned, rollback, sandbox test), tool registry with risk levels, autonomy L0–L4, durable queue (locks, retries, backoff, timeouts, dedupe, crash recovery), budgets and cost tracking, approvals, KB retrieval, scoped memory, provider fallback | ✅ |
| 4 Commerce | Server pricing, packages, coupons, proposals (versioned, accept → cart), Stripe Checkout (payment + subscription), signed idempotent webhooks, invoice fallback, receipts, orders → projects | ✅ |
| 5 Delivery | Project templates per category, requirements onboarding, PM planning, specialist drafts, independent QA with revision loop, customer review/approval, files, feedback | ✅ core loop. Agents produce **documents/specs**, not deployed websites (see Next). |
| 6 Marketing | SEO (meta, canonical, OG, Organization/Service/FAQ/Breadcrumb schema), sitemap, robots, llms.txt, services.json, content studio (AI drafts, versions, SEO/readability scores, approve/schedule/publish), blog | ✅ |
| 7 Autonomous ops | Workflow engine + JSON builder (7 default workflows), CASL-aware follow-ups, renewal reminders, daily/weekly owner reports, growth review, opportunity research, owner assistant, finance workspace | ✅ |
| 8 Hardening | 48 automated tests + E2E browser run (see below). Legacy endpoints locked down. | ✅ first pass. Load testing and a professional pen-test are still open. |

## Verified
- `php tests/run.php` reports **48 passed**. It covers pricing and tamper resistance, auth/lockout/CSRF/MFA, encrypted secrets, SSRF guards, Stripe signatures, duplicate webhooks, underpayment, mixed-interval checkout, tool permissions, cross-customer isolation, prompt injection, autonomy approvals, delegation limits, QA independence, provider outage retries and escalation, crash recovery, budgets, loop bounds, agent versioning and rollback, AI chat creating a lead and proposal, offline fallback, workflow delays and conditions, builder validation, consent/suppression, dedupe, follow-up caps, upload attacks, memory privacy, real-data metrics, verified backups and a full cron cycle.
- An E2E browser run covered visitor → cart → checkout (account creation) → invoice order → owner marks it paid → projects open → customer submits requirements, plus every admin and portal page returning 200. Customers get 403 on `/admin`, and a POST without CSRF gets 419. All 33 public routes return 200; legacy endpoints return 401/410.

## Known limits & next steps (prioritised)
1. **Website production sandbox.** Today the Website Dev agent writes specs, sitemaps and copy into deliverables. Next: generate static preview builds into `storage/previews/<project>` served at a signed preview URL, with deployment to 20i as an approval-gated tool.
2. **Government filings:** automatic for BC incorporations via API (v3.1). Assisted one-click elsewhere until registries offer APIs. See docs/04-FILING-AGENT.md.
3. **Domain/mailbox provisioning** through the 20i API, as approval-gated tools for the PM agent. The adapters exist in the legacy proxy; port them to `Tools`.
4. **Inbound email replies** are not parsed. Follow-ups stop on chat or ticket activity or a status change. Add Resend inbound routing to stop on email reply.
5. **Streaming chat responses.** Currently a typing indicator plus a full reply, because PHP-FPM on shared hosting buffers output.
6. **Visual workflow builder.** Workflows are validated JSON today.
7. **Analytics.** GA4 / Search Console verification hook exists. Load scripts only after consent and extend the CSP.
8. **Sales tax.** The setting is off by default. Choose a flat rate or Stripe Tax with your accountant.
9. Owner decisions: confirm catalog prices (audit R16), legal pages, and the domain for `APP_URL`.
