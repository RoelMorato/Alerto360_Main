<?php
session_start();
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

$incident_id = intval($_POST['incident_id'] ?? 0);
$admin_id = intval($_POST['admin_id'] ?? $_SESSION['user_id'] ?? 0);

if ($incident_id <= 0 || $admin_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Incident ID and Admin ID are required']);
    exit;
}

try {
    // Verify user is admin
    $admin_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role IN ('admin', 'super_admin')");
    $admin_stmt->execute([$admin_id]);
    $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit;
    }
    
    // Check if incident exists and is declined
    $incident_stmt = $pdo->prepare("SELECT id, status, type, decline_reason, declined_by FROM incidents WHERE id = ?");
    $incident_stmt->execute([$incident_id]);
    $incident = $incident_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident not found']);
        exit;
    }
    
    if ($incident['status'] !== 'declined') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only declined incidents can be reassigned']);
        exit;
    }
    
    // Reset incident to pending status and clear decline info
    $update_stmt = $pdo->prepare("
        UPDATE incidents 
        SET status = 'pending',
            declined_by = NULL, 
            decline_reason = NULL, 
            declined_at = NULL,
            reassigned_by = ?,
            reassigned_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$admin_id, $incident_id]);
    
    // Create notification for all responders
    $responder_stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'responder'");
    $responders = $responder_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notification_message = "Incident #{$incident_id} ({$incident['type']}) has been reassigned and is now available for acceptance.";
    
    $notify_stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, incident_id, message, type, created_at) 
        VALUES (?, ?, ?, 'incident_reassigned', NOW())
    ");
    
    foreach ($responders as $responder) {
        $notify_stmt->execute([$responder['id'], $incident_id, $notification_message]);
    }
    
    // Log the reassignment
    error_log("Incident #{$incident_id} reassigned by {$admin['name']} (ID: {$admin_id})");
    
    echo json_encode([
        'success' => true,
        'message' => 'Incident reassigned successfully',
        'incident_id' => $incident_id,
        'reassigned_by' => $admin['name']
    ]);
    
} catch (PDOException $e) {
    error_log("Error in api_reassign_incident.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}