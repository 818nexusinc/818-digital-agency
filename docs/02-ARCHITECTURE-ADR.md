# ADR-001 — Platform architecture for the AI-operated 818 Digital Agency

**Status:** Accepted · 2026-11-22

## Context
- Production runs on 20i/cPanel shared hosting with LiteSpeed, PHP 8.x, MySQL and cron. Long-running processes are not available.
- The current frontend is a compiled React bundle with **no source code in the deliverable**. That makes it impossible to maintain or extend safely.
- Every business record lives in browser `localStorage` (see the audit). Security is broken at the root.
- The goal is durable, auditable, AI-driven operations under owner control.

## Options considered
| Option | Pros | Cons |
|---|---|---|
| **A. Upgrade on PHP/cPanel** | No migration cost. Runs on the existing hosting. Cron is available. No build chain. | Needs a hand-built framework (no Composer assumed). |
| B. Hybrid (PHP site + separate Node/Python AI backend on a VPS) | Rich AI tooling | A second host to pay for and secure. Cross-origin auth. Two deploy pipelines. More moving parts for a solo founder. |
| C. Full rewrite (Next.js + Postgres on Vercel) | Modern DX | The prompt says don't move without a reason. Adds Vercel/Neon cost. Throws away the cPanel email/20i reseller setup. |

## Decision
**Option A.** Build a dependency-free, server-rendered PHP application on MySQL:

- `public_html/index.php` is the single front controller. `app/` sits **outside the web root** and holds code, views, migrations, `.env` and storage.
- **Server-rendered HTML with small vanilla-JS modules** (chat, cart, admin helpers). Pages are fast, crawlable and cloak-free, and there's no build step.
- **Durable queue on MySQL, driven by cron.** `agent_tasks`, `workflow_runs`, `workflow_steps` and `events` tables use row locking (`locked_until` + `locked_by`), exponential backoff retries, timeouts and dead-lettering. `cron.php` runs every minute and processes bounded batches within a time budget.
- **AI provider abstraction.** Anthropic is the default, with OpenAI and an offline fallback. Each agent can have its own model, budget and autonomy level. Tools are checked against server-side permissions, not model instructions.
- **Secrets** live in `.env` (outside the web root) or the `integrations` table, encrypted with libsodium `secretbox` under `APP_KEY`. They never reach the browser.
- **Money** is stored as integer cents in CAD, and all prices are computed on the server from the catalog.
- **Option B stays available later.** The `AiProvider` and `ToolRegistry` interfaces let a future worker (for example the CONARO AI workforce) take tasks over HTTP without schema changes.

## Consequences
- The legacy SPA is retired from the public site and archived at `/classic/` behind admin login, so the owner can check old screens during transition.
- Agents can't run arbitrary code on the server. Website builds produce **preview artifacts** in storage. Deployment is an approval-gated task.
- Throughput is bounded by the cron cadence (about 1 minute of latency for background work). Interactive chat runs synchronously within the request.

## Layer map
| Layer | Implementation |
|---|---|
| 1 Public website | `app/views/public/*`, `Seo.php` |
| 2 AI interaction | `ChatService.php`, `/api/v1/chat`, `assets/js/chat.js` |
| 3 Agent workforce | `agents` table (18 seeded), `AgentRunner.php`, `Tools.php` |
| 4 Workflow engine | `Workflows.php`, `events`/`workflow_*` tables, `cron.php` |
| 5 Business apps | `Catalog`, `Cart`, `Orders`, `Projects`, `Crm`, `Proposals`, `Tickets`, `Documents` |
| 6 Integrations | `Integrations.php`, `Stripe.php`, `Mailer.php`, `Providers/*`, hardened `api/*.php` |
| 7 Data | MySQL. `app/migrations/*.sql` (portable tokens for SQLite) |
| 8 Owner Control Tower | `app/views/admin/*`, `OwnerAssistant` agent |
