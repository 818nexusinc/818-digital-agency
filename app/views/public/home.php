<?php
use App\{Catalog, Filings, Csrf};
include_once APP_ROOT . '/views/partials/icons.php';
$fp = function (string $slug) { $s = Catalog::find($slug); return $s ? Catalog::fromPrice($s) : null; };
$regFrom = min(array_filter([$fp('sole_prop'), $fp('partnership'), $fp('corp_numbered')]));
$svc6 = [
  ['/register', 'building', 'blue', 'One-time', 'From ' . money($regFrom), 'Register Business', 'Corporation, partnership or sole proprietorship in any province', 'Guided forms and the government filing. We prepare the paperwork and file it once you have signed.', 'Start now'],
  ['/manage-business', 'calendar', 'violet', 'One-time', 'From ' . money($fp('bm_documents')), 'Manage Business', 'Annual returns, changes, documents', 'Keep your registered business in good standing: annual returns, corporate changes, document orders and name services.', 'Manage business'],
  ['/domain', 'globe', 'teal', 'Per year', 'From ' . money($fp('domain_register')) . '/yr', 'Domain Name', 'Register .ca, .com and more', 'Search and register your domain. DNS is set up for the email and website you order with us.', 'Register domain'],
  ['/email', 'mail', 'cyan', 'Subscription', 'From ' . money($fp('email_starter')) . '/mo', 'Business Email', 'Mailboxes on your domain', 'Professional business email on your own domain, set up for you after payment.', 'Start now'],
  ['/website', 'layout', 'green', 'Subscription', 'From ' . money($fp('website_launch')) . '/mo', 'Website Design', 'Designed, hosted and maintained', 'A monthly website subscription: we build it, host it and keep it updated so you can run your business.', 'Choose plan'],
  ['/bundle', 'stack', 'amber', 'Bundle', 'Save up to 15%', 'Bundle & Save', 'Combine services, one dashboard', 'Registration, management, domain, email, website and maintenance together, with a discount that grows as you add services.', 'Get the bundle'],
];
$steps = [['edit', 'violet', 'Submit your application', 'Complete a short guided form with your business details.', 'Each form checks names, addresses and required details as you go, so nothing is missed.'],
  ['search', 'teal', 'We review and prepare', 'We check everything and prepare the filing for the right registry.', 'Our filing agent validates the details against your registry’s rules and flags anything that needs a change.'],
  ['certificate', 'green', 'You sign, we file', 'You review and sign once; then it is submitted to the registry.', 'Nothing is filed until you have paid, the checks pass and you have signed. Any change after signing needs a new signature.'],
  ['calendar', 'blue', 'You’re good to go', 'Your documents and next steps arrive in your portal.', 'Track every order in your portal, download documents anytime and get reminders before deadlines.']];
$mark = ['y' => '✓ ', 'x' => '✗ ', 'w' => '▲ '];
?>
<section class="hero-c"><div class="wrap">
  <span class="badge-pill">One-stop Canadian business platform</span>
  <h1>Everything to launch <span>your business</span></h1>
  <p class="lead">Register your company, secure your domain, set up email and launch your website, all in one place.</p>
  <div class="hero-actions"><a class="btn btn-primary btn-lg" href="/register">Get started</a><a class="btn btn-ghost btn-lg" href="/bundle">Bundle &amp; save</a></div>
</div></section>
<section class="stats" aria-label="At a glance">
  <div><span class="mc-ic tone-blue"><?= nx_icon('building') ?></span><b><?= count(Filings::JURISDICTIONS) ?></b><span>Registries we file with</span></div>
  <div><span class="mc-ic tone-teal"><?= nx_icon('cash') ?></span><b>From <?= e(money($regFrom)) ?></b><span>Government fees included</span></div>
  <div><span class="mc-ic tone-violet"><?= nx_icon('certificate') ?></span><b>1 signature</b><span>Before anything is filed</span></div>
  <div><span class="mc-ic tone-amber"><?= nx_icon('globe') ?></span><b>10 + federal</b><span>Provinces served</span></div>
</section>
<div class="prov-strip" aria-label="Provinces"><ul><?php $pv = ['FED' => 'Federal (Canada-wide)'] + Catalog::PROVINCES; for ($k = 0; $k < 2; $k++) foreach ($pv as $c => $n): ?><li<?= $k ? ' aria-hidden="true"' : '' ?>><?= e($n) ?><small>✓ Available now</small></li><?php endforeach; ?></ul></div>

<section class="sec"><div class="wrap">
  <div class="sec-head center"><span class="badge-pill">Our services</span><h2>Built for Canadian entrepreneurs</h2><p>Six services. One dashboard.</p></div>
  <div class="svc6">
  <?php foreach ($svc6 as $i => [$href, $ic, $tone, $kind, $price, $t, $k, $d, $go]): ?>
    <a class="s6" href="<?= e($href) ?>">
      <span class="s6-top"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span class="s6-pr"><small><?= e($kind) ?></small><b><?= e($price) ?></b></span></span>
      <span class="s6-n"><?= sprintf('%02d', $i + 1) ?></span><h3><?= e($t) ?></h3><p class="s6-k"><?= e($k) ?></p><p><?= e($d) ?></p>
      <span class="s6-go"><?= e($go) ?> <?= nx_icon('arrow') ?></span>
    </a>
  <?php endforeach; ?>
  </div>
</div></section>

<section class="sec alt"><div class="wrap">
  <div class="sec-head center"><span class="badge-pill">How it works</span><h2>Simple, guided, end to end, in four steps</h2><p>From your browser to the government registry. Select a step to learn more.</p></div>
  <div class="steps4" data-steps4>
  <?php foreach ($steps as $i => [$ic, $tone, $t, $d, $more]): ?>
    <button type="button" class="st4<?= $i === 0 ? ' on' : '' ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"><span class="n"><?= sprintf('%02d', $i + 1) ?></span><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><h3><?= e($t) ?></h3><p><?= e($d) ?></p><span class="more"><?= e($more) ?></span></button>
  <?php endforeach; ?>
  </div>
  <div class="steps-cta"><a class="btn btn-primary btn-lg" href="/register">Start your business journey</a><small>Based in Moncton, New Brunswick</small></div>
</div></section>

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
