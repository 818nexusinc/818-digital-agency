<article class="section tight"><div class="wrap prose">
  <a class="small" href="/blog">← All articles</a><h1 style="margin-top:12px"><?= e($post['title']) ?></h1><p class="small muted"><?= e(substr((string)($post['publish_at'] ?? $post['updated_at']), 0, 10)) ?></p>
  <?php foreach (preg_split('/\n{2,}/', (string)$post['body']) as $para): $para = trim($para); if ($para === '') continue;
    if (preg_match('/^(#{2,3})\s+(.*)$/', $para, $m)): ?><h2><?= e($m[2]) ?></h2>
    <?php elseif (preg_match('/^[-*] /m', $para)): ?><ul><?php foreach (preg_split('/\n/', $para) as $li): ?><li><?= e(preg_replace('/^[-*]\s+/', '', $li)) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p><?= nl2br(e($para)) ?></p><?php endif; endforeach; ?>
  <div class="card" style="margin-top:30px"><b>Have a question about this?</b> <button class="btn btn-ghost btn-sm" type="button" data-chat="">Ask the assistant</button></div>
</div></article>
