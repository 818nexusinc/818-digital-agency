<?php $badge = function ($s) { include APP_ROOT . '/views/partials/status.php'; }; ?>
<div class="topbar"><h1>Orders &amp; billing</h1></div>
<?php if (!$orders): ?><div class="empty">No orders yet. <a href="/services">Browse services</a></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td><a href="/portal/orders/<?= e($o['number']) ?>"><?= e($o['number']) ?></a></td><td><?= e(substr($o['created_at'], 0, 10)) ?></td><td><?= money((int)$o['total_cents']) ?></td><td><?php $badge($o['status']); ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
