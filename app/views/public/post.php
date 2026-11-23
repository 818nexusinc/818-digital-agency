<?php
$meta = jdec((string)($post['meta'] ?? '{}'));
$u = url('/blog/' . $post['slug']);
// Inline markdown links: internal paths and https only.
$inline = function (string $t): string {
    $h = e($t);
    return preg_replace_callback('/\[([^\]]{1,120})\]\((\/[a-z0-9\/_\-#?=&.]*|https:\/\/[^\s)]{4,300})\)/i', function ($m) {
        $ext = str_starts_with($m[2], 'https://');
        return '<a href="' . $m[2] . '"' . ($ext ? ' rel="noopener" target="_blank"' : '') . '>' . $m[1] . '</a>';
    }, $h);
};
$mins = max(1, (int)round(str_word_count((string)$post['body']) / 220));
$primary = $meta['links'][0] ?? '/register';
?>
<section class="trust-hero post-hero"><div class="wrap">
  <nav class="crumbs" aria-label="<?= e(t('Breadcrumb')) ?>"><a href="/"><?= e(t('Home')) ?></a><span aria-hidden="true">/</span><a href="/blog"><?= e(t('Blog')) ?></a><span aria-hidden="true">/</span><b aria-current="page"><?= e(str_limit($post['title'], 60)) ?></b></nav>
  <h1><?= e($post['title']) ?></h1>
  <p class="post-meta"><?= e(substr((string)($post['publish_at'] ?? $post['updated_at']), 0, 10)) ?> · <?= $mins ?> <?= e(t('min read')) ?><?php if ($post['keyword']): ?> · <?= e($post['keyword']) ?><?php endif; ?></p>
</div></section>
<section class="trust-body"><div class="wrap trust-grid">
  <aside class="toc" aria-label="<?= e(t('On this page')) ?>"><p><?= e(t('On this page')) ?></p><ol data-toc></ol>
    <div class="share"><p><?= e(t('Share')) ?></p>
      <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($u) ?>" target="_blank" rel="noopener">LinkedIn</a>
      <a href="https://twitter.com/intent/tweet?url=<?= rawurlencode($u) ?>&amp;text=<?= rawurlencode($post['title']) ?>" target="_blank" rel="noopener">X</a>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($u) ?>" target="_blank" rel="noopener">Facebook</a>
    </div></aside>
  <article class="prose legal" data-toc-src>
  <?php foreach (preg_split('/\n{2,}/', (string)$post['body']) as $para): $para = trim($para); if ($para === '') continue;
    if (preg_match('/^(#{2,3})\s+(.*)$/', $para, $m)): ?><h2><?= e($m[2]) ?></h2>
    <?php elseif (preg_match('/^[-*] /m', $para)): ?><ul><?php foreach (preg_split('/\n/', $para) as $li): ?><li><?= $inline(preg_replace('/^[-*]\s+/', '', $li)) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p><?= nl2br($inline($para)) ?></p><?php endif; endforeach; ?>
  <?php if (!empty($meta['faqs'])): App\View::$shared['ld'][] = App\Seo::faq(array_map(fn($f) => ['q' => (string)($f['q'] ?? ''), 'a' => (string)($f['a'] ?? '')], $meta['faqs'])); ?>
    <h2><?= e(t('Questions')) ?></h2><div class="faq-l"><?php foreach ($meta['faqs'] as $f): ?><details><summary><?= e($f['q'] ?? '') ?></summary><p><?= e($f['a'] ?? '') ?></p></details><?php endforeach; ?></div>
  <?php endif; ?>
  <div class="post-cta"><div><b><?= e(t('Ready to take the next step?')) ?></b><p><?= e(t('Start online in minutes, or ask a question first.')) ?></p></div><div class="row"><a class="btn btn-primary" href="<?= e($primary) ?>"><?= e(t('Start now')) ?></a><button class="btn btn-ghost" type="button" data-chat=""><?= e(t('Ask the assistant')) ?></button></div></div>
  </article>
</div></section>
