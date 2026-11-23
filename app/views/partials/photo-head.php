<?php /* Light page header with a photo. Expects $ph = ['img' => media name, 'title' => '', 'lead' => '', 'crumb' => [href => label] (optional)]. */ ?>
<section class="photo-head"><div class="wrap">
  <img src="/media/<?= e($ph['img']) ?>.webp" alt="" aria-hidden="true" width="1200" height="750">
  <div>
    <?php if (!empty($ph['crumb'])): ?><nav class="ph-crumb" aria-label="Breadcrumb"><?php foreach ($ph['crumb'] as $h => $l): ?><a href="<?= e($h) ?>"><?= e($l) ?></a> / <?php endforeach; ?><span><?= e($ph['title']) ?></span></nav><?php endif; ?>
    <h1><?= e($ph['title']) ?></h1>
    <?php if (!empty($ph['lead'])): ?><p class="lead"><?= e($ph['lead']) ?></p><?php endif; ?>
  </div>
</div></section>
