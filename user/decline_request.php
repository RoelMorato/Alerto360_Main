<?php
session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Only allow responders
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'responder') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['decline_incident'])) {
    header('Location: responder_dashboard.php');
    exit;
}

$incident_id = intval($_POST['incident_id'] ?? 0);
$decline_reason = trim($_POST['decline_reason'] ?? '');
$responder_id = $_SESSION['user_id'];

if ($incident_id <= 0) {
    $_SESSION['error_message'] = 'Invalid incident ID';
    header('Location: responder_dashboard.php');
    exit;
}

if (empty($decline_reason)) {
    $_SESSION['error_message'] = 'Please provide a reason for declining';
    header('Location: responder_dashboard.php');
    exit;
}

try {
    // Get responder info
    $responder_stmt = $pdo->prepare("SELECT name, responder_type FROM users WHERE id = ?");
    $responder_stmt->execute([$responder_id]);
    $responder = $responder_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$responder) {
        $_SESSION['error_message'] = 'Responder not found';
        header('Location: responder_dashboard.php');
        exit;
    }
    
    // Get incident info
    $incident_stmt = $pdo->prepare("SELECT id, type, status FROM incidents WHERE id = ?");
    $incident_stmt->execute([$incident_id]);
    $incident = $incident_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        $_SESSION['error_message'] = 'Incident not found';
        header('Location: responder_dashboard.php');
        exit;
    }
    
    if ($incident['status'] !== 'pending') {
        $_SESSION['error_message'] = 'This incident is no longer pending';
        header('Location: responder_dashboard.php');
        exit;
    }
    
    // Update incident status to declined
    $update_stmt = $pdo->prepare("
        UPDATE incidents 
        SET status = 'declined',
            declined_by = ?,
            decline_reason = ?,
            declined_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$responder_id, $decline_reason, $incident_id]);
    
    // Notify admins about the decline
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($admins as $admin) {
        createNotification(
            $pdo,
            $admin['id'],
            'incident_declined',
            "Incident #{$incident_id} ({$incident['type']}) was declined by {$responder['name']} ({$responder['responder_type']}). Reason: {$decline_reason}",
            $incident_id
        );
    }
    
    $_SESSION['success_message'] = "Incident #{$incident_id} has been declined. Admin has been notified.";
    
} catch (PDOException $e) {
    error_log("Error declining incident: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error declining incident: ' . $e->getMessage();
}

header('Location: responder_dashboard.php');
exit;
