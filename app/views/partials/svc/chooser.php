<?php /* One-row chooser: open columns separated by hairlines, each answering "which one is for me?".
   $cols = [[situation, name, cents|null, price note, [facts], href, button], …]; $featured = index of the highlighted column. */ ?>
<div class="chooser" style="--n:<?= count($cols) ?>">
<?php foreach ($cols as $i => [$sit, $name, $cents, $note, $facts, $href, $btn]): ?>
  <article class="cz<?= ($featured ?? -1) === $i ? ' cz-on' : '' ?>">
    <p class="cz-sit"><?= e(t($sit)) ?></p>
    <h3><a href="<?= e($href) ?>"><?= e(t($name)) ?></a></h3>
    <p class="cz-price"><?php if ($cents !== null): ?><small><?= e(t('From')) ?></small> <b><?= e(money($cents)) ?></b><span><?= e(t($note)) ?></span><?php else: ?><b class="cz-q"><?= e(t(ucfirst($note))) ?></b><?php endif; ?></p>
    <ul><?php foreach ($facts as $f): ?><li><?= e(t($f)) ?></li><?php endforeach; ?></ul>
    <a class="btn <?= ($featured ?? -1) === $i ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($href) ?>"><?= e(t($btn)) ?></a>
  </article>
<?php endforeach; ?>
</div>
