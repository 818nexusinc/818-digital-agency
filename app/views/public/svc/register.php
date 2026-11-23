<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/icons.php';
$min = function (string $slug) { $s = Catalog::find($slug); $p = $s ? Catalog::fromPrice($s) : null; return $p; };
$nuans = $min('nuans'); $sole = $min('sole_prop'); $part = $min('partnership'); $corp = $min('corp_numbered'); $coop = $min('cooperative');
$hero = ['tone' => 'blue', 'pills' => ['Every province + federal', 'From ' . money(min(array_filter([$sole, $part, $corp])))],
  'title' => 'Your business, officially registered.', 'sub' => 'Pick a structure, answer a few questions and we file it with the registry. Government fees included.',
  'actions' => [['#structures', 'Choose your structure', true], ['/register/nuans', 'NUANS name search (' . money($nuans) . ')']],
  'media' => ['video' => 'team-talk'], 'chips' => [['pen', 'You approve and sign', 'Before anything is filed'], ['shield', 'Filed with the registry', 'Status in your portal']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$cards = [
  ['nuans', '/register/nuans', 'search', 'violet', '#6d3fd8', 'Start here', 'NUANS Name Search', 'Check your business name is available before you incorporate.', $nuans, 'One-time · PDF report', ['Searches registered names and trademarks across Canada', 'Report usually the same day', 'Valid for 90 days', 'Up to 3 proposed names']],
  ['sole', '/register/sole-proprietorship', 'user', 'teal', '#0f7c74', 'Most popular', 'Sole Proprietorship', 'The fastest, simplest way to start operating as a business in Canada.', $sole, 'From · government fee included', ['No NUANS search needed', 'Register a trade name in your province', 'Lowest-cost structure', 'Guidance on CRA business number']],
  ['part', '/register/partnership', 'users', 'blue', '#2457d6', 'For teams', 'Partnership', 'Run a business with one or more partners under a shared name.', $part, 'From · general or limited', ['General and limited partnerships', 'Every province', 'Partnership agreement template', 'Confirmation document']],
  ['corp', '/register/corporation', 'building', 'amber', '#b86a00', 'Best for growth', 'Corporation (Inc.)', 'Incorporate with limited liability protection under Canadian law.', $corp, 'From · provincial or federal', ['Provincial or federal (CBCA)', 'Named or numbered', 'Articles of incorporation filed', 'Minute book, seal and registered address available']],
  ['coop', '/register/cooperative', 'network', 'green', '#178a43', 'Community owned', 'Co-operative', 'Register a member-owned, democratically controlled co-operative.', $coop, 'From · quoted for your province', ['Worker, consumer, producer and multi-stakeholder co-ops', 'Articles and by-laws template', 'Member register template', 'Every province']],
];
?>
<section class="feature-row-wrap"><div class="wrap feature-row">
  <?php foreach ([['users', 'teal', 'Personal liability protection', 'Incorporating separates your personal assets from the business.'], ['cash', 'blue', 'Tax planning', 'Corporations can access the small business tax rate.'], ['stack', 'violet', 'Investment ready', 'Corporations can issue shares, making it easier to attract investors.'], ['certificate', 'amber', 'Official government filing', 'We file directly with the provincial or federal registry for you.']] as [$ic, $tone, $t, $d]): ?>
  <div><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span><b><?= e($t) ?></b><p><?= e($d) ?></p></span></div>
  <?php endforeach; ?>
</div></section>
<section class="sec" id="structures"><div class="wrap">
  <div class="sec-head center"><h2>Choose your business structure</h2><p>Each structure has different legal, tax and operational implications. Pick one to see what's included, then start your registration.</p></div>
  <div class="hub-grid">
  <?php foreach ($cards as [$id, $href, $ic, $tone, $col, $flag, $t, $d, $price, $sub, $list]): ?>
    <article class="hub-card" data-rv id="hc-<?= $id ?>" style="--tone:<?= $col ?>">
      <div class="hc-top"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span class="hc-flag"><?= e($flag) ?></span></div>
      <h3><?= e($t) ?></h3><p class="hc-d"><?= e($d) ?></p>
      <p class="hc-price"><b><?= $price !== null ? e(money($price)) : 'Quote' ?></b><small><?= e($sub) ?></small></p>
      <ul class="hc-list"><?php foreach ($list as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ul>
      <div class="hc-actions"><a class="btn btn-primary" href="<?= e($href) ?>">Get started</a></div>
    </article>
  <?php endforeach; ?>
  </div>
  <div class="quiz"><h3>Not sure which structure to choose?</h3><p>Start as a <b>sole proprietor</b> if you're testing an idea or freelancing. <b>Incorporate</b> when you need liability protection or plan to raise investment. Choose a <b>partnership</b> when two or more people run the business together, and a <b>co-operative</b> for member-owned democratic businesses.</p>
    <div class="quiz-grid">
      <?php foreach ([['sole', 'user', 'teal', 'Just me, starting out', 'Sole proprietorship'], ['part', 'users', 'blue', 'Two or more founders', 'Partnership'], ['corp', 'building', 'amber', 'Need liability protection', 'Corporation'], ['coop', 'network', 'green', 'Community-owned business', 'Co-operative'], ['nuans', 'search', 'violet', 'Need to check my name first', 'NUANS name search']] as [$id, $ic, $tone, $s, $b]): ?>
      <a href="#hc-<?= $id ?>" data-jump="hc-<?= $id ?>"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span><small><?= e($s) ?></small><b><?= e($b) ?> →</b></span></a>
      <?php endforeach; ?>
    </div></div>
</div></section>
<?php $faqs = [
  ['Which business structure is right for me?', 'Sole proprietorships are simplest for one owner. Partnerships suit two or more owners. Corporations give limited liability and tax flexibility. Co-operatives are member-owned and democratically run.'],
  ['Do I need a NUANS name search before I register?', 'Only for a named corporation, federally and in most provinces. Sole proprietorships, partnerships and numbered corporations don’t need one.'],
  ['Which provinces do you cover?', 'Every province and federal incorporation under the CBCA. Territories are coming soon.'],
  ['How long does registration take?', 'Usually 1 to 7 business days depending on the structure and registry, once you have signed the final filing.'],
  ['What documents will I receive?', 'Your registration or incorporation certificate, the filed documents and next-step guidance, all saved in your portal.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
