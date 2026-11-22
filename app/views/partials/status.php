<?php $cls = match (true) { in_array($s, ['paid', 'completed', 'active', 'approved', 'passed', 'resolved', 'accepted', 'connected', 'delivered', 'ready', 'enabled', 'converted', 'sent', 'processed', 'ok'], true) => 'b-green',
  in_array($s, ['failed', 'cancelled', 'rejected', 'error', 'refunded', 'expired', 'bounced', 'disabled', 'high'], true) => 'b-red',
  in_array($s, ['awaiting_requirements', 'awaiting_customer', 'awaiting_approval', 'needs_approval', 'needs_info', 'pending', 'awaiting_payment', 'revision', 'blocked', 'paused', 'queued', 'medium', 'changes_requested', 'not_configured', 'configured'], true) => 'b-amber',
  default => 'b-blue' }; ?><span class="badge <?= $cls ?>"><?= e(label((string)$s)) ?></span>
