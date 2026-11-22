<div class="topbar"><div><a class="small" href="/portal/projects/<?= e($p['number']) ?>">← <?= e($p['title']) ?></a><h1>Project requirements</h1></div></div>
<form class="panel" method="post" enctype="multipart/form-data" action="/portal/projects/<?= e($p['number']) ?>/requirements" style="max-width:760px"><?= App\Csrf::field() ?>
  <p class="muted">Answer what you can. If something is unclear, our team will ask you.</p>
  <?php foreach ($questions as $k => $q): ?><div class="field"><label for="q-<?= e($k) ?>"><?= e($q) ?></label><textarea id="q-<?= e($k) ?>" name="a[<?= e($k) ?>]" style="min-height:80px"><?= e($answers[$k] ?? '') ?></textarea></div><?php endforeach; ?>
  <div class="field"><label for="f">Attach a file (optional — logo, brief, documents · max 10 MB)</label><input id="f" type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.webp,.gif,.txt,.csv,.docx,.xlsx,.pptx"></div>
  <button class="btn btn-primary">Submit requirements</button></form>
