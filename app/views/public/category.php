<?php use App\Catalog;
$imgs = ['registration' => 'owner-open', 'management' => 'signing', 'domains' => 'welcome', 'email' => 'desk-poster', 'websites' => 'laptop', 'apps' => 'developer', 'growth' => 'baker'];
$leads = ['registration' => 'Every province and Corporations Canada. Government fees included.', 'management' => 'Annual returns, changes and records, filed on time.', 'domains' => '.ca and .com, checked live against the registry.',
  'email' => 'Professional addresses on your own domain.', 'websites' => 'Fast, mobile-ready sites you can grow into.', 'apps' => 'Booking systems, portals and tools built around how you work.', 'growth' => 'Brand, local search and AI-search visibility.'];
$ph = ['img' => $imgs[$cat] ?? 'owner-open', 'title' => Catalog::CATEGORIES[$cat], 'lead' => $leads[$cat] ?? '', 'crumb' => ['/services' => 'Services']];
include APP_ROOT . '/views/partials/photo-head.php'; ?>
<section class="section tight"><div class="wrap">
  <?php if ($cat === 'domains'): ?><div class="card" style="margin:18px 0"><form id="domain-check" class="row"><label class="sr" for="domain-input">Domain</label><input id="domain-input" type="text" placeholder="mybusiness.ca" style="flex:1;min-width:200px"><button class="btn btn-primary">Check availability</button></form><div id="domain-result" style="margin-top:12px" aria-live="polite"></div></div><?php endif; ?>
  <div class="grid g3" style="margin-top:22px">
  <?php foreach ($services as $s): ?>
    <div class="card"><h3><?= e($s['name']) ?></h3><p class="muted small"><?= e($s['summary']) ?></p><div class="price"><?= e(Catalog::priceLabel($s)) ?></div>
      <?php if ($s['deliverables']): ?><ul class="list small"><?php foreach (array_slice($s['deliverables'], 0, 4) as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="/services/<?= e($s['slug']) ?>">Details<?= $s['pricing_model'] === 'quote' ? ' &amp; quote' : ' &amp; order' ?></a></div>
  <?php endforeach; ?>
  </div>
</div></section>
