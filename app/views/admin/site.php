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
    <div class="se-logos">
      <p class="se-sub se-lead">Upload a dark logo and a white logo. The website shows the one that stands out against the colour behind it: dark on white pages and light sections, white on dark sections, photos and video. It switches by itself as visitors scroll.</p>
      <?php foreach (['dark' => ['Dark logo', 'For white and light backgrounds', 'se-prev-light'], 'light' => ['White logo', 'For dark backgrounds, photos and video', 'se-prev-dark']] as $slot => [$ttl, $use, $cls]): $has = $logos[$slot]; ?>
      <section class="db-card se-logo">
        <div class="se-lh"><div><h2><?= $ttl ?></h2><p class="se-sub"><?= $use ?></p></div><span class="se-st<?= $has ? ' on' : '' ?>"><?= $has ? 'Uploaded · ' . e(strtoupper(App\SiteText::LOGO_TYPES[$has] ?? 'image')) : 'Not uploaded' ?></span></div>
        <div class="<?= $cls ?> se-box"><span class="se-mark"><?php $logoBg = $slot === 'dark' ? 'light' : 'dark'; include APP_ROOT . '/views/partials/logo.php'; ?></span><?php if (!$logos['dark'] && !$logos['light'] || $wordmark): ?><span class="se-wm"><b><?= e($company) ?></b></span><?php endif; ?></div>
        <?php if (!$has && ($logos['dark'] || $logos['light'])): ?><p class="se-fallback">Until you upload this one, the site shows a one-colour version of your <?= $slot === 'dark' ? 'white' : 'dark' ?> logo here, as above.</p><?php endif; ?>
        <form method="post" action="/admin/site/brand" enctype="multipart/form-data" class="se-up">
          <?= App\Csrf::field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="slot" value="<?= $slot ?>">
          <label class="se-drop"><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg" required data-logo-file><b><?= $has ? 'Replace the ' . strtolower($ttl) : 'Choose the ' . strtolower($ttl) ?></b><span data-logo-name>PNG, SVG, JPG or WebP, up to 600 KB, transparent background</span></label>
          <div class="se-acts"><button class="btn btn-primary">Upload</button></div>
        </form>
        <?php if ($has): ?><form method="post" action="/admin/site/brand" class="se-rm" data-confirm="Remove the <?= strtolower($ttl) ?>?"><?= App\Csrf::field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="slot" value="<?= $slot ?>"><button class="sf-reset">Remove</button></form><?php endif; ?>
      </section>
      <?php endforeach; ?>
      <div class="se-row2 db-card">
        <form method="post" action="/admin/site/brand"><?= App\Csrf::field() ?><input type="hidden" name="action" value="wordmark"><label class="se-check"><input type="checkbox" name="wordmark" value="1"<?= $wordmark ? ' checked' : '' ?> data-autosubmit> Show “<?= e($company) ?>” next to the logo <small>Turn this off if the name is already part of your logo.</small></label><noscript><button class="btn">Save</button></noscript></form>
        <?php if ($logos['dark'] || $logos['light']): ?><form method="post" action="/admin/site/brand" data-confirm="Remove both logos and go back to the original 818 logo?"><?= App\Csrf::field() ?><input type="hidden" name="action" value="reset"><button class="btn">Use the original 818 logo</button></form><?php endif; ?>
      </div>
    </div>
    <aside class="db-card se-help">
      <h2>Good to know</h2>
      <ul>
        <li>The website checks the colour right behind the logo on every page, including the header bar after scrolling and the dark homepage video.</li>
        <li>The footer and the Control Tower menu are dark, so they use the white logo.</li>
        <li>If you upload only one logo, the other background gets a plain one-colour version of it. Uploading both looks best.</li>
        <li>Logos are stored in your database, so installing updates never removes them.</li>
        <li>Wide logos work best, about 3 to 5 times wider than tall. The header shows them 40 pixels high.</li>
        <li>SVG files with scripts or outside links are refused for safety.</li>
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
