<?php
use App\{Catalog, Filings, Csrf};
include_once APP_ROOT . '/views/partials/icons.php';
$fp = function (string $slug) { $s = Catalog::find($slug); return $s ? Catalog::fromPrice($s) : null; };
$from = fn(?int $c, string $suf = '') => $c === null ? t('Custom quote') : t('From') . ' ' . money($c) . ($suf ? '/' . t($suf) : '');
$regFrom = min(array_filter([$fp('sole_prop'), $fp('partnership'), $fp('corp_numbered')]));
$jur = ['FED' => 'Federal (CBCA)'] + Catalog::PROVINCES;
$due = (int)date('n') % 12;
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$mark = ['y' => '✓ ', 'x' => '✗ ', 'w' => '▲ '];
$chap = [['open', 'Open', 'brand', '#0d1117', 'dark'], ['register', 'Register', 'register', PILLARS['register']['deep'], 'dark'], ['manage', 'Manage', 'manage', PILLARS['manage']['soft'], 'light'],
  ['web', 'Web', 'web', PILLARS['web']['soft'], 'light'], ['bundle', 'Bundle', 'bundle', PILLARS['bundle']['soft'], 'light'], ['funding', 'Funding', 'opps', PILLARS['opps']['soft'], 'light'], ['start', 'Start', 'brand', '#f5f6f8', 'light']];
$attr = function (int $i) use ($chap) { [$id, $lbl, $p, $flow, $tone] = $chap[$i]; return 'id="' . $id . '" data-flow="' . $flow . '" data-tone="' . $tone . '" data-pillar="' . $p . '" style="--chbg:' . $flow . ';' . pillar_css($p) . '" aria-label="' . e(t($lbl)) . '"'; };
$paths = [
  ['register', 'signing', 'Make it official.', [['/register/sole-proprietorship', 'Sole proprietorship', $fp('sole_prop')], ['/register/partnership', 'Partnership', $fp('partnership')], ['/register/corporation', 'Corporation', $fp('corp_numbered')], ['/register/nuans', 'NUANS name search', $fp('nuans')]]],
  ['manage', 'docs-typing', 'Stay in good standing.', [['/manage-business/annual-returns', 'Annual returns', $fp('bm_annual')], ['/manage-business/corporate-changes', 'Corporate changes', $fp('bm_changes')], ['/manage-business/corporate-documents', 'Documents', $fp('bm_documents')]]],
  ['web', 'developer', 'Get found online.', [['/domain', 'Domain name', $fp('domain_register')], ['/email', 'Business email', $fp('email_starter')], ['/website', 'Website', $fp('website_launch')], ['/web-application', 'Custom software', null]]],
  ['bundle', 'partners', 'Save up to 15%.', [['/bundle', 'Build a bundle', null]]],
  ['opps', 'team-deal', 'Find funding.', [['/opportunities', 'Grants and programs', null]]],
];
?>
<nav class="rail" aria-label="<?= e(t('Homepage chapters')) ?>" data-rail>
  <ol><?php foreach ($chap as $i => [$id, $lbl, $p]): ?><li style="<?= pillar_css($p) ?>"><a href="#<?= $id ?>"><span class="rl"><?= e(t($lbl)) ?></span><span class="rn"><?= sprintf('%02d', $i + 1) ?></span></a></li><?php endforeach; ?></ol>
  <span class="rail-bar" aria-hidden="true"><i data-rail-fill></i></span>
</nav>

<div class="story">
<section class="ch ch-open ch-idea" <?= $attr(0) ?>>
  <video class="ch-bg" autoplay muted loop playsinline preload="auto" poster="/media/opening-day-poster.webp" aria-hidden="true" data-autoplay><source src="/media/opening-day-m.mp4" type="video/mp4" media="(max-width: 760px) and (orientation: portrait)"><source src="/media/opening-day.mp4" type="video/mp4"></video>
  <div class="wrap ch-in">
    <h1 class="st-in"><span class="ln"><span><?= e(t('Open for')) ?></span></span> <span class="ln"><span><?= e(t('business.')) ?></span></span></h1>
    <p class="st-in"><?= e(t('Register it. Get it online. Keep it compliant. One Canadian team does it all.')) ?></p>
    <div class="ch-act st-in"><a class="btn btn-light btn-lg" href="/register"><?= e(t('Start your business')) ?></a><a class="btn btn-outline-light btn-lg" href="#register"><?= e(t('Explore services')) ?></a></div>
  </div>
  <a class="ch-next" href="#register" aria-label="<?= e(t('Next chapter')) ?>"><span><?= e(t('Scroll')) ?></span><i></i></a>
</section>

<section class="ch ch-register" <?= $attr(1) ?>>
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <a class="ch-k" href="/register"><i></i><?= e(t('Register Business')) ?></a>
      <h2 class="h2-lines"><span class="ln1"><?= e(t('Name it.')) ?></span><span class="ln2"><?= e(t('We’ll make it official.')) ?></span></h2>
      <div class="nm-form">
        <label class="nm-field"><span><?= e(t('Your business name')) ?></span><input type="text" maxlength="60" autocomplete="off" spellcheck="false" placeholder="Maple &amp; Pine" data-bizname></label>
        <div class="nm-row">
          <label class="nm-sel"><span><?= e(t('Registry')) ?></span><select data-jur><?php foreach ($jur as $c => $n): ?><option value="<?= e($c) ?>"<?= $c === 'NB' ? ' selected' : '' ?>><?= e(t($n)) ?></option><?php endforeach; ?></select></label>
          <fieldset class="nm-end"><legend><?= e(t('Ending')) ?></legend><?php foreach (['Inc.', 'Ltd.', 'Corp.'] as $k => $en): ?><label><input type="radio" name="hm_end" value="<?= $en ?>"<?= $k ? '' : ' checked' ?> data-end><span><?= $en ?></span></label><?php endforeach; ?></fieldset>
        </div>
      </div>
      <div class="ch-cta"><a class="btn btn-light btn-lg" href="/register/nuans" data-name-link="/register/nuans"><?= e(t('Check the name first')) ?></a><a class="btn btn-outline-light btn-lg" href="/register"><?= e(t('Register from')) ?> <?= e(money($regFrom)) ?></a></div>
      <p class="fine one-line"><?= e(t('Government fees included. Filed with the registry as soon as your order is in.')) ?></p>
    </div>
    <div class="aoi-wrap">
      <img class="aoi-bg" src="/media/paperwork.webp" alt="" loading="lazy" decoding="async" width="1400" height="934">
      <figure class="sheet aoi" aria-label="<?= e(t('Preview of your articles of incorporation')) ?>">
        <div class="aoi-head"><p class="aoi-k"><?= e(t('Draft prepared for filing')) ?> · <span data-jur-out><?= e(t('New Brunswick')) ?></span></p><p class="aoi-t"><?= e(t('Articles of Incorporation')) ?></p></div>
        <ol class="aoi-list">
          <li><span><?= e(t('Corporate name')) ?></span><b class="aoi-name"><span data-name-out>Maple &amp; Pine</span> <span data-end-out>Inc.</span></b></li>
          <li><span><?= e(t('Province or territory of the registered office')) ?></span><b data-jur-out2><?= e(t('New Brunswick')) ?></b></li>
          <li><span><?= e(t('Classes and maximum number of shares the corporation may issue')) ?></span><b><?= e(t('An unlimited number of common shares')) ?></b></li>
          <li><span><?= e(t('Restrictions on share transfers')) ?></span><b><?= e(t('See attached schedule')) ?></b></li>
          <li class="aoi-2"><span><?= e(t('Minimum and maximum number of directors')) ?></span><b><?= e(t('Minimum 1, maximum 10')) ?></b></li>
          <li class="aoi-2"><span><?= e(t('Restrictions on the business')) ?></span><b><?= e(t('None')) ?></b></li>
          <li class="aoi-2"><span><?= e(t('Other provisions')) ?></span><b><?= e(t('See attached schedule')) ?></b></li>
          <li class="aoi-2"><span><?= e(t('Incorporator')) ?></span><b><?= e(t('You')) ?></b></li>
        </ol>
        <p class="aoi-foot"><?= e(t('Preview, not a government document')) ?></p>
        <div class="stamp" aria-hidden="true"><span><?= e(t('Ready to file')) ?></span></div>
      </figure>
    </div>
  </div>
</section>

<section class="ch ch-manage" <?= $attr(2) ?>>
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <a class="ch-k" href="/manage-business"><i></i><?= e(t('Manage Business')) ?></a>
      <h2 data-split><?= e(t('Stay compliant. Skip the paperwork.')) ?></h2>
      <ul class="plist">
        <li><a href="/manage-business/annual-returns"><span><?= e(t('Annual returns')) ?></span><b><?= e($from($fp('bm_annual'))) ?></b></a></li>
        <li><a href="/manage-business/corporate-changes"><span><?= e(t('Corporate changes')) ?></span><b><?= e($from($fp('bm_changes'))) ?></b></a></li>
        <li><a href="/manage-business/corporate-documents"><span><?= e(t('Certificates and copies')) ?></span><b><?= e($from($fp('bm_documents'))) ?></b></a></li>
      </ul>
      <a class="btn btn-primary btn-lg" href="/manage-business"><?= e(t('Manage your business')) ?></a>
    </div>
    <div class="year" aria-hidden="true">
      <?php foreach ($months as $i => $m): $isDue = $i === $due; $isRem = $i === ($due + 11) % 12; ?>
        <div class="mo<?= $isDue ? ' due' : '' ?><?= $isRem ? ' rem' : '' ?>" style="--i:<?= $i ?>"><span><?= e(t($m)) ?></span><?php if ($isDue): ?><em><?= e(t('Annual return filed')) ?></em><?php elseif ($isRem): ?><em><?= e(t('We remind you')) ?></em><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="ch ch-web" <?= $attr(3) ?>>
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <a class="ch-k" href="/web-services"><i></i><?= e(t('Web Services')) ?></a>
      <h2 data-split><?= e(t('Get found. Get customers.')) ?></h2>
      <form class="dsearch" action="/domain" method="get" role="search">
        <label class="sr" for="dm-q"><?= e(t('Search a domain')) ?></label>
        <span class="ds-pre" aria-hidden="true">www.</span>
        <input id="dm-q" name="q" type="text" placeholder="mapleandpine.ca" autocomplete="off" autocapitalize="none" spellcheck="false" data-dm>
        <button class="btn btn-primary" type="submit"><?= e(t('Search')) ?></button>
      </form>
      <ul class="plist">
        <li><a href="/domain"><span><?= e(t('Domain name')) ?></span><b><?= e($from($fp('domain_register'), 'yr')) ?></b></a></li>
        <li><a href="/email"><span><?= e(t('Business email')) ?></span><b><?= e($from($fp('email_starter'), 'mo')) ?></b></a></li>
        <li><a href="/website"><span><?= e(t('Website, hosted and maintained')) ?></span><b><?= e($from($fp('website_launch'), 'mo')) ?></b></a></li>
      </ul>
      <a class="btn btn-primary btn-lg" href="/web-services"><?= e(t('Start Web Services')) ?></a>
    </div>
    <div class="site-mock" aria-hidden="true">
      <div class="sm-bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="sm-url">https://www.<b data-slug-out>mapleandpine</b>.ca</span></div>
      <div class="sm-nav"><b class="sm-logo" data-name-out>Maple &amp; Pine</b><span><?= e(t('Shop')) ?></span><span><?= e(t('About')) ?></span><span><?= e(t('Contact')) ?></span><span class="sm-cart"><?= e(t('Cart')) ?> (2)</span></div>
      <div class="sm-hero">
        <video autoplay muted loop playsinline preload="none" poster="/media/packing-poster.webp" data-autoplay><source src="/media/packing.mp4" type="video/mp4"></video>
        <div class="sm-copy"><p class="sm-h"><?= e(t('Handmade in Canada.')) ?></p><p class="sm-s"><?= e(t('Shipping across Canada')) ?></p><span class="sm-btn"><?= e(t('Shop now')) ?></span></div>
      </div>
      <div class="sm-mail"><span class="av-s">M</span><span><b><?= e(t('New order')) ?></b><small>orders@<span data-slug-out>mapleandpine</span>.ca</small></span></div>
    </div>
  </div>
</section>

<section class="ch ch-bundle" <?= $attr(4) ?>>
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <a class="ch-k" href="/bundle"><i></i><?= e(t('Bundle & Save')) ?></a>
      <h2 data-split><?= e(t('Do it all. Save up to 15%.')) ?></h2>
      <p><?= e(t('The more you add, the less you pay. Applied automatically.')) ?></p>
      <a class="btn btn-primary btn-lg" href="/bundle"><?= e(t('Build your bundle')) ?></a>
    </div>
    <ol class="tierbars" aria-label="<?= e(t('Bundle discounts')) ?>">
      <?php foreach ([[2, 5], [3, 10], [4, 12], ['5+', 15]] as $k => [$n, $pc]): ?>
        <li style="--h:<?= $pc * 6 ?>%;--i:<?= $k ?>"><b><?= $pc ?>%</b><i></i><span><?= e($n) ?> <?= e(t('services')) ?></span></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="ch ch-funding" <?= $attr(5) ?>>
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <a class="ch-k" href="/opportunities"><i></i><?= e(t('Opportunities')) ?></a>
      <h2 data-split><?= e(t('Funding is out there.')) ?></h2>
      <p><?= e(t('Grants, loans and government programs. Most start with a registered business.')) ?></p>
      <a class="btn btn-primary btn-lg" href="/opportunities"><?= e(t('Find funding')) ?></a>
    </div>
    <ul class="progs" aria-label="<?= e(t('Programs')) ?>">
      <li><b>IRAP</b><span><?= e(t('National Research Council of Canada')) ?></span></li>
      <li><b>ACOA</b><span><?= e(t('Atlantic Canada Opportunities Agency')) ?></span></li>
      <li><b>CSBFP</b><span><?= e(t('Canada Small Business Financing Program')) ?></span></li>
      <li><b>ISC</b><span><?= e(t('Innovative Solutions Canada')) ?></span></li>
    </ul>
  </div>
</section>

<section class="ch ch-start" <?= $attr(6) ?>>
  <div class="wrap">
    <div class="start-head"><h2 data-split><?= e(t('Start here.')) ?></h2><p><?= e(t('Pick a path. We’ll take it from there.')) ?></p></div>
    <div class="paths">
    <?php foreach ($paths as $k => [$p, $img, $line, $links]): ?>
      <article class="path<?= $k === 0 ? ' on' : '' ?>" style="<?= pillar_css($p) ?>" tabindex="0">
        <img src="/media/<?= e($img) ?>.webp" alt="" loading="lazy" decoding="async" width="1400" height="934">
        <div class="path-in">
          <p class="path-n"><?= sprintf('%02d', $k + 1) ?></p>
          <h3><a href="<?= e(PILLARS[$p]['home']) ?>"><?= e(t(PILLARS[$p]['name'])) ?></a></h3>
          <p class="path-l"><?= e(t($line)) ?></p>
          <ul><?php foreach ($links as [$h, $n, $c]): ?><li><a href="<?= e($h) ?>"><span><?= e(t($n)) ?></span><?php if ($c !== null): ?><b><?= e(t('From')) ?> <?= e(money($c)) ?></b><?php endif; ?></a></li><?php endforeach; ?></ul>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  </div>
</section>
</div>

<section class="sec"><div class="wrap">
  <div class="sec-head"><h2>Why 818</h2><p>Versus a lawyer. Versus doing it yourself.</p></div>
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
  <div><h2>Be first to know.</h2><p>New services and launch offers. No spam.</p></div>
  <div><?php if (!empty($_GET['subscribed'])): ?><p class="alert alert-success">Thanks! You're on the list. You can unsubscribe anytime from any email.</p>
  <?php else: ?><form method="post" action="/subscribe"><?= Csrf::field() ?><label class="sr" for="news-email">Email address</label><input id="news-email" type="email" name="email" required placeholder="you@yourbusiness.ca" autocomplete="email"><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn btn-primary">Notify me</button></form>
  <small>By subscribing you agree to receive occasional emails from 818 Nexus Inc. No spam. Unsubscribe anytime.</small><?php endif; ?></div>
</div></section>
