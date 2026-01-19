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
$on_duty = intval($_POST['on_duty'] ?? 0);

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Update duty status
    $stmt = $pdo->prepare("
        UPDATE user_online_status 
        SET on_duty = ? 
        WHERE user_id = ?
    ");
    
    $stmt->execute([$on_duty, $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Duty status updated',
        'on_duty' => $on_duty
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
