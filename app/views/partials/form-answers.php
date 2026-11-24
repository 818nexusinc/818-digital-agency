<?php
/* What a customer entered on a website form, laid out by the form's own steps. Needs $fa_slug and $fa_data; optional $fa_compact. */
$fa_secs = App\FormSchema::sections((string)$fa_slug, (array)$fa_data, $fa_path ?? null);
if ($fa_secs): ?>
<div class="fa<?= !empty($fa_compact) ? ' fa-compact' : '' ?>">
  <?php foreach ($fa_secs as $sec): ?>
  <section class="fa-sec<?= $sec['title'] === 'Customer authorization' ? ' fa-auth' : '' ?>">
    <h4><?= e($sec['title']) ?></h4>
    <?php if ($sec['rows']): ?><dl><?php foreach ($sec['rows'] as [$l, $v]): ?><div><dt><?= e($l) ?></dt><dd><?= nl2br(e($v)) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
    <?php foreach ($sec['groups'] as $g): ?><div class="fa-grp"><h5><?= e($g['title']) ?></h5><dl><?php foreach ($g['rows'] as [$l, $v]): ?><div><dt><?= e($l) ?></dt><dd><?= nl2br(e($v)) ?></dd></div><?php endforeach; ?></dl></div><?php endforeach; ?>
  </section>
  <?php endforeach; ?>
</div>
<?php endif; unset($fa_secs, $fa_compact, $fa_path);
