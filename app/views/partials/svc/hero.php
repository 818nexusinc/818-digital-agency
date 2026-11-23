<?php
/* Page header. Text on the left; optional photo or video on the right with floating info chips.
   $hero = ['tone', 'pills' => [], 'title', 'sub', 'icon', 'back' => [href, label], 'actions' => [[href, label, primary?]],
            'media' => ['img' => name] | ['video' => name, 'poster' => name], 'chips' => [[icon, title, sub], …], 'proof' => [text, …]] */
include_once APP_ROOT . '/views/partials/icons.php';
$hm = $hero['media'] ?? null;
$land = !empty($hero['landing']); $lp = PILLARS[pillar($path ?? '/')] ?? PILLARS['brand'];
?>
<section class="svc-hero tone-h-<?= e($hero['tone'] ?? 'blue') ?><?= $hm ? ' split' : '' ?><?= $land ? ' landing land-' . $lp['tone'] : '' ?>"<?= $land ? ' style="--land-bg:' . ($lp['tone'] === 'dark' ? $lp['deep'] : $lp['soft']) . '"' : '' ?>><div class="wrap">
  <div class="sh-text">
    <?php if (!empty($hero['back'])): ?><a class="sh-back" href="<?= e($hero['back'][0]) ?>">← <?= e(t($hero['back'][1])) ?></a><?php endif; ?>
    <?php if (!empty($hero['pills'])): ?><ul class="sh-pills"><?php foreach ($hero['pills'] as $hp): ?><li><?= e(t($hp)) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <h1 data-rv><?= e(t($hero['title'])) ?></h1>
    <?php if (!empty($hero['sub'])): ?><p class="sh-sub" data-rv><?= e(t($hero['sub'])) ?></p><?php endif; ?>
    <?php if (!empty($hero['actions'])): ?><div class="hero-actions" data-rv><?php foreach ($hero['actions'] as $a): ?><a class="btn btn-lg <?= !empty($a[2]) ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($a[0]) ?>"><?= e(t($a[1])) ?></a><?php endforeach; ?></div><?php endif; ?>
    <?php if (!empty($hero['proof'])): ?><ul class="sh-proof"><?php foreach ($hero['proof'] as $pr): ?><li><?= e(t($pr)) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
  <?php if ($hm): ?><div class="sh-media" data-rv>
    <div class="sh-frame">
      <?php if (!empty($hm['video'])): ?><video autoplay muted loop playsinline preload="metadata" poster="/media/<?= e($hm['poster'] ?? $hm['video'] . '-poster') ?>.webp" aria-hidden="true" data-autoplay><source src="/media/<?= e($hm['video']) ?>.mp4" type="video/mp4"></video>
      <?php else: ?><img src="/media/<?= e($hm['img']) ?>.webp" alt="" width="1400" height="934" fetchpriority="high"><?php endif; ?>
    </div>
    <?php if (!empty($hero['chips'])): ?><p class="sh-cap"><?php foreach (array_slice($hero['chips'], 0, 2) as [$ic, $ct, $cs]): ?><span><b><?= e(t($ct)) ?></b> <?= e(t($cs)) ?></span><?php endforeach; ?></p><?php endif; ?>
  </div><?php endif; ?>
</div></section>
