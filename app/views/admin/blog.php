<?php [$ni, $nt] = $next; ?>
<div class="topbar"><div><h1>Blog engine</h1><p class="muted" style="margin:4px 0 0">One SEO article a week, written for Canadian small business owners, with internal links to your service pages and ready-to-post social copy.</p></div>
  <div class="row"><a class="btn btn-ghost btn-sm" href="/blog" target="_blank" rel="noopener">View blog</a><a class="btn btn-ghost btn-sm" href="/blog/feed.xml" target="_blank" rel="noopener">RSS feed</a></div></div>
<div class="grid g2" style="align-items:start">
  <div class="panel"><h2>Write an article now</h2>
    <p class="small muted"><?= $ai ? 'Written by your connected AI model, checked for length and structure. Facts come from your catalog and knowledge base.' : 'No AI provider is connected, so articles are composed from your catalog, prices and knowledge base. Connect Anthropic or OpenAI for fully written articles.' ?></p>
    <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="generate">
      <label>Topic<select name="topic"><option value="">Next in the plan: <?= e($nt[0]) ?></option><?php foreach ($topics as $i => $tp): ?><option value="<?= $i ?>"><?= e($tp[0]) ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-primary">Write article</button></form></div>
  <div class="panel"><h2>Schedule</h2>
    <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="settings">
      <label class="check"><input type="checkbox" name="enabled" value="1"<?= $enabled ? ' checked' : '' ?>> Write a new article every 7 days (runs with the daily background job)</label>
      <label class="check"><input type="checkbox" name="autopublish" value="1"<?= $autopublish ? ' checked' : '' ?>> Publish automatically. Untick to review each article before it goes live.</label>
      <button class="btn btn-dark btn-sm">Save schedule</button></form>
    <p class="small muted" style="margin-top:12px">Last article: <?= $last ? e(substr($last, 0, 16)) . ' UTC' : 'none yet' ?>. Connect the RSS feed to Buffer, Zapier or LinkedIn to share each new article automatically.</p></div>
</div>
<div class="panel"><h2>Articles</h2>
<?php if (!$posts): ?><div class="empty">No articles yet. Write the first one above.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Article</th><th>SEO</th><th>Status</th><th>Social posts</th><th></th></tr></thead><tbody>
<?php foreach ($posts as $p): $meta = jdec((string)$p['meta']); ?>
<tr><td><b><?= e($p['title']) ?></b><div class="small muted"><?= e($p['keyword']) ?> · <?= e(substr((string)($p['publish_at'] ?? $p['created_at']), 0, 10)) ?> · <?= e(label((string)($meta['generator'] ?? $p['author']))) ?></div></td>
  <td><span class="badge <?= $p['seo_score'] >= 70 ? 'b-green' : ($p['seo_score'] >= 50 ? 'b-blue' : '') ?>"><?= (int)$p['seo_score'] ?></span><div class="small muted">read <?= (int)$p['readability'] ?></div></td>
  <td><span class="badge <?= $p['status'] === 'published' ? 'b-green' : 'b-blue' ?>"><?= e(label($p['status'])) ?></span></td>
  <td><?php if (!empty($meta['social'])): ?><details class="small"><summary>LinkedIn, X, Facebook</summary><?php foreach ($meta['social'] as $net => $txt): ?><p><b><?= e(ucfirst($net)) ?></b><br><textarea readonly rows="3" style="width:100%;font-size:13px"><?= e($txt) ?></textarea></p><?php endforeach; ?></details><?php endif; ?></td>
  <td><div class="row" style="gap:6px;flex-wrap:nowrap">
    <?php if ($p['status'] === 'published'): ?><a class="btn btn-ghost btn-sm" href="/blog/<?= e($p['slug']) ?>" target="_blank" rel="noopener">Open</a><?php endif; ?>
    <form method="post"><?= App\Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="<?= $p['status'] === 'published' ? 'unpublish' : 'publish' ?>"><button class="btn btn-<?= $p['status'] === 'published' ? 'ghost' : 'primary' ?> btn-sm"><?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form>
    <a class="btn btn-ghost btn-sm" href="/admin/content?edit=<?= (int)$p['id'] ?>">Edit</a>
    <form method="post" onsubmit="return confirm('Delete this article?')"><?= App\Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-ghost btn-sm">Delete</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
