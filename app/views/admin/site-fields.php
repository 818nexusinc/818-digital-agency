<?php
/** Field list for the Site editor (also returned on its own after the preview loads). */
$groups = ['main' => [], 'shared' => []];
foreach ($fields as $i => $f) $groups[$f['shared'] ? 'shared' : 'main'][] = [$i, $f];
$names = ['main' => 'On this page', 'shared' => 'Menu, header and footer'];
$tips = ['main' => 'Changes here apply to this page unless you choose “Every page”.', 'shared' => 'These appear on every page, so changes apply everywhere unless you choose “This page”.'];
?>
<?php if (!$fields): ?>
  <div class="se-empty" data-se-empty><b>Reading this page…</b><p>The preview on the right is loading. Its text appears here in a moment.</p></div>
<?php else: foreach ($groups as $g => $list): if (!$list) continue; ?>
  <section class="se-group">
    <header><h2><?= e($names[$g]) ?> <span><?= count($list) ?></span></h2><p><?= e($tips[$g]) ?></p></header>
    <?php foreach ($list as [$i, $f]): $long = mb_strlen($f['src']) > 70; ?>
    <div class="sf<?= $f['edited'] ? ' is-edited' : '' ?>" data-sf data-key="<?= e($f['key']) ?>" data-src="<?= e($f['src']) ?>" data-fr-default="<?= e($f['frDefault']) ?>" data-live="<?= e($f['en']) ?>">
      <div class="sf-h">
        <span class="sf-badge"><?= $f['edited'] ? 'Changed' : '' ?></span>
        <select data-scope aria-label="Where this change applies"><option value="one"<?= $f['scope'] === 'one' ? ' selected' : '' ?>>This page</option><option value="all"<?= $f['scope'] === 'all' ? ' selected' : '' ?>>Every page</option></select>
        <button type="button" class="sf-reset" data-reset<?= $f['edited'] ? '' : ' hidden' ?>>Undo change</button>
      </div>
      <label class="sf-l"><span>English</span><textarea data-l="en" rows="<?= $long ? 3 : 1 ?>" spellcheck="true"><?= e($f['en']) ?></textarea></label>
      <label class="sf-l"><span>French</span><textarea data-l="fr" rows="<?= $long ? 3 : 1 ?>" lang="fr" spellcheck="true" placeholder="<?= e($f['frDefault'] ?: 'Same as English') ?>"><?= e($f['fr']) ?></textarea></label>
      <?php if ($f['edited'] && $f['en'] !== $f['src']): ?><p class="sf-was">Originally: <?= e(str_limit($f['src'], 160)) ?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
<?php endforeach; endif; ?>
