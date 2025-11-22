<?php
session_start();
require 'db_connect.php';

// Only allow responders
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'responder') {
    header('Location: login.php');
    exit;
}

// Function to accept incident (Step 1: Accept and get directions)
function acceptIncident($pdo, $incident_id, $responder_id) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get incident details and check if it's still pending
        $check_stmt = $pdo->prepare("SELECT i.*, u.name as reporter_name FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.id = ? AND i.status = 'pending'");
        $check_stmt->execute([$incident_id]);
        $incident = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$incident) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Incident not found or already processed'];
        }
        
        // Get responder details
        $responder_stmt = $pdo->prepare("SELECT name, responder_type FROM users WHERE id = ? AND role = 'responder'");
        $responder_stmt->execute([$responder_id]);
        $responder = $responder_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$responder) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Invalid responder'];
        }
        
        // Update incident status to 'accepted' and assign responder
        $update_stmt = $pdo->prepare("UPDATE incidents SET status = 'accepted', accepted_by = ?, accepted_at = NOW() WHERE id = ?");
        $update_result = $update_stmt->execute([$responder_id, $incident_id]);
        
        if (!$update_result) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to accept incident'];
        }
        
        // Log the acceptance action
        error_log("Incident #{$incident_id} accepted by responder #{$responder_id} ({$responder['name']})");
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'Incident accepted successfully! You can now get directions to the location.',
            'incident_id' => $incident_id,
            'responder_name' => $responder['name'],
            'latitude' => $incident['latitude'],
            'longitude' => $incident['longitude'],
            'incident_type' => $incident['type'],
            'description' => $incident['description']
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Accept incident error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error accepting incident: ' . $e->getMessage()];
    }
}

// Function to complete incident (Step 2: Mark as complete and notify admin)
function completeIncident($pdo, $incident_id, $responder_id) {
    require_once 'notification_functions.php';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get incident details and check if it's accepted by this responder
        $check_stmt = $pdo->prepare("SELECT i.*, u.name as reporter_name FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.id = ? AND i.status = 'accepted' AND i.accepted_by = ?");
        $check_stmt->execute([$incident_id, $responder_id]);
        $incident = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$incident) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Incident not found, not accepted, or not assigned to you'];
        }
        
        // Get responder details
        $responder_stmt = $pdo->prepare("SELECT name, responder_type FROM users WHERE id = ? AND role = 'responder'");
        $responder_stmt->execute([$responder_id]);
        $responder = $responder_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$responder) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Invalid responder'];
        }
        
        // Update incident status to 'completed'
        $update_stmt = $pdo->prepare("UPDATE incidents SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $update_result = $update_stmt->execute([$incident_id]);
        
        if (!$update_result) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to complete incident'];
        }
        
        // Log the completion action
        error_log("Incident #{$incident_id} completed by responder #{$responder_id} ({$responder['name']})");
        
        // Notify all admins that the incident has been completed
        $completion_message = "✅ INCIDENT COMPLETED\n";
        $completion_message .= "Incident ID: #{$incident_id}\n";
        $completion_message .= "Type: {$incident['type']}\n";
        $completion_message .= "Reporter: {$incident['reporter_name']}\n";
        $completion_message .= "Completed by: {$responder['name']} ({$responder['responder_type']})\n";
        $completion_message .= "Status: COMPLETED\n";
        $completion_message .= "Completed at: " . date('Y-m-d H:i:s');
        
        // Send notification to all admins
        notifyAllAdmins($pdo, $completion_message);
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'Incident completed successfully! Admins have been notified.',
            'incident_id' => $incident_id,
            'responder_name' => $responder['name']
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Complete incident error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error completing incident: ' . $e->getMessage()];
    }
}

// Handle POST request for accepting incident (Step 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_incident'])) {
    $incident_id = intval($_POST['incident_id']);
    $responder_id = $_SESSION['user_id'];
    
    $result = acceptIncident($pdo, $incident_id, $responder_id);
    
    if ($result['success']) {
        // Set success message in session
        $_SESSION['success_message'] = $result['message'];
        $_SESSION['accepted_incident_id'] = $result['incident_id'];
        $_SESSION['incident_location'] = [
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'type' => $result['incident_type'],
            'description' => $result['description']
        ];
        
        // Redirect back to responder dashboard
        header('Location: responder_dashboard.php?accepted=1');
        exit;
    } else {
        // Set error message and redirect back
        $_SESSION['error_message'] = $result['message'];
        header('Location: responder_dashboard.php');
        exit;
    }
}

// Handle POST request for completing incident (Step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_incident'])) {
    $incident_id = intval($_POST['incident_id']);
    $responder_id = $_SESSION['user_id'];
    
    $result = completeIncident($pdo, $incident_id, $responder_id);
    
    if ($result['success']) {
        // Set success message in session
        $_SESSION['success_message'] = $result['message'];
        $_SESSION['completed_incident_id'] = $result['incident_id'];
        $_SESSION['responder_name'] = $result['responder_name'];
        
        // Redirect back to responder dashboard
        header('Location: responder_dashboard.php?completed=1');
        exit;
    } else {
        // Set error message and redirect back
        $_SESSION['error_message'] = $result['message'];
        header('Location: responder_dashboard.php');
        exit;
    }
}

// Handle AJAX request for quick acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_accept'])) {
    header('Content-Type: application/json');
    
    $incident_id = intval($_POST['incident_id']);
    $responder_id = $_SESSION['user_id'];
    
    $result = acceptPendingRequest($pdo, $incident_id, $responder_id);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'redirect_url' => 'admin_dashboard.php?from_acceptance=1&incident_id=' . $incident_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    exit;
}

// If accessed directly, redirect to responder dashboard
header('Location: responder_dashboard.php');
exit;
?>
