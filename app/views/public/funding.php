<?php
include_once APP_ROOT . '/views/partials/icons.php';
$hero = ['landing' => true, 'tone' => 'blue', 'pills' => ['Federal & regional programs'], 'title' => 'Funding is out there. Find yours.', 'sub' => 'Grants, loans and procurement programs for Canadian entrepreneurs, linked to each official page.',
  'media' => ['img' => 'team-deal'], 'chips' => [['cash', 'Official sources', 'Links to every program'], ['check', 'Get registered first', 'Most programs require it']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$progs = [
  ['Federal funding', 'Innovative Solutions Canada (ISC)', 'ISED / Innovation Canada', 'https://ised-isde.canada.ca/site/innovative-solutions-canada/en', 'Federal departments challenge Canadian small businesses to solve real problems with innovation, funding proof of feasibility and prototype development, and can become your first government customer.', ['Canadian for-profit business', 'Small business (499 or fewer employees)', 'R&D carried out in Canada'], ['Phased funding for feasibility and prototypes', 'Government may buy the result', 'Non-dilutive: no equity given up']],
  ['Federal funding', 'Industrial Research Assistance Program (IRAP)', 'National Research Council of Canada', 'https://nrc.canada.ca/en/support-technology-innovation', 'Advice, connections and funding for innovative small and medium-sized businesses developing new technology.', ['Canadian incorporated SME', 'Generally fewer than 500 employees', 'Pursuing technology innovation'], ['Non-repayable contributions for R&D', 'Access to industry technology advisors', 'Can cover staff and contractor costs']],
  ['Regional development', 'Atlantic Canada Opportunities Agency (ACOA)', 'ACOA', 'https://www.canada.ca/en/atlantic-canada-opportunities.html', 'Programs for Atlantic Canadian businesses to grow, innovate, adopt technology and reach new markets, including the Regional Artificial Intelligence Initiative.', ['Business in an Atlantic province', 'Project that supports growth or innovation'], ['Repayable and non-repayable contributions', 'AI and technology adoption support', 'Market expansion projects']],
  ['Financing', 'Canada Small Business Financing Program', 'ISED', 'https://ised-isde.canada.ca/site/canada-small-business-financing-program/en', 'Makes it easier for small businesses to get loans from financial institutions by sharing the risk with lenders.', ['Small business with gross revenue under the program limit', 'Apply through your bank or credit union'], ['Loans for equipment, property and improvements', 'Lines of credit for working capital']],
  ['Youth & skills', 'Digital Skills for Youth', 'ISED', 'https://ised-isde.canada.ca/site/digital-skills-youth/en', 'Funding through partner organizations to hire post-secondary graduates for digital work placements.', ['Canadian businesses and non-profits', 'Hiring underemployed post-secondary graduates'], ['Wage subsidies for digital roles', 'Supports digital transformation projects']],
  ['Provincial', 'Opportunities New Brunswick', 'Government of New Brunswick', 'https://onbcanada.ca/', 'New Brunswick’s business development agency: support for companies growing, exporting or investing in the province.', ['Business operating in New Brunswick'], ['Financial assistance for growth projects', 'Export and investment support']],
];
?>
<section class="sec" style="padding-top:36px"><div class="wrap narrow">
  <p class="note-box">Programs change often. Always confirm eligibility, amounts and deadlines on the official program page before applying.</p>
  <div style="display:grid;gap:14px">
  <?php foreach ($progs as [$kind, $t, $by, $url, $d, $elig, $hi]): ?>
    <article class="prog"><div class="prog-head"><div><span class="prog-meta"><?= e($kind) ?> · <?= e($by) ?></span><h3><?= e($t) ?></h3></div><a class="btn btn-ghost btn-sm" href="<?= e($url) ?>" target="_blank" rel="noopener">Learn more ↗</a></div>
      <p><?= e($d) ?></p>
      <div class="prog-cols"><div><b>Eligibility</b><ul><?php foreach ($elig as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><div><b>Highlights</b><ul><?php foreach ($hi as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div></div></article>
  <?php endforeach; ?>
  </div>
</div></section>
