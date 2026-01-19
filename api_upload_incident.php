<?php
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

// Get form data
$user_id = intval($_POST['user_id'] ?? 0);
$type = trim($_POST['type'] ?? '');
$description = trim($_POST['description'] ?? '');
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

// Get device tracking info
$device_type = $_POST['device_type'] ?? detectDeviceType();
$device_info = $_POST['device_info'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
$ip_address = getClientIP();
$submitted_at = date('Y-m-d H:i:s');

// Function to detect device type from User-Agent (more accurate)
function detectDeviceType() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check if it's from Flutter/Dart app first
    if (strpos($ua, 'Dart') !== false || strpos($ua, 'Flutter') !== false || strpos($ua, 'Alerto360 App') !== false) {
        return 'Mobile'; // Flutter app is always mobile
    }
    
    // Check for tablets first (before mobile check)
    if (preg_match('/iPad|Android(?!.*Mobile)|Tablet|PlayBook|Silk/i', $ua)) {
        return 'Tablet';
    }
    
    // Check for mobile devices
    if (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini|Opera Mobi/i', $ua)) {
        return 'Mobile';
    }
    
    // Default to Desktop
    return 'Desktop';
}

// Function to get detailed device info
function getDetailedDeviceInfo($ua) {
    $info = [];
    
    // Detect OS
    if (strpos($ua, 'Alerto360 App') !== false) {
        // Parse Flutter app info: "Alerto360 App | android 14"
        if (preg_match('/Alerto360 App \| (\w+) (.+)/i', $ua, $matches)) {
            $info['os'] = ucfirst($matches[1]);
            $info['os_version'] = $matches[2];
            $info['browser'] = 'Alerto360 Mobile App';
        }
    } elseif (preg_match('/Android ([0-9.]+)/i', $ua, $matches)) {
        $info['os'] = 'Android';
        $info['os_version'] = $matches[1];
    } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $matches)) {
        $info['os'] = 'iOS';
        $info['os_version'] = str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/Windows NT ([0-9.]+)/i', $ua, $matches)) {
        $info['os'] = 'Windows';
        $winVersions = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
        $info['os_version'] = $winVersions[$matches[1]] ?? $matches[1];
    } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $matches)) {
        $info['os'] = 'macOS';
        $info['os_version'] = str_replace('_', '.', $matches[1]);
    } elseif (strpos($ua, 'Linux') !== false) {
        $info['os'] = 'Linux';
        $info['os_version'] = '';
    }
    
    // Detect Browser (if not already set)
    if (!isset($info['browser'])) {
        if (strpos($ua, 'Edg/') !== false) {
            $info['browser'] = 'Microsoft Edge';
        } elseif (strpos($ua, 'Chrome/') !== false) {
            $info['browser'] = 'Google Chrome';
        } elseif (strpos($ua, 'Firefox/') !== false) {
            $info['browser'] = 'Mozilla Firefox';
        } elseif (strpos($ua, 'Safari/') !== false && strpos($ua, 'Chrome') === false) {
            $info['browser'] = 'Safari';
        } else {
            $info['browser'] = 'Unknown Browser';
        }
    }
    
    return $info;
}

// Function to get client IP address
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    return trim($ip);
}

// Auto-assign responder type based on incident type
function getResponderType($incidentType) {
    switch (strtolower($incidentType)) {
        case 'fire':
            return 'BFP';
        case 'crime':
            return 'PNP';
        case 'flood':
        case 'landslide':
        case 'accident':
            return 'MDDRMO';
        default:
            return 'MDDRMO';
    }
}

$responder_type = getResponderType($type);

if ($user_id <= 0 || empty($type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID and type are required']);
    exit;
}

try {
    $image_path = null;
    
    // Handle image upload if present
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed']);
            exit;
        }
        
        // Check file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File too large. Maximum 5MB allowed']);
            exit;
        }
        
        $filename = 'incident_' . uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }
    
    // AI Image Analysis - Only if type is not provided or is "Other"
    // Mobile app already does AI analysis, so we trust that result
    // This is a fallback for web uploads or manual submissions
    if ($image_path && (empty($type) || $type === 'Other')) {
        require_once 'simple_ai_helper.php';
        $aiResult = simpleAIAnalysis($image_path);
        
        if ($aiResult && $aiResult['confidence'] > 0.6) {
            $type = $aiResult['type'];
            // Add AI description
            if (!empty($aiResult['description'])) {
                $description = $aiResult['description'] . ($description ? "\n\nUser notes: " . $description : '');
            }
            $responder_type = getResponderType($type);
        }
    }
    
    // Insert incident into database with device tracking
    $stmt = $pdo->prepare("
        INSERT INTO incidents (user_id, type, description, latitude, longitude, image_path, status, responder_type, created_at, device_type, device_info, ip_address, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $type,
        $description,
        $latitude,
        $longitude,
        $image_path,
        $responder_type,
        $device_type,
        substr($device_info, 0, 255), // Limit to 255 chars
        $ip_address,
        $submitted_at
    ]);
    
    $incident_id = $pdo->lastInsertId();
    
    // Auto-assign responder based on emergency type and availability
    require_once 'auto_assign_responder.php';
    $assignmentResult = autoAssignResponder($pdo, $incident_id, $type, $latitude, $longitude);
    
    // Send notifications
    require_once 'notification_functions.php';
    notifyNewIncident($pdo, $incident_id, $type);
    
    $response = [
        'success' => true,
        'message' => 'Incident reported successfully',
        'incident_id' => $incident_id,
        'image_path' => $image_path,
        'detected_type' => $type,
        'responder_type' => $responder_type
    ];
    
    // Add assignment info if successful
    if ($assignmentResult['success']) {
        $response['auto_assigned'] = true;
        $response['assigned_to'] = $assignmentResult['responder_name'];
        $response['assignment_message'] = $assignmentResult['message'];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
