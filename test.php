<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'status' => 'success',
    'message' => 'Alerto360 API is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => 'Vercel',
    'php_version' => phpversion()
]);
?>