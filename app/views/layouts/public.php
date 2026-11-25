<?php
use App\{Cart, Settings, Seo, Integrations};
$desc = $description ?? Settings::get('seo.default_description');
$fullTitle = ($title ?? '') ? $title . ' | ' . Settings::get('company.name') : Settings::get('company.name');
$canon = url($path ?? '/');
$cartCount = count(Cart::lines());
?><!doctype html>
<html lang="<?= lang() === 'fr' ? 'fr-CA' : 'en-CA' ?>"<?= lang() === 'fr' ? " data-i18n='" . e(js_dict()) . "'" : '' ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e(str_limit((string)$desc, 160)) ?>">
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?= e($fullTitle) ?>"><meta property="og:description" content="<?= e(str_limit((string)$desc, 200)) ?>"><meta property="og:url" content="<?= e($canon) ?>"><meta property="og:site_name" content="<?= e(Settings::get('company.name')) ?>">
<meta name="twitter:card" content="summary"><meta name="theme-color" content="#ffffff">
<?php if ($v = Integrations::secret('search_console', 'verification') ?: Settings::get('seo.google_verification')): ?><meta name="google-site-verification" content="<?= e($v) ?>"><?php endif; ?>
<?php if ($v = Settings::get('seo.bing_verification')): ?><meta name="msvalidate.01" content="<?= e($v) ?>"><?php endif; ?>
<link rel="alternate" hreflang="en-CA" href="<?= e($canon) ?>"><link rel="alternate" hreflang="fr-CA" href="<?= e($canon . (str_contains($canon, '?') ? '&' : '?') . 'lang=fr') ?>"><link rel="alternate" hreflang="x-default" href="<?= e($canon) ?>">
<meta name="robots" content="<?= !empty($noindex) ? 'noindex,nofollow' : 'index,follow,max-snippet:-1,max-image-preview:large' ?>">
<meta name="csrf" content="<?= e($csrf ?? '') ?>"><meta name="nx-ft" content="<?= e(App\Spam::token()) ?>"><?php if ($tsk = App\Integrations::secret('turnstile', 'site_key')): ?><meta name="nx-ts" content="<?= e($tsk) ?>"><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" nonce="<?= e($nonce ?? '') ?>" async defer></script><?php endif; ?>
<link rel="alternate" type="text/plain" href="/llms.txt" title="LLM guide"><link rel="alternate" type="application/rss+xml" href="/blog/feed.xml" title="818 Digital Agency guides"><link rel="alternate" type="application/json" href="/services.json" title="Service catalog">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">

<link rel="preload" href="/assets/fonts/inter-var.woff2" as="font" type="font/woff2" crossorigin><link rel="stylesheet" href="/assets/css/app.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/site.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/v8.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/v9.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/v10.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/v11.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/v12.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/v13.css?v=<?= APP_VERSION ?>">
<?php foreach (array_merge($jsonld ?? [], App\View::$shared['ld'] ?? []) as $ld) if ($ld) echo Seo::jsonLd($ld); ?>
<meta property="og:image" content="<?= e(url('/media/opening-day-poster.webp')) ?>">
</head>
<body class="site" data-pillar="<?= e(pillar($path ?? '/')) ?>" style="<?= pillar_css(pillar($path ?? '/')) ?>">
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap hd">
    <a class="brand" href="/" aria-label="818 Digital Agency home"><?php include APP_ROOT . '/views/partials/logo.php'; ?><span class="wm<?= App\SiteText::logoVersion() && App\Settings::get('site.logo_wordmark', '1') !== '1' ? ' wm-off' : '' ?>"><b>818 Digital Agency</b><span>by 818 Nexus Inc.</span></span></a>
    <div class="navbar" id="site-navbar"><?php include APP_ROOT . '/views/partials/megamenu.php'; ?>
      <div class="drawer-foot"><?php if (!empty($user)): ?><a class="btn btn-primary" href="<?= App\Auth::isStaff($user) ? '/admin' : '/portal' ?>"><?= App\Auth::isStaff($user) ? 'Control Tower' : 'My portal' ?></a><?php else: ?><a class="btn btn-ghost" href="/login">Log in</a><a class="btn btn-primary" href="/signup">Create account</a><?php endif; ?></div>
    </div>
    <div class="nav-cta">
      <a class="lang-sw" href="?lang=<?= lang() === 'fr' ? 'en' : 'fr' ?>" hreflang="<?= lang() === 'fr' ? 'en' : 'fr' ?>" aria-label="<?= lang() === 'fr' ? 'English' : 'Français' ?>"><span<?= lang() === 'en' ? ' class="on"' : '' ?>>EN</span><span<?= lang() === 'fr' ? ' class="on"' : '' ?>>FR</span></a>
      <a class="cart-pill" href="/cart" aria-label="Cart (<?= $cartCount ?> items)"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg><?php if ($cartCount): ?><span class="n"><?= $cartCount ?></span><?php endif; ?></a>
      <?php if (!empty($user)): ?><a class="btn btn-primary btn-sm" href="<?= App\Auth::isStaff($user) ? '/admin' : '/portal' ?>"><?= App\Auth::isStaff($user) ? 'Control Tower' : 'My portal' ?></a>
      <?php else: ?><a class="btn btn-ghost btn-sm" href="/login">Log in</a><a class="btn btn-primary btn-sm" href="/signup">Create account</a><?php endif; ?>
      <button class="menu-btn" data-toggle="site-navbar" aria-expanded="false" aria-controls="site-navbar" aria-label="Menu"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg><span>Menu</span></button>
    </div>
  </div>
</header>
<main id="main">
<?php $fl = App\Session::takeFlash(); if ($fl): ?><div class="flash-stack" aria-live="polite"><?php foreach ($fl as [$t, $m]): ?><div class="flash flash-<?= $t === 'error' ? 'error' : 'ok' ?>" role="<?= $t === 'error' ? 'alert' : 'status' ?>" data-flash><span><?= e($m) ?></span><button type="button" aria-label="Dismiss" data-flash-x>×</button></div><?php endforeach; ?></div><?php endif; ?>
<?= $content ?>
<?php $pl = pillar($path ?? '/'); if ($pl !== 'brand'): $cta = ['register' => ['Your business, official this week.', 'Start registration', '/register'], 'manage' => ['Stay in good standing. All year.', 'See management services', '/manage-business'],
  'web' => ['Get found. Get customers.', 'Choose a website plan', '/website'], 'bundle' => ['Do it all. Save up to 15%.', 'Build your bundle', '/bundle'], 'opps' => ['Most programs need a registered business.', 'Register first', '/register']][$pl]; ?>
<section class="close-cta" style="<?= pillar_css($pl) ?>"><div class="wrap"><p class="cc-k"><?= e(t(PILLARS[$pl]['name'])) ?></p><h2><?= e(t($cta[0])) ?></h2>
  <div class="cc-act"><a class="btn btn-light btn-lg" href="<?= ($path ?? '') === $cta[2] ? '#main' : e($cta[2]) ?>"><?= e(t($cta[1])) ?></a><a class="btn btn-outline-light btn-lg" href="/contact"><?= e(t('Talk to a person')) ?></a></div></div></section>
<?php endif; ?>
<?php include APP_ROOT . '/views/partials/newsletter.php'; ?>
</main>
<footer class="site-footer"><div class="wrap">
  <div class="foot-grid">
    <div><a class="brand" href="/" style="color:#fff"><?php if (App\SiteText::logoVersion()) { $logoBg = 'dark'; include APP_ROOT . '/views/partials/logo.php'; } ?><span class="wm<?= App\SiteText::logoVersion() && App\Settings::get('site.logo_wordmark', '1') !== '1' ? ' wm-off' : '' ?>"><b style="color:#fff">818 Digital Agency</b><span style="color:#97a6c4">by 818 Nexus Inc.</span></span></a>
      <p style="margin-top:14px;max-width:34ch">Business registration, domains, email, websites and custom software for Canadian entrepreneurs — guided by AI, accountable to people.</p>
      <p><?= e(Settings::get('company.legal')) ?> · <?= e(Settings::get('company.city')) ?><br><a href="mailto:<?= e(Settings::get('company.email')) ?>"><?= e(Settings::get('company.email')) ?></a></p>
      <?php if ($soc = App\Social::profiles()): ?><ul class="foot-social" aria-label="Follow us"><?php foreach ($soc as $sn): ?><li><a href="<?= e($sn['url']) ?>" rel="noopener me" target="_blank"><?= e($sn['label']) ?></a></li><?php endforeach; ?></ul><?php endif; ?></div>
    <div><h4>Services</h4><ul><?php foreach (App\Catalog::CATEGORIES as $k => $n): if ($k === 'addon') continue; ?><li><a href="/services/category/<?= $k ?>"><?= e($n) ?></a></li><?php endforeach; ?></ul></div>
    <div><h4>Company</h4><ul><li><a href="/about">About</a></li><li><a href="/blog">Blog</a></li><li><a href="/funding">Funding opportunities</a></li><li><a href="/faq">FAQ</a></li><li><a href="/contact">Contact</a></li></ul></div>
    <div><h4>Trust</h4><ul><li><a href="/legal/privacy">Privacy</a></li><li><a href="/legal/terms">Terms</a></li><li><a href="/legal/refunds">Refunds</a></li><li><a href="/legal/cookies">Cookies</a></li><li><a href="/legal/ai">How we use AI</a></li><li><a href="/llms.txt">AI-readable guide</a></li></ul></div>
  </div>
  <div class="foot-bottom"><span>© <?= gmdate('Y') ?> <?= e(Settings::get('company.legal')) ?>. All prices in CAD.</span><span>Not a law firm · government fees noted per service</span></div>
</div></footer>
<?php include APP_ROOT . '/views/partials/chat.php'; ?>
<div class="cookie" id="cookie" hidden><b>Cookies</b><p class="small" style="margin:6px 0 10px">We use one essential cookie to keep you signed in and remember your cart. No advertising trackers. <a href="/legal/cookies">Details</a></p><button class="btn btn-dark btn-sm" data-consent="essential">OK</button></div>
<script src="/assets/js/app.js?v=<?= APP_VERSION ?>" nonce="<?= e($nonce ?? '') ?>" defer></script><script src="/assets/js/wizard.js?v=<?= APP_VERSION ?>" nonce="<?= e($nonce ?? '') ?>" defer></script>
</body>
</html>
