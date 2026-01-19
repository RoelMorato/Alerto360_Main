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
require 'notification_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$incident_id = intval($_POST['incident_id'] ?? 0);
$user_id = intval($_POST['user_id'] ?? 0);

if ($incident_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Incident ID and User ID are required']);
    exit;
}

try {
    // Verify user is a responder
    $user_stmt = $pdo->prepare("SELECT name, responder_type FROM users WHERE id = ? AND role = 'responder'");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit;
    }
    
    // Check if incident exists and is accepted by this user
    $incident_stmt = $pdo->prepare("SELECT id, status, accepted_by, type FROM incidents WHERE id = ?");
    $incident_stmt->execute([$incident_id]);
    $incident = $incident_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident not found']);
        exit;
    }
    
    if ($incident['status'] !== 'accepted') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Incident is not in accepted status']);
        exit;
    }
    
    if ($incident['accepted_by'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only complete incidents you accepted']);
        exit;
    }
    
    // Update incident to completed
    $update_stmt = $pdo->prepare("
        UPDATE incidents 
        SET status = 'completed', 
            completed_at = NOW() 
        WHERE id = ?
    ");
    $update_stmt->execute([$incident_id]);
    
    // Notify admins about completion
    notifyAdminsIncidentCompleted($pdo, $incident_id, $user['name'], $incident['type']);
    
    // Log the completion
    error_log("Incident #{$incident_id} completed by {$user['name']} (ID: {$user_id})");
    
    echo json_encode([
        'success' => true,
        'message' => 'Incident marked as completed successfully',
        'incident_id' => $incident_id,
        'completed_by' => $user['name']
    ]);
    
} catch (PDOException $e) {
    error_log("Error in api_complete_incident.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
