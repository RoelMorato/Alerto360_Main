<?php
// Initialize API with clean JSON output
require_once 'api_init.php';
require_once 'db_connect.php';
require_once 'email_verification_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$code = trim($_POST['code'] ?? '');

if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and verification code are required'
    ]);
    exit;
}

try {
    $result = verifyCode($email, $code);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Verification error: ' . $e->getMessage()
    ]);
}
