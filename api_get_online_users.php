<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require 'db_connect.php';

try {
    // Get all users with their online status
    // Consider users offline if last_seen is more than 5 minutes ago
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role,
            COALESCE(s.is_online, 0) as is_online,
            s.last_seen,
            s.device_info,
            CASE 
                WHEN s.last_seen IS NULL THEN 'Never logged in'
                WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 'Online'
                WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 30 THEN 'Away'
                ELSE 'Offline'
            END as status
        FROM users u
        LEFT JOIN user_online_status s ON u.id = s.user_id
        ORDER BY 
            CASE 
                WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 1
                WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 30 THEN 2
                ELSE 3
            END,
            s.last_seen DESC
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count statistics
    $online_count = 0;
    $away_count = 0;
    $offline_count = 0;
    
    foreach ($users as $user) {
        if ($user['status'] === 'Online') $online_count++;
        elseif ($user['status'] === 'Away') $away_count++;
        else $offline_count++;
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'statistics' => [
            'online' => $online_count,
            'away' => $away_count,
            'offline' => $offline_count,
            'total' => count($users)
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
