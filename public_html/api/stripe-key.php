<?php
// Retired in v3.0 (security audit). Stripe keys are managed in the Control Tower: Admin -> Integrations (encrypted). This endpoint allowed unauthenticated key replacement and has been removed.
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'error' => 'Stripe keys are managed in the Control Tower: Admin -> Integrations (encrypted). This endpoint allowed unauthenticated key replacement and has been removed.']);
