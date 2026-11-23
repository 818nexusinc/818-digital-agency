<?php
/* Page header. Text on the left; optional photo or video on the right with floating info chips.
   $hero = ['tone', 'pills' => [], 'title', 'sub', 'icon', 'back' => [href, label], 'actions' => [[href, label, primary?]],
            'media' => ['img' => name] | ['video' => name, 'poster' => name], 'chips' => [[icon, title, sub], …], 'proof' => [text, …]] */
include_once APP_ROOT . '/views/partials/icons.php';
$hm = $hero['media'] ?? null;
$land = !empty($hero['landing']); $pk = pillar($path ?? '/'); $lp = PILLARS[$pk] ?? PILLARS['brand'];
// Breadcrumb: Home / business / this page
$bc = [['/', 'Home']];
if ($pk !== 'brand' && $lp['home'] !== ($path ?? '')) $bc[] = [$lp['home'], $lp['name']];
$bc[] = [null, $crumb ?? ($pk !== 'brand' && $lp['home'] === ($path ?? '') ? $lp['name'] : $hero['title'])];
// Pages with an order form get a clear first step
$acts = $hero['actions'] ?? [];
if (!$acts && !$land && !empty($hero['form'])) $acts = [['#start', 'Start now', true], ['/contact', 'Ask a question']];
$facts = $hero['chips'] ?? [];
// Quick-start card for pages with an order form: price, turnaround and what to have ready.
$QS = ['/register/corporation' => ['corp_numbered', '', ['Proposed name, or numbered', 'Director names and addresses', 'Registered office address']],
  '/register/sole-proprietorship' => ['sole_prop', '', ['Business name', 'Your name and address', 'What the business does']],
  '/register/partnership' => ['partnership', '', ['Partnership name', 'Each partner’s details', 'How profits are shared']],
  '/register/cooperative' => ['cooperative', '', ['Co-op name and purpose', 'Three or more founding members']],
  '/register/nuans' => ['nuans', '', ['Up to three proposed names', 'What the business does']],
  '/manage-business/annual-returns' => ['bm_annual', '', ['Corporation number', 'Year you are filing for']],
  '/manage-business/corporate-changes' => ['bm_changes', '', ['Corporation number', 'What changed and when']],
  '/manage-business/corporate-documents' => ['bm_documents', '', ['Corporation number', 'Which documents you need']],
  '/manage-business/name-services' => ['biz_name', '', ['Current registered name', 'New name, if changing']],
  '/domain' => ['domain_register', '/yr', ['The name you want']],
  '/email' => ['email_starter', '/mo', ['Your domain, or we register one', 'Mailbox names']],
  '/website' => ['website_launch', '/mo', ['Your plan', 'Pages you need', 'Logo and photos, if you have them']],
  '/website/maintenance' => ['maintenance_care', '/mo', ['Your website address', 'Platform it runs on']],
  '/web-application' => ['web_app', '', ['What it should do', 'Who will use it']]];
$qs = null;
if (!empty($hero['form']) && isset($QS[$path ?? ''])) {
    [$qslug, $qsuf, $qneeds] = $QS[$path];
    $qsv = App\Catalog::find($qslug);
    $qs = ['price' => $qsv ? App\Catalog::fromPrice($qsv) : null, 'suf' => $qsuf, 'time' => $qsv['timeline'] ?? '', 'needs' => $qneeds];
}
?>
<section class="svc-hero tone-h-<?= e($hero['tone'] ?? 'blue') ?><?= $hm ? ' split' : '' ?><?= $land ? ' landing land-' . $lp['tone'] : '' ?>"<?= $land ? ' style="--land-bg:' . ($lp['tone'] === 'dark' ? $lp['deep'] : $lp['soft']) . '"' : '' ?>><div class="wrap">
  <div class="sh-text">
    <nav class="crumbs" aria-label="<?= e(t('Breadcrumb')) ?>"><?php foreach ($bc as $i => [$h, $n]): ?><?php if ($i): ?><span aria-hidden="true">/</span><?php endif; ?><?php if ($h): ?><a href="<?= e($h) ?>"><?= e(t($n)) ?></a><?php else: ?><b aria-current="page"><?= e(t($n)) ?></b><?php endif; ?><?php endforeach; ?></nav>
    <?php if (!empty($hero['back'])): ?><a class="sh-back" href="<?= e($hero['back'][0]) ?>">← <?= e(t($hero['back'][1])) ?></a><?php endif; ?>
    <?php if (!empty($hero['pills'])): ?><ul class="sh-pills"><?php foreach ($hero['pills'] as $hp): ?><li><?= e(t($hp)) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <h1 data-rv><?= e(t($hero['title'])) ?></h1>
    <?php if (!empty($hero['sub'])): ?><p class="sh-sub" data-rv><?= e(t($hero['sub'])) ?></p><?php endif; ?>
    <?php if ($acts): ?><div class="hero-actions" data-rv><?php foreach ($acts as $a): ?><a class="btn btn-lg <?= !empty($a[2]) ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($a[0]) ?>"><?= e(t($a[1])) ?></a><?php endforeach; ?></div><?php endif; ?>
    <?php if (!empty($hero['proof'])): ?><ul class="sh-proof"><?php foreach ($hero['proof'] as $pr): ?><li><?= e(t($pr)) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
  <?php if ($hm): ?><div class="sh-media" data-rv>
    <div class="sh-frame" data-tilt>
      <?php if (!empty($hm['video'])): ?><video autoplay muted loop playsinline preload="metadata" poster="/media/<?= e($hm['poster'] ?? $hm['video'] . '-poster') ?>.webp" aria-hidden="true" data-autoplay><source src="/media/<?= e($hm['video']) ?>.mp4" type="video/mp4"></video>
      <?php else: ?><img src="/media/<?= e($hm['img']) ?>.webp" alt="" width="1200" height="800" fetchpriority="high" decoding="async"><?php endif; ?>
    </div>
    <?php if ($qs): ?><aside class="qs" aria-label="<?= e(t('Quick start')) ?>">
      <div class="qs-top"><span><small><?= e(t($qs['price'] === null ? 'Pricing' : 'From')) ?></small><b><?= $qs['price'] === null ? e(t('Written quote')) : e(money($qs['price'])) . '<i>' . e(str_replace(['/mo', '/yr'], ['/' . t('mo'), '/' . t('yr')], $qs['suf'])) . '</i>' ?></b></span><span><small><?= e(t('Turnaround')) ?></small><b class="qs-t"><?= e(t($qs['time'])) ?></b></span></div>
      <p class="qs-k"><?= e(t('Have this ready')) ?></p>
      <ul><?php foreach ($qs['needs'] as $n): ?><li><?= e(t($n)) ?></li><?php endforeach; ?></ul>
    </aside><?php endif; ?>
  </div><?php endif; ?>
</div>
<?php if ($facts): ?><div class="wrap"><ul class="hero-facts" style="--fc:<?= count($facts) ?>"><?php foreach ($facts as [$ic, $ct, $cs]): ?><li><span class="hf-ic"><?= nx_icon($ic) ?></span><span><b><?= e(t($ct)) ?></b><small><?= e(t($cs)) ?></small></span></li><?php endforeach; ?></ul></div><?php endif; ?>
</section>
