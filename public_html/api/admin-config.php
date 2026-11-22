<?php
// Retired in v3.0 (security audit). API configuration moved to Admin -> Integrations (encrypted, admin-only).
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'error' => 'API configuration moved to Admin -> Integrations (encrypted, admin-only).']);
