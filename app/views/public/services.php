<?php use App\Catalog; ?>
<?php $ph = ['img' => 'laptop', 'title' => 'Services and prices', 'lead' => 'All in CAD. Registration prices include the government fee. Custom work is quoted in writing.']; include APP_ROOT . '/views/partials/photo-head.php'; ?>
<section class="section tight"><div class="wrap">
  <?php foreach ($cats as $k => $c): if (!$c['services']) continue; ?>
  <h2 id="<?= e($k) ?>" style="margin-top:42px"><?= e($c['name']) ?></h2>
  <div class="grid g3">
  <?php foreach ($c['services'] as $s): ?>
    <a class="card card-link" href="/services/<?= e($s['slug']) ?>"><h3><?= e($s['name']) ?></h3><p class="muted small"><?= e($s['summary']) ?></p>
      <div class="row spread"><b><?= e(Catalog::priceLabel($s)) ?></b><?php if ($s['timeline']): ?><span class="badge"><?= e($s['timeline']) ?></span><?php endif; ?></div></a>
  <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php if ($bundle): ?><div class="card" id="bundle" style="margin-top:40px"><div class="row spread"><div><span class="badge b-blue">Bundle · save <?= (int)$bundle['discount_pct'] ?>%</span><h3 style="margin-top:8px"><?= e($bundle['name']) ?></h3><p class="muted"><?= e($bundle['description']) ?></p></div><a class="btn btn-primary" href="/#bundle">Configure bundle</a></div></div><?php endif; ?>
</div></section>
