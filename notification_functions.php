<?php
/**
 * Notification Functions for Alerto360
 * Handles automatic notifications to responders when incidents are reported
 */

require_once 'db_connect.php';

/**
 * Send notification to all responders of a specific type
 * @param PDO $pdo Database connection
 * @param string $responder_type Type of responder (PNP, BFP, MDDRMO)
 * @param int $incident_id ID of the incident
 * @param string $incident_type Type of incident (Fire, Crime, etc.)
 * @param string $location_info Location description or coordinates
 * @return bool Success status
 */
function notifyResponders($pdo, $responder_type, $incident_id, $incident_type, $location_info = '') {
    try {
        // Get all responders of the specified type
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role = 'responder' AND responder_type = ?");
        $stmt->execute([$responder_type]);
        $responders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($responders)) {
            return false; // No responders of this type found
        }
        
        // Create notification message
        $message = "🚨 NEW EMERGENCY ALERT 🚨\n";
        $message .= "Type: " . $incident_type . "\n";
        $message .= "Incident ID: #" . $incident_id . "\n";
        if ($location_info) {
            $message .= "Location: " . $location_info . "\n";
        }
        $message .= "Status: PENDING - Requires immediate response\n";
        $message .= "Time: " . date('Y-m-d H:i:s');
        
        // Insert notifications for each responder
        $notification_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        
        $success_count = 0;
        foreach ($responders as $responder) {
            if ($notification_stmt->execute([$responder['id'], $message])) {
                $success_count++;
            }
        }
        
        // Log the notification event
        logNotificationEvent($pdo, $incident_id, $responder_type, $success_count, count($responders));
        
        return $success_count > 0;
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to all responders (for critical incidents)
 * @param PDO $pdo Database connection
 * @param int $incident_id ID of the incident
 * @param string $incident_type Type of incident
 * @param string $location_info Location description
 * @return bool Success status
 */
function notifyAllResponders($pdo, $incident_id, $incident_type, $location_info = '') {
    try {
        // Get all responders regardless of type
        $stmt = $pdo->query("SELECT id, name, email, responder_type FROM users WHERE role = 'responder'");
        $responders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($responders)) {
            return false;
        }
        
        // Create urgent notification message
        $message = "🚨 URGENT: ALL RESPONDERS ALERT 🚨\n";
        $message .= "Critical Incident Type: " . $incident_type . "\n";
        $message .= "Incident ID: #" . $incident_id . "\n";
        if ($location_info) {
            $message .= "Location: " . $location_info . "\n";
        }
        $message .= "⚠️ IMMEDIATE RESPONSE REQUIRED ⚠️\n";
        $message .= "Time: " . date('Y-m-d H:i:s');
        
        // Insert notifications for all responders
        $notification_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        
        $success_count = 0;
        foreach ($responders as $responder) {
            if ($notification_stmt->execute([$responder['id'], $message])) {
                $success_count++;
            }
        }
        
        // Log the notification event
        logNotificationEvent($pdo, $incident_id, 'ALL_RESPONDERS', $success_count, count($responders));
        
        return $success_count > 0;
        
    } catch (Exception $e) {
        error_log("All responders notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to admin users
 * @param PDO $pdo Database connection
 * @param int $incident_id ID of the incident
 * @param string $incident_type Type of incident
 * @param string $action Action taken (e.g., "New incident reported", "Incident accepted")
 * @return bool Success status
 */
function notifyAdmins($pdo, $incident_id, $incident_type, $action) {
    try {
        // Get all admin users
        $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'admin'");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($admins)) {
            return false;
        }
        
        // Create admin notification message
        $message = "📊 ADMIN NOTIFICATION\n";
        $message .= "Action: " . $action . "\n";
        $message .= "Incident Type: " . $incident_type . "\n";
        $message .= "Incident ID: #" . $incident_id . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s');
        
        // Insert notifications for all admins
        $notification_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        
        $success_count = 0;
        foreach ($admins as $admin) {
            if ($notification_stmt->execute([$admin['id'], $message])) {
                $success_count++;
            }
        }
        
        return $success_count > 0;
        
    } catch (Exception $e) {
        error_log("Admin notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log notification events for tracking
 * @param PDO $pdo Database connection
 * @param int $incident_id ID of the incident
 * @param string $responder_type Type of responders notified
 * @param int $success_count Number of successful notifications
 * @param int $total_count Total number of responders
 */
function logNotificationEvent($pdo, $incident_id, $responder_type, $success_count, $total_count) {
    try {
        // Create a simple log entry (you can expand this table if needed)
        $log_message = "Notified {$success_count}/{$total_count} {$responder_type} responders for incident #{$incident_id}";
        error_log("NOTIFICATION LOG: " . $log_message);
        
        // Optional: Create a notification_log table for better tracking
        // For now, we'll just use error_log
        
    } catch (Exception $e) {
        error_log("Notification logging error: " . $e->getMessage());
    }
}

/**
 * Get unread notifications for a user
 * @param PDO $pdo Database connection
 * @param int $user_id ID of the user
 * @return array Array of unread notifications
 */
function getUnreadNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Notify all admin users
 * @param PDO $pdo Database connection
 * @param string $message Message to send to all admins
 * @return array Array of notification results
 */
function notifyAllAdmins($pdo, $message) {
    try {
        // Get all admin users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($admins)) {
            error_log("No admin users found for notification");
            return [];
        }
        
        // Insert notifications directly into database
        $notification_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        
        $success_count = 0;
        $results = [];
        
        // Send notification to each admin
        foreach ($admins as $admin) {
            try {
                if ($notification_stmt->execute([$admin['id'], $message])) {
                    $success_count++;
                    $results[] = true;
                } else {
                    $results[] = false;
                }
            } catch (Exception $e) {
                error_log("Failed to notify admin {$admin['id']}: " . $e->getMessage());
                $results[] = false;
            }
        }
        
        // Log the notification event
        $total_count = count($admins);
        error_log("Notified {$success_count}/{$total_count} admins: " . substr($message, 0, 100));
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Notify all admins error: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 * @param PDO $pdo Database connection
 * @param int $notification_id ID of the notification
 * @param int $user_id ID of the user (for security)
 * @return bool Success status
 */
function markNotificationAsRead($pdo, $notification_id, $user_id) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification count for a user
 * @param PDO $pdo Database connection
 * @param int $user_id ID of the user
 * @return int Number of unread notifications
 */
function getNotificationCount($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Get notification count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Determine which responders to notify based on incident type
 * @param string $incident_type Type of incident
 * @return array Array of responder types to notify
 */
function getResponderTypesForIncident($incident_type) {
    $incident_type = strtolower($incident_type);
    
    switch ($incident_type) {
        case 'fire':
            return ['BFP']; // Fire incidents go to BFP
            
        case 'crime':
            return ['PNP']; // Crime incidents go to PNP
            
        case 'flood':
        case 'landslide':
        case 'accident':
            return ['MDDRMO']; // Disaster incidents go to MDDRMO
            
        case 'other':
            return ['MDDRMO', 'PNP']; // Other incidents go to both
            
        default:
            return ['MDDRMO']; // Default to MDDRMO
    }
}

/**
 * Main function to handle incident notifications
 * @param PDO $pdo Database connection
 * @param int $incident_id ID of the incident
 * @param string $incident_type Type of incident
 * @param string $location_info Location information
 * @param bool $notify_all Whether to notify all responders (for critical incidents)
 * @return array Results of notification attempts
 */
function handleIncidentNotifications($pdo, $incident_id, $incident_type, $location_info = '', $notify_all = false) {
    $results = [];
    
    if ($notify_all) {
        // Critical incident - notify everyone
        $results['all_responders'] = notifyAllResponders($pdo, $incident_id, $incident_type, $location_info);
    } else {
        // Normal incident - notify specific responder types
        $responder_types = getResponderTypesForIncident($incident_type);
        
        foreach ($responder_types as $type) {
            $results[$type] = notifyResponders($pdo, $type, $incident_id, $incident_type, $location_info);
        }
    }
    
    // Always notify admins
    $results['admins'] = notifyAdmins($pdo, $incident_id, $incident_type, 'New incident reported');
    
    return $results;
}
?>
