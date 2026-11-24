<?php $cls = match (true) { in_array($s, ['paid', 'completed', 'active', 'approved', 'passed', 'resolved', 'accepted', 'connected', 'delivered', 'ready', 'enabled', 'converted', 'sent', 'processed', 'ok', 'configured'], true) => 'b-green',
  in_array($s, ['failed', 'cancelled', 'rejected', 'error', 'refunded', 'expired', 'bounced', 'disabled', 'high'], true) => 'b-red',
  in_array($s, ['awaiting_requirements', 'awaiting_customer', 'awaiting_approval', 'needs_approval', 'needs_info', 'pending', 'awaiting_payment', 'revision', 'blocked', 'paused', 'queued', 'medium', 'changes_requested'], true) => 'b-amber',
  in_array($s, ['not_configured'], true) => 'b-grey',
  default => 'b-blue' }; ?><span class="badge <?= $cls ?>"><?= e(label((string)$s)) ?></span>
