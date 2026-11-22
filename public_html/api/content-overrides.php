<?php
// v3.0: site content is server-rendered and edited in the Control Tower. GET kept for archived UI compatibility.
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') { echo json_encode(['ok' => true, 'content' => null, 'overrides' => new stdClass(), 'ts' => 0]); exit; }
http_response_code(410);
echo json_encode(['ok' => false, 'error' => 'Content editing moved to the Control Tower (Content studio / Services & pricing).']);
