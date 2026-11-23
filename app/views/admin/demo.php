<div class="topbar"><div><h1>Demo data</h1><p class="muted" style="margin:4px 0 0">Sample customers, leads, orders, tickets, unfinished forms, a chat and blog posts, so you can try every screen. Every sample row is tracked, so one click removes all of it and nothing else.</p></div></div>
<div class="grid g2" style="align-items:start">
  <div class="panel"><h2><?= $count ? (int)$count . ' demo records loaded' : 'No demo data loaded' ?></h2>
    <?php if ($by): ?><ul class="small"><?php foreach ($by as $b): ?><li><?= e(label($b['tbl'])) ?>: <?= (int)$b['n'] ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if (!$count): ?><form method="post"><?= App\Csrf::field() ?><input type="hidden" name="action" value="seed"><button class="btn btn-primary">Load demo data</button></form>
    <?php else: ?><p class="small muted">Demo emails end in @example.com and order numbers start with DEMO-, so they are easy to spot. Demo blog posts are public while loaded.</p><?php endif; ?></div>
  <div class="panel"><h2>Delete before launch</h2>
    <p class="small muted">Removes every demo record listed here. Real customers, orders and articles are never touched.</p>
    <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="purge">
      <label>Type DELETE to confirm<input name="confirm" autocomplete="off" placeholder="DELETE"<?= $count ? '' : ' disabled' ?>></label>
      <button class="btn btn-dark"<?= $count ? '' : ' disabled' ?>>Delete all demo data</button></form></div>
</div>
