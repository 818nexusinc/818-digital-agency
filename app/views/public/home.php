<?php
use App\{Catalog, Filings, Csrf};
$named = Catalog::find('corp_named');
$prices = array_map(fn($c) => $c === null ? null : money($c), $named['province_prices'] ?? []);
$pricesN = array_map(fn($c) => $c === null ? null : money($c), Catalog::find('corp_numbered')['province_prices'] ?? []);
$acts = ['FED' => ['Corporations Canada', 'Canada Business Corporations Act'], 'AB' => ['Province of Alberta', 'Business Corporations Act (Alberta)'], 'BC' => ['Province of British Columbia', 'Business Corporations Act (British Columbia)'],
  'MB' => ['Province of Manitoba', 'The Corporations Act (Manitoba)'], 'NB' => ['Province of New Brunswick', 'Business Corporations Act (New Brunswick)'], 'NL' => ['Province of Newfoundland and Labrador', 'Corporations Act (Newfoundland and Labrador)'],
  'NS' => ['Province of Nova Scotia', 'Companies Act (Nova Scotia)'], 'ON' => ['Province of Ontario', 'Business Corporations Act (Ontario)'], 'PE' => ['Province of Prince Edward Island', 'Business Corporations Act (Prince Edward Island)'],
  'QC' => ['Province of Québec', 'Business Corporations Act (Québec)'], 'SK' => ['Province of Saskatchewan', 'The Business Corporations Act, 2021 (Saskatchewan)']];
$from = function (array $svcs) { $pp = array_filter($svcs, fn($s) => $s['pricing_model'] === 'per_province'); if ($pp) $svcs = $pp; $p = array_filter(array_map([Catalog::class, 'fromPrice'], $svcs), fn($x) => $x !== null); return $p ? min($p) : null; };
// One photo per category; the first tile is the large one.
$photos = ['registration' => ['owner-open', 'A shop owner holding an open sign'], 'management' => ['signing', 'A director signing company documents'],
  'websites' => ['laptop', 'A laptop showing a website'], 'domains' => ['welcome', 'A welcome sign on a shop door'], 'email' => ['desk-poster', 'A business owner working at a laptop'],
  'apps' => ['developer', 'A developer working on a laptop'], 'growth' => ['baker', 'A baker presenting fresh pastries']];
$steps = [['You tell us', 'A short guided form, shaped to your registry.'], ['We check it', 'Names, legal endings, directors and addresses are checked automatically.'],
  ['You sign once', 'One e-signature locks the exact details. Any change needs a new one.'], ['Our agent files', 'By API where the registry has one, through the official portal everywhere else.'], ['You\'re on the record', 'Corporation number and documents land in your portal.']];
?>
<section class="nf-hero">
  <video class="nf-media" autoplay muted loop playsinline preload="metadata" poster="/media/hero-open-poster.webp" aria-hidden="true" data-autoplay><source src="/media/hero-open.mp4" type="video/mp4"></video>
  <div class="wrap">
    <div class="hero-copy" data-reveal>
      <h1>Your company,<br><span class="glowtext">on the record.</span></h1>
      <p class="lead">Incorporate in any province or federally, then launch your domain, email and website. You sign once. Our filing agent does the rest.</p>
      <div class="hero-actions"><a class="btn btn-primary btn-lg" href="#certificate">Name your company</a><a class="btn btn-glass btn-lg" href="/services">Services and prices</a></div>
    </div>
    <div class="cert-wrap" id="certificate" data-tilt>
      <div class="cert" aria-label="Certificate preview">
        <span class="preview">Preview</span>
        <div class="cert-border"><div class="cert-inner">
          <p class="issuer" data-cert="issuer">Province of New Brunswick</p>
          <p class="title">Certificate of Incorporation</p>
          <p class="certify">This is to certify that</p>
          <p class="cname" data-cert="name">Your Company Inc.</p>
          <p class="act">was incorporated under the <span data-cert="act">Business Corporations Act (New Brunswick)</span></p>
          <div class="cert-foot"><div class="line">No. <span data-cert="number">assigned on filing</span></div>
            <div class="seal stamped" aria-hidden="true"><svg viewBox="0 0 120 120"><defs><path id="sealc" d="M60,60 m-44,0 a44,44 0 1,1 88,0 a44,44 0 1,1 -88,0"/></defs>
              <circle cx="60" cy="60" r="57" fill="#eef2ff" stroke="#2f5bea" stroke-width="2"/><circle cx="60" cy="60" r="36" fill="none" stroke="#2f5bea" stroke-width="1" stroke-dasharray="2 3"/>
              <text font-family="Hanken, sans-serif" font-size="9.2" font-weight="700" letter-spacing="2.4" fill="#2f5bea"><textPath href="#sealc">818 DIGITAL AGENCY · FILED · 818 DIGITAL AGENCY ·</textPath></text>
              <g fill="none" stroke-width="3.2"><circle cx="45" cy="52" r="5.6" stroke="#0e2340"/><circle cx="45" cy="67" r="7" stroke="#0e2340"/><circle cx="75" cy="52" r="5.6" stroke="#2f5bea"/><circle cx="75" cy="67" r="7" stroke="#2f5bea"/><path d="M58 51 L61.5 47 V74" stroke="#7f98e8" stroke-linecap="round" stroke-linejoin="round"/></g></svg></div>
            <div class="line" data-cert="date"><?= e(gmdate('F j, Y')) ?></div></div>
        </div></div>
      </div>
      <form class="cert-controls" method="post" action="/cart/add" data-prices="<?= e(jenc($prices)) ?>" data-prices-numbered="<?= e(jenc($pricesN)) ?>" data-acts="<?= e(jenc($acts)) ?>"><?= Csrf::field() ?><input type="hidden" name="slug" value="corp_named" data-cert-slug>
        <div><label for="cn">Business name</label><input id="cn" type="text" placeholder="Type a name" autocomplete="organization" maxlength="80"></div>
        <div><label for="ce">Ending</label><select id="ce"><option>Inc.</option><option>Ltd.</option><option>Corp.</option><option>Limited</option><option>Numbered</option></select></div>
        <div><label for="cp">Registry</label><select id="cp" name="province"><?php foreach (Catalog::PROVINCES as $c => $n): if (($named['province_prices'][$c] ?? null) === null) continue; ?><option value="<?= $c ?>"<?= $c === 'NB' ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="full"><span><span class="price" data-cert="price"><?= e($prices['NB'] ?? '') ?></span> <span class="muted small">incl. government fee</span></span><button class="btn btn-primary">Start this incorporation</button></div>
      </form>
    </div>
  </div>
  <div class="marquee" aria-label="Registries we file with"><ul><?php for ($k = 0; $k < 2; $k++): foreach (Filings::JURISDICTIONS as $j => $r): ?><li<?= $k ? ' aria-hidden="true"' : '' ?>><?= e($r[0]) ?></li><?php endforeach; endfor; ?></ul></div>
  <button class="vid-toggle" type="button" data-vid-toggle aria-label="Pause background video"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path class="i-pause" d="M7 5h4v14H7zM13 5h4v14h-4z"/><path class="i-play" d="M8 5v14l11-7z"/></svg></button>
</section>

<section class="nf-sec"><div class="wrap">
  <h2 class="nf-title" data-reveal>Everything after the paperwork.</h2>
  <p class="nf-sub" data-reveal>One account for the registration, the address and everything your customers see.</p>
  <div class="bento">
  <?php $i = 0; foreach ($cats as $k => $c): if (!$c['services'] || !isset($photos[$k])) continue; $min = $from($c['services']); [$img, $alt] = $photos[$k]; ?>
    <a class="tile<?= $i++ === 0 ? ' tile-lg' : '' ?>" href="/services/category/<?= e($k) ?>" data-reveal data-spot>
      <img src="/media/<?= $img ?>.webp" alt="<?= e($alt) ?>" loading="<?= $i < 3 ? 'eager' : 'lazy' ?>" width="1200" height="800">
      <span class="tile-body"><span class="tile-name"><?= e($c['name']) ?></span>
        <span class="tile-items"><?php foreach (array_slice($c['services'], 0, 3) as $s): ?><span><?= e(preg_replace('/^(Business Email|Website) — /', '', $s['name'])) ?></span><?php endforeach; ?></span>
        <span class="tile-price"><?= $min !== null ? 'from ' . e(money($min)) : 'Quoted after a free consult' ?></span></span>
    </a>
  <?php endforeach; ?>
  </div>
</div></section>

<section class="story" data-story><div class="wrap">
  <div class="story-media"><video muted loop playsinline preload="none" poster="/media/sign-poster.webp" data-lazy-video aria-hidden="true"><source data-src="/media/sign.mp4" type="video/mp4"></video>
    <div class="story-count" aria-hidden="true"><b data-story-n>1</b>of <?= count($steps) ?></div><div class="story-bar" aria-hidden="true"></div></div>
  <div>
    <h2 class="nf-title" data-reveal>One signature. Every registry.</h2>
    <ol class="chapters">
      <?php foreach ($steps as $n => [$t, $d]): ?><li<?= $n === 0 ? ' class="on"' : '' ?>><span class="k">Step <?= $n + 1 ?></span><h3><?= e($t) ?></h3><p><?= e($d) ?></p><?php if ($n === count($steps) - 1): ?><p class="story-note">Nothing reaches a registry until you've paid, every check has passed and you've signed. We're not a law firm.</p><?php endif; ?></li><?php endforeach; ?>
    </ol>
  </div>
</div></section>

<section class="nf-sec" style="padding-top:40px"><div class="wrap">
  <div class="claim-grid">
    <div class="glass domain-box" data-reveal>
      <h3>Is your name free online?</h3>
      <form id="domain-check" class="domain-form"><label class="sr" for="domain-input">Domain</label><input id="domain-input" type="text" placeholder="yourcompany.ca" autocomplete="off"><button class="btn btn-primary">Check</button></form>
      <div id="domain-result" aria-live="polite"></div>
    </div>
    <?php if ($bundle): ?><div class="glass bundle" data-reveal>
      <h3><?= e($bundle['name']) ?> <span class="save"><?= (int)$bundle['discount_pct'] ?>% off</span></h3>
      <ul class="plain"><?php foreach (jdec($bundle['items']) as $it): $s = Catalog::find($it['slug']); if ($s): ?><li><span><?= e($s['name']) ?></span><span><?= e(Catalog::priceLabel($s)) ?></span></li><?php endif; endforeach; ?></ul>
      <form method="post" action="/cart/add" class="row"><?= Csrf::field() ?><input type="hidden" name="package" value="<?= e($bundle['slug']) ?>"><select name="province" style="width:auto;flex:1" aria-label="Province"><?php foreach (Catalog::PROVINCES as $c => $n): ?><option value="<?= $c ?>"<?= $c === 'NB' ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><button class="btn btn-dark">Add the bundle</button></form>
    </div><?php endif; ?>
  </div>
</div></section>

<section class="ask-stage">
  <video class="nf-media" muted loop playsinline preload="none" poster="/media/desk-poster.webp" data-lazy-video aria-hidden="true"><source data-src="/media/desk.mp4" type="video/mp4"></video>
  <div class="wrap">
    <h2 class="nf-title" style="margin-inline:auto" data-reveal>Just tell us about your business.</h2>
    <form class="composer" id="composer" aria-label="Describe what you need" data-reveal>
      <label class="sr" for="composer-text">What do you need?</label>
      <textarea id="composer-text" placeholder="I'm opening a bakery in Moncton and need everything to get going."></textarea>
      <div class="composer-foot"><div class="chips"><button class="chip" type="button">Incorporate in New Brunswick</button><button class="chip" type="button">I need a website</button><button class="chip" type="button">Email for my team</button></div><button class="btn btn-primary" type="submit">Get a plan</button></div>
    </form>
    <p class="note">The assistant quotes only from our published prices. A person reviews anything legal.</p>
  </div>
</section>

<?php if ($reviews): ?><section class="nf-sec"><div class="wrap"><h2 class="nf-title" data-reveal>From verified customers</h2><div class="grid g3">
<?php foreach ($reviews as $rv): ?><figure class="card" style="margin:0" data-reveal><div aria-label="<?= (int)$rv['rating'] ?> out of 5"><?= str_repeat('★', (int)$rv['rating']) ?></div><blockquote style="margin:10px 0;font-family:var(--serif);font-size:1.1rem;color:#fff"><?= e($rv['body']) ?></blockquote><figcaption class="muted small"><?= e($rv['display_name'] ?: 'Verified customer') ?></figcaption></figure><?php endforeach; ?></div></div></section><?php endif; ?>

<section class="closing-v">
  <video class="nf-media" muted loop playsinline preload="none" poster="/media/coast-poster.webp" data-lazy-video aria-hidden="true"><source data-src="/media/coast.mp4" type="video/mp4"></video>
  <div class="wrap"><h2 data-reveal>Built in Moncton. <span class="glowtext">Filing coast to coast.</span></h2><div class="hero-actions"><a class="btn btn-primary btn-lg" href="#certificate">Start an incorporation</a><a class="btn btn-glass btn-lg" href="/start">Talk it through first</a></div></div>
</section>
