<?php
declare(strict_types=1);
namespace App;

/**
 * Idempotent seed. Catalog prices are carried over verbatim from the v2.6.0 bundle defaults (`bi` pricing object).
 * The owner should review them in Admin → Catalog (see docs/01-AUDIT.md R16 for the inconsistencies found).
 */
final class Seed
{
    public static function run(): void
    {
        Rbac::seed();
        Integrations::seed();
        Agents::seed();
        Workflows::seed();
        self::services();
        self::packages();
        self::coupons();
        self::kb();
    }

    private static function pp(array $dollars): string { return jenc(array_map(fn($v) => $v === null ? null : (int)round($v * 100), $dollars)); }

    public static function services(): void
    {
        $sole = ['AB' => 149, 'BC' => 149, 'SK' => 149, 'MB' => 149, 'ON' => 149, 'QC' => 149, 'NB' => 199, 'NS' => 149, 'PE' => 149, 'NL' => 149];
        $part = ['AB' => 199, 'BC' => 199, 'SK' => 199, 'MB' => 199, 'ON' => 199, 'QC' => 199, 'NB' => 249, 'NS' => 199, 'PE' => 199, 'NL' => 199];
        $num = ['FED' => 449, 'AB' => 499, 'BC' => 599, 'SK' => 499, 'MB' => 599, 'ON' => 549, 'QC' => 649, 'NB' => 499, 'NS' => 499, 'PE' => 449, 'NL' => 699];
        $named = ['FED' => 449, 'AB' => 549, 'BC' => 649, 'SK' => 549, 'MB' => 649, 'ON' => 599, 'QC' => 699, 'NB' => 549, 'NS' => 549, 'PE' => 499, 'NL' => 899];
        $biz = ['AB' => 149, 'BC' => 149, 'SK' => 149, 'MB' => 149, 'ON' => 149, 'QC' => 149, 'NB' => 199, 'NS' => 149, 'PE' => 199, 'NL' => null];
        $tlds = ['ca' => 2799, 'com' => 1699, 'net' => 1299, 'org' => 1199, 'io' => 3999, 'co' => 2499, 'biz' => 999];
        $tldsRenew = $tlds; $tldsTransfer = ['ca' => 2499, 'com' => 1499, 'net' => 1199, 'org' => 1099, 'io' => 3499, 'co' => 2199, 'biz' => 899];
        $human = ['Submitting your order form authorizes the filing, so work starts as soon as your order is paid. Our Filing Agent then submits it: automatically where the registry offers an API, through the official registry portal elsewhere.'];
        $S = [
            // slug, category, name, summary, model, price$, setup$, timeline, deliverables, extra
            ['sole_prop', 'registration', 'Sole Proprietorship Registration', 'Register your business name as a sole proprietor in your province.', 'per_province', null, 0, '1–3 business days',
                ['Business name registration filed with the provincial registry', 'Registration confirmation delivered to your portal', 'Next-steps checklist (CRA Business Number, GST/HST)'], ['province_prices' => self::pp($sole), 'human_required' => jenc($human), 'delivery_agent' => 'registration']],
            ['partnership', 'registration', 'Partnership Registration', 'Register a general partnership for two or more owners.', 'per_province', null, 0, '1–3 business days',
                ['Partnership registration filed', 'Registration confirmation delivered', 'Partnership agreement checklist'], ['province_prices' => self::pp($part), 'human_required' => jenc($human), 'delivery_agent' => 'registration']],
            ['corp_numbered', 'registration', 'Numbered Corporation', 'Incorporate provincially or federally under a numbered name (e.g. 1234567 N.B. Inc.).', 'per_province', null, 0, '1–5 business days',
                ['Articles of Incorporation prepared and filed', 'Certificate of Incorporation', 'Initial filings (directors, registered office)', 'CRA Business Number guidance'], ['province_prices' => self::pp($num), 'human_required' => jenc($human), 'delivery_agent' => 'registration']],
            ['corp_named', 'registration', 'Named Corporation', 'Incorporate with your chosen name, including the required NUANS name search.', 'per_province', null, 0, '2–7 business days',
                ['NUANS name search and reservation', 'Articles of Incorporation prepared and filed', 'Certificate of Incorporation', 'Initial filings'], ['province_prices' => self::pp($named), 'human_required' => jenc($human), 'delivery_agent' => 'registration']],
            ['biz_name', 'registration', 'Business Name Registration', 'Register an operating (trade) name for an existing business.', 'per_province', null, 0, '1–3 business days',
                ['Business/trade name registration filed', 'Confirmation delivered'], ['province_prices' => self::pp($biz), 'human_required' => jenc($human), 'delivery_agent' => 'registration']],
            ['cooperative', 'registration', 'Co-operative Registration', 'Incorporate a co-operative, including a bylaws template.', 'quote', 349, 0, '1–3 weeks',
                ['Articles and bylaws template', 'Filing with the provincial co-operatives registry'], ['human_required' => jenc($human)]],
            ['nuans', 'registration', 'NUANS Name Search', 'Official NUANS reservation report to check and reserve a corporate name (valid 90 days).', 'one_time', 42.50, 0, 'Same day',
                ['NUANS reservation report (PDF)', 'Plain-language summary of conflicts'], ['delivery_agent' => 'registration']],
            ['bm_annual', 'management', 'Annual Return Filing', 'Prepare and file your annual return to keep your business in good standing.', 'one_time', 149, 0, '1–2 business days',
                ['Annual return prepared and filed', 'Compliance confirmation', 'Filing confirmation delivered'], ['human_required' => jenc($human), 'delivery_agent' => 'registration']],
            ['bm_changes', 'management', 'Corporate Changes', 'Director, officer, address and other corporate record updates filed with the registry.', 'one_time', 509, 0, '2–5 business days',
                ['Address changes', 'Director appointments / resignations', 'Officer changes', 'Updated certificate delivered'], ['human_required' => jenc($human), 'delivery_agent' => 'registration']],
            ['bm_documents', 'management', 'Corporate Document Order', 'Order official documents: certificate of status, corporate profile, certified copies.', 'one_time', 127, 0, '1–3 business days',
                ['Certificate of Incorporation / Status', 'Corporate profile or search report', 'Certified copies of articles'], ['delivery_agent' => 'registration']],
            ['domain_register', 'domains', 'Domain Registration', 'Register your .ca, .com or other domain with WHOIS privacy included where available.', 'yearly', null, 0, 'Same day',
                ['Domain registered in your name', 'WHOIS privacy (where the registry allows)', 'DNS pointed to your website/email'], ['options' => jenc(['tlds' => $tlds, 'billing' => 'yearly'])]],
            ['domain_renew', 'domains', 'Domain Renewal', 'Renew a domain we manage for another year.', 'yearly', null, 0, 'Same day', ['One-year renewal'], ['options' => jenc(['tlds' => $tldsRenew, 'billing' => 'yearly'])]],
            ['domain_transfer', 'domains', 'Domain Transfer', 'Move a domain you own to 818 management (includes one year where the registry adds it).', 'one_time', null, 0, '5–7 days',
                ['Transfer initiated with your authorisation code', 'DNS preserved'], ['options' => jenc(['tlds' => $tldsTransfer]), 'human_required' => jenc(['Transfers require your authorisation code and owner approval.'])]],
            ['dns_management', 'domains', 'DNS Management', 'We configure DNS records for websites, email and verification. Included with domains we manage; quoted for external domains.', 'quote', null, 0, '1 business day', ['Records configured and verified'], ['human_required' => jenc(['DNS changes that could interrupt service require owner approval.'])]],
            ['email_starter', 'email', 'Business Email — Starter', '1 mailbox at your domain with 10 GB storage.', 'monthly', 9.99, 0, 'Within 1 business day', ['Custom domain email address', '10 GB mailbox', 'Webmail & mobile access', 'IMAP / POP3 / SMTP', 'Spam & virus filtering'], []],
            ['email_business', 'email', 'Business Email — Business', 'Up to 5 mailboxes with 25 GB each, aliases and priority support.', 'monthly', 24.99, 0, 'Within 1 business day', ['Up to 5 mailboxes', '25 GB per mailbox', 'Aliases', 'Priority support', 'ActiveSync'], []],
            ['email_team', 'email', 'Business Email — Team', 'Up to 20 mailboxes with 100 GB each and admin console.', 'monthly', 69.99, 0, '1–2 business days', ['Up to 20 mailboxes', '100 GB per mailbox', 'Team calendar sharing', 'Admin console'], []],
            ['website_launch', 'websites', 'Website — Launch', 'A professional one-page website with hosting, SSL and basic SEO.', 'monthly', 149, 249, '1–2 weeks',
                ['1-page professional website', 'Mobile responsive design', 'Basic SEO setup', 'Contact form', 'SSL certificate', 'Hosting included', '1 revision round'], ['delivery_agent' => 'website_dev']],
            ['website_grow', 'websites', 'Website — Grow', 'Up to 5 pages, blog, analytics and e-commerce-ready.', 'monthly', 249, 399, '2–4 weeks',
                ['Up to 5 pages', 'E-commerce ready', 'Advanced SEO', 'Blog/news section', 'Google Analytics', 'Priority support', '3 revision rounds', 'Custom domain setup'], ['delivery_agent' => 'website_dev']],
            ['website_scale', 'websites', 'Website — Scale', 'Unlimited pages, full online store and custom integrations.', 'monthly', 467, 679, '4–8 weeks',
                ['Unlimited pages', 'Full e-commerce store', 'SEO & performance audit', 'Custom integrations', 'Dedicated account manager', 'Monthly analytics report', 'Unlimited revisions'], ['delivery_agent' => 'website_dev']],
            ['website_redesign', 'websites', 'Website Redesign', 'Modernise an existing website: new design, faster pages, better SEO.', 'quote', null, 0, '2–6 weeks', ['Audit of current site', 'New design and build', 'Redirects to protect SEO'], ['delivery_agent' => 'website_dev']],
            ['maintenance_care', 'websites', 'Website Care Plan', 'Backups, updates, SSL renewal and a monthly report.', 'monthly', 79, 0, 'Ongoing', ['Backups', 'Updates', 'SSL renewal', 'Monthly report'], []],
            ['maintenance_protect', 'websites', 'Website Protect Plan', 'Care plus priority security scanning, database optimisation and speed tuning.', 'monthly', 149, 0, 'Ongoing', ['Everything in Care', 'Security scanning', 'DB optimisation', 'Speed tuning'], []],
            ['maintenance_grow', 'websites', 'Website Grow Plan', 'Full-service maintenance with weekly scans, SEO checks, landing pages and a dedicated manager.', 'monthly', 249, 0, 'Ongoing', ['Weekly scans', 'SEO checks', 'Landing pages', 'Dedicated manager'], []],
            ['web_app', 'apps', 'Custom Web Application', 'Purpose-built software for your workflow — scoped and quoted after a free consultation.', 'quote', null, 0, 'Scoped per project', ['Specification', 'Design', 'Build', 'Testing', 'Launch'], ['delivery_agent' => 'web_app']],
            ['booking_system', 'apps', 'Booking System', 'Online booking with calendar, reminders and payments.', 'quote', null, 0, 'Scoped per project', ['Booking flow', 'Calendar sync', 'Reminders'], ['delivery_agent' => 'web_app']],
            ['client_portal', 'apps', 'Client Portal', 'A secure portal where your customers log in, see documents and track work.', 'quote', null, 0, 'Scoped per project', ['Secure login', 'Documents', 'Status tracking'], ['delivery_agent' => 'web_app']],
            ['crm_solution', 'apps', 'CRM Solution', 'A CRM tailored to how you sell and serve customers.', 'quote', null, 0, 'Scoped per project', ['Pipeline', 'Contacts', 'Reporting'], ['delivery_agent' => 'web_app']],
            ['ecommerce', 'apps', 'E-commerce Development', 'Online store development beyond our website plans.', 'quote', null, 0, 'Scoped per project', ['Catalog', 'Checkout', 'Payments', 'Shipping'], ['delivery_agent' => 'web_app']],
            ['custom_software', 'apps', 'Custom Software', 'Integrations, automations and internal tools.', 'quote', null, 0, 'Scoped per project', ['Specification', 'Build', 'Documentation'], ['delivery_agent' => 'web_app']],
            ['brand_identity', 'growth', 'Logo & Brand Identity', 'Logo concepts, colours, typography and a simple brand guide.', 'quote', null, 0, '1–3 weeks', ['Logo concepts', 'Colour palette', 'Brand guide'], ['delivery_agent' => 'brand']],
            ['gbp_setup', 'growth', 'Google Business Profile Setup', 'Help creating and optimising your Google Business Profile.', 'quote', null, 0, '1 week', ['Profile setup assistance', 'Category and service optimisation'], []],
            ['seo_setup', 'growth', 'SEO & AI-Search Setup', 'Technical SEO, structured data and AI-search (GEO) readiness for your site.', 'quote', null, 0, '1–2 weeks', ['Technical audit', 'Metadata & schema', 'llms.txt', 'Recommendations'], ['delivery_agent' => 'seo']],
            ['addon_minute_book', 'addon', 'Minute Book', 'Share certificates, registers and corporate records binder.', 'one_time', 299, 0, '3–5 business days', ['Corporate minute book'], []],
            ['addon_seal', 'addon', 'Corporate Seal', 'Official embossing seal, required on some filings.', 'one_time', 79, 0, '5–7 business days', ['Embossing seal'], []],
            ['addon_registered_address', 'addon', 'Registered Address', 'Moncton, NB office address with mail forwarding.', 'one_time', 169, 0, '1 business day', ['Registered office address', 'Mail forwarding'], []],
            ['addon_expedited', 'addon', 'Expedited Filing', 'Priority processing: 24-hour turnaround where the registry allows.', 'one_time', 99, 0, '24 hours', ['Priority handling'], []],
            ['addon_extra_copies', 'addon', 'Extra Certificate Copies', '3 additional certified copies of your certificate.', 'one_time', 49, 0, '3–5 business days', ['3 certified copies'], []],
        ];
        foreach ($S as $i => [$slug, $cat, $name, $sum, $model, $price, $setup, $tl, $deliv, $extra]) {
            if (Db::val('SELECT 1 FROM services WHERE slug = ?', [$slug])) continue;
            Db::insert('services', array_merge(['slug' => $slug, 'category' => $cat, 'name' => $name, 'summary' => $sum, 'description' => $sum, 'benefits' => '[]',
                'deliverables' => jenc($deliv), 'pricing_model' => $model, 'price_cents' => $price === null ? null : (int)round($price * 100), 'setup_cents' => (int)round($setup * 100),
                'province_prices' => '{}', 'options' => '{}', 'timeline' => $tl, 'requirements' => jenc(array_values(Projects::QUESTIONS[$cat] ?? [])), 'faqs' => '[]', 'addons' => '[]',
                'human_required' => '[]', 'limitations' => $cat === 'registration' ? '818 Digital Agency is not a law firm and does not give legal advice.' : null,
                'ai_capability' => in_array($cat, ['registration', 'management'], true) ? 'prepare' : 'assist', 'status' => 'active', 'sort' => $i * 10, 'created_at' => now(), 'updated_at' => now()], $extra));
        }
    }

    public static function packages(): void
    {
        if (Db::val('SELECT 1 FROM packages WHERE slug = ?', ['launch_bundle'])) return;
        Db::insert('packages', ['slug' => 'launch_bundle', 'name' => 'Launch My Business Bundle', 'description' => 'Corporation, .ca domain, business email and a Launch website — 15% off each item (legacy "All-in-One Bundle" discount).',
            'discount_pct' => 15, 'items' => jenc([['slug' => 'corp_numbered'], ['slug' => 'domain_register', 'opts' => ['tld' => 'ca']], ['slug' => 'email_starter'], ['slug' => 'website_launch']]),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function coupons(): void
    {
        // Carried over from v2.6.0 (all expired as of the audit; kept for history, editable in admin).
        $c = [['LAUNCH25', 'percent', 25, ['registration'], 0, '2025-12-31', 100], ['ATLANTIC10', 'percent', 10, ['all'], 1, '2025-12-31', 500], ['BUNDLE30', 'percent', 30, ['launch_bundle'], 1, '2025-09-30', 50],
            ['DOMAIN0', 'fixed', 1299, ['domains'], 0, '2025-08-15', 200], ['STARTUPOS', 'percent', 20, ['websites'], 0, '2025-11-30', 75]];
        foreach ($c as [$code, $type, $val, $applies, $active, $exp, $max]) {
            if (Db::val('SELECT 1 FROM coupons WHERE code = ?', [$code])) continue;
            Db::insert('coupons', ['code' => $code, 'type' => $type, 'value_amount' => $val, 'applies_to' => jenc($applies), 'active' => $active, 'expires_at' => $exp . ' 23:59:59', 'uses' => 0, 'max_uses' => $max, 'created_at' => now()]);
        }
    }

    public static function kb(): void
    {
        $a = [
            ['How 818 Digital Agency works', 'company', "818 Digital Agency is operated by 818 Nexus Inc. in Moncton, New Brunswick. You tell our AI assistant what you need, get transparent catalog pricing or a proposal, pay online, then track every step in your customer portal. AI agents prepare and draft work; a human reviews anything legal, financial or irreversible (government filings, domain transfers, deployments) before it happens.", 'ai, process, portal'],
            ['Sole proprietorship vs corporation', 'registration', "A sole proprietorship is the simplest structure: you and the business are the same legal person, so you are personally liable for debts, and business income is taxed on your personal return. A corporation is a separate legal entity: it can limit personal liability and may offer tax planning options, but has more filings (annual returns, corporate tax return). Many founders start as a sole proprietor and incorporate later. This is general information, not legal or tax advice — talk to an accountant or lawyer about your situation.", 'sole proprietorship, corporation, incorporate, liability'],
            ['Federal vs provincial incorporation', 'registration', "Provincial incorporation registers the corporation in one province; operating in other provinces may need extra-provincial registration. Federal (CBCA) incorporation gives country-wide name protection and you then register in each province where you operate. Our prices are listed per province on each registration page.", 'federal, CBCA, provincial, extra-provincial'],
            ['What is a NUANS search?', 'registration', "A NUANS report compares your proposed corporate name against existing corporations and trademarks. It is required for named corporations federally and in several provinces (including NB, NS, PE and ON). A report is valid for 90 days.", 'nuans, name search, reservation'],
            ['Refund policy (summary)', 'policy', "Government filing fees and third-party costs (e.g. domain registry fees) are non-refundable once submitted. Work not yet started can be cancelled for a refund. For website and app projects, refunds depend on the milestones completed. Refund requests are reviewed by a person — open a support request in your portal. See the full policy at /legal/refunds.", 'refund, cancel, money back'],
            ['Privacy and your data', 'policy', "We collect only what we need to deliver your services, store it in Canada-hosted infrastructure where possible, and never sell it. You can view, export or delete the information our assistant remembers about you from your portal (Account → Privacy). Marketing emails are only sent with your consent and every one has an unsubscribe link. See /legal/privacy.", 'privacy, data, pipeda, delete, casl'],
            ['How we use AI', 'policy', "Our AI assistant answers questions, recommends services from our catalog, prepares proposals and drafts project work. It cannot set prices on its own, submit government filings, move money or deploy websites without human approval. You can always ask for a person.", 'ai disclosure, human'],
            ['Payment methods', 'billing', "We accept major credit and debit cards through Stripe Checkout. Subscriptions (email, website plans) renew automatically and can be cancelled from your portal or by contacting us. Prefer an invoice? Choose 'Pay by invoice' at checkout and we'll email one.", 'payment, stripe, card, invoice, subscription'],
            ['Business email setup', 'email', "After your mailbox is created you'll receive login details. Webmail is available in any browser; for phones and Outlook use IMAP (port 993, SSL) and SMTP (port 587, STARTTLS) with your full email address as the username. Our support team can walk you through it.", 'email, imap, smtp, outlook, phone setup'],
            ['What happens after I pay?', 'orders', "You get an order confirmation email immediately. A project is opened for each service and you receive a short requirements form. Once we have your details, our project manager agent plans the work, specialists prepare it, a QA agent reviews it independently, and you see each step on your project timeline. Anything that needs your approval appears in your portal.", 'after payment, next steps, timeline'],
            ['How government filings work', 'registration', "After you order a registration, incorporation, annual return or corporate change, your portal shows a guided filing form for your province or Corporations Canada. We validate it against the registry's rules (legal ending, name reservation, registered office location, director details), then work starts right away under the authorization you gave when you submitted the form. Our Filing Agent submits it: automatically through the registry's API where one exists (BC Registries), and through the official registry portal elsewhere. Your corporation number and documents arrive in your portal. If the registry asks for a correction, we'll tell you what to change.", 'filing, submit, registry, status, sign, authorize, corporation number'],
            ['Funding programs for Canadian businesses', 'growth', "Federal programs that small businesses commonly explore include IRAP (NRC Industrial Research Assistance Program), regional development agencies such as ACOA in Atlantic Canada, and the Canada Small Business Financing Program. Eligibility and availability change — always confirm on the official government page before applying.", 'funding, grants, ACOA, IRAP'],
        ];
        foreach ($a as [$t, $cat, $body, $tags]) {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-');
            if (Db::val('SELECT 1 FROM kb_articles WHERE slug = ?', [$slug])) continue;
            Db::insert('kb_articles', ['slug' => $slug, 'title' => $t, 'category' => $cat, 'body' => $body, 'tags' => $tags, 'status' => 'published', 'uses' => 0, 'updated_at' => now()]);
        }
    }
}
