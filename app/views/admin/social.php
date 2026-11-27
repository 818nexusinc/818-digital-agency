<?php $live = array_filter($acc, fn($a) => $a['post']); ?>
<div class="db sm">
<div class="db-head"><div><h1>Social media</h1><p class="db-bc">Your accounts, posting, and every post sent from here</p></div>
  <div class="db-acts"><a class="btn btn-ghost btn-sm" href="/admin/integrations?tab=<?= $live ? 'connected' : 'available' ?>#facebook">Manage accounts</a></div></div>

<div class="sm-grid">
<section class="db-card sm-acc"><div class="db-ch"><div><h2>Accounts</h2><p class="db-sub">Profile links show on the website footer and help search engines connect your accounts.</p></div></div>
  <ul><?php foreach ($acc as $slug => $a): ?><li>
    <div><b><?= e($a['label']) ?></b><?php if ($a['url']): ?><a href="<?= e($a['url']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https://(www\.)?#', '', $a['url'])) ?></a><?php else: ?><small>No profile link</small><?php endif; ?></div>
    <span class="sm-st <?= $a['post'] ? 'on' : ($a['url'] ? 'mid' : '') ?>"><?= $a['post'] ? 'Can post' : ($a['url'] ? ($a['canPost'] ? 'Link only' : 'Linked') : 'Not connected') ?></span>
    <a class="db-more" href="/admin/integrations?tab=<?= $a['url'] || $a['post'] ? 'connected' : 'available' ?>#<?= e($slug) ?>"><?= $a['url'] || $a['post'] ? 'Edit' : 'Connect' ?></a></li><?php endforeach; ?></ul>
</section>

<section class="db-card sm-post"><div class="db-ch"><div><h2>Post now</h2><p class="db-sub">Goes out straight away to the accounts you tick. Posts written by the Social Media Agent wait in the Action center for your approval.</p></div></div>
  <?php if (!$live): ?><div class="ct-empty"><b>No account can post yet.</b><p>Add an access token for Facebook, Instagram, LinkedIn or X, or connect TikTok, in Integrations.</p></div>
  <?php else: ?>
  <form method="post" action="/admin/social" class="sm-form" enctype="multipart/form-data" data-confirm="Publish this post now?"><?= App\Csrf::field() ?>
    <label>Post<textarea name="text" rows="5" maxlength="3000" required placeholder="What do you want to share?" data-count></textarea><small data-counter>0 characters · X allows 280</small></label>
    <div class="pz-2"><label>Link (optional)<input type="url" name="link" placeholder="https://818digitalagency.com/…"></label><label>Picture address (needed for Instagram)<input type="url" name="image_url" placeholder="https://…"></label></div>
    <label>Or upload a picture <small>(needed for TikTok · JPG, PNG or WebP, up to 15 MB · hosted on your website)</small><input type="file" name="picture" accept="image/jpeg,image/png,image/webp"></label>
    <?php if (isset($live['tiktok'])): ?><label>TikTok audience<select name="tiktok_privacy"><option value="">Choose who can see it…</option><?php foreach (App\TikTok::PRIVACY as $pv => $pl): ?><option value="<?= e($pv) ?>"><?= e($pl) ?></option><?php endforeach; ?></select>
      <small>By posting to TikTok you agree to TikTok's <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener">Music Usage Confirmation</a>.<?= App\Integrations::setting('tiktok', 'post_mode', 'direct') === 'inbox' ? ' TikTok posts go to your TikTok inbox to finish in the app.' : '' ?></small></label><?php endif; ?>
    <fieldset class="sm-nets"><legend>Post to</legend><?php foreach ($live as $slug => $a): ?><label class="se-check"><input type="checkbox" name="networks[]" value="<?= e($slug) ?>" checked> <?= e($a['label']) ?></label><?php endforeach; ?></fieldset>
    <button class="btn btn-primary">Publish</button>
  </form>
  <?php endif; ?>
  <?php if ($drafts): ?><div class="sm-drafts"><h3>Drafts from the Content studio</h3><ul><?php foreach ($drafts as $d): ?><li><a href="/admin/content?edit=<?= (int)$d['id'] ?>"><?= e($d['title']) ?></a> <small><?= e(str_limit((string)preg_replace('/\s+/', ' ', (string)$d['body']), 110)) ?></small></li><?php endforeach; ?></ul></div><?php endif; ?>
</section>
</div>

<section class="db-card sm-hist"><div class="db-ch"><div><h2>Posts sent</h2></div></div>
  <?php if (!$posts): ?><div class="ct-empty"><b>Nothing posted yet.</b></div><?php else: ?>
  <table><thead><tr><th>When</th><th>Account</th><th>Post</th><th>Result</th><th>By</th></tr></thead><tbody>
  <?php foreach ($posts as $p): ?><tr><td><?= e(substr((string)$p['created_at'], 0, 16)) ?></td><td><?= e(App\Social::NETWORKS[$p['network']][0] ?? $p['network']) ?></td><td><?= e(str_limit((string)$p['body'], 120)) ?></td>
    <td><?= $p['status'] === 'published' ? '<span class="sm-st on">Published</span>' : '<span class="sm-st bad" title="' . e((string)$p['error']) . '">Failed</span> <small>' . e(str_limit((string)$p['error'], 80)) . '</small>' ?></td><td><?= e($p['created_by'] === 'social' ? 'Social Media Agent' : $p['created_by']) ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</section>
</div>
