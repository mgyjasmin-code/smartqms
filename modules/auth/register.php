<?php
/** Client account registration was retired by the no-account queue workflow. */
require_once __DIR__ . '/../../config/config.php';
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'error' => 'Client accounts are no longer required. Use the public queue form.',
    'join_url' => APP_URL . '/queue/join/',
]);
