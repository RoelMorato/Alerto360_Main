<?php
session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Only allow citizens
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'citizen') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$report_msg = '';

// Map incident type to responder type (user can override)
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
        // Remove data:image/jpeg;base64, prefix
        $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $decoded_image = base64_decode($image_data);
        
        if ($decoded_image !== false) {
            $img_name = uniqid('captured_') . '.jpg';
            $target_dir = '../uploads/';
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
        $target_dir = '../uploads/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . $img_name;
        if (move_uploaded_file($_FILES['incident_image']['tmp_name'], $target_file)) {
            $image_path = $target_file;
        }
    }

    // Placeholder for image analysis (if image uploaded and no description)
    if ($image_path && empty($description)) {
        // TODO: Integrate image recognition here
        $description = '[Auto-detected incident type or description here]';
    }

    // Assign responder type (user-selected or auto)
    $responder_type = getResponderType($type, $user_responder);

    // Insert into DB (now includes responder_type)
    $stmt = $pdo->prepare("INSERT INTO incidents (user_id, type, description, latitude, longitude, image_path, status, responder_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $type, $description, $latitude, $longitude, $image_path, $status, $responder_type])) {
        $incident_id = $pdo->lastInsertId();
        
        // Create location info for notifications
        $location_info = '';
        if ($latitude && $longitude) {
            $location_info = "GPS: {$latitude}, {$longitude}";
        }
        
        // Send notifications to responders and admins
        $notification_results = handleIncidentNotifications($pdo, $incident_id, $type, $location_info);
        
        // Count successful notifications
        $notified_count = 0;
        foreach ($notification_results as $result) {
            if ($result) $notified_count++;
        }
        
        $report_msg = '<div class="alert alert-success">';
        $report_msg .= '<h5><i class="fas fa-check-circle"></i> Incident Reported Successfully!</h5>';
        $report_msg .= '<strong>Incident ID:</strong> #' . $incident_id . '<br>';
        $report_msg .= '<strong>Assigned to:</strong> ' . htmlspecialchars($responder_type) . ' responders<br>';
        $report_msg .= '<strong>Notifications sent:</strong> ' . $notified_count . ' responder groups notified<br>';
        $report_msg .= '<i class="fas fa-bell"></i> <em>Emergency responders have been automatically notified and will respond shortly.</em>';
        $report_msg .= '</div>';
        
        // Log the incident creation
        error_log("New incident reported: ID #{$incident_id}, Type: {$type}, User: {$user_id}, Notifications: {$notified_count}");
        
    } else {
        $report_msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to report incident. Please try again.</div>';
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
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Alerto360">
    <link rel="apple-touch-icon" href="icon-192.png">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            max-width: 800px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .header .subtitle {
            opacity: 0.9;
            margin-top: 0.5rem;
        }
        .logout-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        .form-container {
            padding: 2.5rem;
        }
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .form-section h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        #map {
            height: 300px;
            border-radius: 15px;
            border: 3px solid #e9ecef;
            overflow: hidden;
        }
        .camera-section {
            text-align: center;
        }
        .camera-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        .camera-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .camera-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .camera-btn.secondary {
            background: #6c757d;
        }
        .camera-btn.secondary:hover {
            background: #5a6268;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }
        #videoPreview {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border-radius: 15px;
            background: #f8f9fa;
            border: 3px dashed #dee2e6;
            object-fit: cover;
        }
        #photoCanvas {
            border-radius: 15px;
            max-width: 100%;
            height: auto;
        }
        .photo-preview {
            margin-top: 1rem;
            text-align: center;
        }
        .submit-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }
        .submit-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .emergency-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .emergency-type {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #495057;
        }
        .emergency-type:hover, .emergency-type.active {
            border-color: #667eea;
            background: #f8f9ff;
            color: #667eea;
            transform: translateY(-2px);
        }
        .emergency-type i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        .location-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            text-align: center;
        }
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        /* AI Analysis Styling */
        .analysis-container {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9ff 0%, #e7f3ff 100%);
            border: 2px solid #b8daff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .analysis-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }
        
        .analysis-results {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .analysis-icon {
            font-size: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }
        
        .analysis-item {
            margin-bottom: 1rem;
        }
        
        .analysis-item label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .analysis-description {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1rem;
            border-radius: 8px;
            font-style: italic;
            color: #495057;
        }
        
        .analysis-actions {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
        }
        
        .progress {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .badge {
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header">
            <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <h1><i class="fas fa-shield-alt"></i> Alerto360</h1>
            <div class="subtitle">Emergency Response System</div>
            <div class="subtitle">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></div>
        </div>
        
        <div class="form-container">
            <?= $report_msg ?>
            
            <form method="post" enctype="multipart/form-data" id="incidentForm">
                <input type="hidden" name="report_incident" value="1">
                <input type="hidden" name="latitude" id="latitude" required>
                <input type="hidden" name="longitude" id="longitude" required>
                <input type="hidden" name="captured_image" id="capturedImageData">
                
                <!-- Emergency Type Selection -->
                <div class="form-section">
                    <h6><i class="fas fa-exclamation-triangle"></i> Emergency Type</h6>
                    <div class="emergency-types">
                        <div class="emergency-type" data-type="Fire">
                            <i class="fas fa-fire" style="color: #dc3545;"></i>
                            <div>Fire</div>
                        </div>
                        <div class="emergency-type" data-type="Crime">
                            <i class="fas fa-user-shield" style="color: #6f42c1;"></i>
                            <div>Crime</div>
                        </div>
                        <div class="emergency-type" data-type="Flood">
                            <i class="fas fa-water" style="color: #0dcaf0;"></i>
                            <div>Flood</div>
                        </div>
                        <div class="emergency-type" data-type="Landslide">
                            <i class="fas fa-mountain" style="color: #795548;"></i>
                            <div>Landslide</div>
                        </div>
                        <div class="emergency-type" data-type="Accident">
                            <i class="fas fa-car-crash" style="color: #ff9800;"></i>
                            <div>Accident</div>
                        </div>
                        <div class="emergency-type" data-type="Other">
                            <i class="fas fa-plus-circle" style="color: #6c757d;"></i>
                            <div>Other</div>
                        </div>
                    </div>
                    <select name="type" id="emergencyTypeSelect" class="form-select" required style="display: none;">
                        <option value="">Select type</option>
                        <option value="Fire">Fire</option>
                        <option value="Crime">Crime</option>
                        <option value="Flood">Flood</option>
                        <option value="Landslide">Landslide</option>
                        <option value="Accident">Accident</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <!-- Responder Type -->
                <div class="form-section">
                    <h6><i class="fas fa-users"></i> Response Team</h6>
                    <select name="responder_type" class="form-select">
                        <option value="">Auto-assign based on emergency type</option>
                        <option value="BFP">🔥 BFP (Bureau of Fire Protection)</option>
                        <option value="PNP">👮 PNP (Philippine National Police)</option>
                        <option value="MDDRMO">🚑 MDDRMO (Disaster Response)</option>
                    </select>
                </div>
                
                <!-- Location -->
                <div class="form-section">
                    <h6><i class="fas fa-map-marker-alt"></i> Location</h6>
                    <div id="map"></div>
                    <div class="location-info">
                        <i class="fas fa-info-circle"></i> 
                        <span id="locationText">Getting your location...</span>
                    </div>
                </div>
                
                <!-- Camera Section -->
                <div class="form-section camera-section">
                    <h6><i class="fas fa-camera"></i> Photo Evidence</h6>
                    <p class="text-muted mb-3">Take a photo or upload an image of the emergency</p>
                    
                    <div class="camera-controls">
                        <button type="button" class="camera-btn" id="startCameraBtn">
                            <i class="fas fa-camera"></i> Take Photo
                        </button>
                        <button type="button" class="camera-btn secondary" id="uploadBtn">
                            <i class="fas fa-upload"></i> Upload Image
                        </button>
                    </div>
                    
                    <div id="cameraContainer" style="display: none;">
                        <video id="videoPreview" autoplay playsinline></video>
                        <div class="camera-controls mt-3">
                            <button type="button" class="camera-btn" id="captureBtn">
                                <i class="fas fa-camera-retro"></i> Capture
                            </button>
                            <button type="button" class="camera-btn secondary" id="stopCameraBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                    
                    <input type="file" class="form-control" name="incident_image" id="fileInput" accept="image/*" style="display: none;">
                    
                    <div id="photoPreview" class="photo-preview" style="display: none;">
                        <canvas id="photoCanvas"></canvas>
                        <div class="camera-controls mt-3">
                            <button type="button" class="camera-btn secondary" id="retakeBtn">
                                <i class="fas fa-redo"></i> Retake
                            </button>
                        </div>
                    </div>
                    
                    <!-- AI Analysis Container -->
                    <div id="analysisContainer" class="analysis-container" style="display: none;">
                        <!-- AI analysis results will be displayed here -->
                    </div>
                </div>
                
                <!-- Description -->
                <div class="form-section">
                    <h6><i class="fas fa-comment-alt"></i> Description</h6>
                    <textarea class="form-control" name="description" rows="3" 
                              placeholder="Describe what happened, how many people are affected, and any other important details..."></textarea>
                    <small class="text-muted">Optional but recommended for faster response</small>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <i class="fas fa-paper-plane"></i> Submit Emergency Report
                </button>
            </form>
        </div>
    </div>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global variables
let map, marker;
let videoStream = null;
let selectedEmergencyType = '';
let hasLocation = false;
let hasPhoto = false;

// Initialize map
function initMap() {
    map = L.map('map').setView([6.6833, 125.3167], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Get user's location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            setLocation(lat, lng, 'Current location detected');
        }, function(error) {
            document.getElementById('locationText').textContent = 'Click on map to set location';
            enableMapClick();
        });
    } else {
        document.getElementById('locationText').textContent = 'Click on map to set location';
        enableMapClick();
    }
}

// Set location on map
function setLocation(lat, lng, message) {
    map.setView([lat, lng], 15);
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng], {draggable: true}).addTo(map);
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('locationText').textContent = message || 'Location set';
    hasLocation = true;
    
    marker.on('dragend', function(e) {
        const coords = e.target.getLatLng();
        document.getElementById('latitude').value = coords.lat;
        document.getElementById('longitude').value = coords.lng;
        document.getElementById('locationText').textContent = 'Location updated';
    });
    
    checkFormValidity();
}

// Enable map click for location selection
function enableMapClick() {
    map.on('click', function(e) {
        setLocation(e.latlng.lat, e.latlng.lng, 'Location selected on map');
    });
}

// Emergency type selection
document.querySelectorAll('.emergency-type').forEach(type => {
    type.addEventListener('click', function() {
        // Remove active class from all types
        document.querySelectorAll('.emergency-type').forEach(t => t.classList.remove('active'));
        
        // Add active class to selected type
        this.classList.add('active');
        
        // Set the value
        selectedEmergencyType = this.dataset.type;
        document.getElementById('emergencyTypeSelect').value = selectedEmergencyType;
        
        checkFormValidity();
    });
});

// Camera functionality
const startCameraBtn = document.getElementById('startCameraBtn');
const uploadBtn = document.getElementById('uploadBtn');
const cameraContainer = document.getElementById('cameraContainer');
const videoPreview = document.getElementById('videoPreview');
const captureBtn = document.getElementById('captureBtn');
const stopCameraBtn = document.getElementById('stopCameraBtn');
const photoPreview = document.getElementById('photoPreview');
const photoCanvas = document.getElementById('photoCanvas');
const retakeBtn = document.getElementById('retakeBtn');
const fileInput = document.getElementById('fileInput');

// Start camera
startCameraBtn.addEventListener('click', async function() {
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment', // Use back camera on mobile
                width: { ideal: 1280 },
                height: { ideal: 720 }
            } 
        });
        videoPreview.srcObject = videoStream;
        cameraContainer.style.display = 'block';
        startCameraBtn.style.display = 'none';
        uploadBtn.style.display = 'none';
    } catch (error) {
        alert('Camera access denied or not available. Please use the upload option.');
        console.error('Camera error:', error);
    }
});

// Stop camera
stopCameraBtn.addEventListener('click', function() {
    stopCamera();
    resetCameraUI();
});

// Capture photo
captureBtn.addEventListener('click', function() {
    const canvas = photoCanvas;
    const context = canvas.getContext('2d');
    
    // Set canvas size to video size
    canvas.width = videoPreview.videoWidth;
    canvas.height = videoPreview.videoHeight;
    
    // Draw video frame to canvas
    context.drawImage(videoPreview, 0, 0, canvas.width, canvas.height);
    
    // Convert to base64
    const imageData = canvas.toDataURL('image/jpeg', 0.8);
    document.getElementById('capturedImageData').value = imageData;
    
    // Show preview
    photoPreview.style.display = 'block';
    cameraContainer.style.display = 'none';
    
    // Stop camera
    stopCamera();
    
    hasPhoto = true;
    
    // Analyze captured image with AI
    analyzeImageWithAI(canvas);
    
    checkFormValidity();
});

// Retake photo
retakeBtn.addEventListener('click', function() {
    photoPreview.style.display = 'none';
    resetCameraUI();
    hasPhoto = false;
    document.getElementById('capturedImageData').value = '';
    checkFormValidity();
});

// Upload button
uploadBtn.addEventListener('click', function() {
    fileInput.click();
});

// File input change
fileInput.addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        const file = e.target.files[0];
        const reader = new FileReader();
        
        reader.onload = function(event) {
            const img = new Image();
            img.onload = function() {
                const canvas = photoCanvas;
                const context = canvas.getContext('2d');
                
                // Resize image if too large
                const maxWidth = 800;
                const maxHeight = 600;
                let { width, height } = img;
                
                if (width > maxWidth || height > maxHeight) {
                    const ratio = Math.min(maxWidth / width, maxHeight / height);
                    width *= ratio;
                    height *= ratio;
                }
                
                canvas.width = width;
                canvas.height = height;
                context.drawImage(img, 0, 0, width, height);
                
                photoPreview.style.display = 'block';
                hasPhoto = true;
                
                // Analyze uploaded image with AI
                analyzeImageWithAI(canvas);
                
                checkFormValidity();
            };
            img.src = event.target.result;
        };
        
        reader.readAsDataURL(file);
    }
});

// Stop camera stream
function stopCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
}

// Reset camera UI
function resetCameraUI() {
    cameraContainer.style.display = 'none';
    startCameraBtn.style.display = 'inline-flex';
    uploadBtn.style.display = 'inline-flex';
}

// Check form validity
function checkFormValidity() {
    const submitBtn = document.getElementById('submitBtn');
    const isValid = selectedEmergencyType && hasLocation;
    
    submitBtn.disabled = !isValid;
    
    if (isValid) {
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Emergency Report';
    } else {
        let missing = [];
        if (!selectedEmergencyType) missing.push('emergency type');
        if (!hasLocation) missing.push('location');
        submitBtn.innerHTML = `<i class="fas fa-exclamation-circle"></i> Please select ${missing.join(' and ')}`;
    }
}

// AI-Powered Image Analysis
function analyzeImageWithAI(canvas) {
    // Show analysis loading
    showAnalysisLoading();
    
    // Convert canvas to blob
    canvas.toBlob(function(blob) {
        const formData = new FormData();
        formData.append('image', blob, 'incident_image.jpg');
        
        fetch('../analyze_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideAnalysisLoading();
            
            if (data.success) {
                displayAnalysisResults(data.analysis);
                applyAnalysisToForm(data.analysis);
            } else {
                console.warn('Image analysis failed:', data.error);
                if (data.fallback) {
                    displayAnalysisResults(data.fallback, true);
                }
            }
        })
        .catch(error => {
            hideAnalysisLoading();
            console.error('Analysis error:', error);
            showAnalysisError();
        });
    }, 'image/jpeg', 0.8);
}

function showAnalysisLoading() {
    const analysisContainer = document.getElementById('analysisContainer');
    analysisContainer.innerHTML = `
        <div class="analysis-loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Analyzing...</span>
            </div>
            <div class="ms-3">
                <strong>🤖 AI Analyzing Image...</strong>
                <div class="text-muted">Detecting incident type and generating description</div>
            </div>
        </div>
    `;
    analysisContainer.style.display = 'block';
}

function hideAnalysisLoading() {
    const analysisContainer = document.getElementById('analysisContainer');
    const loadingElement = analysisContainer.querySelector('.analysis-loading');
    if (loadingElement) {
        loadingElement.remove();
    }
}

function displayAnalysisResults(analysis, isFallback = false) {
    const analysisContainer = document.getElementById('analysisContainer');
    
    const confidenceClass = analysis.confidence > 70 ? 'success' : analysis.confidence > 40 ? 'warning' : 'secondary';
    const confidenceIcon = analysis.confidence > 70 ? 'check-circle' : analysis.confidence > 40 ? 'exclamation-triangle' : 'question-circle';
    
    const resultsHTML = `
        <div class="analysis-results">
            <div class="d-flex align-items-center mb-3">
                <div class="analysis-icon">
                    🤖
                </div>
                <div class="ms-3">
                    <h6 class="mb-1">${isFallback ? '⚠️ Analysis Failed' : '✅ AI Analysis Complete'}</h6>
                    <small class="text-muted">${isFallback ? 'Using fallback detection' : 'Incident detected and analyzed'}</small>
                </div>
            </div>
            
            <div class="analysis-details">
                <div class="row">
                    <div class="col-md-6">
                        <div class="analysis-item">
                            <label class="form-label">🔍 Detected Incident Type:</label>
                            <div class="fw-bold text-primary">${analysis.incident_type}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="analysis-item">
                            <label class="form-label">📊 Confidence Level:</label>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-${confidenceClass} me-2">
                                    <i class="fas fa-${confidenceIcon}"></i> ${analysis.confidence}%
                                </span>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-${confidenceClass}" style="width: ${analysis.confidence}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="analysis-item mt-3">
                    <label class="form-label">📝 AI-Generated Description:</label>
                    <div class="analysis-description">${analysis.description}</div>
                </div>
                
                <div class="analysis-item mt-3">
                    <label class="form-label">🚨 Recommended Responder:</label>
                    <div class="fw-bold text-success">${analysis.responder_type}</div>
                </div>
                
                <div class="analysis-actions mt-3">
                    <button type="button" class="btn btn-success btn-sm" onclick="acceptAnalysis()">
                        <i class="fas fa-check"></i> Accept AI Analysis
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="editAnalysis()">
                        <i class="fas fa-edit"></i> Edit Details
                    </button>
                </div>
            </div>
        </div>
    `;
    
    analysisContainer.innerHTML = resultsHTML;
    
    // Store analysis data for later use
    window.currentAnalysis = analysis;
}

function applyAnalysisToForm(analysis) {
    // Auto-select incident type if confidence is high enough
    if (analysis.confidence > 60) {
        const typeElement = document.querySelector(`[data-type="${analysis.incident_type}"]`);
        if (typeElement) {
            // Remove active class from all types
            document.querySelectorAll('.emergency-type').forEach(t => t.classList.remove('active'));
            
            // Add active class to detected type
            typeElement.classList.add('active');
            selectedEmergencyType = analysis.incident_type;
            document.getElementById('emergencyTypeSelect').value = selectedEmergencyType;
        }
    }
    
    // Auto-fill description if confidence is high
    if (analysis.confidence > 50) {
        document.getElementById('description').value = analysis.description;
    }
    
    checkFormValidity();
}

function acceptAnalysis() {
    if (window.currentAnalysis) {
        // Apply analysis to form
        applyAnalysisToForm(window.currentAnalysis);
        
        // Show success message
        const analysisContainer = document.getElementById('analysisContainer');
        analysisContainer.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <strong>AI Analysis Applied!</strong> 
                Incident details have been automatically filled based on image analysis.
            </div>
        `;
        
        setTimeout(() => {
            analysisContainer.style.display = 'none';
        }, 3000);
    }
}

function editAnalysis() {
    const analysisContainer = document.getElementById('analysisContainer');
    analysisContainer.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-edit"></i> 
            <strong>Manual Edit Mode</strong> 
            Please manually select the incident type and enter description below.
        </div>
    `;
    
    setTimeout(() => {
        analysisContainer.style.display = 'none';
    }, 2000);
}

function showAnalysisError() {
    const analysisContainer = document.getElementById('analysisContainer');
    analysisContainer.innerHTML = `
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Analysis Unavailable</strong> 
            Please manually select incident type and description.
        </div>
    `;
    
    setTimeout(() => {
        analysisContainer.style.display = 'none';
    }, 3000);
}

// Form submission
document.getElementById('incidentForm').addEventListener('submit', function(e) {
    if (!selectedEmergencyType || !hasLocation) {
        e.preventDefault();
        alert('Please select an emergency type and set your location.');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Report...';
    submitBtn.disabled = true;
});

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    checkFormValidity();
});

// Clean up camera when page unloads
window.addEventListener('beforeunload', function() {
    stopCamera();
});

// Register service worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('../service-worker.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}
</script>
</body>
</html>