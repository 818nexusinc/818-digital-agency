<?php use App\Catalog; $quote = $s['pricing_model'] === 'quote'; ?>
<section class="section tight"><div class="wrap">
  <nav class="small muted" aria-label="Breadcrumb"><a href="/services">Services</a> / <a href="/services/category/<?= e($s['category']) ?>"><?= e(Catalog::CATEGORIES[$s['category']] ?? '') ?></a> / <?= e($s['name']) ?></nav>
  <div class="grid g2" style="margin-top:14px;align-items:start;grid-template-columns:1.3fr .9fr">
    <div>
      <h1><?= e($s['name']) ?></h1><p class="lead"><?= e($s['description'] ?: $s['summary']) ?></p>
      <?php if ($s['deliverables']): ?><h2 style="font-size:1.2rem;margin-top:28px">What you get</h2><ul class="list"><?php foreach ($s['deliverables'] as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($s['requirements']): ?><h2 style="font-size:1.2rem;margin-top:28px">What we'll ask you</h2><ul><?php foreach ($s['requirements'] as $q): ?><li><?= e($q) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($s['human_required']): ?><div class="alert alert-info" style="margin-top:20px"><?php foreach ($s['human_required'] as $h): ?><?= e($h) ?> <?php endforeach; ?></div><?php endif; ?>
      <?php if ($s['limitations']): ?><p class="small muted"><?= e($s['limitations']) ?></p><?php endif; ?>
      <?php if ($faq): ?><h2 style="font-size:1.2rem;margin-top:28px">Questions</h2><div class="faq"><?php foreach ($faq as $f): ?><details><summary><?= e($f['q']) ?></summary><p><?= e($f['a']) ?></p></details><?php endforeach; ?></div><?php endif; ?>
    </div>
    <aside class="card" style="position:sticky;top:90px">
      <div class="price"><?= e(Catalog::priceLabel($s)) ?></div>
      <?php if ($s['timeline']): ?><p class="small muted">Typical timeline: <?= e($s['timeline']) ?></p><?php endif; ?>
      <?php if ($quote): ?>
        <p class="small">This service is priced from your requirements. You'll get a written proposal with a fixed price before any payment.</p>
        <a class="btn btn-primary" style="width:100%" href="/start?service=<?= e($s['slug']) ?>">Request a quote</a>
        <button class="btn btn-ghost" style="width:100%;margin-top:8px" type="button" data-chat="I'd like a quote for <?= e($s['name']) ?>">Discuss with the assistant</button>
      <?php else: ?>
      <form method="post" action="/cart/add"><?= App\Csrf::field() ?><input type="hidden" name="slug" value="<?= e($s['slug']) ?>">
        <?php if ($s['pricing_model'] === 'per_province'): ?>
          <label for="prov">Province</label><select id="prov" name="province" required>
          <?php foreach (Catalog::PROVINCES as $c => $n): if (!array_key_exists($c, $s['province_prices'])) continue; $pv = $s['province_prices'][$c]; ?>
            <option value="<?= $c ?>"<?= $pv === null ? ' disabled' : '' ?><?= $c === 'NB' ? ' selected' : '' ?>><?= e($n) ?> — <?= $pv === null ? 'not offered' : money($pv) ?></option><?php endforeach; ?></select>
        <?php elseif (!empty($s['options']['tlds'])): ?>
          <label for="dom">Domain name</label><input id="dom" name="domain" type="text" placeholder="mybusiness" required>
          <label for="tld" style="margin-top:10px">Extension</label><select id="tld" name="tld"><?php foreach ($s['options']['tlds'] as $t => $c): ?><option value="<?= e($t) ?>">.<?= e($t) ?> — <?= money($c) ?></option><?php endforeach; ?></select>
        <?php endif; ?>
        <button class="btn btn-primary" style="width:100%;margin-top:14px">Add to cart</button></form>
        <button class="btn btn-ghost" style="width:100%;margin-top:8px" type="button" data-chat="I have a question about <?= e($s['name']) ?>">Ask a question</button>
      <?php endif; ?>
    </aside>
  </div>
  <?php if ($related): ?><h2 style="margin-top:48px;font-size:1.3rem">Related</h2><div class="grid g3"><?php foreach ($related as $x): ?><a class="card card-link" href="/services/<?= e($x['slug']) ?>"><h3><?= e($x['name']) ?></h3><b><?= e(Catalog::priceLabel($x)) ?></b></a><?php endforeach; ?></div><?php endif; ?>
</div></section>
