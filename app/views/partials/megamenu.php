<?php
/* Main navigation, matching the original 818digitalagency.com structure:
   Register Business ▾ · Manage Business ▾ · Web Services ▾ · Bundle & Save · Business Opportunities.
   Prices come from the live catalog, never typed in. */
use App\Catalog;
include_once APP_ROOT . '/views/partials/icons.php';
$fp = function (string $slug, string $suffix = '') { $s = Catalog::find($slug); $p = $s ? Catalog::fromPrice($s) : null; return $p === null ? 'Custom quote' : 'From ' . money($p) . $suffix; };
$menus = [
  'register' => ['label' => 'Register Business', 'icon' => 'building', 'title' => 'Register your business in Canada', 'sub' => 'Every province and Federal (CBCA) · Government fees included',
    'all' => ['/register', 'View all registration options'], 'extra' => ['/start', 'Not sure which structure fits? Ask our assistant'],
    'items' => [
      ['nuans', 'search', 'violet', 'NUANS Name Search', 'Check your business name is unique across Canada. Required before incorporating a named corporation.', $fp('nuans')],
      ['sole_prop', 'user', 'teal', 'Sole Proprietorship', 'Register a trade name and start operating. The simplest structure, no NUANS needed.', $fp('sole_prop')],
      ['partnership', 'users', 'blue', 'Partnership', 'General or limited partnership between two or more people or corporations.', $fp('partnership')],
      ['corp_named', 'building', 'amber', 'Corporation', 'Limited-liability incorporation under provincial or federal (CBCA) law, named or numbered.', $fp('corp_numbered')],
      ['cooperative', 'network', 'green', 'Co-operative', 'Member-owned organizations: agricultural, worker, housing or consumer co-ops.', $fp('cooperative')],
    ]],
  'manage' => ['label' => 'Manage Business', 'icon' => 'calendar', 'title' => 'Manage your registered business', 'sub' => 'Annual returns · Corporate changes · Official documents · Business name services',
    'all' => ['/manage-business', 'View all business management services'],
    'items' => [
      ['bm_annual', 'calendar', 'blue', 'Annual Returns', 'File your annual return on time and stay in good standing with your registry.', 'Compliance required'],
      ['bm_changes', 'edit', 'amber', 'Corporate Changes', 'Update your registered address, directors, officers and resignations.', 'Address · Directors · Officers'],
      ['bm_documents', 'certificate', 'violet', 'Corporate Documents', 'Order certified copies, certificates of status and corporate profile reports.', 'Official certified copies'],
      ['biz_name', 'text', 'teal', 'Business Name Services', 'Register, renew or change a business name for sole proprietorships and partnerships.', 'Renewal · Name change'],
    ]],
  'web' => ['label' => 'Web Services', 'icon' => 'globe', 'title' => 'Web services', 'sub' => 'Domains · Email · Websites · Maintenance · Custom web apps',
    'all' => ['/services', 'View all services and prices'],
    'items' => [
      ['domain_register', 'globe', 'teal', 'Domain Name', 'Register .ca and .com domains with WHOIS privacy, DNS set-up and auto-renewal.', $fp('domain_register', '/yr')],
      ['email_starter', 'mail', 'cyan', 'Business Email', 'Professional email at your own domain, with webmail, IMAP and spam filtering.', $fp('email_starter', '/mo')],
      ['website_launch', 'layout', 'green', 'Website Design', 'Managed websites for Canadian businesses. Hosting, SSL and monthly updates included.', $fp('website_launch', '/mo')],
      ['maintenance_care', 'refresh', 'violet', 'Website Maintenance', 'Ongoing care for your existing site: backups, updates, security patches and edits.', $fp('maintenance_care', '/mo')],
      ['web_app', 'laptop', 'amber', 'Web Application', 'Custom web apps and client portals: booking systems, dashboards and business tools.', 'Custom quote'],
    ]],
];
$cur = $path ?? '';
$links = ['nuans' => '/register/nuans', 'sole_prop' => '/register/sole-proprietorship', 'partnership' => '/register/partnership', 'corp_named' => '/register/corporation', 'cooperative' => '/register/cooperative',
  'bm_annual' => '/manage-business/annual-returns', 'bm_changes' => '/manage-business/corporate-changes', 'bm_documents' => '/manage-business/corporate-documents', 'biz_name' => '/manage-business/name-services',
  'domain_register' => '/domain', 'email_starter' => '/email', 'website_launch' => '/website', 'maintenance_care' => '/website/maintenance', 'web_app' => '/web-application'];
?>
<nav class="mainnav" id="site-nav" aria-label="Main">
  <ul class="mn-list">
  <?php foreach ($menus as $id => $m): ?>
    <li class="mn-item" data-mega>
      <button class="mn-btn" type="button" aria-expanded="false" aria-controls="mega-<?= $id ?>"><?= nx_icon($m['icon']) ?><?= e($m['label']) ?><span class="mn-chev"><?= nx_icon('chev') ?></span></button>
      <div class="mega" id="mega-<?= $id ?>" hidden>
        <div class="wrap">
          <div class="mega-head"><div><p class="mega-title"><?= e($m['title']) ?></p><p class="mega-sub"><?= e($m['sub']) ?></p></div><a class="mega-all" href="<?= e($m['all'][0]) ?>"><?= e($m['all'][1]) ?> <?= nx_icon('arrow') ?></a></div>
          <ul class="mega-grid mega-<?= count($m['items']) ?>">
          <?php foreach ($m['items'] as [$slug, $ic, $tone, $name, $desc, $badge]): ?>
            <li><a class="mega-card" href="<?= e($links[$slug] ?? '/services/' . $slug) ?>"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span class="mc-body"><span class="mc-name"><?= e($name) ?></span><span class="mc-desc"><?= e($desc) ?></span><span class="mc-badge"><?= e($badge) ?></span></span></a></li>
          <?php endforeach; ?>
          </ul>
          <?php if (!empty($m['extra'])): ?><p class="mega-foot"><a href="<?= e($m['extra'][0]) ?>"><?= e($m['extra'][1]) ?> <?= nx_icon('arrow') ?></a></p><?php endif; ?>
        </div>
      </div>
    </li>
  <?php endforeach; ?>
    <li class="mn-item"><a class="mn-btn mn-hi<?= $cur === '/bundle' ? ' on' : '' ?>" href="/bundle"><?= nx_icon('stack') ?>Bundle &amp; Save</a></li>
    <li class="mn-item"><a class="mn-btn<?= in_array($cur, ['/opportunities', '/funding'], true) ? ' on' : '' ?>" href="/opportunities"><?= nx_icon('cash') ?>Opportunities</a></li>
  </ul>
</nav>
