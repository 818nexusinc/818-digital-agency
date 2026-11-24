<?php
$label = fn(string $p) => $p === '*' ? 'Every page' : ($p === '/' ? 'Home' : ucfirst(str_replace(['-', '/'], [' ', ' › '], ltrim($p, '/'))));
?>
<div class="se" data-site-editor>
  <div class="se-top">
    <div>
      <h1>Site editor</h1>
      <p class="se-sub">Edit the website where it lives: open any page while signed in as the owner, click the words or pictures you want to change, and save. Only the owner can do this.</p>
    </div>
    <nav class="seg" aria-label="Site editor sections"><a href="/admin/site"<?= $tab === 'pages' ? ' aria-current="page"' : '' ?>>Pages &amp; pictures</a><a href="/admin/site?tab=brand"<?= $tab === 'brand' ? ' aria-current="page"' : '' ?>>Logo</a></nav>
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
  <section class="db-card se-launch">
    <div><h2>Edit on the website</h2>
      <ol class="se-howto"><li>Open any page of the website. You stay signed in as the owner.</li><li>Click <b>Edit this page</b> at the bottom left.</li><li>Click any words to type over them; the French version and "this page / every page" are in the side panel.</li><li>Click a picture to replace it. It goes live straight away.</li><li>Click <b>Save and publish</b>. The page refreshes with your changes.</li></ol></div>
    <div class="se-go"><a class="btn btn-primary" href="/?nx_edit=1" target="_blank" rel="noopener">Open the home page in the editor</a><a class="btn" href="/" target="_blank" rel="noopener">Browse the website</a></div>
  </section>

  <div class="se-lists">
  <section class="db-card se-list">
    <div class="db-ch"><div><h2>Pages with changes</h2><p class="db-sub">Undo removes every text change on that page.</p></div></div>
    <?php if (!$pages): ?><div class="ct-empty"><b>No text changes yet.</b><p>Edited pages appear here.</p></div><?php else: ?>
    <table><thead><tr><th>Page</th><th class="num">Changes</th><th>Last change</th><th></th></tr></thead><tbody>
    <?php foreach ($pages as $p): ?><tr><td><?= $p['page'] === '*' ? '<b>Every page</b> <small class="muted">menus, header, footer</small>' : '<a href="' . e($p['page']) . '?nx_edit=1" target="_blank" rel="noopener">' . e($label($p['page'])) . '</a> <small class="muted">' . e($p['page']) . '</small>' ?></td>
      <td class="num"><?= (int)$p['n'] ?></td><td><?= e(substr((string)$p['at'], 0, 16)) ?> UTC</td>
      <td class="se-act"><?php if ($p['page'] !== '*'): ?><a class="db-more" href="<?= e($p['page']) ?>?nx_edit=1" target="_blank" rel="noopener">Edit</a><?php endif; ?>
        <form method="post" action="/admin/site" data-confirm="Undo all <?= (int)$p['n'] ?> change(s) on <?= e($label($p['page'])) ?>?"><?= App\Csrf::field() ?><input type="hidden" name="action" value="reset_page"><input type="hidden" name="page" value="<?= e($p['page']) ?>"><button class="sf-reset">Undo all</button></form></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </section>
  <section class="db-card se-list">
    <div class="db-ch"><div><h2>Replaced pictures</h2><p class="db-sub">A replaced picture changes everywhere it is used.</p></div></div>
    <?php if (!$images): ?><div class="ct-empty"><b>No pictures replaced yet.</b><p>Click a picture in the editor to replace it.</p></div><?php else: ?>
    <ul class="se-imgs"><?php foreach ($images as $im): ?><li><img src="/brand/img/<?= e($im['skey']) ?>" alt=""><div><b><?= e(basename($im['src'])) ?></b><small><?= e(substr((string)$im['updated_at'], 0, 16)) ?> UTC · <?= e((string)$im['updated_by']) ?></small></div>
      <form method="post" action="/admin/site" data-confirm="Put the original picture back?"><?= App\Csrf::field() ?><input type="hidden" name="action" value="reset_image"><input type="hidden" name="src" value="<?= e($im['src']) ?>"><button class="sf-reset">Restore original</button></form></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
  </div>
  <?php if ($texts): ?>
  <section class="db-card se-list">
    <div class="db-ch"><div><h2>Latest text changes</h2></div></div>
    <table><thead><tr><th>Page</th><th>Language</th><th>Original</th><th>Now</th><th>When</th></tr></thead><tbody>
    <?php foreach ($texts as $t): ?><tr><td><?= e($label($t['page'])) ?></td><td><?= e(strtoupper($t['lang'])) ?></td><td class="muted"><?= e(str_limit((string)$t['src'], 80)) ?></td><td><?= e(str_limit((string)$t['value'], 80)) ?></td><td><?= e(substr((string)$t['updated_at'], 0, 16)) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <?php endif; ?>
<?php endif; ?>
</div>
