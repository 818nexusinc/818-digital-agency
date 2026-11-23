<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/icons.php';
$min = function (string $slug) { $s = Catalog::find($slug); $p = $s ? Catalog::fromPrice($s) : null; return $p; };
$nuans = $min('nuans'); $sole = $min('sole_prop'); $part = $min('partnership'); $corp = $min('corp_numbered'); $coop = $min('cooperative');
$hero = ['tone' => 'blue', 'pills' => ['Every province + federal', 'From ' . money(min(array_filter([$sole, $part, $corp])))],
  'title' => 'Your business, officially registered.', 'sub' => 'Pick a structure, answer a few questions and we file it with the registry. Government fees included.',
  'actions' => [['#structures', 'Choose your structure', true], ['/register/nuans', 'NUANS name search (' . money($nuans) . ')']],
  'media' => ['video' => 'team-talk'], 'chips' => [['bolt', 'Worked on immediately', 'As soon as your order is in'], ['shield', 'Filed with the registry', 'Status in your portal']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$cols = [
  ['Just checking a name', 'NUANS Name Search', $nuans, 'one-time', ['Official report, same day', 'Valid 90 days', 'Needed for named corporations'], '/register/nuans', 'Search a name'],
  ['Just me, starting out', 'Sole Proprietorship', $sole, 'gov. fee included', ['Fastest and simplest', 'No NUANS needed', 'Any province'], '/register/sole-proprietorship', 'Register'],
  ['Two or more owners', 'Partnership', $part, 'gov. fee included', ['General or limited', 'Agreement template', 'Any province'], '/register/partnership', 'Register'],
  ['Ready to grow', 'Corporation', $corp, 'gov. fee included', ['Limited liability', 'Provincial or federal', 'Named or numbered'], '/register/corporation', 'Incorporate'],
  ['Owned by members', 'Co-operative', $coop, 'written quote', ['One member, one vote', 'Bylaws template', 'Quote in 1 business day'], '/register/cooperative', 'Get a quote'],
];
?>
<section class="sec chooser-sec" id="structures"><div class="wrap">
  <div class="sec-head"><h2>Pick your structure.</h2><p>Not sure? Start with what describes you.</p></div>
  <?php $featured = 3; include APP_ROOT . '/views/partials/svc/chooser.php'; ?>
</div></section>
<?php $faqs = [
  ['Which business structure is right for me?', 'Sole proprietorships are simplest for one owner. Partnerships suit two or more owners. Corporations give limited liability and tax flexibility. Co-operatives are member-owned and democratically run.'],
  ['Do I need a NUANS name search before I register?', 'Only for a named corporation, federally and in most provinces. Sole proprietorships, partnerships and numbered corporations don’t need one.'],
  ['Which provinces do you cover?', 'Every province and federal incorporation under the CBCA. Territories are coming soon.'],
  ['How long does registration take?', 'Usually 1 to 7 business days depending on the structure and registry, once your order is paid.'],
  ['What documents will I receive?', 'Your registration or incorporation certificate, the filed documents and next-step guidance, all saved in your portal.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
