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
$action = $_POST['action'] ?? ''; // 'accept' or 'resolve'

if ($incident_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Incident ID and User ID required']);
    exit;
}

try {
    if ($action === 'accept') {
        // Accept incident
        $stmt = $pdo->prepare("UPDATE incidents SET status = 'accepted', accepted_by = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$user_id, $incident_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Incident accepted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incident already accepted or not found']);
        }
    } elseif ($action === 'resolve') {
        // Resolve incident
        $stmt = $pdo->prepare("UPDATE incidents SET status = 'resolved' WHERE id = ? AND accepted_by = ?");
        $stmt->execute([$incident_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Incident resolved']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot resolve incident']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
