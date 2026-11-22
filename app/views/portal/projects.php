<?php $badge = function ($s) { include APP_ROOT . '/views/partials/status.php'; }; ?>
<div class="topbar"><h1>Projects</h1></div>
<?php if (!$projects): ?><div class="empty">No projects yet. Projects open automatically when an order is paid.</div><?php endif; ?>
<div class="grid g2"><?php foreach ($projects as $p): ?><a class="card card-link" href="/portal/projects/<?= e($p['number']) ?>"><div class="row spread"><h3 style="margin:0"><?= e($p['title']) ?></h3><?php $badge($p['status']); ?></div><p class="small muted"><?= e($p['number']) ?> · updated <?= e(substr($p['updated_at'], 0, 10)) ?></p><div class="progress"><span style="width:<?= (int)$p['progress'] ?>%"></span></div></a><?php endforeach; ?></div>
