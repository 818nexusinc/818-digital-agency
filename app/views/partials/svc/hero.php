<?php /* Service page header, as on the original site: tag pills, title, one-line summary, optional actions.
   $hero = ['tone' => blue|teal|amber|violet|green|orange|rose|sky, 'pills' => [], 'title' => '', 'sub' => '', 'icon' => icon name, 'back' => [href, label], 'actions' => [[href, label, primary?]]] */
include_once APP_ROOT . '/views/partials/icons.php';
?>
<section class="svc-hero tone-h-<?= e($hero['tone'] ?? 'blue') ?>"><div class="wrap">
  <?php if (!empty($hero['back'])): ?><a class="sh-back" href="<?= e($hero['back'][0]) ?>">← <?= e($hero['back'][1]) ?></a><?php endif; ?>
  <?php if (!empty($hero['pills'])): ?><ul class="sh-pills"><?php foreach ($hero['pills'] as $hp): ?><li><?= e($hp) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="sh-row"><?php if (!empty($hero['icon'])): ?><span class="sh-ic"><?= nx_icon($hero['icon']) ?></span><?php endif; ?><h1><?= e($hero['title']) ?></h1></div>
  <?php if (!empty($hero['sub'])): ?><p class="sh-sub"><?= e($hero['sub']) ?></p><?php endif; ?>
  <?php if (!empty($hero['actions'])): ?><div class="hero-actions"><?php foreach ($hero['actions'] as $a): ?><a class="btn <?= !empty($a[2]) ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($a[0]) ?>"><?= e($a[1]) ?></a><?php endforeach; ?></div><?php endif; ?>
</div></section>
