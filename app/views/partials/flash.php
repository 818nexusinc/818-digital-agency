<?php foreach (App\Session::takeFlash() as [$type, $msg]): ?>
<div class="alert alert-<?= $type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info') ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
<?php endforeach; ?>
