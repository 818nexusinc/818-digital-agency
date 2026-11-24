<?php
/* Shared console shell for the Control Tower and the client portal.
   Expects: $area ('admin'|'portal'), $groups [group => [href => label | [label, count]]], $areaName, $home, $title, $path, $user, $content */
include_once APP_ROOT . '/views/partials/icons.php';
$ico = function (string $h): string {
    foreach (['admin/update' => 'stack', 'automations' => 'refresh', 'admin/spam' => 'shield', 'admin/search' => 'globe', 'guide' => 'help', 'security' => 'shield', 'drafts' => 'text', 'blog' => 'edit', 'simulator' => 'bot', 'demo' => 'stack', 'approvals' => 'inbox', 'projects' => 'folder', 'filings' => 'certificate', 'tickets' => 'help', 'support' => 'help', 'leads' => 'search', 'customers' => 'users',
        'proposals' => 'text', 'orders' => 'cart', 'agents' => 'bot', 'tasks' => 'check', 'workflows' => 'network', 'kb' => 'stack', 'content' => 'edit', 'growth' => 'chart',
        'finance' => 'cash', 'catalog' => 'layout', 'integrations' => 'bolt', 'settings' => 'cog', 'audit' => 'shield', 'system' => 'refresh', 'classic' => 'clock',
        'documents' => 'certificate', 'assistant' => 'bot', 'account' => 'user', 'services' => 'layout', 'cart' => 'cart'] as $k => $i) if (str_contains($h, $k)) return $i;
    return 'home';
};
$nm = trim((string)($user['name'] ?? ''));
$ini = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', $nm ?: 'U'), 0, 2))));
$crumb = '';
foreach ($groups as $g => $links) foreach ($links as $h => $l) if (($path ?? "") !== $home && (($path ?? '') === $h || ($h !== $home && str_starts_with((string)($path ?? ''), $h . '/')))) $crumb = $g;
?><!doctype html>
<html lang="<?= lang() === 'fr' ? 'fr-CA' : 'en-CA' ?>"<?= lang() === 'fr' ? " data-i18n='" . e(js_dict()) . "'" : '' ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(t($title ?? $areaName)) ?> · <?= e(t($areaName)) ?></title><meta name="robots" content="noindex">
<meta name="csrf" content="<?= e($csrf ?? '') ?>"><link rel="icon" href="/assets/img/favicon.svg"><link rel="preload" href="/fonts/hanken-grotesk-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/css/app.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/console5.css?v=<?= APP_VERSION ?>"><link rel="stylesheet" href="/assets/css/console7.css?v=<?= APP_VERSION ?>"></head>
<body class="con con-<?= e($area) ?>"<?= ($path ?? '') === '/portal/assistant' ? ' data-chat-inline' : '' ?>>
<a class="skip" href="#main"><?= e(t('Skip to content')) ?></a>
<div class="shell">
<aside class="side" id="side">
  <a class="c-brand" href="<?= e($home) ?>"><span class="c-logo"><?php include APP_ROOT . '/views/partials/logo.php'; ?></span><span class="c-bt"><b>818 Digital Agency</b><small><?= e(t($areaName)) ?></small></span></a>
  <label class="c-find"><?= nx_icon('search') ?><span class="sr"><?= e(t('Find a page')) ?></span><input type="search" placeholder="<?= e(t('Search pages')) ?>" data-navfind autocomplete="off"><kbd>/</kbd></label>
  <nav class="c-nav" aria-label="<?= e(t($areaName)) ?>">
  <?php $gc = ['Operate' => '#7c9cff', 'Sell' => '#f472b6', 'AI workforce' => '#2dd4bf', 'Grow' => '#fb923c', 'System' => '#a3adc2'];
  $urgent = ['/admin/approvals', '/portal/support'];
  foreach ($groups as $g => $links): $gid = is_int($g) ? 'g' . $g : strtolower(preg_replace('/[^a-z]+/i', '-', $g));
    $hasCur = false; foreach ($links as $h => $l) if (($path ?? '') === $h || ($h !== $home && str_starts_with((string)($path ?? ''), $h))) $hasCur = true; ?>
  <div class="c-grp" data-grp="<?= e($gid) ?>" style="--g:<?= e($gc[$g] ?? '#7c9cff') ?>"<?= $hasCur ? ' data-cur' : '' ?>>
    <?php if (!is_int($g)): ?><button type="button" class="c-gh" aria-expanded="true" aria-controls="gl-<?= e($gid) ?>"><span><?= e(t($g)) ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button><?php endif; ?>
    <div class="c-gl" id="gl-<?= e($gid) ?>">
    <?php foreach ($links as $h => $l): [$lbl, $n] = is_array($l) ? $l : [$l, 0]; $cur = ($path ?? '') === $h || ($h !== $home && str_starts_with((string)($path ?? ''), $h)); ?>
      <a href="<?= e($h) ?>"<?= $cur ? ' aria-current="page"' : '' ?>><span class="ni"><?= nx_icon($h === $home ? 'home' : $ico($h)) ?></span><span class="nl"><?= e(t($lbl)) ?></span><?php if ($n): ?><i class="count<?= in_array($h, $urgent, true) ? ' hot' : '' ?>"><?= (int)$n ?></i><?php endif; ?></a>
    <?php endforeach; ?>
    </div></div>
  <?php endforeach; ?>
  </nav>
  <div class="c-me">
    <span class="av"><?= e($ini) ?></span><span class="who"><b><?= e($nm) ?></b><small><?= e(t(ucfirst((string)($user['role'] ?? '')))) ?> · v<?= e(APP_VERSION) ?></small></span>
    <a class="c-ico" href="/" title="<?= e(t('View website')) ?>" aria-label="<?= e(t('View website')) ?>"><?= nx_icon('globe') ?></a>
    <form method="post" action="/logout"><?= App\Csrf::field() ?><button class="c-ico" title="<?= e(t('Sign out')) ?>" aria-label="<?= e(t('Sign out')) ?>"><?= nx_icon('out') ?></button></form>
  </div>
</aside>
<div class="c-main">
  <header class="c-top">
    <button class="c-ico c-menu" data-toggle="side" aria-label="<?= e(t('Menu')) ?>"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    <nav class="c-crumb" aria-label="Breadcrumb"><a href="<?= e($home) ?>"><?= e(t($areaName)) ?></a><?php if ($crumb && !is_int($crumb)): ?><span>/</span><span><?= e(t($crumb)) ?></span><?php endif; ?><?php if (($path ?? '') !== $home): ?><span>/</span><b><?= e(t($title ?? '')) ?></b><?php endif; ?></nav>
    <div class="c-actions">
      <a class="c-lang" href="?lang=<?= lang() === 'fr' ? 'en' : 'fr' ?>" hreflang="<?= lang() === 'fr' ? 'en' : 'fr' ?>"><?= lang() === 'fr' ? 'EN' : 'FR' ?></a>
      <a class="c-pill" href="/"><?= nx_icon('globe') ?><span><?= e(t('View website')) ?></span></a>
      <span class="av" title="<?= e($nm) ?>"><?= e($ini) ?></span>
    </div>
  </header>
  <main class="main" id="main"><?php include APP_ROOT . '/views/partials/flash.php'; ?><?= $content ?></main>
</div></div>
<?php if ($area === 'portal') include APP_ROOT . '/views/partials/chat.php'; ?>
<?php if (($path ?? '') === '/admin/settings'): ?><script src="/assets/js/qrcode.js?v=<?= APP_VERSION ?>" nonce="<?= e($nonce ?? '') ?>" defer></script><?php endif; ?><script src="/assets/js/app.js?v=<?= APP_VERSION ?>" nonce="<?= e($nonce ?? '') ?>" defer></script></body></html>
