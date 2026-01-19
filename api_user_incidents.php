<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    // Get user's incidents with responder info
    $stmt = $pdo->prepare("
        SELECT 
            incidents.*,
            responder.name AS responder_name,
            responder.responder_type AS assigned_responder_type
        FROM incidents 
        LEFT JOIN users AS responder ON incidents.accepted_by = responder.id
        WHERE incidents.user_id = ?
        ORDER BY incidents.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'incidents' => $incidents
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
