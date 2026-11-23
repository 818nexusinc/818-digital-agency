<?php
/* Main navigation, matching the original 818digitalagency.com structure:
   Register Business ▾ · Manage Business ▾ · Web Services ▾ · Bundle & Save · Business Opportunities.
   Prices come from the live catalog, never typed in. */
use App\Catalog;
if (!function_exists('nx_icon')) {
    function nx_icon(string $n): string {
        $p = [
            'search' => '<circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M5 20c1-4 4-6 7-6s6 2 7 6"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 19c.8-3.4 3.2-5 6-5s5.2 1.6 6 5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c1.6.6 2.6 2.2 3 5"/>',
            'building' => '<path d="M4 20V6l8-3 8 3v14"/><path d="M9 20v-4h6v4M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01"/><path d="M2 20h20"/>',
            'network' => '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="18" r="2.5"/><circle cx="19" cy="18" r="2.5"/><path d="M12 7.5v4M7 16.5l4-4.5M17 16.5 13 12"/>',
            'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/><path d="m9.5 15 2 2 3.5-3.5"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
            'certificate' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7"/><path d="M14 3v5h5M14 3l5 5v3"/><circle cx="17.5" cy="16" r="2.5"/><path d="m16 18.2-.8 3.3 2.3-1.2 2.3 1.2-.8-3.3"/><path d="M8 9h3M8 13h4"/>',
            'text' => '<path d="M4 7V5h16v2M12 5v14M9 19h6"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.7 5.6 3.7 9s-1.2 6.4-3.7 9c-2.5-2.6-3.7-5.6-3.7-9S9.5 5.6 12 3z"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'layout' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.8-4M4 4v4h4M4 13a8 8 0 0 0 14.8 4M20 20v-4h-4"/>',
            'laptop' => '<rect x="5" y="5" width="14" height="10" rx="1.5"/><path d="M3 19h18"/>',
            'stack' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
            'cash' => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M7 12h.01M17 12h.01"/>',
            'chev' => '<path d="m7 10 5 5 5-5"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        ][$n] ?? '';
        return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }
}
$fp = function (string $slug, string $suffix = '') { $s = Catalog::find($slug); $p = $s ? Catalog::fromPrice($s) : null; return $p === null ? 'Custom quote' : 'From ' . money($p) . $suffix; };
$menus = [
  'register' => ['label' => 'Register Business', 'icon' => 'building', 'title' => 'Register your business in Canada', 'sub' => 'Every province and Federal (CBCA) · Government fees included',
    'all' => ['/services/category/registration', 'View all registration options'], 'extra' => ['/start', 'Not sure which structure fits? Ask our assistant'],
    'items' => [
      ['nuans', 'search', 'violet', 'NUANS Name Search', 'Check your business name is unique across Canada. Required before incorporating a named corporation.', $fp('nuans')],
      ['sole_prop', 'user', 'teal', 'Sole Proprietorship', 'Register a trade name and start operating. The simplest structure, no NUANS needed.', $fp('sole_prop')],
      ['partnership', 'users', 'blue', 'Partnership', 'General or limited partnership between two or more people or corporations.', $fp('partnership')],
      ['corp_named', 'building', 'amber', 'Corporation', 'Limited-liability incorporation under provincial or federal (CBCA) law, named or numbered.', $fp('corp_numbered')],
      ['cooperative', 'network', 'green', 'Co-operative', 'Member-owned organizations: agricultural, worker, housing or consumer co-ops.', $fp('cooperative')],
    ]],
  'manage' => ['label' => 'Manage Business', 'icon' => 'calendar', 'title' => 'Manage your registered business', 'sub' => 'Annual returns · Corporate changes · Official documents · Business name services',
    'all' => ['/services/category/management', 'View all business management services'],
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
            <li><a class="mega-card" href="/services/<?= e($slug) ?>"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span class="mc-body"><span class="mc-name"><?= e($name) ?></span><span class="mc-desc"><?= e($desc) ?></span><span class="mc-badge"><?= e($badge) ?></span></span></a></li>
          <?php endforeach; ?>
          </ul>
          <?php if (!empty($m['extra'])): ?><p class="mega-foot"><a href="<?= e($m['extra'][0]) ?>"><?= e($m['extra'][1]) ?> <?= nx_icon('arrow') ?></a></p><?php endif; ?>
        </div>
      </div>
    </li>
  <?php endforeach; ?>
    <li class="mn-item"><a class="mn-btn mn-hi<?= $cur === '/services' ? ' on' : '' ?>" href="/services#bundle"><?= nx_icon('stack') ?>Bundle &amp; Save</a></li>
    <li class="mn-item"><a class="mn-btn<?= $cur === '/funding' ? ' on' : '' ?>" href="/funding"<?= $cur === '/funding' ? ' aria-current="page"' : '' ?>><?= nx_icon('cash') ?>Business Opportunities</a></li>
  </ul>
</nav>
