# Corporate Registry Filing Agent

## What it does
For every registration and corporate-maintenance order (sole proprietorship, partnership, business name, numbered or named corporation, co-op, annual return, notice of change, document order, NUANS), the platform:

1. **Opens a filing** when the order is paid, tied to the project and the province (or FED) the customer chose.
2. **Collects structured details** in the portal through a guided form tailored to the filing type and jurisdiction: name and legal ending, name reservation, registered and records offices, directors and incorporators, owners and partners, share structure, company key.
3. **Validates deterministically** against registry rules: legal endings, NUANS or BC NR requirements, registered office in the province, Canadian postal codes, director and incorporator presence, the unincorporated-name rule, Ontario Company Key, and a CBCA residency warning.
4. **Collects the customer's e-signature.** The signer must be a listed director, owner or partner. The platform stores name, capacity, IP, user agent, time and a **SHA-256 hash of the exact data**. Any later change voids the signature.
5. **Runs an AI review.** The *Corporate Registry Filing Agent* reads the filing (`filing_get`) and either submits it (`filing_submit`) or asks the customer for a correction (`filing_flag`).
6. **Submits automatically** once every gate passes: paid · validated · signature hash matches the current data · jurisdiction policy is not `manual` · daily cap (`filing.daily_cap`, default 25).
7. **Tracks the outcome.** It polls API filings, records the corporation number, delivers documents, completes the project milestones and emails the customer. Rejections go back to the agent with the registry's reason.

If no AI provider is connected, cron advances authorized filings on the deterministic checks alone.

## Channels — what "automatic" means in each jurisdiction (verified Nov 2026)
| Registry | Channel | Why |
|---|---|---|
| **BC Registries** (incorporation) | **API, fully automatic** | Business Registry API with an API key and Account-Id, for Premium accounts with pre-authorized debit. Connector: `BcRegistryConnector`. |
| Corporations Canada (CBCA) | Assisted | The Online Filing Centre needs a personal, identity-verified ISED account. No public filing API. Registered-intermediary status is currently suspended. |
| Ontario OBR | Assisted | Intermediary Partner Portal (web). API access only through ministry-licensed service providers. |
| AB, SK, MB, QC, NB, NS, PE, NL | Assisted | Web portals or registry agents. No public filing API found. |

**Assisted** means the filing is fully prepared, validated and customer-signed, then appears in **Admin → Government filings** with a copy-ready filing sheet and a link to the official portal. After filing on the portal, enter the registry number (and optionally upload the certificate). The customer is notified and the project completes automatically.

The platform deliberately does **not** script government web portals. That would require sharing identity-verified personal credentials and getting past their bot checks, which breaches registry terms and puts the business's registry access at risk.

## Switching on BC automatic filing
1. Create a BC Registries **Premium** account with pre-authorized debit and request API access (developer.connect.gov.bc.ca → Get started).
2. In Admin → Integrations → BC Registries Business API, enter the API key and Account ID. Set the base URL to the **sandbox** gateway first.
3. Run one test incorporation end to end in the sandbox. The connector follows the published `incorporationApplication` model but has not been run against BC's live gateway. Check field names against the OpenAPI spec you download with your key.
4. Switch the base URL to production.

## Policy (Admin → Government filings)
Per jurisdiction: `auto` (submit when gates pass), `approval` (owner approves each filing in the Action center) or `manual`. The default is `auto`.

## Expanding automation later
- **Ontario:** contract a licensed OBR service provider's API and add an `OntarioServiceProviderConnector` implementing `RegistryConnector` (`submit`, `status`).
- **Federal:** reapply when Corporations Canada reopens registered-intermediary status. A filing API would plug in the same way.
- Every connector is a single class registered in `RegistryConnectors::for()`.

## Tests
`php tests/run.php` covers 11 filing scenarios. They include validation, signature locking, unpaid blocking, the NB assisted path to completion, BC API submit → poll → registered, retries and escalation, approval policy, rejection routing, the agent's tool-driven submission, and cross-project isolation.
