<?php
/**
 * Auto-assign responder based on emergency type and availability
 */

function autoAssignResponder($pdo, $incidentId, $emergencyType, $latitude = null, $longitude = null) {
    // Map emergency types to responder types (matching database values)
    $responderTypeMap = [
        'Fire' => 'BFP',
        'Flood' => 'MDDRMO',
        'Landslide' => 'MDDRMO',
        'Accident' => 'MDDRMO',
        'Crime' => 'PNP',
        'Other' => null // Will assign any available responder
    ];
    
    $preferredType = $responderTypeMap[$emergencyType] ?? null;
    
    // Build query to find available responders
    $query = "
        SELECT u.id, u.name, u.responder_type, 
               os.is_online, os.on_duty,
               COUNT(i.id) as active_incidents
        FROM users u
        LEFT JOIN user_online_status os ON u.id = os.user_id
        LEFT JOIN incidents i ON u.id = i.accepted_by AND i.status IN ('accepted', 'in_progress')
        WHERE u.role = 'responder'
        AND (os.on_duty = 1 OR os.on_duty IS NULL)
    ";
    
    // Add responder type filter if specified
    if ($preferredType) {
        $query .= " AND u.responder_type = " . $pdo->quote($preferredType);
    }
    
    $query .= "
        GROUP BY u.id
        ORDER BY 
            os.is_online DESC,
            os.on_duty DESC,
            active_incidents ASC,
            u.id ASC
        LIMIT 1
    ";
    
    $stmt = $pdo->query($query);
    $responder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($responder) {
        // Check if assigned_to column exists
        $hasAssignedTo = false;
        try {
            $check = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'assigned_to'");
            $hasAssignedTo = $check->rowCount() > 0;
        } catch (Exception $e) {
            $hasAssignedTo = false;
        }
        
        // Assign the incident - use assigned_to if available, otherwise use accepted_by
        if ($hasAssignedTo) {
            $update = $pdo->prepare("
                UPDATE incidents 
                SET assigned_to = ?, 
                    status = 'pending'
                WHERE id = ?
            ");
        } else {
            // Fallback: use accepted_by but keep status pending
            $update = $pdo->prepare("
                UPDATE incidents 
                SET accepted_by = ?, 
                    status = 'pending'
                WHERE id = ?
            ");
        }
        
        if ($update->execute([$responder['id'], $incidentId])) {
            // Create notification for responder
            require_once 'notification_functions.php';
            createNotification(
                $pdo,
                $responder['id'],
                "New $emergencyType incident assigned to you - Please Accept or Decline",
                'incident',
                $incidentId
            );
            
            return [
                'success' => true,
                'responder_id' => $responder['id'],
                'responder_name' => $responder['name'],
                'responder_type' => $responder['responder_type'],
                'message' => "Assigned to {$responder['name']} ({$responder['responder_type']}) - Awaiting acceptance"
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'No available responders found'
    ];
}

/**
 * Get responder recommendation without assigning
 */
function getResponderRecommendation($pdo, $emergencyType) {
    $responderTypeMap = [
        'Fire' => 'BFP',
        'Flood' => 'MDDRMO',
        'Landslide' => 'MDDRMO',
        'Accident' => 'MDDRMO',
        'Crime' => 'PNP',
        'Other' => 'MDDRMO'
    ];
    
    return [
        'recommended_type' => $responderTypeMap[$emergencyType] ?? 'MDDRMO',
        'emergency_type' => $emergencyType
    ];
}
