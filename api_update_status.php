<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
$is_online = intval($_POST['is_online'] ?? 1);
$device_info = trim($_POST['device_info'] ?? 'Unknown');

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Insert or update online status
    $stmt = $pdo->prepare("
        INSERT INTO user_online_status (user_id, is_online, device_info, last_seen) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            is_online = VALUES(is_online),
            device_info = VALUES(device_info),
            last_seen = NOW()
    ");
    
    $stmt->execute([$user_id, $is_online, $device_info]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
