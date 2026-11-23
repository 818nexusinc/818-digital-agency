<?php
$site = url('/'); $prod = !str_contains($site, '.vercel.app') && !str_contains($site, 'localhost');
$items = [
  ['Production domain', $prod, $prod ? 'Pages are published under ' . $site : 'Search engines would index ' . $site . '. Point 818digitalagency.com to Vercel and set APP_URL.', null],
  ['AI assistants allowed', true, 'robots.txt welcomes GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, Applebot and others.', '/robots.txt'],
  ['AI guides published', true, 'llms.txt (summary) and llms-full.txt (every service, price and answer) for ChatGPT, Claude, Perplexity and Copilot.', '/llms.txt'],
  ['Sitemap', true, $urls . ' pages with last-modified dates and English/French alternates.', '/sitemap.xml'],
  ['Structured data', true, 'Organization, WebSite, Service, FAQ, Article and breadcrumb schema on the pages that need them.', null],
  ['Google Search Console verified', $google !== '', $google !== '' ? 'Verification tag is on every page.' : 'Not verified yet. Paste the code below.', null],
  ['Bing Webmaster verified', $bing !== '', $bing !== '' ? 'Verification tag is on every page.' : 'Not verified yet. Bing powers ChatGPT search, Microsoft Copilot and DuckDuckGo.', null],
  ['Instant indexing (IndexNow)', (bool)($last['at'] ?? null) && in_array((int)($last['code'] ?? 0), [200, 202], true), $last ? 'Last sent ' . e(substr($last['at'], 0, 16)) . ' UTC: ' . (int)$last['count'] . ' pages (response ' . (int)$last['code'] . ').' : 'Not sent yet. New articles are sent automatically once the production domain is live.', null],
  ['Fresh content every week', $blogOn, $posts . ' published guide' . ($posts === 1 ? '' : 's') . '. ' . ($blogOn ? 'A new one is written every 7 days.' : 'Weekly articles are paused.'), '/admin/blog'],
];
$done = count(array_filter($items, fn($i) => $i[1]));
?>
<div class="topbar"><div><h1>Search & AI visibility</h1><p class="muted" style="margin:4px 0 0">What makes the site findable on Google, Bing and AI assistants, what is already in place, and the few steps only you can do.</p></div>
  <form method="post"><?= App\Csrf::field() ?><input type="hidden" name="action" value="ping"><button class="btn btn-primary"<?= $prod ? '' : ' disabled title="Available once the production domain is live"' ?>>Notify search engines now</button></form></div>

<section class="sc-score"><div class="sc-ring" style="--p:<?= round($done / count($items) * 100) ?>;--c:#4f46e5"><b><?= $done ?>/<?= count($items) ?></b></div>
  <div class="sc-sum"><h2><?= $done === count($items) ? 'Everything is in place.' : (count($items) - $done) . ' step' . (count($items) - $done === 1 ? '' : 's') . ' left' ?></h2><p>Indexing usually starts within a few days of verifying with Google and Bing. Rankings build over weeks as articles, links and reviews accumulate; no one can guarantee a position.</p></div></section>

<div class="sc-list" style="margin-bottom:18px">
<?php foreach ($items as [$t, $ok, $d, $link]): ?><div class="sc-item <?= $ok ? 'pass' : 'warn' ?>"><i aria-hidden="true"><?= $ok ? '✓' : '!' ?></i><div><b><?= e($t) ?></b><p><?= $d ?></p></div><span><?php if ($link): ?><a class="btn btn-ghost btn-sm" href="<?= e($link) ?>" target="_blank" rel="noopener">Open</a><?php endif; ?></span></div><?php endforeach; ?>
</div>

<div class="grid g2" style="align-items:start">
  <div class="panel"><h2>Verify ownership</h2>
    <p class="small muted">Paste the code (or the whole meta tag) each service gives you, save, then press Verify in that service.</p>
    <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="save">
      <label>Google Search Console<input name="google" value="<?= e($google) ?>" placeholder="google-site-verification code"></label>
      <label>Bing Webmaster Tools<input name="bing" value="<?= e($bing) ?>" placeholder="msvalidate.01 code"></label>
      <label>Official profiles (one per line, https only)<textarea name="same_as" rows="3" placeholder="https://www.linkedin.com/company/…&#10;https://www.facebook.com/…"><?= e(str_replace(',', "\n", $sameAs)) ?></textarea></label>
      <button class="btn btn-dark btn-sm">Save</button></form></div>
  <div class="panel"><h2>Your steps (about 20 minutes)</h2>
    <ol class="small" style="padding-left:18px;display:grid;gap:8px;margin:0">
      <li><b>Google Search Console</b>: add the property <?= e($site) ?>, choose "HTML tag", paste the code here, then submit <code>sitemap.xml</code>. <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Open</a></li>
      <li><b>Bing Webmaster Tools</b>: sign in and "Import from Google Search Console" (fastest), or use the meta tag. Submit the sitemap. <a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Open</a></li>
      <li><b>Google Business Profile</b> for 818 Digital Agency in Moncton: local results and Google's AI answers lean on it. <a href="https://business.google.com" target="_blank" rel="noopener">Open</a></li>
      <li><b>Bing Places</b> and <b>Apple Business Connect</b>: import from Google once it is verified.</li>
      <li>Add your LinkedIn and Facebook pages above so assistants connect them to the business.</li>
      <li>Press <b>Notify search engines now</b> after each big change. New articles are sent automatically.</li>
    </ol>
    <p class="small muted" style="margin-top:12px">IndexNow key file: <code>/<?= e($key) ?>.txt</code></p></div>
</div>
