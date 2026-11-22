<section class="section tight"><div class="wrap" style="max-width:860px">
  <span class="eyebrow">FAQ</span><h1 style="margin-top:8px">Frequently asked questions</h1>
  <div class="faq" style="margin-top:22px"><?php foreach ($articles as $a): ?><details><summary><?= e($a['title']) ?></summary><p><?= nl2br(e($a['body'])) ?></p></details><?php endforeach; ?></div>
  <p style="margin-top:22px">Didn't find it? <button class="btn btn-ghost btn-sm" type="button" data-chat="">Ask the assistant</button> or <a href="/contact">contact us</a>.</p>
</div></section>
