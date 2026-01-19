<?php
// Suppress HTML error output
error_reporting(0);
ini_set('display_errors', 0);

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
$user_id = intval($_POST['user_id'] ?? 0);
$decline_reason = trim($_POST['decline_reason'] ?? '');

if ($incident_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Incident ID and User ID are required']);
    exit;
}

if (empty($decline_reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Decline reason is required']);
    exit;
}

try {
    // Verify user is a responder and get their details for accuracy
    $user_stmt = $pdo->prepare("SELECT id, name, email, responder_type FROM users WHERE id = ? AND role = 'responder'");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized or user not found']);
        exit;
    }
    
    // Log the responder details for accuracy verification
    error_log("Decline request from responder: {$user['name']} (ID: {$user_id}, Type: {$user['responder_type']}, Email: {$user['email']})");
    
    // Check if incident exists and is not already declined by this responder
    $incident_stmt = $pdo->prepare("SELECT id, status, declined_by FROM incidents WHERE id = ?");
    $incident_stmt->execute([$incident_id]);
    $incident = $incident_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident not found']);
        exit;
    }
    
    // Prevent duplicate declines by the same responder
    if ($incident['declined_by'] == $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You have already declined this incident']);
        exit;
    }
    
    if ($incident['status'] === 'declined') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Incident is already declined']);
        exit;
    }
    
    if (!in_array($incident['status'], ['pending', 'accepted'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Incident cannot be declined in current status']);
        exit;
    }
    
    // Update incident with decline information and keep status as declined
    // Admin can use reassign button to make it available again
    $update_stmt = $pdo->prepare("
        UPDATE incidents 
        SET declined_by = ?, 
            decline_reason = ?, 
            declined_at = NOW(),
            status = 'declined'
        WHERE id = ?
    ");
    $update_stmt->execute([$user_id, $decline_reason, $incident_id]);
    
    // Create notification for admins
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notification_message = "Incident #{$incident_id} ({$incident['type']}) was declined by {$user['name']} ({$user['responder_type']}). Reason: {$decline_reason}";
    
    $notify_stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, incident_id, message, type, created_at) 
        VALUES (?, ?, ?, 'incident_declined', NOW())
    ");
    
    foreach ($admins as $admin) {
        $notify_stmt->execute([$admin['id'], $incident_id, $notification_message]);
    }
    
    // Log the decline
    error_log("Incident #{$incident_id} declined by {$user['name']} (ID: {$user_id}). Reason: {$decline_reason}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Incident declined successfully',
        'incident_id' => $incident_id,
        'declined_by' => $user['name'],
        'decline_reason' => $decline_reason
    ]);
    
} catch (PDOException $e) {
    error_log("Error in api_decline_incident.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
