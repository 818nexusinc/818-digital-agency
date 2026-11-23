<?php
/* Main navigation, matching the original 818digitalagency.com structure:
   Register Business ▾ · Manage Business ▾ · Web Services ▾ · Bundle & Save · Business Opportunities.
   Prices come from the live catalog, never typed in. */
use App\Catalog;
include_once APP_ROOT . '/views/partials/icons.php';
$fp = function (string $slug, string $suffix = '') { $s = Catalog::find($slug); $p = $s ? Catalog::fromPrice($s) : null; return $p === null ? 'Custom quote' : 'From ' . money($p) . $suffix; };
$menus = [
  'register' => ['label' => 'Register Business', 'title' => 'Make it official.', 'sub' => 'Every province and federal. Government fees included.',
    'all' => ['/register', 'Explore Register Business'],
    'items' => [
      ['nuans', 'search', 'NUANS Name Search', 'Check your name is free.', $fp('nuans')],
      ['sole_prop', 'user', 'Sole Proprietorship', 'Simplest. Fastest. One owner.', $fp('sole_prop')],
      ['partnership', 'users', 'Partnership', 'Two or more owners.', $fp('partnership')],
      ['corp_named', 'building', 'Corporation', 'Limited liability. Named or numbered.', $fp('corp_numbered')],
      ['cooperative', 'network', 'Co-operative', 'Member-owned.', $fp('cooperative')],
    ]],
  'manage' => ['label' => 'Manage Business', 'title' => 'Stay in good standing.', 'sub' => 'We file the paperwork. You run the business.',
    'all' => ['/manage-business', 'Explore Manage Business'],
    'items' => [
      ['bm_annual', 'calendar', 'Annual Returns', 'Filed on time, every year.', $fp('bm_annual')],
      ['bm_changes', 'edit', 'Corporate Changes', 'Address, directors, officers.', $fp('bm_changes')],
      ['bm_documents', 'certificate', 'Corporate Documents', 'Certificates and certified copies.', $fp('bm_documents')],
      ['biz_name', 'text', 'Business Name Services', 'Renew or change a name.', 'Priced by province'],
    ]],
  'web' => ['label' => 'Web Services', 'title' => 'Get found online.', 'sub' => 'Domain, email, website and custom software.',
    'all' => ['/web-services', 'Explore Web Services'],
    'items' => [
      ['domain_register', 'globe', 'Domain Name', '.ca, .com and more.', $fp('domain_register', '/yr')],
      ['email_starter', 'mail', 'Business Email', 'you@yourbusiness.ca', $fp('email_starter', '/mo')],
      ['website_launch', 'layout', 'Website Design', 'Built, hosted, maintained.', $fp('website_launch', '/mo')],
      ['maintenance_care', 'refresh', 'Website Maintenance', 'Care for an existing site.', $fp('maintenance_care', '/mo')],
      ['web_app', 'laptop', 'Web Application', 'Portals, booking, dashboards.', 'Custom quote'],
    ]],
];
$cur = $path ?? '';
$links = ['nuans' => '/register/nuans', 'sole_prop' => '/register/sole-proprietorship', 'partnership' => '/register/partnership', 'corp_named' => '/register/corporation', 'cooperative' => '/register/cooperative',
  'bm_annual' => '/manage-business/annual-returns', 'bm_changes' => '/manage-business/corporate-changes', 'bm_documents' => '/manage-business/corporate-documents', 'biz_name' => '/manage-business/name-services',
  'domain_register' => '/domain', 'email_starter' => '/email', 'website_launch' => '/website', 'maintenance_care' => '/website/maintenance', 'web_app' => '/web-application'];
?>
<nav class="mainnav" id="site-nav" aria-label="Main">
  <ul class="mn-list">
  <?php foreach ($menus as $id => $m): $pc = PILLARS[$id]; ?>
    <li class="mn-item" data-mega data-pillar="<?= $id ?>" style="<?= pillar_css($id) ?>">
      <button class="mn-btn<?= pillar($cur) === $id ? ' on' : '' ?>" type="button" aria-expanded="false" aria-controls="mega-<?= $id ?>"><i class="mn-dot" aria-hidden="true"></i><?= e($m['label']) ?><span class="mn-chev"><?= nx_icon('chev') ?></span></button>
      <div class="mega" id="mega-<?= $id ?>" hidden>
        <div class="wrap mega-in">
          <div class="mega-lead"><p class="mega-k"><?= e($m['label']) ?></p><p class="mega-title"><?= e($m['title']) ?></p><p class="mega-sub"><?= e($m['sub']) ?></p><a class="btn btn-light btn-sm mega-all" href="<?= e($m['all'][0]) ?>"><?= e($m['all'][1]) ?></a></div>
          <ul class="mega-grid" style="--cols:<?= count($m['items']) ?>">
          <?php foreach ($m['items'] as [$slug, $ic, $name, $desc, $badge]): ?>
            <li><a class="mega-card" href="<?= e($links[$slug] ?? '/services/' . $slug) ?>"><span class="mc-ic"><?= nx_icon($ic) ?></span><span class="mc-body"><span class="mc-name"><?= e($name) ?></span><span class="mc-desc"><?= e($desc) ?></span></span><span class="mc-badge"><?= e($badge) ?></span></a></li>
          <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </li>
  <?php endforeach; ?>
    <li class="mn-item" data-pillar="bundle" style="<?= pillar_css('bundle') ?>"><a class="mn-btn<?= $cur === '/bundle' ? ' on' : '' ?>" href="/bundle"><i class="mn-dot" aria-hidden="true"></i>Bundle &amp; Save</a></li>
    <li class="mn-item" data-pillar="opps" style="<?= pillar_css('opps') ?>"><a class="mn-btn<?= in_array($cur, ['/opportunities', '/funding'], true) ? ' on' : '' ?>" href="/opportunities"><i class="mn-dot" aria-hidden="true"></i>Opportunities</a></li>
  </ul>
</nav>
