<?php
$paths = $pages['paths']; $counts = $pages['counts'];
if (!in_array($pg, $paths, true)) array_unshift($paths, $pg);
$label = fn(string $p) => $p === '/' ? 'Home' : ucfirst(str_replace(['-', '/'], [' ', ' › '], ltrim($p, '/')));
?>
<div class="se" data-site-editor data-page="<?= e($pg) ?>">
  <div class="se-top">
    <div>
      <h1>Site editor</h1>
      <p class="se-sub">Change the words on any page of the website, in English and French, and replace the logo. Changes go live as soon as you save.</p>
    </div>
    <nav class="seg" aria-label="Site editor sections"><a href="/admin/site?page=<?= e(rawurlencode($pg)) ?>"<?= $tab === 'pages' ? ' aria-current="page"' : '' ?>>Page text</a><a href="/admin/site?tab=brand"<?= $tab === 'brand' ? ' aria-current="page"' : '' ?>>Logo</a></nav>
  </div>

<?php if ($tab === 'brand'): ?>
  <div class="se-brand">
    <section class="db-card se-logo">
      <h2>Website logo</h2>
      <p class="se-sub">Shown at the top of every page of the website and in this Control Tower.</p>
      <div class="se-prev">
        <figure><div class="se-prev-light"><span class="se-mark"><?php include APP_ROOT . '/views/partials/logo.php'; ?></span><?php if (!$logo || $wordmark): ?><span class="se-wm"><b><?= e($company) ?></b><small>by 818 Nexus Inc.</small></span><?php endif; ?></div><figcaption>On the website header</figcaption></figure>
        <figure><div class="se-prev-dark"><span class="se-mark"><?php include APP_ROOT . '/views/partials/logo.php'; ?></span></div><figcaption>On a dark background</figcaption></figure>
      </div>
      <p class="se-now"><?= $logo ? 'Using your uploaded logo (' . e(strtoupper(App\SiteText::LOGO_TYPES[$logoMime] ?? 'image')) . ').' : 'Using the original 818 logo.' ?></p>
      <form method="post" action="/admin/site/brand" enctype="multipart/form-data" class="se-up">
        <?= App\Csrf::field() ?><input type="hidden" name="action" value="upload">
        <label class="se-drop"><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg" required data-logo-file><b>Choose a logo file</b><span data-logo-name>PNG, SVG, JPG or WebP, up to 600 KB. A transparent background looks best.</span></label>
        <label class="se-check"><input type="checkbox" name="wordmark" value="1"<?= $wordmark ? ' checked' : '' ?>> Show “<?= e($company) ?>” next to the logo <small>Turn this off if the name is already part of your logo.</small></label>
        <div class="se-acts"><button class="btn btn-primary">Upload and use this logo</button></div>
      </form>
      <?php if ($logo): ?>
      <div class="se-row2">
        <form method="post" action="/admin/site/brand"><?= App\Csrf::field() ?><input type="hidden" name="action" value="wordmark"><label class="se-check"><input type="checkbox" name="wordmark" value="1"<?= $wordmark ? ' checked' : '' ?> data-autosubmit> Show the company name next to the logo</label><noscript><button class="btn">Save</button></noscript></form>
        <form method="post" action="/admin/site/brand" data-confirm="Go back to the original 818 logo?"><?= App\Csrf::field() ?><input type="hidden" name="action" value="remove"><button class="btn">Use the original 818 logo</button></form>
      </div>
      <?php endif; ?>
    </section>
    <aside class="db-card se-help">
      <h2>Good to know</h2>
      <ul>
        <li>The logo is stored in your database, so installing updates never removes it.</li>
        <li>Wide logos work best, about 3 to 5 times wider than tall. The header shows them 40 pixels high.</li>
        <li>For SVG files, use a plain export. Files with scripts or outside links are refused for safety.</li>
        <li>To change the company name used in emails and search results, go to <a href="/admin/settings">Settings &amp; team</a>.</li>
      </ul>
    </aside>
  </div>
<?php else: ?>
  <div class="se-bar">
    <label class="se-pick"><span>Page</span>
      <select data-se-page aria-label="Page to edit">
        <?php foreach ($paths as $p): $n = ($counts[$p] ?? 0); ?><option value="<?= e($p) ?>"<?= $p === $pg ? ' selected' : '' ?>><?= e($label($p)) ?><?= $n ? ' · ' . $n . ' changed' : '' ?> (<?= e($p) ?>)</option><?php endforeach; ?>
      </select>
    </label>
    <label class="se-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" placeholder="Find text on this page" data-se-find aria-label="Find text on this page"></label>
    <label class="se-only"><input type="checkbox" data-se-changed> Changed only</label>
    <a class="btn" href="<?= e($pg) ?>" target="_blank" rel="noopener">Open page</a>
  </div>
  <?php if (!empty($counts['*'])): ?><p class="se-note"><?= (int)$counts['*'] ?> change<?= $counts['*'] === 1 ? '' : 's' ?> apply on every page.</p><?php endif; ?>

  <div class="se-grid">
    <form method="post" action="/admin/site" class="se-form" data-se-form>
      <?= App\Csrf::field() ?><input type="hidden" name="page" value="<?= e($pg) ?>"><input type="hidden" name="changes" value="" data-se-changes>
      <div class="se-fields" data-se-fields><?= App\View::render('admin/site-fields', ['fields' => $fields]) ?></div>
      <div class="se-save" data-se-save hidden>
        <span data-se-count>1 unsaved change</span>
        <button type="button" class="btn" data-se-discard>Discard</button>
        <button class="btn btn-primary">Save and publish</button>
      </div>
    </form>
    <div class="se-view">
      <div class="se-view-h"><span>Preview</span><small>Click any text to edit it. Links are paused here.</small></div>
      <div class="se-frame" data-se-frame><iframe src="<?= e($pg) ?>?nx_edit=1" title="Preview of <?= e($label($pg)) ?>" data-se-iframe></iframe></div>
    </div>
  </div>
<?php endif; ?>
</div>
