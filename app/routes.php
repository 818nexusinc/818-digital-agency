<?php
declare(strict_types=1);

use App\{Agents, Approvals, Audit, Auth, Cart, Catalog, ChatService, Crm, Csrf, Db, Documents, Env, Events, HttpError, Integrations, Kb, Log, Mailer, Memory,
    Metrics, Orchestrator, Orders, Projects, Proposals, Rbac, Request, Router, Seo, Session, Settings, Stripe, Tickets, Totp, Crypto, UserError, View, Workflows, RateLimit};

$r = new Router();
$admin = fn(string $perm = 'dashboard.view') => ['perm' => $perm];

/* ─────────────────────────── Public site ─────────────────────────── */

$r->get('/', function () {
    $legacy = ['sole_prop' => '/register/sole-proprietorship', 'partnership' => '/register/partnership', 'corporation' => '/register/corporation', 'cooperative' => '/register/cooperative',
        'nuans' => '/register/nuans', 'domain' => '/domain', 'email' => '/email', 'website' => '/website', 'maintenance' => '/website/maintenance', 'web_app' => '/web-application', 'webapp' => '/web-application',
        'bundle' => '/bundle', 'manage_biz' => '/manage-business', 'annual' => '/manage-business/annual-returns', 'bm_changes' => '/manage-business/corporate-changes', 'bm_docs' => '/manage-business/corporate-documents',
        'bm_names' => '/manage-business/name-services', 'opportunities' => '/opportunities', 'blog' => '/blog', 'about' => '/about', 'contact' => '/contact', 'privacy' => '/legal/privacy', 'terms' => '/legal/terms',
        'refund' => '/legal/refunds', 'cookies' => '/legal/cookies', 'cart' => '/cart', 'auth' => '/login', 'login' => '/login', 'signup' => '/signup', 'dashboard' => '/portal', 'register' => '/register'];
    if (isset($_GET['view'])) { $v = preg_replace('/[^a-z_]/', '', (string)$_GET['view']); header('Location: ' . ($legacy[$v] ?? '/'), true, 301); exit; }
    $cats = [];
    foreach (Catalog::CATEGORIES as $k => $n) if ($k !== 'addon') $cats[$k] = ['name' => $n, 'services' => Catalog::byCategory($k)];
    $reviews = Db::all('SELECT rating, body, display_name FROM reviews WHERE published = 1 AND verified = 1 AND consent_publish = 1 ORDER BY id DESC LIMIT 3');
    View::page('public/home', ['title' => 'Register & Launch Your Business in Canada', 'cats' => $cats, 'reviews' => $reviews,
        'bundle' => Db::one("SELECT * FROM packages WHERE slug = 'launch_bundle' AND status = 'active'"), 'jsonld' => [Seo::organization(), Seo::website()]]);
});

$r->get('/services', function () {
    $cats = [];
    foreach (Catalog::CATEGORIES as $k => $n) $cats[$k] = ['name' => $n, 'services' => Catalog::byCategory($k)];
    View::page('public/services', ['title' => 'Services & pricing', 'cats' => $cats, 'bundle' => Db::one("SELECT * FROM packages WHERE slug = 'launch_bundle' AND status = 'active'"),
        'jsonld' => [Seo::breadcrumbs(['Home' => '/', 'Services' => '/services'])]]);
});

$r->get('/services/category/{cat}', function ($p) {
    $cat = $p['cat'];
    if (!isset(Catalog::CATEGORIES[$cat])) throw new HttpError(404, 'Category not found.');
    View::page('public/category', ['title' => Catalog::CATEGORIES[$cat], 'cat' => $cat, 'services' => Catalog::byCategory($cat), 'chat_area' => $cat,
        'jsonld' => [Seo::breadcrumbs(['Home' => '/', 'Services' => '/services', Catalog::CATEGORIES[$cat] => '/services/category/' . $cat])]]);
});

$r->get('/services/{slug}', function ($p) {
    $s = Catalog::find($p['slug']) ?? throw new HttpError(404, 'Service not found.');
    $faq = array_map(fn($a) => ['q' => $a['title'], 'a' => $a['content']], Kb::search($s['name'] . ' ' . $s['category'], 3));
    View::page('public/service', ['title' => $s['name'], 'description' => $s['summary'], 's' => $s, 'faq' => $faq, 'related' => array_slice(array_filter(Catalog::byCategory($s['category']), fn($x) => $x['slug'] !== $s['slug']), 0, 3),
        'chat_service' => $s['slug'], 'jsonld' => array_filter([Seo::service($s), Seo::faq($faq), Seo::breadcrumbs(['Home' => '/', 'Services' => '/services', $s['name'] => '/services/' . $s['slug']])])]);
});

foreach (['how-it-works' => 'How it works', 'about' => 'About', 'faq' => 'Frequently asked questions', 'funding' => 'Funding opportunities'] as $infoPath => $infoTitle) {
    $r->get('/' . $infoPath, function () use ($infoPath, $infoTitle) {
        $path = $infoPath; $title = $infoTitle;
        $extra = $path === 'faq' ? ['articles' => Db::all("SELECT title, body FROM kb_articles WHERE status = 'published' ORDER BY category, id")] : [];
        if ($path === 'faq') $extra['jsonld'] = [Seo::faq(array_map(fn($a) => ['q' => $a['title'], 'a' => $a['body']], $extra['articles']))];
        View::page('public/' . $path, ['title' => $title] + $extra);
    });
}

$r->get('/legal/{page}', function ($p) {
    $pages = ['privacy' => 'Privacy policy', 'terms' => 'Terms of service', 'refunds' => 'Refund & cancellation policy', 'cookies' => 'Cookie policy', 'ai' => 'How we use AI'];
    if (!isset($pages[$p['page']])) throw new HttpError(404, 'Page not found.');
    View::page('public/legal', ['title' => $pages[$p['page']], 'page' => $p['page']]);
});

$r->get('/blog', function () {
    View::page('public/blog', ['title' => 'Insights for Canadian entrepreneurs', 'posts' => Db::all("SELECT title, slug, excerpt, publish_at, updated_at FROM content_items WHERE kind = 'blog' AND status = 'published' ORDER BY COALESCE(publish_at, updated_at) DESC LIMIT 50")]);
});
$r->get('/blog/feed.xml', function () { header('Content-Type: application/rss+xml; charset=UTF-8'); header('Cache-Control: public, max-age=3600'); echo App\BlogEngine::rss(); });
$r->get('/blog/{slug}', function ($p) {
    $post = Db::one("SELECT * FROM content_items WHERE slug = ? AND kind = 'blog' AND status = 'published'", [$p['slug']]) ?? throw new HttpError(404, 'Article not found.');
    $ld = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post['title'], 'description' => $post['excerpt'], 'datePublished' => str_replace(' ', 'T', (string)($post['publish_at'] ?? $post['created_at'])) . 'Z',
        'dateModified' => str_replace(' ', 'T', (string)$post['updated_at']) . 'Z', 'author' => ['@type' => 'Organization', 'name' => App\Settings::get('company.name')], 'publisher' => ['@type' => 'Organization', 'name' => App\Settings::get('company.name')],
        'mainEntityOfPage' => url('/blog/' . $post['slug']), 'keywords' => (string)$post['keyword']];
    View::page('public/post', ['title' => $post['title'], 'description' => $post['excerpt'], 'post' => $post, 'path' => '/blog/' . $post['slug'], 'jsonld' => [$ld, App\Seo::breadcrumbs(['Home' => '/', 'Blog' => '/blog', $post['title'] => '/blog/' . $post['slug']])]]);
});

$r->get('/contact', fn() => View::page('public/contact', ['title' => 'Contact us']));
$r->get('/start', fn() => View::page('public/start', ['title' => 'Tell us what you need', 'services' => Catalog::all()]));

$intake = function () {
    App\Spam::guard(Request::path() === '/start' ? 'start' : 'contact', ['email' => Request::str('email', 190), 'name' => Request::str('name', 190), 'business_name' => Request::str('business_name', 190), 'message' => Request::str('message', 4000)],
        function () { Session::flash('success', "Thanks! We've received your request and emailed a confirmation. Expect a reply within one business day."); View::redirect(Request::path() . '?sent=1'); });
    if (!RateLimit::hit('intake:' . client_ip(), 6, 3600)) throw new UserError('Too many submissions — please email us instead.');
    $c = Crm::upsertCustomer(['email' => Request::str('email', 190), 'name' => Request::str('name', 190), 'phone' => Request::str('phone', 40),
        'business_name' => Request::str('business_name', 190), 'location' => Request::str('location', 190), 'industry' => Request::str('industry', 120)], 'website_form');
    if (Request::input('consent_marketing')) Db::update('customers', ['consent_marketing' => 1, 'consent_at' => now()], 'id = ?', [$c['id']]);
    $services = array_values(array_filter((array)Request::input('services', []), 'is_string'));
    $lead = Crm::createLead($c, ['summary' => Request::str('message', 4000), 'services' => $services, 'budget' => Request::str('budget', 60), 'timeline' => Request::str('timeline', 60),
        'intent' => Request::path() === '/start' ? 'intake' : 'contact'], Request::path() === '/start' ? 'intake_form' : 'contact_form');
    Mailer::send('lead_confirmation', $c['email'], ['name' => $c['name'] ?: 'there', 'summary' => str_limit(Request::str('message'), 300)], 'lead-confirm:' . $lead['id'], 'lead', (int)$lead['id']);
    Session::flash('success', "Thanks! We've received your request and emailed a confirmation. Expect a reply within one business day.");
    View::redirect(Request::path() . '?sent=1');
};
$r->post('/contact', $intake);
$r->post('/start', $intake);

$r->get('/unsubscribe', function () {
    $e = strtolower((string)($_GET['e'] ?? ''));
    $ok = filter_var($e, FILTER_VALIDATE_EMAIL) && hash_equals(Mailer::unsubscribeToken($e), (string)($_GET['t'] ?? ''));
    if ($ok) {
        if (!Db::val('SELECT 1 FROM email_suppressions WHERE email = ?', [$e])) Db::insert('email_suppressions', ['email' => $e, 'reason' => 'unsubscribed', 'created_at' => now()]);
        Db::run('UPDATE customers SET consent_marketing = 0, updated_at = ? WHERE email = ?', [now(), $e]);
        Db::run('UPDATE leads SET opted_out = 1, next_follow_up_at = NULL WHERE customer_id = (SELECT id FROM customers WHERE email = ?)', [$e]);
        Audit::log('email.unsubscribe', 'email', $e);
    }
    View::page('public/message', ['title' => $ok ? 'You are unsubscribed' : 'Link not valid', 'message' => $ok ? 'You will no longer receive marketing emails from us. Transactional emails about your orders will still be sent.' : 'This unsubscribe link is invalid or incomplete.']);
});


/* Original 818digitalagency.com structure: same paths, same page arrangement, same forms. */
$svcPage = function (string $view, string $title, string $desc, array $extra = []) {
    return function () use ($view, $title, $desc, $extra) {
        $path = Request::path(); $p = pillar($path);
        $crumbs = ['Home' => '/'];
        if ($p !== 'brand' && PILLARS[$p]['home'] !== $path) $crumbs[PILLARS[$p]['name']] = PILLARS[$p]['home'];
        $crumbs[$extra['crumb'] ?? $title] = $path;
        $ld = [App\Seo::breadcrumbs($crumbs)];
        if (!empty($extra['svc']) && ($s = App\Catalog::find($extra['svc']))) { $sv = App\Seo::service($s); $sv['url'] = url($path); $ld[] = $sv; }
        View::page('public/svc/' . $view, ['title' => $title, 'description' => $desc, 'path' => $path, 'jsonld' => $ld] + $extra);
    };
};
$r->get('/register', $svcPage('register', 'Register a Business in Canada Online', 'Register a sole proprietorship, partnership, corporation or co-op in any province or federally. Government fees included. Start online in minutes.', ['crumb' => 'Register Business']));
$r->get('/register/nuans', $svcPage('nuans', 'NUANS Name Search Report, Same Day', 'Order an official NUANS name search report online. Required before incorporating a named corporation. Delivered to your inbox, valid 90 days.', ['crumb' => 'NUANS Name Search', 'svc' => 'nuans']));
$r->get('/register/sole-proprietorship', $svcPage('sole_prop', 'Register a Sole Proprietorship Online', 'Register your sole proprietorship or trade name in any province. Government fee included, filed fast, no NUANS needed. Start in minutes.', ['crumb' => 'Sole Proprietorship', 'svc' => 'sole_prop']));
$r->get('/register/partnership', $svcPage('partnership', 'Register a Partnership in Canada', 'Register a general or limited partnership online in any province. Government fee and agreement template included. Start in minutes.', ['crumb' => 'Partnership', 'svc' => 'partnership']));
$r->get('/register/corporation', $svcPage('corporation', 'Incorporate a Business in Canada Online', 'Incorporate provincially or federally (CBCA), named or numbered. Articles prepared and filed for you, government fee included. Start now.', ['crumb' => 'Corporation', 'svc' => 'corp_numbered']));
$r->get('/register/cooperative', $svcPage('cooperative', 'Register a Co-operative in Canada', 'Register a worker, consumer, producer or multi-stakeholder co-op. Tell us about it and get a written quote within one business day.', ['crumb' => 'Co-operative', 'svc' => 'cooperative']));
$r->get('/manage-business', $svcPage('manage', 'Annual Returns & Corporate Changes', 'Keep your Canadian business in good standing. Annual returns, director and address changes, and official documents, filed for you.', ['crumb' => 'Manage Business']));
foreach (['annual-returns' => ['bm_annual', 'Annual Return Filing'], 'corporate-changes' => ['bm_changes', 'Corporate Changes'], 'corporate-documents' => ['bm_documents', 'Corporate Documents & Certificates'], 'name-services' => ['biz_name', 'Business Name Renewal & Change']] as $seg => [$mslug, $mtitle]) {
    $r->get('/manage-business/' . $seg, $svcPage('manage_form', $mtitle, $mtitle . ' filed with your provincial or federal registry on your behalf. Fast, accurate and tracked in your portal. Start online.', ['mkind' => $seg, 'crumb' => $mtitle, 'svc' => $mslug]));
}
$r->get('/web-services', $svcPage('web_services', 'Web Services: Domains, Email & Websites', 'Domains, business email, websites, maintenance and custom web apps for Canadian businesses. Search your domain and get online today.', ['crumb' => 'Web Services']));
$r->get('/domain', $svcPage('domain', 'Register a .ca or .com Domain', 'Search and register your .ca or .com domain. WHOIS privacy where allowed and DNS set up for your email and website. Check availability now.', ['crumb' => 'Domain Name', 'svc' => 'domain_register']));
$r->get('/email', $svcPage('email', 'Business Email on Your Own Domain', 'Professional email at your own domain with webmail, mobile access and spam filtering. Set up for you after checkout. Plans from $9.99/month.', ['crumb' => 'Business Email', 'svc' => 'email_starter']));
$r->get('/website', $svcPage('website', 'Small Business Website Design & Hosting', 'A custom, mobile-first website built, hosted and maintained for your Canadian business. Hosting and SSL included. Choose a plan today.', ['crumb' => 'Website Design', 'svc' => 'website_launch']));
$r->get('/website/maintenance', $svcPage('maintenance', 'Website Maintenance Plans', 'Daily backups, updates, security patches and content edits for your existing website. Monthly plans, cancel anytime.', ['crumb' => 'Website Maintenance', 'svc' => 'maintenance_care']));
$r->get('/web-application', $svcPage('web_app', 'Custom Web App Development, Canada', 'Booking systems, client portals, dashboards and business tools, built around how you work. Free consultation and a written quote.', ['crumb' => 'Web Application', 'svc' => 'web_app']));
$r->get('/bundle', $svcPage('bundle', 'Business Bundle: Register, Domain & Website', 'Register your business and add a domain, email and website in one order. Save up to 15% automatically. Build your bundle now.', ['crumb' => 'Bundle & Save']));
$r->get('/opportunities', fn() => View::page('public/funding', ['title' => 'Small Business Grants & Funding in Canada', 'description' => 'Federal and Atlantic Canada funding programs for small businesses: IRAP, ACOA, CSBFP and more, with links to each official page.', 'path' => '/opportunities']));
$r->get('/signup', fn() => View::page('public/register', ['title' => 'Create your account', 'noindex' => true]));

$r->post('/subscribe', function () {
    App\Spam::guard('subscribe', ['email' => Request::str('email', 190)], fn() => View::redirect('/?subscribed=1#news'));
    if (!RateLimit::hit('subscribe:' . client_ip(), 5, 3600)) throw new UserError('Too many sign-ups from this network. Try again later.');
    $email = strtolower(Request::str('email', 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('Enter a valid email address.');
    $c = Crm::upsertCustomer(['email' => $email], 'newsletter');
    Db::update('customers', ['consent_marketing' => 1, 'consent_at' => now()], 'id = ?', [$c['id']]); // express consent (CASL): they asked to be notified
    Db::run('DELETE FROM email_suppressions WHERE email = ? AND reason = ?', [$email, 'unsubscribed']);
    Audit::log('newsletter.subscribe', 'customer', (string)$c['id']);
    $back = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) ?: '/';
    if (!preg_match('#^/[a-z0-9/_-]*$#i', $back) || str_starts_with($back, '//')) $back = '/';
    View::redirect($back . '?subscribed=1#news');
});

/* Form endpoints (JSON, CSRF-checked like every POST) */
$r->post('/order/add', function () { $in = Request::json(); App\Spam::guard('order', $in + ['_ft' => $in['_ft'] ?? null], fn() => View::json(['ok' => true, 'reference' => 'R' . strtoupper(bin2hex(random_bytes(3)))])); $r = App\Wizard::add($in); App\FormDrafts::complete($in['draft'] ?? null); View::json($r); });
$r->post('/order/bundle', function () { $in = Request::json(); App\Spam::guard('order', $in + ['_ft' => $in['_ft'] ?? null], fn() => View::json(['ok' => true, 'reference' => 'R' . strtoupper(bin2hex(random_bytes(3)))])); $r = App\Wizard::bundle($in); App\FormDrafts::complete($in['draft'] ?? null); View::json($r); });
$r->post('/order/quote', function () { $in = Request::json(); App\Spam::guard('order', $in + ['_ft' => $in['_ft'] ?? null], fn() => View::json(['ok' => true, 'reference' => 'R' . strtoupper(bin2hex(random_bytes(3)))])); $r = App\Wizard::quote($in); App\FormDrafts::complete($in['draft'] ?? null); View::json($r); });
$r->post('/order/draft', function () { $in = Request::json(); App\Spam::guard('draft', [], fn() => View::json(['ok' => true, 'token' => bin2hex(random_bytes(16))])); View::json(App\FormDrafts::save($in)); });

/* SEO / machine-readable */
$r->get('/sitemap.xml', function () { header('Content-Type: application/xml; charset=UTF-8'); echo Seo::sitemap(); });
$r->get('/llms.txt', function () { header('Content-Type: text/plain; charset=UTF-8'); echo Seo::llms(); });
$serveLogo = function (string $key) {
    $a = App\SiteText::asset($key);
    if (!$a) { http_response_code(404); header('Content-Type: text/plain'); echo 'No logo'; return; }
    $etag = '"' . substr(sha1($a['updated_at']), 0, 16) . '"';
    header('Content-Type: ' . $a['mime']); header('Cache-Control: public, max-age=86400'); header('ETag: ' . $etag);
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox"); header('X-Content-Type-Options: nosniff');
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return; }
    echo base64_decode((string)$a['data']);
};
$r->get('/brand/logo', fn() => $serveLogo('logo'));
$r->get('/brand/img/{key}', function ($p) {
    $k = (string)$p['key'];
    $a = preg_match('/^[0-9a-f]{40}$/', $k) ? App\SiteText::image($k) : null;
    if (!$a) { http_response_code(404); header('Content-Type: text/plain'); echo 'Not found'; return; }
    $etag = '"' . substr(sha1($a['updated_at']), 0, 16) . '"';
    header('Content-Type: ' . $a['mime']); header('Cache-Control: public, max-age=604800'); header('ETag: ' . $etag); header('X-Content-Type-Options: nosniff');
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return; }
    echo base64_decode((string)$a['data']);
});

/* In-page site editor: the owner edits text and pictures on the live website. */
$ownerOnly = function () { if (!App\SiteText::isOwner()) throw new HttpError(403, 'Only the owner can edit the website.'); };
$r->post('/site-edit/save', function () use ($ownerOnly) {
    $ownerOnly();
    $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    $page = '/' . ltrim((string)($in['page'] ?? '/'), '/'); $page = rtrim((string)preg_replace('#[^a-zA-Z0-9/_\-.]#', '', $page), '/') ?: '/';
    $changes = is_array($in['changes'] ?? null) ? $in['changes'] : [];
    foreach ($changes as &$c) foreach (['en', 'fr'] as $l) if (isset($c[$l])) $c[$l] = trim((string)preg_replace('/\s+/u', ' ', (string)$c[$l]));
    unset($c);
    [$n, $bad] = App\SiteText::save($page, $changes, (string)(Auth::user()['email'] ?? 'owner'));
    View::json(['ok' => !$bad, 'saved' => $n, 'problems' => $bad]);
});
$r->post('/site-edit/image', function () use ($ownerOnly) {
    $ownerOnly();
    try {
        $src = Request::str('src', 500);
        if (Request::str('action', 10) === 'reset') { App\SiteText::resetImage($src); View::json(['ok' => true, 'url' => (string)(parse_url($src, PHP_URL_PATH) ?: '')]); return; }
        if (Request::str('action', 10) === 'alt') { App\SiteText::setImageAlt($src, Request::str('alt', 300)); View::json(['ok' => true]); return; }
        View::json(['ok' => true] + App\SiteText::saveImage($src, $_FILES['picture'] ?? [], Request::str('alt', 300), (string)(Auth::user()['email'] ?? 'owner')));
    } catch (UserError $e) { View::json(['ok' => false, 'error' => $e->getMessage()], 422); }
});
$r->get('/brand/logo-light', fn() => $serveLogo('logo_light'));
$r->get('/robots.txt', function () { header('Content-Type: text/plain; charset=UTF-8'); header('Cache-Control: public, max-age=3600'); echo Seo::robots(); });
$r->get('/llms-full.txt', function () { header('Content-Type: text/plain; charset=UTF-8'); header('Cache-Control: public, max-age=3600'); echo Seo::llmsFull(); });
$r->get('/{key}.txt', function ($p) { if (!hash_equals(Seo::indexNowKey(), (string)$p['key'])) throw new HttpError(404, 'Page not found.'); header('Content-Type: text/plain; charset=UTF-8'); echo Seo::indexNowKey(); });
$r->get('/services.json', fn() => View::json(Seo::catalogJson()));
$r->get('/content.json', fn() => View::json(['name' => Settings::get('company.name'), 'version' => APP_VERSION, 'catalog' => url('/services.json'), 'llms' => url('/llms.txt')]));

/* ─────────────────────────── Cart & checkout ─────────────────────────── */

$r->get('/cart', function () {
    $t = Cart::totals();
    View::page('public/cart', ['title' => 'Your cart', 't' => $t, 'stripe' => Stripe::enabled(), 'noindex' => true]);
});
$r->post('/cart/add', function () {
    $pkg = Request::str('package', 60);
    if ($pkg) Cart::addPackage($pkg, ['province' => Request::str('province', 3)]);
    else Cart::add(Request::str('slug', 60), ['province' => Request::str('province', 3), 'tld' => Request::str('tld', 10), 'domain' => Request::str('domain', 120)]);
    if (Request::wantsJson()) { View::json(['ok' => true, 'count' => count(Cart::lines())]); return; }
    Session::flash('success', 'Added to your cart.');
    View::redirect('/cart');
});
$r->post('/cart/remove', function () { Cart::remove((int)Request::input('index')); View::redirect('/cart'); });
$r->post('/cart/coupon', function () {
    $code = Request::str('coupon', 40);
    Cart::setCoupon($code ?: null);
    if ($code && !Cart::validCoupon($code)) Session::flash('error', 'That code is not valid or has expired.');
    View::redirect('/cart');
});

$r->get('/checkout', function () {
    $t = Cart::totals();
    if (!$t['lines']) View::redirect('/cart');
    View::page('public/checkout', ['title' => 'Checkout', 't' => $t, 'user' => Auth::user(), 'customer' => Auth::customer(), 'stripe' => Stripe::enabled(), 'noindex' => true]);
});

$r->post('/checkout', function () {
    $t = Cart::totals();
    if (!$t['lines']) throw new UserError('Your cart is empty.');
    if (!Request::input('agree_terms')) throw new UserError('Please accept the Terms of Service and Refund Policy.');
    $user = Auth::user();
    if (!$user) {
        // Create the account as part of checkout so customers can track orders.
        $email = Request::str('email', 190);
        if (Db::val('SELECT 1 FROM users WHERE email = ?', [strtolower($email)])) throw new UserError('You already have an account — please sign in first.');
        $user = Auth::register(Request::str('name', 190), $email, (string)Request::input('password', ''), ['phone' => Request::str('phone', 40), 'business_name' => Request::str('business_name', 190), 'consent_marketing' => (bool)Request::input('consent_marketing')]);
        Auth::loginAs((int)$user['id']);
    }
    $cust = Db::one('SELECT * FROM customers WHERE user_id = ?', [$user['id']]) ?? Crm::upsertCustomer(['email' => $user['email'], 'name' => $user['name']]);
    if (!$cust['user_id']) Db::update('customers', ['user_id' => $user['id']], 'id = ?', [$cust['id']]);
    Session::start();
    $order = Orders::createFromTotals($t, $cust, (int)$user['id'], ['proposal_id' => $_SESSION['proposal_id'] ?? null, 'notes' => Request::str('notes', 2000),
        'province' => strtoupper(Request::str('province', 3)) ?: null, 'ip' => client_ip()]);
    unset($_SESSION['proposal_id']);
    if (Stripe::enabled() && Request::input('method') !== 'invoice') {
        $urlTo = Stripe::checkout($order, $cust);
        Cart::clear();
        header('Location: ' . $urlTo, true, 303); exit;
    }
    // Honest fallback: no online payment configured (or customer chose invoice).
    Db::update('orders', ['status' => 'awaiting_payment', 'updated_at' => now()], 'id = ?', [$order['id']]);
    Approvals::request('payment_exception', 'Send invoice for ' . $order['number'], ['what' => 'Customer requested an invoice (' . money((int)$order['total_cents']) . '). Send it and mark the order paid when funds arrive.',
        'why' => Stripe::enabled() ? 'Customer chose pay-by-invoice.' : 'Stripe is not configured.', 'impact' => 'Work starts after payment.', 'cost' => money((int)$order['total_cents'])], 'order', (int)$order['id'], 'finance', 'medium');
    Mailer::notifyOwner('Invoice requested ' . $order['number'], money((int)$order['total_cents']) . ' — ' . $cust['email'], '/admin/orders/' . $order['number']);
    Cart::clear();
    View::redirect('/checkout/success?o=' . $order['number'] . '&invoice=1');
});

$r->get('/checkout/success', function () {
    $o = Db::one('SELECT * FROM orders WHERE number = ?', [preg_replace('/[^A-Z0-9\-]/', '', (string)($_GET['o'] ?? ''))]);
    $u = Auth::user();
    if (!$o || !$u || ((int)$o['user_id'] !== (int)$u['id'] && !Auth::isStaff($u))) throw new HttpError(404, 'Order not found.');
    View::page('public/success', ['title' => 'Thank you', 'o' => $o, 'invoice' => isset($_GET['invoice']), 'noindex' => true]);
});

/* Proposals (public link with unguessable token) */
$r->get('/proposal/{token}', function ($p) {
    $prop = Proposals::byToken($p['token']) ?? throw new HttpError(404, 'Proposal not found.');
    $cust = $prop['customer_id'] ? Db::one('SELECT name, business_name FROM customers WHERE id = ?', [$prop['customer_id']]) : null;
    View::page('public/proposal', ['title' => 'Proposal ' . $prop['number'], 'p' => $prop, 'cust' => $cust, 'noindex' => true]);
});
$r->post('/proposal/{token}/accept', function ($p) {
    $prop = Proposals::byToken($p['token']) ?? throw new HttpError(404, 'Proposal not found.');
    Proposals::accept($prop);
    if ($prop['needs_quote']) { Session::flash('success', 'Thanks — we will price the custom items and send you an updated proposal shortly.'); View::redirect('/proposal/' . $p['token']); }
    View::redirect('/checkout');
});

/* ─────────────────────────── Auth ─────────────────────────── */

$r->get('/login', fn() => View::page('public/login', ['title' => 'Sign in', 'noindex' => true]));
$r->post('/login', function () {
    App\Spam::guard('login', []);
    $res = Auth::attempt(Request::str('email', 190), (string)Request::input('password', ''));
    if ($res === 'mfa') View::redirect('/login/mfa');
    Session::start();
    $to = $_SESSION['after_login'] ?? (Auth::isStaff() ? '/admin' : '/portal');
    unset($_SESSION['after_login']);
    View::redirect($to);
});
$r->get('/login/mfa', fn() => View::page('public/mfa', ['title' => 'Two-step verification', 'noindex' => true]));
$r->post('/login/mfa', function () {
    if (!Auth::verifyMfa(Request::str('code', 10))) throw new UserError('That code did not match. Try the latest code from your authenticator app.');
    View::redirect('/admin');
});
$signup = function () {
    App\Spam::guard('signup', ['email' => Request::str('email', 190), 'name' => Request::str('name', 190), 'business_name' => Request::str('business_name', 190)]);
    if (!RateLimit::hit('register:' . client_ip(), 5, 3600)) throw new UserError('Too many sign-ups from this network. Try again later.');
    $u = Auth::register(Request::str('name', 190), Request::str('email', 190), (string)Request::input('password', ''), ['phone' => Request::str('phone', 40), 'business_name' => Request::str('business_name', 190), 'consent_marketing' => (bool)Request::input('consent_marketing')]);
    Auth::loginAs((int)$u['id']);
    Session::regenerate();
    View::redirect('/portal');
};
$r->post('/signup', $signup);
$r->post('/register', $signup);
$r->post('/logout', function () { Auth::logout(); View::redirect('/'); });
$r->get('/forgot', fn() => View::page('public/forgot', ['title' => 'Reset password', 'noindex' => true]));
$r->post('/forgot', function () {
    App\Spam::guard('forgot', ['email' => Request::str('email', 190)], function () { Session::flash('success', 'If that email has an account, a reset link is on its way.'); View::redirect('/login'); });
    if (RateLimit::hit('forgot:' . client_ip(), 5, 3600) && ($t = Auth::createResetToken(Request::str('email', 190)))) {
        Mailer::send('password_reset', Request::str('email', 190), ['link' => url('/reset/' . $t)]);
    }
    Session::flash('success', 'If that email has an account, a reset link is on its way.');
    View::redirect('/login');
});
$r->get('/reset/{token}', fn($p) => View::page('public/reset', ['title' => 'Choose a new password', 'token' => $p['token'], 'noindex' => true]));
$r->post('/reset/{token}', function ($p) {
    if (!Auth::resetPassword($p['token'], (string)Request::input('password', ''))) throw new UserError('This reset link is invalid or expired.');
    Session::flash('success', 'Password updated. Please sign in.');
    View::redirect('/login');
});

/* ─────────────────────────── JSON API ─────────────────────────── */

$r->post('/api/v1/chat', function () {
    App\Spam::guard('chat', ['message' => Request::str('message', 2000)], fn() => View::json(['ok' => true, 'reply' => 'Thanks — a member of our team will follow up.', 'actions' => [], 'suggestions' => []]));
    $res = ChatService::handle(Request::str('conversation', 64) ?: null, Request::str('message', 2000), ['path' => Request::str('path', 190), 'service' => Request::str('service', 60)]);
    View::json(['ok' => true] + $res);
});
$r->get('/api/v1/chat/history', fn() => View::json(['ok' => true, 'messages' => ChatService::history($_GET['conversation'] ?? null)]));
$r->post('/api/v1/domain-check', function () {
    if (!RateLimit::hit('domain:' . client_ip(), 30, 3600)) throw new UserError('Too many checks. Try again later.');
    $d = strtolower(Request::str('domain', 120));
    if (!str_contains($d, '.')) $d .= '.ca';
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $d)) throw new UserError('Enter a valid domain like mybusiness.ca');
    View::json(['ok' => true] + App\Domains::check($d));
});
$r->post('/webhooks/stripe', function () {
    $payload = file_get_contents('php://input') ?: '';
    $secret = Integrations::secret('stripe', 'webhook_secret');
    if (!$secret || !Stripe::verify($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
        App\Audit::security('webhook.stripe_bad_signature', substr($payload, 0, 80));
        View::json(['ok' => false], 400); return;
    }
    $code = Stripe::handle(json_decode($payload, true) ?: []);
    View::json(['ok' => $code === 200], $code);
    if (function_exists('litespeed_finish_request')) litespeed_finish_request(); elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    Workflows::tick();
}, ['nocsrf' => true]);

/* ─────────────────────────── Customer portal ─────────────────────────── */

$portalCustomer = function (): array {
    $c = Auth::customer();
    if (!$c) { $u = Auth::user(); $c = Crm::upsertCustomer(['email' => $u['email'], 'name' => $u['name']], 'account'); Db::update('customers', ['user_id' => $u['id']], 'id = ?', [$c['id']]); $c = Auth::customer(); }
    return $c;
};
$ownProject = function (string $num) use ($portalCustomer): array {
    $c = $portalCustomer();
    return Db::one('SELECT * FROM projects WHERE number = ? AND customer_id = ?', [$num, $c['id']]) ?? throw new HttpError(404, 'Project not found.');
};

$r->get('/portal', function () use ($portalCustomer) {
    $c = $portalCustomer();
    View::page('portal/dashboard', ['title' => 'Dashboard', 'c' => $c, 'orders' => Db::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5', [$c['id']]),
        'projects' => Projects::forCustomer((int)$c['id']), 'subs' => Db::all('SELECT s.*, o.number FROM subscriptions s LEFT JOIN orders o ON o.id = s.order_id WHERE s.customer_id = ? AND s.status = ?', [$c['id'], 'active']),
        'tickets' => Db::all("SELECT * FROM tickets WHERE customer_id = ? AND status NOT IN ('resolved','closed') ORDER BY updated_at DESC", [$c['id']]),
        'pending' => Db::all("SELECT * FROM projects WHERE customer_id = ? AND status IN ('awaiting_requirements','awaiting_customer')", [$c['id']]),
        'proposals' => Db::all("SELECT * FROM proposals WHERE customer_id = ? AND status = 'sent' ORDER BY id DESC", [$c['id']])]);
}, ['auth' => true]);

$r->get('/portal/orders', function () use ($portalCustomer) {
    $c = $portalCustomer();
    View::page('portal/orders', ['title' => 'My orders', 'orders' => Db::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC', [$c['id']])]);
}, ['auth' => true]);
$r->get('/portal/orders/{num}', function ($p) use ($portalCustomer) {
    $c = $portalCustomer();
    $o = Db::one('SELECT * FROM orders WHERE number = ? AND customer_id = ?', [$p['num'], $c['id']]) ?? throw new HttpError(404, 'Order not found.');
    View::page('portal/order', ['title' => 'Order ' . $o['number'], 'o' => $o, 'items' => Orders::items((int)$o['id']), 'projects' => Db::all('SELECT * FROM projects WHERE order_id = ?', [$o['id']]),
        'payments' => Db::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$o['id']]), 'invoices' => Db::all('SELECT * FROM invoices WHERE order_id = ?', [$o['id']])]);
}, ['auth' => true]);

$r->get('/portal/projects', function () use ($portalCustomer) {
    View::page('portal/projects', ['title' => 'My projects', 'projects' => Projects::forCustomer((int)$portalCustomer()['id'])]);
}, ['auth' => true]);
$r->get('/portal/projects/{num}', function ($p) use ($ownProject) {
    $pr = $ownProject($p['num']);
    View::page('portal/project', ['title' => $pr['title'], 'p' => $pr, 'milestones' => Db::all('SELECT * FROM project_milestones WHERE project_id = ? ORDER BY sort', [$pr['id']]),
        'activity' => Db::all('SELECT * FROM project_activity WHERE project_id = ? AND visible_to_customer = 1 ORDER BY id DESC LIMIT 50', [$pr['id']]),
        'deliverables' => Db::all("SELECT * FROM deliverables WHERE project_id = ? AND status IN ('ready','delivered','approved') ORDER BY id DESC", [$pr['id']]),
        'docs' => Db::all('SELECT * FROM documents WHERE project_id = ? ORDER BY id DESC', [$pr['id']]),
        'reviewed' => (bool)Db::val('SELECT 1 FROM reviews WHERE project_id = ?', [$pr['id']])]);
}, ['auth' => true]);
$r->get('/portal/projects/{num}/requirements', function ($p) use ($ownProject) {
    $pr = $ownProject($p['num']);
    if (App\Filings::typeFor($pr['service_slug'])) View::redirect('/portal/projects/' . $pr['number'] . '/filing');
    $cat = Catalog::find($pr['service_slug'], false)['category'] ?? 'addon';
    View::page('portal/requirements', ['title' => 'Project requirements', 'p' => $pr, 'questions' => Projects::QUESTIONS[$cat] ?? Projects::QUESTIONS['addon'], 'answers' => jdec($pr['requirements'])]);
}, ['auth' => true]);
$r->post('/portal/projects/{num}/requirements', function ($p) use ($ownProject, $portalCustomer) {
    $pr = $ownProject($p['num']);
    Projects::submitRequirements($pr, (array)Request::input('a', []));
    if (!empty($_FILES['file']['name'])) Documents::store($_FILES['file'], (int)$portalCustomer()['id'], (int)$pr['id'], 'customer', 'requirements');
    Session::flash('success', 'Thank you — your project is now being planned.');
    View::redirect('/portal/projects/' . $pr['number']);
}, ['auth' => true]);
$r->post('/portal/projects/{num}/deliverable', function ($p) use ($ownProject) {
    $pr = $ownProject($p['num']);
    $d = Db::one('SELECT * FROM deliverables WHERE id = ? AND project_id = ?', [(int)Request::input('id'), $pr['id']]) ?? throw new HttpError(404, 'Not found.');
    $approve = Request::input('decision') === 'approve';
    Db::update('deliverables', ['status' => $approve ? 'approved' : 'revision', 'updated_at' => now()], 'id = ?', [$d['id']]);
    Projects::activity((int)$pr['id'], 'customer_review', ($approve ? 'You approved: ' : 'You requested changes to: ') . $d['title'], 'customer');
    if (!$approve) {
        Orchestrator::enqueue($d['produced_by'] ?: 'project_manager', 'revision', 'Customer revision: ' . $d['title'], ['deliverable_id' => $d['id'], 'customer_feedback' => Request::str('feedback', 4000)], ['project_id' => $pr['id'], 'customer_id' => $pr['customer_id'], 'created_by' => 'customer']);
        Db::update('projects', ['status' => 'revision', 'updated_at' => now()], 'id = ?', [$pr['id']]);
    } else {
        $ms = Db::one("SELECT id FROM project_milestones WHERE project_id = ? AND agent_slug = 'customer' AND status = 'pending' ORDER BY sort LIMIT 1", [$pr['id']]);
        if ($ms) Projects::completeMilestone((int)$ms['id'], 'customer');
    }
    View::redirect('/portal/projects/' . $pr['number']);
}, ['auth' => true]);
$r->post('/portal/projects/{num}/feedback', function ($p) use ($ownProject) {
    $pr = $ownProject($p['num']);
    if (Db::val('SELECT 1 FROM reviews WHERE project_id = ?', [$pr['id']])) throw new UserError('Thanks — you already left feedback.');
    $rating = max(1, min(5, (int)Request::input('rating')));
    Db::insert('reviews', ['customer_id' => $pr['customer_id'], 'project_id' => $pr['id'], 'rating' => $rating, 'body' => Request::str('body', 3000), 'consent_publish' => Request::input('consent_publish') ? 1 : 0,
        'verified' => 1, 'published' => 0, 'display_name' => Request::str('display_name', 120) ?: null, 'created_at' => now()]);
    if ($rating <= 3) Tickets::create((int)$pr['customer_id'], 'Feedback follow-up: ' . $pr['title'], "Customer rated {$rating}/5.\n" . Request::str('body', 3000), 'general', 'customer', 'Customer');
    Session::flash('success', 'Thank you for your feedback!');
    View::redirect('/portal/projects/' . $pr['number']);
}, ['auth' => true]);

$r->get('/portal/projects/{num}/filing', function ($p) use ($ownProject) {
    $pr = $ownProject($p['num']);
    $f = App\Filings::openForProject($pr) ?? throw new HttpError(404, 'This project has no government filing.');
    View::page('portal/filing', ['title' => App\Filings::TYPE_LABELS[$f['filing_type']], 'p' => $pr, 'f' => $f, 'd' => jdec($f['data']), 'v' => jdec($f['validation']),
        'events' => Db::all('SELECT * FROM filing_events WHERE filing_id = ? ORDER BY id DESC LIMIT 12', [$f['id']])]);
}, ['auth' => true]);
$r->post('/portal/projects/{num}/filing', function ($p) use ($ownProject) {
    $pr = $ownProject($p['num']);
    $f = App\Filings::openForProject($pr) ?? throw new HttpError(404, 'No filing.');
    $f = App\Filings::saveDetails($f, $_POST, 'customer');
    Session::flash($f['status'] === 'awaiting_authorization' ? 'success' : 'error', $f['status'] === 'awaiting_authorization' ? 'Details checked. We’re starting on your filing now.' : 'A few details need attention — see the highlighted items.');
    View::redirect('/portal/projects/' . $pr['number'] . '/filing#' . ($f['status'] === 'awaiting_authorization' ? 'sign' : 'issues'));
}, ['auth' => true]);
$r->post('/portal/projects/{num}/filing/authorize', function ($p) use ($ownProject) {
    $pr = $ownProject($p['num']);
    $f = App\Filings::openForProject($pr) ?? throw new HttpError(404, 'No filing.');
    App\Filings::authorize($f, Request::str('signer', 190), Request::str('capacity', 60), (bool)Request::input('attest'));
    Session::flash('success', 'Thank you — your filing is authorized. Our Filing Agent will review and submit it to the registry.');
    View::redirect('/portal/projects/' . $pr['number']);
}, ['auth' => true]);

$r->get('/portal/documents', function () use ($portalCustomer) {
    $c = $portalCustomer();
    View::page('portal/documents', ['title' => 'Documents', 'docs' => Db::all('SELECT d.*, p.number AS project_number FROM documents d LEFT JOIN projects p ON p.id = d.project_id WHERE d.customer_id = ? ORDER BY d.id DESC', [$c['id']]),
        'projects' => Projects::forCustomer((int)$c['id'])]);
}, ['auth' => true]);
$r->post('/portal/documents/upload', function () use ($portalCustomer) {
    $c = $portalCustomer();
    $pid = (int)Request::input('project_id') ?: null;
    if ($pid && !Db::val('SELECT 1 FROM projects WHERE id = ? AND customer_id = ?', [$pid, $c['id']])) throw new HttpError(403, 'Not your project.');
    if (!RateLimit::hit('upload:' . $c['id'], 30, 3600)) throw new UserError('Upload limit reached for now.');
    $d = Documents::store($_FILES['file'] ?? [], (int)$c['id'], $pid, 'customer');
    if ($pid) Projects::activity($pid, 'file', 'File uploaded: ' . $d['filename'], 'customer');
    Session::flash('success', 'File uploaded.');
    View::redirect('/portal/documents');
}, ['auth' => true]);
$r->get('/documents/{id}', function ($p) {
    Router::guardLogin();
    $d = Db::one('SELECT * FROM documents WHERE id = ?', [(int)$p['id']]);
    if (!$d || !Documents::canAccess($d, Auth::user())) { Audit::security('document.denied', (string)$p['id']); throw new HttpError(404, 'File not found.'); }
    $bytes = Documents::content($d);
    if ($bytes === null) throw new HttpError(404, 'File missing.');
    Audit::log('document.download', 'document', $d['id']);
    header('Content-Type: ' . $d['mime']);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $d['filename']) . '"');
    header('Content-Length: ' . strlen($bytes));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'");
    echo $bytes;
});

/* One-time installer for hosts without a terminal (Vercel). Enabled only while SETUP_TOKEN is set; remove it afterwards. */
$r->post('/setup', function () {
    $token = (string)Env::get('SETUP_TOKEN', '');
    if (strlen($token) < 24 || !hash_equals($token, (string)($_SERVER['HTTP_X_SETUP_TOKEN'] ?? ''))) throw new HttpError(404, 'Page not found.');
    $applied = Db::migrate();
    App\Seed::run();
    $out = ['ok' => true, 'migrations' => $applied];
    $email = strtolower((string)Env::get('OWNER_EMAIL', ''));
    if ($email && !Db::val("SELECT 1 FROM users WHERE role = 'owner'")) {
        $pw = bin2hex(random_bytes(8)) . 'A1';
        Db::insert('users', ['email' => $email, 'name' => 'Owner', 'password_hash' => Auth::hash($pw), 'role' => 'owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Settings::set('owner.notify_email', $email);
        $out['owner'] = $email; $out['temporary_password'] = $pw; $out['next'] = 'Sign in at /login, change this password, enable MFA, then delete SETUP_TOKEN in Vercel.';
    }
    Audit::log('system.setup', 'system', null, ['migrations' => $applied]);
    View::json($out);
}, ['nocsrf' => true]);

/* Vercel Cron (vercel.json → /cron). Vercel sends "Authorization: Bearer $CRON_SECRET". */
$r->get('/cron', function () {
    $secret = (string)Env::get('CRON_SECRET', '');
    if ($secret === '' || !hash_equals('Bearer ' . $secret, (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''))) throw new HttpError(401, 'Unauthorized');
    $stats = App\Cron::run(50);
    if (gmdate('H') === '06') $stats['note'] = 'Use your database provider\'s automated backups on Vercel.';
    View::json(['ok' => true] + $stats);
});

$r->get('/portal/support', function () use ($portalCustomer) {
    $c = $portalCustomer();
    View::page('portal/support', ['title' => 'Support', 'tickets' => Db::all('SELECT * FROM tickets WHERE customer_id = ? ORDER BY updated_at DESC', [$c['id']])]);
}, ['auth' => true]);
$r->post('/portal/support', function () use ($portalCustomer) {
    $c = $portalCustomer();
    if (!RateLimit::hit('ticket:' . $c['id'], 10, 3600)) throw new UserError('Too many requests — please wait.');
    $t = Tickets::create((int)$c['id'], Request::str('subject', 190) ?: 'Support request', Request::str('body', 8000), Request::str('category', 30), 'customer', $c['name'] ?: 'Customer');
    View::redirect('/portal/support/' . $t['number']);
}, ['auth' => true]);
$r->get('/portal/support/{num}', function ($p) use ($portalCustomer) {
    $c = $portalCustomer();
    $t = Db::one('SELECT * FROM tickets WHERE number = ? AND customer_id = ?', [$p['num'], $c['id']]) ?? throw new HttpError(404, 'Ticket not found.');
    View::page('portal/ticket', ['title' => $t['subject'], 't' => $t, 'messages' => Db::all('SELECT * FROM ticket_messages WHERE ticket_id = ? AND internal = 0 ORDER BY id', [$t['id']])]);
}, ['auth' => true]);
$r->post('/portal/support/{num}', function ($p) use ($portalCustomer) {
    $c = $portalCustomer();
    $t = Db::one('SELECT * FROM tickets WHERE number = ? AND customer_id = ?', [$p['num'], $c['id']]) ?? throw new HttpError(404, 'Ticket not found.');
    if (Request::input('resolve')) { Db::update('tickets', ['status' => 'resolved', 'resolved_at' => now(), 'csat' => (int)Request::input('csat') ?: null, 'updated_at' => now()], 'id = ?', [$t['id']]); }
    else { Tickets::reply((int)$t['id'], Request::str('body', 8000), 'customer', $c['name'] ?: 'Customer'); Events::emit('ticket.customer_reply', ['ticket_id' => $t['id']]); }
    View::redirect('/portal/support/' . $t['number']);
}, ['auth' => true]);

$r->get('/portal/assistant', fn() => View::page('portal/assistant', ['title' => 'AI workspace']), ['auth' => true]);

$r->get('/portal/account', function () use ($portalCustomer) {
    $c = $portalCustomer();
    View::page('portal/account', ['title' => 'Account & privacy', 'c' => $c, 'u' => Auth::user(), 'memories' => Memory::forCustomer((int)$c['id'])]);
}, ['auth' => true]);
$r->post('/portal/account', function () use ($portalCustomer) {
    $c = $portalCustomer(); $u = Auth::user();
    $act = Request::str('action', 30);
    if ($act === 'profile') {
        Db::update('customers', ['name' => Request::str('name', 190), 'phone' => Request::str('phone', 40), 'business_name' => Request::str('business_name', 190), 'location' => Request::str('location', 190),
            'consent_marketing' => Request::input('consent_marketing') ? 1 : 0, 'consent_at' => Request::input('consent_marketing') ? now() : $c['consent_at'], 'memory_opt_in' => Request::input('memory_opt_in') ? 1 : 0, 'updated_at' => now()], 'id = ?', [$c['id']]);
        Db::update('users', ['name' => Request::str('name', 190), 'updated_at' => now()], 'id = ?', [$u['id']]);
        if (!Request::input('memory_opt_in')) Memory::forgetCustomer((int)$c['id']);
        Audit::log('customer.profile_update', 'customer', $c['id']);
    } elseif ($act === 'password') {
        if (!password_verify((string)Request::input('current', ''), $u['password_hash'])) throw new UserError('Current password is incorrect.');
        if ($pp = Auth::passwordProblem((string)Request::input('new', ''))) throw new UserError($pp);
        Db::update('users', ['password_hash' => Auth::hash((string)Request::input('new')), 'updated_at' => now()], 'id = ?', [$u['id']]);
        Session::regenerate();
        Audit::log('user.password_change', 'user', $u['id']);
    } elseif ($act === 'forget') {
        Memory::forgetCustomer((int)$c['id'], (int)Request::input('memory_id') ?: null);
    }
    Session::flash('success', 'Saved.');
    View::redirect('/portal/account');
}, ['auth' => true]);
$r->get('/portal/account/export', function () use ($portalCustomer) {
    $c = $portalCustomer();
    Audit::log('customer.export', 'customer', $c['id']);
    header('Content-Disposition: attachment; filename="my-818-data.json"');
    View::json(['customer' => array_diff_key($c, ['notes' => 1]), 'orders' => Db::all('SELECT number, status, total_cents, created_at FROM orders WHERE customer_id = ?', [$c['id']]),
        'projects' => Db::all('SELECT number, title, status, requirements, created_at FROM projects WHERE customer_id = ?', [$c['id']]), 'memories' => Memory::forCustomer((int)$c['id']),
        'tickets' => Db::all('SELECT number, subject, status, created_at FROM tickets WHERE customer_id = ?', [$c['id']]),
        'conversations' => Db::all('SELECT m.role, m.content, m.created_at FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE c.customer_id = ? ORDER BY m.id', [$c['id']])]);
}, ['auth' => true]);

/* ─────────────────────────── Admin ─────────────────────────── */

require APP_ROOT . '/routes_admin.php';

return $r;
