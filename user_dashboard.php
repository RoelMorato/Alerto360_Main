<?php
session_start();
require 'db_connect.php';
require 'notification_functions.php';

// Only allow citizens
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'citizen') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$report_msg = '';

// Map incident type to responder type
function getResponderType($incidentType, $userResponder = '') {
    if (!empty($userResponder)) {
        return $userResponder;
    }
    switch (strtolower($incidentType)) {
        case 'fire': return 'BFP';
        case 'crime': return 'PNP';
        case 'flood':
        case 'landslide':
        case 'accident': return 'MDDRMO';
        default: return 'MDDRMO';
    }
}

// Handle incident report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_incident'])) {
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $status = 'pending';
    $image_path = null;
    $user_responder = $_POST['responder_type'] ?? '';

    // Handle captured image data (from camera)
    if (!empty($_POST['captured_image'])) {
        $image_data = $_POST['captured_image'];
        $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $decoded_image = base64_decode($image_data);
        
        if ($decoded_image !== false) {
            $img_name = uniqid('captured_') . '.jpg';
            $target_dir = 'uploads/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $target_file = $target_dir . $img_name;
            if (file_put_contents($target_file, $decoded_image)) {
                $image_path = $target_file;
            }
        }
    }

    // Handle regular file upload
    elseif (isset($_FILES['incident_image']) && $_FILES['incident_image']['error'] === UPLOAD_ERR_OK) {
        $img_name = uniqid('incident_') . '_' . basename($_FILES['incident_image']['name']);
        $target_dir = 'uploads/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . $img_name;
        if (move_uploaded_file($_FILES['incident_image']['tmp_name'], $target_file)) {
            $image_path = $target_file;
        }
    }

    // AI Image Analysis - Auto-detect emergency type if image uploaded
    $ai_detected = false;
    if ($image_path) {
        require_once 'simple_ai_helper.php';
        $aiResult = simpleAIAnalysis($image_path);
        
        if ($aiResult && $aiResult['confidence'] > 0.6) {
            if (empty($type) || $type === 'Other') {
                $type = $aiResult['type'];
                $ai_detected = true;
            }
            if (empty($description)) {
                $description = $aiResult['description'];
            } else {
                $description = $aiResult['description'] . "\n\nUser notes: " . $description;
            }
        }
    }

    // Assign responder type (user-selected or auto)
    $responder_type = getResponderType($type, $user_responder);

    // Get accurate device tracking info
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Accurate device type detection
    $device_type = 'Desktop'; // Default
    if (preg_match('/iPad|Android(?!.*Mobile)|Tablet|PlayBook|Silk/i', $ua)) {
        $device_type = 'Tablet';
    } elseif (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
        $device_type = 'Mobile';
    }
    
    // Build detailed device info string
    $os = 'Unknown OS';
    $browser = 'Unknown Browser';
    
    // Detect OS
    if (preg_match('/Android ([0-9.]+)/i', $ua, $m)) {
        $os = 'Android ' . $m[1];
    } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m)) {
        $os = 'iOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Windows NT ([0-9.]+)/i', $ua, $m)) {
        $winVer = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
        $os = 'Windows ' . ($winVer[$m[1]] ?? $m[1]);
    } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $m)) {
        $os = 'macOS ' . str_replace('_', '.', $m[1]);
    } elseif (strpos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }
    
    // Detect Browser
    if (strpos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'Safari/') !== false && strpos($ua, 'Chrome') === false) {
        $browser = 'Safari';
    }
    
    $device_info = "Web Browser | $browser on $os";
    
    // Get IP address
    $ip_address = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    $ip_address = trim($ip_address);
    
    $submitted_at = date('Y-m-d H:i:s');

    // Insert into DB with device tracking
    $stmt = $pdo->prepare("INSERT INTO incidents (user_id, type, description, latitude, longitude, image_path, status, responder_type, device_type, device_info, ip_address, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $type, $description, $latitude, $longitude, $image_path, $status, $responder_type, $device_type, $device_info, $ip_address, $submitted_at])) {
        $incident_id = $pdo->lastInsertId();
        
        // Auto-assign responder
        require_once 'auto_assign_responder.php';
        $assignmentResult = autoAssignResponder($pdo, $incident_id, $type, $latitude, $longitude);
        
        $location_info = '';
        if ($latitude && $longitude) {
            $location_info = "GPS: {$latitude}, {$longitude}";
        }
        
        $notification_results = handleIncidentNotifications($pdo, $incident_id, $type, $location_info);
        $notified_count = 0;
        foreach ($notification_results as $result) {
            if ($result) $notified_count++;
        }
        
        $report_msg = '<div class="success-dialog"><div class="success-icon"><i class="fas fa-check-circle"></i></div>';
        $report_msg .= '<h4>Incident Reported Successfully!</h4>';
        $report_msg .= '<p><strong>Incident ID:</strong> #' . $incident_id . '</p>';
        
        if ($ai_detected) {
            $report_msg .= '<p><span class="ai-badge"><i class="fas fa-robot"></i> AI Detected:</span> ' . htmlspecialchars($type) . '</p>';
        }
        
        if ($assignmentResult['success']) {
            $report_msg .= '<p><span class="assigned-badge"><i class="fas fa-user-check"></i> Auto-Assigned:</span> ' . htmlspecialchars($assignmentResult['responder_name']) . '</p>';
        }
        $report_msg .= '<p><i class="fas fa-bell"></i> ' . $notified_count . ' responder groups notified</p>';
        $report_msg .= '</div>';
        
        error_log("New incident: ID #{$incident_id}, Type: {$type}, User: {$user_id}");
    } else {
        $report_msg = '<div class="error-dialog"><i class="fas fa-exclamation-triangle"></i> Failed to report incident.</div>';
    }
}

// Fetch user's incidents
$stmt = $pdo->prepare("SELECT * FROM incidents WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Emergency - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#7b7be0">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .app-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Header - Flutter Style */
        .app-header {
            padding: 20px;
            color: white;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: bold;
        }
        .brand i { font-size: 24px; }
        .subtitle {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 4px;
        }
        .welcome-text {
            font-size: 13px;
            margin-top: 2px;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        /* Main Content - Flutter Style */
        .main-content {
            flex: 1;
            background: white;
            border-radius: 30px 30px 0 0;
            padding: 24px;
            overflow-y: auto;
        }
        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        .section-header i {
            color: #7b7be0;
            font-size: 20px;
        }
        .auto-filled-badge {
            background: #ffc107;
            color: #000;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 8px;
        }
        /* Emergency Type Cards - Flutter Style */
        .emergency-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .emergency-card {
            background: #f5f5f5;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .emergency-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .emergency-card.active {
            border-color: var(--card-color, #7b7be0);
            background: var(--card-bg, rgba(123,123,224,0.1));
        }
        .emergency-card i {
            font-size: 32px;
            margin-bottom: 8px;
            display: block;
        }
        .emergency-card span {
            font-size: 12px;
            font-weight: 500;
        }
        .emergency-card.active span {
            font-weight: 600;
            color: var(--card-color, #7b7be0);
        }
        /* Response Team Dropdown */
        .response-team-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            color: #666;
            background: white;
            margin-bottom: 24px;
        }
        /* Map Section */
        #map {
            height: 180px;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            margin-bottom: 12px;
        }
        .location-btn {
            width: 100%;
            padding: 12px;
            background: #f5f5f5;
            border: none;
            border-radius: 8px;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        .location-btn.detected {
            background: #e8f5e9;
            color: #2e7d32;
        }
        /* Photo Evidence Section */
        .photo-section {
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin-bottom: 24px;
        }
        .photo-section.has-image {
            background: #e8f5e9;
            border-color: #a5d6a7;
        }
        .photo-section i.main-icon {
            font-size: 32px;
            color: #7b7be0;
            margin-bottom: 12px;
        }
        .photo-section.has-image i.main-icon {
            color: #4caf50;
        }
        .photo-section p {
            font-size: 13px;
            color: #666;
            margin-bottom: 16px;
        }
        .photo-section.has-image p {
            color: #2e7d32;
            font-weight: 600;
        }
        .photo-buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
        }
        .photo-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .photo-btn.primary {
            background: #7b7be0;
            color: white;
        }
        .photo-btn.secondary {
            background: #616161;
            color: white;
        }
        .photo-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        /* Image Preview */
        .image-preview-container {
            position: relative;
            margin-bottom: 16px;
            border-radius: 12px;
            overflow: hidden;
        }
        .image-preview-container img,
        .image-preview-container canvas {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 12px;
        }
        .image-preview-container .remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        /* AI Analysis Overlay */
        .ai-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            border-radius: 12px;
        }
        .ai-overlay .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .ai-overlay p {
            margin-top: 16px;
            font-size: 14px;
        }
        /* Description Textarea */
        .description-textarea {
            width: 100%;
            padding: 16px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }
        .description-textarea:focus {
            outline: none;
            border-color: #7b7be0;
            border-width: 2px;
        }
        .description-hint {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
            margin-bottom: 24px;
        }
        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .submit-btn.disabled {
            background: #bdbdbd;
            color: white;
            cursor: not-allowed;
        }
        .submit-btn.ready {
            background: #7b7be0;
            color: white;
        }
        .submit-btn.auto-filled {
            background: #4caf50;
            color: white;
        }
        .submit-btn:not(.disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        /* Success/Error Dialogs */
        .success-dialog {
            background: white;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .success-dialog .success-icon {
            width: 60px;
            height: 60px;
            background: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .success-dialog .success-icon i {
            font-size: 32px;
            color: #4caf50;
        }
        .success-dialog h4 {
            color: #2e7d32;
            margin-bottom: 16px;
        }
        .success-dialog p {
            margin: 8px 0;
            color: #666;
        }
        .ai-badge, .assigned-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .assigned-badge {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .error-dialog {
            background: #ffebee;
            color: #c62828;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
        }
        /* AI Analysis Results */
        .ai-results {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .ai-results-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .ai-results-header .icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #7b7be0, #a18cd1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        .ai-results-header h5 {
            margin: 0;
            font-size: 14px;
        }
        .ai-results-header small {
            color: #999;
            font-size: 12px;
        }
        .ai-detail {
            margin-bottom: 12px;
        }
        .ai-detail label {
            font-size: 12px;
            color: #666;
            display: block;
            margin-bottom: 4px;
        }
        .ai-detail .value {
            font-weight: 600;
            color: #333;
        }
        .confidence-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        .confidence-bar .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .confidence-bar .fill.high { background: #4caf50; }
        .confidence-bar .fill.medium { background: #ff9800; }
        .confidence-bar .fill.low { background: #f44336; }
        /* Camera Container */
        #cameraContainer {
            margin-bottom: 16px;
        }
        #videoPreview {
            width: 100%;
            max-height: 300px;
            border-radius: 12px;
            background: #000;
        }
        .camera-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 12px;
        }
        /* Responsive */
        @media (max-width: 480px) {
            .emergency-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .photo-buttons {
                flex-direction: column;
            }
            .photo-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <div class="app-header">
            <div class="header-content">
                <div>
                    <div class="brand">
                        <i class="fas fa-shield-alt"></i>
                        <span>Alerto360</span>
                    </div>
                    <div class="subtitle">Emergency Response System</div>
                    <div class="welcome-text">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User') ?></div>
                </div>
                <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?= $report_msg ?>
            
            <form method="post" enctype="multipart/form-data" id="incidentForm">
                <input type="hidden" name="report_incident" value="1">
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
                <input type="hidden" name="captured_image" id="capturedImageData">
                
                <!-- Emergency Type -->
                <div class="section-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Emergency Type</span>
                    <span id="autoFilledBadge" class="auto-filled-badge" style="display: none;">
                        <i class="fas fa-magic"></i> Auto-filled
                    </span>
                </div>
                
                <div class="emergency-grid">
                    <div class="emergency-card" data-type="Fire" style="--card-color: #f44336; --card-bg: rgba(244,67,54,0.1);">
                        <i class="fas fa-fire" style="color: #f44336;"></i>
                        <span>Fire</span>
                    </div>
                    <div class="emergency-card" data-type="Crime" style="--card-color: #9c27b0; --card-bg: rgba(156,39,176,0.1);">
                        <i class="fas fa-user-shield" style="color: #9c27b0;"></i>
                        <span>Crime</span>
                    </div>
                    <div class="emergency-card" data-type="Flood" style="--card-color: #2196f3; --card-bg: rgba(33,150,243,0.1);">
                        <i class="fas fa-water" style="color: #2196f3;"></i>
                        <span>Flood</span>
                    </div>
                    <div class="emergency-card" data-type="Landslide" style="--card-color: #795548; --card-bg: rgba(121,85,72,0.1);">
                        <i class="fas fa-mountain" style="color: #795548;"></i>
                        <span>Landslide</span>
                    </div>
                    <div class="emergency-card" data-type="Accident" style="--card-color: #ff9800; --card-bg: rgba(255,152,0,0.1);">
                        <i class="fas fa-car-crash" style="color: #ff9800;"></i>
                        <span>Accident</span>
                    </div>
                    <div class="emergency-card" data-type="Other" style="--card-color: #607d8b; --card-bg: rgba(96,125,139,0.1);">
                        <i class="fas fa-plus-circle" style="color: #607d8b;"></i>
                        <span>Other</span>
                    </div>
                </div>
                
                <select name="type" id="emergencyTypeSelect" style="display: none;" required>
                    <option value="">Select type</option>
                    <option value="Fire">Fire</option>
                    <option value="Crime">Crime</option>
                    <option value="Flood">Flood</option>
                    <option value="Landslide">Landslide</option>
                    <option value="Accident">Accident</option>
                    <option value="Other">Other</option>
                </select>
                
                <!-- Response Team -->
                <div class="section-header">
                    <i class="fas fa-users"></i>
                    <span>Response Team</span>
                    <i class="fas fa-lightbulb" style="color: #ffc107; margin-left: auto;"></i>
                </div>
                
                <select name="responder_type" class="response-team-select">
                    <option value="">Auto-assign based on emergency type</option>
                    <option value="BFP">🔥 BFP (Bureau of Fire Protection)</option>
                    <option value="PNP">👮 PNP (Philippine National Police)</option>
                    <option value="MDDRMO">🚑 MDDRMO (Disaster Response)</option>
                </select>
                
                <!-- Location -->
                <div class="section-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Location</span>
                </div>
                
                <div id="map"></div>
                <button type="button" class="location-btn" id="locationBtn">
                    <i class="fas fa-crosshairs"></i>
                    <span id="locationText">Getting your location...</span>
                </button>
                
                <!-- Photo Evidence -->
                <div class="section-header">
                    <i class="fas fa-camera"></i>
                    <span>Photo Evidence</span>
                </div>
                
                <div id="photoSection" class="photo-section">
                    <div id="imagePreviewContainer" class="image-preview-container" style="display: none;">
                        <canvas id="photoCanvas"></canvas>
                        <button type="button" class="remove-btn" id="removeImageBtn">
                            <i class="fas fa-times"></i>
                        </button>
                        <div id="aiOverlay" class="ai-overlay" style="display: none;">
                            <div class="spinner"></div>
                            <p>Analyzing image with AI...</p>
                        </div>
                    </div>
                    
                    <i class="fas fa-magic main-icon" id="photoIcon"></i>
                    <p id="photoText">Take a photo and AI will auto-fill everything!</p>
                    
                    <div class="photo-buttons">
                        <button type="button" class="photo-btn primary" id="cameraBtn">
                            <i class="fas fa-camera"></i> Take Photo
                        </button>
                        <button type="button" class="photo-btn secondary" id="uploadBtn">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                    
                    <input type="file" id="fileInput" name="incident_image" accept="image/*" style="display: none;">
                    
                    <!-- AI Analysis Results -->
                    <div id="aiResults" class="ai-results" style="display: none;">
                        <div class="ai-results-header">
                            <div class="icon"><i class="fas fa-robot"></i></div>
                            <div>
                                <h5 id="aiResultTitle">AI Analysis Complete</h5>
                                <small id="aiResultSubtitle">Incident detected and analyzed</small>
                            </div>
                        </div>
                        <div class="ai-detail">
                            <label>🔍 Detected Incident Type:</label>
                            <div class="value" id="aiDetectedType">-</div>
                        </div>
                        <div class="ai-detail">
                            <label>📊 Confidence Level:</label>
                            <div class="value" id="aiConfidence">-</div>
                            <div class="confidence-bar">
                                <div class="fill" id="confidenceFill" style="width: 0%;"></div>
                            </div>
                        </div>
                        <div class="ai-detail">
                            <label>📝 AI-Generated Description:</label>
                            <div class="value" id="aiDescription" style="font-weight: normal; font-style: italic;">-</div>
                        </div>
                    </div>
                </div>
                
                <!-- Camera Container -->
                <div id="cameraContainer" style="display: none;">
                    <video id="videoPreview" autoplay playsinline></video>
                    <div class="camera-actions">
                        <button type="button" class="photo-btn primary" id="captureBtn">
                            <i class="fas fa-camera"></i> Capture
                        </button>
                        <button type="button" class="photo-btn secondary" id="cancelCameraBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="section-header">
                    <i class="fas fa-align-left"></i>
                    <span>Description</span>
                </div>
                
                <textarea class="description-textarea" name="description" id="description" 
                    placeholder="Describe what happened, how many people are affected, and any other important details..."></textarea>
                <div class="description-hint">Optional but recommended for faster response</div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn disabled" id="submitBtn" disabled>
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="submitText">Please select emergency type</span>
                </button>
            </form>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Global state
    let map, marker;
    let videoStream = null;
    let selectedType = '';
    let hasLocation = false;
    let hasImage = false;
    let isAutoFilled = false;
    let isAnalyzing = false;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        initEmergencyCards();
        initCamera();
        updateSubmitButton();
    });

    // Map initialization
    function initMap() {
        map = L.map('map').setView([6.6833, 125.3167], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => setLocation(pos.coords.latitude, pos.coords.longitude, 'Current location detected'),
                () => {
                    document.getElementById('locationText').textContent = 'Click on map to set location';
                    enableMapClick();
                }
            );
        } else {
            document.getElementById('locationText').textContent = 'Click on map to set location';
            enableMapClick();
        }
    }

    function setLocation(lat, lng, message) {
        map.setView([lat, lng], 15);
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng], {draggable: true}).addTo(map);
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('locationText').textContent = message;
        document.getElementById('locationBtn').classList.add('detected');
        hasLocation = true;
        
        marker.on('dragend', e => {
            const coords = e.target.getLatLng();
            document.getElementById('latitude').value = coords.lat;
            document.getElementById('longitude').value = coords.lng;
            document.getElementById('locationText').textContent = 'Location updated';
        });
        
        updateSubmitButton();
    }

    function enableMapClick() {
        map.on('click', e => setLocation(e.latlng.lat, e.latlng.lng, 'Location selected'));
    }

    // Emergency type cards
    function initEmergencyCards() {
        document.querySelectorAll('.emergency-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.emergency-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                selectedType = this.dataset.type;
                document.getElementById('emergencyTypeSelect').value = selectedType;
                updateSubmitButton();
            });
        });
    }

    function selectEmergencyType(type) {
        const card = document.querySelector(`.emergency-card[data-type="${type}"]`);
        if (card) {
            document.querySelectorAll('.emergency-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            selectedType = type;
            document.getElementById('emergencyTypeSelect').value = type;
            updateSubmitButton();
        }
    }

    // Camera functionality
    function initCamera() {
        const cameraBtn = document.getElementById('cameraBtn');
        const uploadBtn = document.getElementById('uploadBtn');
        const captureBtn = document.getElementById('captureBtn');
        const cancelCameraBtn = document.getElementById('cancelCameraBtn');
        const removeImageBtn = document.getElementById('removeImageBtn');
        const fileInput = document.getElementById('fileInput');

        cameraBtn.addEventListener('click', startCamera);
        uploadBtn.addEventListener('click', () => fileInput.click());
        captureBtn.addEventListener('click', capturePhoto);
        cancelCameraBtn.addEventListener('click', stopCamera);
        removeImageBtn.addEventListener('click', removeImage);
        fileInput.addEventListener('change', handleFileSelect);
    }

    async function startCamera() {
        try {
            videoStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
            });
            document.getElementById('videoPreview').srcObject = videoStream;
            document.getElementById('cameraContainer').style.display = 'block';
            document.getElementById('cameraBtn').disabled = true;
            document.getElementById('uploadBtn').disabled = true;
        } catch (error) {
            alert('Camera not available. Please use upload option.');
        }
    }

    function stopCamera() {
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
            videoStream = null;
        }
        document.getElementById('cameraContainer').style.display = 'none';
        document.getElementById('cameraBtn').disabled = false;
        document.getElementById('uploadBtn').disabled = false;
    }

    function capturePhoto() {
        const video = document.getElementById('videoPreview');
        const canvas = document.getElementById('photoCanvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        
        document.getElementById('capturedImageData').value = canvas.toDataURL('image/jpeg', 0.8);
        
        stopCamera();
        showImagePreview();
        analyzeImage(canvas);
    }

    function handleFileSelect(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.getElementById('photoCanvas');
                    const ctx = canvas.getContext('2d');
                    
                    let { width, height } = img;
                    const maxSize = 800;
                    if (width > maxSize || height > maxSize) {
                        const ratio = Math.min(maxSize / width, maxSize / height);
                        width *= ratio;
                        height *= ratio;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    showImagePreview();
                    analyzeImage(canvas);
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    }

    function showImagePreview() {
        hasImage = true;
        document.getElementById('imagePreviewContainer').style.display = 'block';
        document.getElementById('photoIcon').style.display = 'none';
        document.getElementById('photoText').style.display = 'none';
        document.getElementById('photoSection').classList.add('has-image');
        updateSubmitButton();
    }

    function removeImage() {
        hasImage = false;
        isAutoFilled = false;
        document.getElementById('imagePreviewContainer').style.display = 'none';
        document.getElementById('photoIcon').style.display = 'block';
        document.getElementById('photoText').style.display = 'block';
        document.getElementById('photoSection').classList.remove('has-image');
        document.getElementById('aiResults').style.display = 'none';
        document.getElementById('autoFilledBadge').style.display = 'none';
        document.getElementById('capturedImageData').value = '';
        document.getElementById('fileInput').value = '';
        document.getElementById('photoIcon').className = 'fas fa-magic main-icon';
        document.getElementById('photoText').textContent = 'Take a photo and AI will auto-fill everything!';
        updateSubmitButton();
    }

    // AI Analysis
    function analyzeImage(canvas) {
        isAnalyzing = true;
        document.getElementById('aiOverlay').style.display = 'flex';
        document.getElementById('cameraBtn').disabled = true;
        document.getElementById('uploadBtn').disabled = true;
        updateSubmitButton();
        
        canvas.toBlob(function(blob) {
            const formData = new FormData();
            formData.append('image', blob, 'incident.jpg');
            
            fetch('analyze_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                isAnalyzing = false;
                document.getElementById('aiOverlay').style.display = 'none';
                document.getElementById('cameraBtn').disabled = false;
                document.getElementById('uploadBtn').disabled = false;
                
                if (data.success && data.analysis) {
                    displayAIResults(data.analysis);
                    applyAIResults(data.analysis);
                } else {
                    showAIError();
                }
                updateSubmitButton();
            })
            .catch(error => {
                console.error('AI Error:', error);
                isAnalyzing = false;
                document.getElementById('aiOverlay').style.display = 'none';
                document.getElementById('cameraBtn').disabled = false;
                document.getElementById('uploadBtn').disabled = false;
                showAIError();
                updateSubmitButton();
            });
        }, 'image/jpeg', 0.8);
    }

    function displayAIResults(analysis) {
        const confidence = analysis.confidence || 50;
        const confidenceClass = confidence > 70 ? 'high' : confidence > 40 ? 'medium' : 'low';
        
        document.getElementById('aiDetectedType').textContent = analysis.incident_type || 'Unknown';
        document.getElementById('aiConfidence').textContent = confidence + '%';
        document.getElementById('confidenceFill').style.width = confidence + '%';
        document.getElementById('confidenceFill').className = 'fill ' + confidenceClass;
        document.getElementById('aiDescription').textContent = analysis.description || 'No description available';
        document.getElementById('aiResults').style.display = 'block';
        
        // Update photo section
        document.getElementById('photoIcon').className = 'fas fa-check-circle main-icon';
        document.getElementById('photoIcon').style.display = 'block';
        document.getElementById('photoText').textContent = 'Image uploaded! AI has auto-filled the form.';
        document.getElementById('photoText').style.display = 'block';
    }

    function applyAIResults(analysis) {
        const confidence = analysis.confidence || 50;
        
        if (confidence > 50 && analysis.incident_type) {
            selectEmergencyType(analysis.incident_type);
            isAutoFilled = true;
            document.getElementById('autoFilledBadge').style.display = 'inline-block';
        }
        
        if (confidence > 40 && analysis.description) {
            document.getElementById('description').value = analysis.description;
        }
        
        updateSubmitButton();
    }

    function showAIError() {
        document.getElementById('aiResults').style.display = 'block';
        document.getElementById('aiResultTitle').textContent = 'AI Analysis Unavailable';
        document.getElementById('aiResultSubtitle').textContent = 'Please select type manually';
        document.getElementById('aiDetectedType').textContent = 'Manual selection required';
        document.getElementById('aiConfidence').textContent = '-';
        document.getElementById('confidenceFill').style.width = '0%';
        document.getElementById('aiDescription').textContent = 'AI detection failed. Please fill in the details manually.';
    }

    // Submit button state
    function updateSubmitButton() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('submitText');
        const icon = btn.querySelector('i');
        
        btn.classList.remove('disabled', 'ready', 'auto-filled');
        
        if (isAnalyzing) {
            btn.classList.add('disabled');
            btn.disabled = true;
            icon.className = 'fas fa-spinner fa-spin';
            text.textContent = 'Analyzing image...';
        } else if (!selectedType) {
            btn.classList.add('disabled');
            btn.disabled = true;
            icon.className = 'fas fa-exclamation-circle';
            text.textContent = 'Please select emergency type';
        } else if (!hasLocation) {
            btn.classList.add('disabled');
            btn.disabled = true;
            icon.className = 'fas fa-exclamation-circle';
            text.textContent = 'Please set your location';
        } else {
            btn.disabled = false;
            if (isAutoFilled) {
                btn.classList.add('auto-filled');
                icon.className = 'fas fa-check-circle';
                text.textContent = 'Complete Report';
            } else {
                btn.classList.add('ready');
                icon.className = 'fas fa-paper-plane';
                text.textContent = 'Submit Emergency Report';
            }
        }
    }

    // Form submission
    document.getElementById('incidentForm').addEventListener('submit', function(e) {
        if (!selectedType || !hasLocation) {
            e.preventDefault();
            alert('Please select emergency type and set your location.');
            return;
        }
        
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('submitText');
        const icon = btn.querySelector('i');
        
        btn.disabled = true;
        icon.className = 'fas fa-spinner fa-spin';
        text.textContent = 'Submitting...';
    });

    // Cleanup
    window.addEventListener('beforeunload', () => {
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
        }
    });
    </script>
</body>
</html>
