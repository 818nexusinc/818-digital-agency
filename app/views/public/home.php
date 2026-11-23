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
<section class="hero-l"><div class="wrap">
  <div>
    <h1>Everything to launch your Canadian business</h1>
    <p class="lead">Register your business in any province or federally, then secure a domain, set up email and launch your website, all in one place.</p>
    <div class="hero-actions"><a class="btn btn-primary" href="#certificate">Start an incorporation</a><a class="btn btn-ghost" href="/services">See services and prices</a></div>
    <ul class="hero-points"><li>Government filing fees included in every price</li><li>You review and sign once before anything is filed</li><li>Track every step in your own portal</li></ul>
  </div>
    <div class="cert-wrap" id="certificate">
      <div class="cert" aria-label="Certificate preview">
        <span class="preview">Preview</span>
        <div class="cert-border"><div class="cert-inner">
          <p class="issuer" data-cert="issuer">Province of New Brunswick</p>
          <p class="title">Certificate of Incorporation</p>
          <p class="certify">This is to certify that</p>
          <p class="cname" data-cert="name">Your Company Inc.</p>
          <p class="act">was incorporated under the <span data-cert="act">Business Corporations Act (New Brunswick)</span></p>
          <div class="cert-foot"><div class="line">No. <span data-cert="number">assigned on filing</span></div>
            <div class="seal" aria-hidden="true"><svg viewBox="0 0 120 120"><defs><path id="sealc" d="M60,60 m-44,0 a44,44 0 1,1 88,0 a44,44 0 1,1 -88,0"/></defs>
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
</div></section>

<section class="registries" aria-label="Registries"><div class="wrap"><p>We file with</p><ul><?php foreach (Filings::JURISDICTIONS as $j => $r): ?><li><?= e($r[0]) ?></li><?php endforeach; ?></ul></div></section>

<section class="sec"><div class="wrap">
  <div class="sec-head"><h2>Our services</h2><p>Everything a new or growing business needs, with prices shown up front.</p></div>
  <div class="svc-grid">
  <?php foreach ($cats as $k => $c): if (!$c['services'] || !isset($photos[$k])) continue; $min = $from($c['services']); [$img, $alt] = $photos[$k]; ?>
    <a class="svc" href="/services/category/<?= e($k) ?>">
      <img src="/media/<?= $img ?>.webp" alt="<?= e($alt) ?>" loading="lazy" width="1200" height="675">
      <span class="svc-body"><span class="svc-name"><?= e($c['name']) ?></span>
        <span class="svc-items"><?php foreach (array_slice($c['services'], 0, 3) as $s): ?><span><?= e(preg_replace('/^(Business Email|Website) — /', '', $s['name'])) ?></span><?php endforeach; ?></span>
        <span class="svc-price"><?= $min !== null ? 'From ' . e(money($min)) : 'Quoted after a free consultation' ?></span></span>
    </a>
  <?php endforeach; ?>
    <a class="svc svc-cta" href="/start"><span class="svc-body"><span class="svc-name">Not sure what you need?</span><span class="svc-items">Tell us about your business and we'll recommend the right services, with a written quote for anything custom.</span><span class="svc-price">Tell us what you need</span></span></a>
  </div>
</div></section>

<section class="sec alt"><div class="wrap how">
  <div class="how-media"><video muted loop playsinline preload="none" poster="/media/sign-poster.webp" data-lazy-video aria-hidden="true"><source data-src="/media/sign.mp4" type="video/mp4"></video></div>
  <div>
    <div class="sec-head" style="margin-bottom:26px"><h2>How a registration works</h2><p>The same five steps for every registration, annual return and corporate change.</p></div>
    <ol class="steps-l">
      <?php foreach ($steps as [$t, $d]): ?><li><h3><?= e($t) ?></h3><p><?= e($d) ?></p></li><?php endforeach; ?>
    </ol>
    <p class="note">Nothing is sent to a registry until you've paid, the details pass every check and you've signed. We are not a law firm.</p>
  </div>
</div></section>

<section class="sec"><div class="wrap duo">
  <div class="panel-l">
    <h3>Find your domain</h3><p class="muted">We check the .ca and .com registries directly.</p>
    <form id="domain-check" class="domain-form"><label class="sr" for="domain-input">Domain</label><input id="domain-input" type="text" placeholder="yourcompany.ca" autocomplete="off"><button class="btn btn-primary">Check</button></form>
    <div id="domain-result" aria-live="polite"></div>
  </div>
  <?php if ($bundle): ?><div class="panel-l" id="bundle">
    <h3><?= e($bundle['name']) ?><span class="save"><?= (int)$bundle['discount_pct'] ?>% off</span></h3><p class="muted">Everything a new company needs, at a bundle price.</p>
    <ul class="plain"><?php foreach (jdec($bundle['items']) as $it): $s = Catalog::find($it['slug']); if ($s): ?><li><span><?= e($s['name']) ?></span><span><?= e(Catalog::priceLabel($s)) ?></span></li><?php endif; endforeach; ?></ul>
    <form method="post" action="/cart/add" class="row"><?= Csrf::field() ?><input type="hidden" name="package" value="<?= e($bundle['slug']) ?>"><select name="province" style="width:auto;flex:1" aria-label="Province"><?php foreach (Catalog::PROVINCES as $c => $n): ?><option value="<?= $c ?>"<?= $c === 'NB' ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><button class="btn btn-primary">Add the bundle</button></form>
  </div><?php endif; ?>
</div></section>

<section class="sec alt"><div class="wrap ask-l">
  <img src="/media/dough.webp" alt="A baker preparing dough in a bakery" loading="lazy" width="1200" height="900">
  <div>
    <div class="sec-head" style="margin-bottom:20px"><h2>Not sure where to start?</h2><p>Describe your business and our assistant will recommend what you need, priced from our catalog.</p></div>
    <form class="composer" id="composer" aria-label="Describe what you need">
      <label class="sr" for="composer-text">What do you need?</label>
      <textarea id="composer-text" placeholder="I'm opening a bakery in Moncton and need everything to get going."></textarea>
      <div class="composer-foot"><div class="chips"><button class="chip" type="button">Incorporate in New Brunswick</button><button class="chip" type="button">I need a website</button><button class="chip" type="button">Email for my team</button></div><button class="btn btn-primary" type="submit">Get a plan</button></div>
    </form>
  </div>
</div></section>

<?php if ($reviews): ?><section class="sec"><div class="wrap"><div class="sec-head"><h2>From verified customers</h2></div><div class="grid g3">
<?php foreach ($reviews as $rv): ?><figure class="card" style="margin:0"><div aria-label="<?= (int)$rv['rating'] ?> out of 5"><?= str_repeat('★', (int)$rv['rating']) ?></div><blockquote style="margin:10px 0"><?= e($rv['body']) ?></blockquote><figcaption class="muted small"><?= e($rv['display_name'] ?: 'Verified customer') ?></figcaption></figure><?php endforeach; ?></div></div></section><?php endif; ?>

<section class="sec"><div class="wrap"><div class="cta-l"><div><h2>Ready to register your business?</h2><p>Based in Moncton, New Brunswick. Filing in every province and federally.</p></div><div class="hero-actions" style="margin:0"><a class="btn btn-primary" href="#certificate">Start an incorporation</a><a class="btn btn-ghost" href="/contact">Contact us</a></div></div></div></section>
