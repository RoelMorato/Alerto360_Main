<?php
// Suppress HTML error output
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
    // Get responder type
    $user_stmt = $pdo->prepare("SELECT responder_type, name FROM users WHERE id = ? AND role = 'responder'");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Not a responder',
            'user_id' => $user_id
        ]);
        exit;
    }
    
    $responder_type = $user['responder_type'];
    
    // Check if assigned_to column exists
    $hasAssignedTo = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'assigned_to'");
        $hasAssignedTo = $check->rowCount() > 0;
    } catch (Exception $e) {
        $hasAssignedTo = false;
    }
    
    // Get incidents: assigned to me, accepted by me, or my responder type (pending, unassigned)
    if ($hasAssignedTo) {
        $stmt = $pdo->prepare("
            SELECT 
                incidents.*,
                users.name AS reporter_name,
                responder.name AS responder_name,
                CASE WHEN incidents.assigned_to = ? THEN 1 ELSE 0 END AS is_assigned_to_me
            FROM incidents 
            JOIN users ON incidents.user_id = users.id
            LEFT JOIN users AS responder ON incidents.accepted_by = responder.id
            WHERE incidents.assigned_to = ?
               OR incidents.accepted_by = ?
               OR (incidents.responder_type = ? AND incidents.status = 'pending' AND incidents.assigned_to IS NULL)
            ORDER BY 
                CASE incidents.status
                    WHEN 'pending' THEN 1
                    WHEN 'accepted' THEN 2
                    ELSE 3
                END,
                incidents.created_at DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $responder_type]);
    } else {
        // Fallback query without assigned_to column
        $stmt = $pdo->prepare("
            SELECT 
                incidents.*,
                users.name AS reporter_name,
                responder.name AS responder_name,
                0 AS is_assigned_to_me
            FROM incidents 
            JOIN users ON incidents.user_id = users.id
            LEFT JOIN users AS responder ON incidents.accepted_by = responder.id
            WHERE incidents.responder_type = ? OR incidents.accepted_by = ?
            ORDER BY 
                CASE incidents.status
                    WHEN 'pending' THEN 1
                    WHEN 'accepted' THEN 2
                    ELSE 3
                END,
                incidents.created_at DESC
        ");
        $stmt->execute([$responder_type, $user_id]);
    }
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("API: Responder {$user['name']} (ID: {$user_id}, Type: {$responder_type}) fetched " . count($incidents) . " incidents");
    
    echo json_encode([
        'success' => true,
        'incidents' => $incidents,
        'responder_type' => $responder_type,
        'responder_name' => $user['name'],
        'count' => count($incidents)
    ]);
    
} catch (PDOException $e) {
    error_log("API Error in api_responder_incidents.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
