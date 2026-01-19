<?php
// Suppress HTML error output for clean JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require 'db_connect.php';

$user_id = intval($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Check if notifications table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->rowCount() == 0) {
        echo json_encode([
            'success' => true,
            'notifications' => [],
            'unread_count' => 0,
            'message' => 'Notifications table not found. Run setup_notifications.php'
        ]);
        exit;
    }
    
    // Get notifications for user
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.user_id,
            n.incident_id,
            n.message,
            n.type,
            COALESCE(n.is_read, 0) as is_read,
            n.created_at,
            i.type AS incident_type,
            i.status AS incident_status
        FROM notifications n
        LEFT JOIN incidents i ON n.incident_id = i.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)");
    $count_stmt->execute([$user_id]);
    $unread_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unread_count,
        'count' => count($notifications)
    ]);
    
} catch (PDOException $e) {
    error_log("Notifications API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
