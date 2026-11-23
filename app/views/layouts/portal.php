<?php $links = ['/portal' => 'Dashboard', '/portal/projects' => 'Projects', '/portal/orders' => 'Orders & billing', '/portal/documents' => 'Documents', '/portal/support' => 'Support', '/portal/assistant' => 'AI workspace', '/portal/account' => 'Account & privacy']; ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title ?? 'Portal') ?> · 818 Portal</title><meta name="robots" content="noindex">
<meta name="csrf" content="<?= e($csrf ?? '') ?>"><link rel="icon" href="/assets/img/favicon.svg"><link rel="stylesheet" href="/assets/css/app.css?v=<?= APP_VERSION ?>"></head>
<body<?= ($path ?? '') === '/portal/assistant' ? ' data-chat-inline' : '' ?>>
<div class="mobile-top"><a class="brand" href="/portal"><?php include APP_ROOT . '/views/partials/logo.php'; ?></a><button class="menu-btn" data-toggle="side" aria-label="Menu">☰</button></div>
<div class="shell">
  <aside class="side" id="side"><a class="brand" href="/"><?php include APP_ROOT . '/views/partials/logo.php'; ?><span class="wm"><b>818</b><span>CLIENT PORTAL</span></span></a>
    <nav aria-label="Portal"><?php foreach ($links as $h => $l): ?><a href="<?= $h ?>"<?= ($path ?? '') === $h ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
      <h6>Shop</h6><a href="/services">Browse services</a><a href="/cart">Cart</a>
      <?php if (App\Auth::isStaff($user ?? null)): ?><h6>Team</h6><a href="/admin">Control Tower</a><?php endif; ?></nav>
    <form method="post" action="/logout" style="margin-top:24px"><?= App\Csrf::field() ?><button class="btn btn-ghost btn-sm" style="width:100%">Sign out</button></form></aside>
  <main class="main" id="main"><?php include APP_ROOT . '/views/partials/flash.php'; ?><?= $content ?></main>
</div>
<?php include APP_ROOT . '/views/partials/chat.php'; ?>
<script src="/assets/js/app.js?v=<?= APP_VERSION ?>" nonce="<?= e($nonce ?? '') ?>" defer></script></body></html>
