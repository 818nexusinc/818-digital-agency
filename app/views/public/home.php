<?php
use App\{Catalog, Filings, Csrf};
include_once APP_ROOT . '/views/partials/icons.php';
$fp = function (string $slug) { $s = Catalog::find($slug); return $s ? Catalog::fromPrice($s) : null; };
$regFrom = min(array_filter([$fp('sole_prop'), $fp('partnership'), $fp('corp_numbered')]));
$svc6 = [
  ['/register', 'building', 'blue', 'From ' . money($regFrom), 'Register your business', 'Corporation, partnership or sole proprietorship, in any province.', 'cafe-owner', 'Start now'],
  ['/manage-business', 'calendar', 'violet', 'From ' . money($fp('bm_documents')), 'Manage your business', 'Annual returns, changes and documents.', 'docs-typing', 'Manage'],
  ['/domain', 'globe', 'teal', 'From ' . money($fp('domain_register')) . '/yr', 'Domain name', '.ca, .com and more.', 'laptop', 'Search'],
  ['/email', 'mail', 'cyan', 'From ' . money($fp('email_starter')) . '/mo', 'Business email', 'Mailboxes on your domain.', 'notes', 'Set up'],
  ['/website', 'layout', 'green', 'From ' . money($fp('website_launch')) . '/mo', 'Website design', 'Built, hosted and maintained.', 'developer', 'Choose plan'],
  ['/bundle', 'stack', 'amber', 'Save up to 15%', 'Bundle & save', 'Everything together, one dashboard.', 'partners', 'Build a bundle'],
];
$steps = [['edit', 'violet', 'Tell us about your business', 'A short guided form that checks details as you type.'],
  ['search', 'teal', 'We review and prepare', 'Our filing agent checks it against your registry’s rules.'],
  ['pen', 'green', 'You sign, we file', 'Nothing is submitted until you approve it.'],
  ['certificate', 'blue', 'You’re in business', 'Documents and next steps land in your portal.']];
$mark = ['y' => '✓ ', 'x' => '✗ ', 'w' => '▲ '];
?>
<section class="hx"><div class="wrap hx-grid">
  <div class="hx-text">
    <span class="badge-pill" data-rv><?= e(t('One-stop Canadian business platform')) ?></span>
    <h1 data-rv><?= e(t('Start your business.')) ?><br><span><?= e(t('We handle the rest.')) ?></span></h1>
    <p class="lead" data-rv><?= e(t('Registration, domain, email and website. One place, one checkout, every province.')) ?></p>
    <div class="hero-actions" data-rv><a class="btn btn-primary btn-lg" href="/register"><?= e(t('Get started')) ?></a><a class="btn btn-ghost btn-lg" href="/bundle"><?= e(t('Bundle & save')) ?></a></div>
    <ul class="hx-proof" data-rv><li><?= nx_icon('check') ?><?= e(t('Government fees included')) ?></li><li><?= nx_icon('check') ?><?= e(t('You sign before we file')) ?></li><li><?= nx_icon('check') ?><?= e(t('Canadian team')) ?></li></ul>
  </div>
  <div class="hx-media" data-rv>
    <div class="sh-frame"><img src="/media/owners-open.webp" alt="" width="1600" height="900" fetchpriority="high" style="object-position:55% 50%"></div>
    <div class="hx-track" aria-hidden="true">
      <b><?= e(t('Your filing')) ?></b>
      <ol data-track><li class="done"><?= e(t('Details submitted')) ?></li><li class="done"><?= e(t('Checks passed')) ?></li><li class="now"><?= e(t('Signed by you')) ?></li><li><?= e(t('Filed with the registry')) ?></li></ol>
    </div>
    <div class="chip-f chip-1"><span class="mc-ic tone-teal"><?= nx_icon('shield') ?></span><span><b><?= e(t('Filed with the registry')) ?></b><small><?= count(Filings::JURISDICTIONS) ?> <?= e(t('registries supported')) ?></small></span></div>
  </div>
</div></section>
<section class="stats" aria-label="At a glance">
  <div><span class="mc-ic tone-blue"><?= nx_icon('building') ?></span><b data-count="<?= count(Filings::JURISDICTIONS) ?>"><?= count(Filings::JURISDICTIONS) ?></b><span><?= e(t('Registries we file with')) ?></span></div>
  <div><span class="mc-ic tone-teal"><?= nx_icon('cash') ?></span><b><?= e(t('From')) ?> <?= e(money($regFrom)) ?></b><span><?= e(t('Government fees included')) ?></span></div>
  <div><span class="mc-ic tone-violet"><?= nx_icon('pen') ?></span><b><?= e(t('1 signature')) ?></b><span><?= e(t('Before anything is filed')) ?></span></div>
  <div><span class="mc-ic tone-amber"><?= nx_icon('globe') ?></span><b><?= e(t('10 + federal')) ?></b><span><?= e(t('Provinces served')) ?></span></div>
</section>
<div class="prov-strip" aria-label="Provinces"><ul><?php $pv = ['FED' => 'Federal (Canada-wide)'] + Catalog::PROVINCES; for ($k = 0; $k < 2; $k++) foreach ($pv as $c => $n): ?><li<?= $k ? ' aria-hidden="true"' : '' ?>><?= e(t($n)) ?><small>✓ <?= e(t('Available now')) ?></small></li><?php endforeach; ?></ul></div>

<section class="sec"><div class="wrap">
  <div class="sec-head split-head" data-rv><div><span class="badge-pill"><?= e(t('Our services')) ?></span><h2><?= e(t('Six services. One dashboard.')) ?></h2></div><p><?= e(t('Start with one. Add the rest when you are ready.')) ?></p></div>
  <div class="bento">
  <?php foreach ($svc6 as $i => [$href, $ic, $tone, $price, $t, $d, $img, $go]): ?>
    <a class="bc bc-<?= $i ?>" href="<?= e($href) ?>" data-rv>
      <span class="bc-img"><img src="/media/<?= e($img) ?>.webp" alt="" loading="lazy" width="1400" height="934"></span>
      <span class="bc-body"><span class="bc-top"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><b class="bc-pr"><?= e(str_starts_with($price, 'From ') ? t('From') . substr($price, 4) : t($price)) ?></b></span>
      <h3><?= e(t($t)) ?></h3><p><?= e(t($d)) ?></p><span class="s6-go"><?= e(t($go)) ?> <?= nx_icon('arrow') ?></span></span>
    </a>
  <?php endforeach; ?>
  </div>
</div></section>

<section class="sec alt"><div class="wrap how">
  <div class="how-l">
    <div class="sec-head" data-rv><span class="badge-pill"><?= e(t('How it works')) ?></span><h2><?= e(t('Four steps. No paperwork.')) ?></h2></div>
    <ol class="how-steps">
    <?php foreach ($steps as $i => [$ic, $tone, $t, $d]): ?>
      <li data-rv><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><div><h3><?= e(t($t)) ?></h3><p><?= e(t($d)) ?></p></div></li>
    <?php endforeach; ?>
    </ol>
    <div class="hero-actions" data-rv><a class="btn btn-primary btn-lg" href="/register"><?= e(t('Start your business')) ?></a></div>
  </div>
  <div class="how-r" data-rv><div class="sh-frame tall"><video autoplay muted loop playsinline preload="none" poster="/media/desk-poster.webp" aria-hidden="true" data-autoplay><source src="/media/desk.mp4" type="video/mp4"></video></div>
    <div class="chip-f chip-0"><span class="mc-ic tone-green"><?= nx_icon('pen') ?></span><span><b><?= e(t('Signed by you')) ?></b><small><?= e(t('Then we file')) ?></small></span></div></div>
</div></section>

<section class="band"><video autoplay muted loop playsinline preload="none" poster="/media/coast-poster.webp" aria-hidden="true" data-autoplay><source src="/media/coast.mp4" type="video/mp4"></video>
  <div class="wrap band-in" data-rv><h2><?= e(t('Proudly built in Atlantic Canada.')) ?></h2><p><?= e(t('Serving entrepreneurs from coast to coast.')) ?></p><a class="btn btn-light btn-lg" href="/about"><?= e(t('Our story')) ?></a></div>
</section>

<section class="trust-sec" aria-label="Why customers trust us"><div class="wrap trust">
  <?php foreach ([['users', 'teal', 'Canadian privacy law', 'Handled under PIPEDA'], ['certificate', 'blue', 'Encrypted connection', 'TLS on every page'], ['cash', 'violet', 'Payments by Stripe', 'Card details never stored here'], ['edit', 'green', 'You sign first', 'Nothing filed without you'], ['building', 'amber', 'Atlantic Canada team', 'Moncton, New Brunswick'], ['search', 'cyan', 'Clear prices', 'Government fees included']] as [$ic, $tone, $t, $s]): ?>
  <div><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span><b><?= e($t) ?></b><span><?= e($s) ?></span></span></div>
  <?php endforeach; ?>
</div></section>

<section class="sec"><div class="wrap">
  <div class="sec-head center"><span class="badge-pill">Why choose us</span><h2>The smarter way to register in Canada</h2><p>How 818 Digital Agency compares with the usual options.</p></div>
  <div class="cmp-wrap"><table class="cmp">
    <thead><tr><th scope="col"><span class="sr">Feature</span></th><th scope="col" class="us">818 Digital Agency<small>Recommended</small></th><th scope="col">Traditional lawyer</th><th scope="col">DIY government portal</th></tr></thead>
    <tbody>
      <?php foreach ([
        ['Business registration', ['y', 'Guided online forms'], ['y', 'Done for you'], ['w', 'You complete the forms']],
        ['NUANS name search', ['y', money($fp('nuans')) . ' flat'], ['w', 'Usually billed separately'], ['w', 'Order it yourself']],
        ['Government fees', ['y', 'Included in the price'], ['w', 'Added to the invoice'], ['y', 'Paid directly']],
        ['Domain, email & website', ['y', 'Same account'], ['x', 'Not offered'], ['x', 'Separate vendors']],
        ['Annual returns & changes', ['y', 'Filed for you, with reminders'], ['y', 'Usually extra fees'], ['w', 'Track it yourself']],
        ['Order tracking', ['y', 'Online portal'], ['w', 'By email or phone'], ['w', 'Registry account']],
        ['Legal advice', ['x', 'Not a law firm'], ['y', 'Yes'], ['x', 'No']],
        ['Typical cost', ['y', 'From ' . money($regFrom)], ['w', 'Typically higher (hourly)'], ['y', 'Government fee + your time']],
      ] as [$row, $us, $law, $diy]): ?>
      <tr><th scope="row"><?= e($row) ?></th><td class="us <?= $us[0] ?>"><?= $mark[$us[0]] . e($us[1]) ?></td><td class="<?= $law[0] ?>"><?= $mark[$law[0]] . e($law[1]) ?></td><td class="<?= $diy[0] ?>"><?= $mark[$diy[0]] . e($diy[1]) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
</div></section>

<?php if ($reviews): ?><section class="sec alt"><div class="wrap"><div class="sec-head center"><h2>From verified customers</h2></div><div class="grid g3">
<?php foreach ($reviews as $rv): ?><figure class="card" style="margin:0"><div aria-label="<?= (int)$rv['rating'] ?> out of 5"><?= str_repeat('★', (int)$rv['rating']) ?></div><blockquote style="margin:10px 0"><?= e($rv['body']) ?></blockquote><figcaption class="muted small"><?= e($rv['display_name'] ?: 'Verified customer') ?></figcaption></figure><?php endforeach; ?></div></div></section><?php endif; ?>

<section class="news" id="news"><div class="wrap">
  <span class="mc-ic tone-blue" style="margin:0 auto 14px"><?= nx_icon('mail') ?></span>
  <h2>Stay updated on new services</h2><p>Get early access and launch offers when new services and provinces go live.</p>
  <?php if (!empty($_GET['subscribed'])): ?><p class="alert alert-success" style="max-width:460px;margin:0 auto">Thanks! You're on the list. You can unsubscribe anytime from any email.</p>
  <?php else: ?><form method="post" action="/subscribe"><?= Csrf::field() ?><label class="sr" for="news-email">Email address</label><input id="news-email" type="email" name="email" required placeholder="you@yourbusiness.ca" autocomplete="email"><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn btn-primary">Notify me</button></form>
  <small>By subscribing you agree to receive occasional emails from 818 Nexus Inc. No spam. Unsubscribe anytime.</small><?php endif; ?>
</div></section>
