<?php
use App\{Catalog, Filings, Csrf};
include_once APP_ROOT . '/views/partials/icons.php';
$fp = function (string $slug) { $s = Catalog::find($slug); return $s ? Catalog::fromPrice($s) : null; };
$regFrom = min(array_filter([$fp('sole_prop'), $fp('partnership'), $fp('corp_numbered')]));
$jur = ['FED' => 'Federal (CBCA)'] + Catalog::PROVINCES;
$idx = [
  ['/register', 'Register a business', 'Sole proprietorship, partnership, corporation or co-op.', 'From ' . money($regFrom), 'signing'],
  ['/manage-business', 'Manage a registered business', 'Annual returns, changes, certificates.', 'From ' . money($fp('bm_documents')), 'docs-typing'],
  ['/domain', 'Domain name', '.ca, .com and more, with DNS set up.', 'From ' . money($fp('domain_register')) . '/yr', 'laptop'],
  ['/email', 'Business email', 'Mailboxes on your own domain.', 'From ' . money($fp('email_starter')) . '/mo', 'notes'],
  ['/website', 'Website', 'Designed, hosted and maintained.', 'From ' . money($fp('website_launch')) . '/mo', 'developer'],
  ['/web-application', 'Custom software', 'Portals, booking systems, dashboards.', 'Quoted in writing', 'team-deal'],
];
$chap = [['idea', 'Idea'], ['name', 'Name'], ['file', 'File'], ['online', 'Online'], ['care', 'Care'], ['start', 'Start']];
$due = (int)date('n') % 12; // a sample anniversary month for the illustration
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$mark = ['y' => '✓ ', 'x' => '✗ ', 'w' => '▲ '];
?>
<nav class="rail" aria-label="Homepage chapters" data-rail>
  <ol><?php foreach ($chap as $i => [$id, $lbl]): ?><li><a href="#<?= $id ?>"><span class="rn"><?= sprintf('%02d', $i + 1) ?></span><span class="rl"><?= e(t($lbl)) ?></span></a></li><?php endforeach; ?></ol>
</nav>

<div class="story">
<section class="ch ch-idea" id="idea" aria-label="<?= e(t('Idea')) ?>">
  <img class="ch-bg" src="/media/owners-open.webp" alt="" width="1600" height="900" fetchpriority="high">
  <div class="wrap ch-in">
    <h1 class="st-in"><?= e(t('It starts with an idea.')) ?></h1>
    <p class="st-in"><?= e(t('We take it from there: the registration, a name online, email and a website. Filed, built and looked after by a team in Moncton, New Brunswick.')) ?></p>
    <div class="ch-act st-in"><a class="btn btn-light btn-lg" href="/register"><?= e(t('Start your business')) ?></a><a class="lnk-light" href="#name"><?= e(t('See how it works')) ?></a></div>
  </div>
  <a class="ch-next" href="#name" aria-label="<?= e(t('Next chapter')) ?>"><span><?= e(t('Scroll')) ?></span><i></i></a>
</section>

<section class="ch ch-name" id="name" aria-label="<?= e(t('Name')) ?>">
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <h2><?= e(t('Give it a name.')) ?></h2>
      <p><?= e(t('Type the name you have in mind and pick where you will register. This is roughly how it will read on your paperwork.')) ?></p>
      <div class="nm-form">
        <label class="nm-field"><span><?= e(t('Business name')) ?></span><input type="text" maxlength="60" autocomplete="off" spellcheck="false" placeholder="Maple &amp; Pine" data-bizname></label>
        <div class="nm-row">
          <label class="nm-sel"><span><?= e(t('Registry')) ?></span><select data-jur><?php foreach ($jur as $c => $n): ?><option value="<?= e($c) ?>"<?= $c === 'NB' ? ' selected' : '' ?>><?= e(t($n)) ?></option><?php endforeach; ?></select></label>
          <fieldset class="nm-end"><legend><?= e(t('Ending')) ?></legend><?php foreach (['Inc.', 'Ltd.', 'Corp.'] as $k => $en): ?><label><input type="radio" name="hm_end" value="<?= $en ?>"<?= $k ? '' : ' checked' ?> data-end><span><?= $en ?></span></label><?php endforeach; ?></fieldset>
        </div>
      </div>
      <p class="ch-links"><a class="lnk" href="/register/nuans" data-name-link="/register/nuans"><?= e(t('Check it with an official NUANS search')) ?></a> <span class="muted"><?= e(money($fp('nuans'))) ?></span></p>
    </div>
    <figure class="sheet" aria-label="<?= e(t('Preview of your corporate name')) ?>">
      <div class="sh-top"><span><?= e(t('Draft')) ?></span><span><?= e(t('Preview, not a government document')) ?></span></div>
      <p class="sh-k"><?= e(t('Corporation name')) ?></p>
      <p class="sh-name"><span data-name-out>Maple &amp; Pine</span> <span data-end-out>Inc.</span></p>
      <dl class="sh-dl">
        <div><dt><?= e(t('Registry')) ?></dt><dd data-jur-out><?= e(t('New Brunswick')) ?></dd></div>
        <div><dt><?= e(t('Directors')) ?></dt><dd><?= e(t('You, and anyone you add')) ?></dd></div>
        <div><dt><?= e(t('Corporation number')) ?></dt><dd><?= e(t('Issued by the registry')) ?></dd></div>
      </dl>
      <div class="sh-sign"><svg viewBox="0 0 220 50" aria-hidden="true"><path d="M6 36c14-22 22-26 26-18s-8 22 0 18 16-28 24-24-6 26 4 22 14-20 22-18 2 16 10 14 12-12 20-12 6 10 14 8 18-10 30-8 30 2 50-2"/></svg><span><?= e(t('Your signature, once you have reviewed it')) ?></span></div>
    </figure>
  </div>
</section>

<section class="ch ch-file" id="file" aria-label="<?= e(t('File')) ?>">
  <div class="ch-media"><video autoplay muted loop playsinline preload="none" poster="/media/desk-poster.webp" aria-hidden="true" data-autoplay><source src="/media/desk.mp4" type="video/mp4"></video></div>
  <div class="ch-side">
    <h2><?= e(t('Sign once. We file it.')) ?></h2>
    <ol class="steps3">
      <li><b><?= e(t('Answer a guided form.')) ?></b><span><?= e(t('It checks names, addresses and directors as you type.')) ?></span></li>
      <li><b><?= e(t('We check it against the registry’s rules.')) ?></b><span><?= e(t('Anything that needs fixing comes back to you in plain language.')) ?></span></li>
      <li><b><?= e(t('You sign. We submit it.')) ?></b><span><?= e(t('Your documents arrive in your portal. Nothing is filed without your signature.')) ?></span></li>
    </ol>
    <p class="fine"><?= e(t('We file with Corporations Canada and every provincial registry.')) ?> <?= e(t('From')) ?> <?= e(money($regFrom)) ?>, <?= e(t('government fees included.')) ?></p>
    <a class="btn btn-primary btn-lg" href="/register"><?= e(t('Choose your structure')) ?></a>
  </div>
</section>

<section class="ch ch-online" id="online" aria-label="<?= e(t('Online')) ?>">
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <h2><?= e(t('Then, get found.')) ?></h2>
      <p><?= e(t('The same name becomes your web address, your inbox and your website. One account, one bill.')) ?></p>
      <ul class="plist">
        <li><span><?= e(t('Domain name')) ?></span><b><?= e(t('From')) ?> <?= e(money($fp('domain_register'))) ?>/<?= e(t('yr')) ?></b></li>
        <li><span><?= e(t('Business email')) ?></span><b><?= e(t('From')) ?> <?= e(money($fp('email_starter'))) ?>/<?= e(t('mo')) ?></b></li>
        <li><span><?= e(t('Website, hosted and maintained')) ?></span><b><?= e(t('From')) ?> <?= e(money($fp('website_launch'))) ?>/<?= e(t('mo')) ?></b></li>
      </ul>
      <a class="btn btn-primary btn-lg" href="/bundle"><?= e(t('Bundle them and save up to 15%')) ?></a>
    </div>
    <div class="stack" aria-hidden="true">
      <div class="st-url"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="u">www.<b data-slug-out>mapleandpine</b>.ca</span></div>
      <div class="st-site">
        <img src="/media/cafe-owner.webp" alt="" loading="lazy" width="1400" height="934">
        <div class="st-site-in"><p class="st-brand" data-name-out>Maple &amp; Pine</p><p class="st-tag"><?= e(t('Open Tuesday to Sunday')) ?></p><span class="st-btn"><?= e(t('Book a table')) ?></span></div>
      </div>
      <div class="st-mail"><span class="av-s">M</span><span><b><?= e(t('New message')) ?></b><small>hello@<span data-slug-out>mapleandpine</span>.ca</small></span></div>
    </div>
  </div>
</section>

<section class="ch ch-care" id="care" aria-label="<?= e(t('Care')) ?>">
  <div class="wrap ch-grid">
    <div class="ch-copy">
      <h2><?= e(t('Stay in good standing.')) ?></h2>
      <p><?= e(t('Corporations file an annual return every year, and records change. We keep the dates, remind you ahead of time and file it for you.')) ?></p>
      <a class="btn btn-primary btn-lg" href="/manage-business"><?= e(t('Manage your business')) ?></a>
    </div>
    <div class="year" aria-hidden="true">
      <?php foreach ($months as $i => $m): $isDue = $i === $due; $isRem = $i === ($due + 11) % 12; ?>
        <div class="mo<?= $isDue ? ' due' : '' ?><?= $isRem ? ' rem' : '' ?>"><span><?= e(t($m)) ?></span><?php if ($isDue): ?><em><?= e(t('Annual return filed')) ?></em><?php elseif ($isRem): ?><em><?= e(t('We remind you')) ?></em><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="ch ch-start" id="start" aria-label="<?= e(t('Start')) ?>">
  <div class="wrap">
    <h2><?= e(t('Where would you like to start?')) ?></h2>
    <ul class="idx">
    <?php foreach ($idx as [$href, $tt, $dd, $pr, $img]): ?>
      <li><a href="<?= e($href) ?>"><span class="ix-t"><?= e(t($tt)) ?></span><span class="ix-d"><?= e(t($dd)) ?></span><span class="ix-p"><?= e(str_starts_with($pr, 'From ') ? t('From') . substr($pr, 4) : t($pr)) ?></span><img src="/media/<?= e($img) ?>.webp" alt="" loading="lazy" width="1400" height="934"></a></li>
    <?php endforeach; ?>
    </ul>
  </div>
</section>
</div>

<section class="sec"><div class="wrap">
  <div class="sec-head"><h2>How we compare</h2><p>Against hiring a lawyer, or filing it all yourself.</p></div>
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
  <div><h2>Stay updated on new services</h2><p>Get early access and launch offers when new services and provinces go live.</p></div>
  <div><?php if (!empty($_GET['subscribed'])): ?><p class="alert alert-success">Thanks! You're on the list. You can unsubscribe anytime from any email.</p>
  <?php else: ?><form method="post" action="/subscribe"><?= Csrf::field() ?><label class="sr" for="news-email">Email address</label><input id="news-email" type="email" name="email" required placeholder="you@yourbusiness.ca" autocomplete="email"><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn btn-primary">Notify me</button></form>
  <small>By subscribing you agree to receive occasional emails from 818 Nexus Inc. No spam. Unsubscribe anytime.</small><?php endif; ?></div>
</div></section>
