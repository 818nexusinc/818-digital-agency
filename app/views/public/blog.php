<section class="section tight"><div class="wrap">
  <span class="eyebrow">Blog</span><h1 style="margin-top:8px"><?= e($title) ?></h1>
  <?php if (!$posts): ?><div class="empty">No articles published yet. Our content team is working on the first guides.</div><?php endif; ?>
  <div class="stack"><?php foreach ($posts as $p): ?><a class="card card-link" href="/blog/<?= e($p['slug']) ?>"><h3><?= e($p['title']) ?></h3><p class="muted small"><?= e($p['excerpt']) ?></p><span class="small muted"><?= e(substr((string)($p['publish_at'] ?? $p['updated_at']), 0, 10)) ?></span></a><?php endforeach; ?></div>
</div></section>
