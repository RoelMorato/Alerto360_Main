<?php
/**
 * AJAX Endpoint for Image Analysis
 * Handles image upload and AI-powered incident detection
 */

header('Content-Type: application/json');
require_once 'image_analysis.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Check if image was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'error' => 'No image uploaded or upload error'
        ]);
        exit;
    }

    $uploaded_file = $_FILES['image'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($uploaded_file['type'], $allowed_types)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid file type. Please upload JPG, PNG, or GIF images.'
        ]);
        exit;
    }

    // Validate file size (max 5MB)
    if ($uploaded_file['size'] > 5 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'error' => 'File too large. Maximum size is 5MB.'
        ]);
        exit;
    }

    // Simplified analysis - no need for temp files
    // Just analyze the uploaded file directly
    $file_info = getimagesize($uploaded_file['tmp_name']);
    
    if (!$file_info) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid image file',
            'fallback' => [
                'incident_type' => 'Other',
                'description' => 'Please manually describe the incident',
                'responder_type' => 'MDDRMO',
                'confidence' => 0
            ]
        ]);
        exit;
    }

    // Perform advanced object detection analysis
    require_once 'advanced_object_detection.php';
    
    // Save temp file for advanced analysis
    $temp_path = tempnam(sys_get_temp_dir(), 'alerto_analysis_');
    move_uploaded_file($uploaded_file['tmp_name'], $temp_path);
    
    // Perform advanced object detection
    $object_detection = detectObjectsInImage($temp_path);
    $analysis_result = performAdvancedAnalysis($uploaded_file, $object_detection);
    $suggestions = generateAdvancedSuggestions($analysis_result, $object_detection);
    
    // Clean up temp file
    unlink($temp_path);

    if ($analysis_result['success']) {
        echo json_encode([
            'success' => true,
            'analysis' => [
                'incident_type' => $analysis_result['incident_type'],
                'description' => $analysis_result['description'],
                'responder_type' => $analysis_result['responder_type'],
                'confidence' => $analysis_result['confidence'],
                'details' => $analysis_result['details'] ?? []
            ],
            'suggestions' => $suggestions['suggestions'] ?? [],
            'message' => 'Image analyzed successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $analysis_result['error'],
            'fallback' => [
                'incident_type' => 'Other',
                'description' => 'Please manually describe the incident',
                'responder_type' => 'MDDRMO',
                'confidence' => 0
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Image analysis endpoint error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error during image analysis',
        'fallback' => [
            'incident_type' => 'Other',
            'description' => 'Please manually describe the incident',
            'responder_type' => 'MDDRMO',
            'confidence' => 0
        ]
    ]);
}

/**
 * Simplified analysis function that works reliably
 */
function performSimplifiedAnalysis($uploaded_file) {
    try {
        // Get file information
        $file_size = $uploaded_file['size'];
        $file_name = strtolower($uploaded_file['name']);
        $file_type = $uploaded_file['type'];
        
        // Simple analysis based on filename and file properties
        $incident_type = 'Other';
        $description = 'Emergency incident detected from uploaded image';
        $responder_type = 'MDDRMO';
        $confidence = 60; // Default confidence
        
        // Basic keyword detection in filename
        if (strpos($file_name, 'fire') !== false || strpos($file_name, 'burn') !== false) {
            $incident_type = 'Fire';
            $description = 'Fire-related incident detected from image filename';
            $responder_type = 'BFP';
            $confidence = 70;
        } elseif (strpos($file_name, 'flood') !== false || strpos($file_name, 'water') !== false) {
            $incident_type = 'Flood';
            $description = 'Flooding incident detected from image filename';
            $responder_type = 'MDDRMO';
            $confidence = 70;
        } elseif (strpos($file_name, 'accident') !== false || strpos($file_name, 'crash') !== false) {
            $incident_type = 'Accident';
            $description = 'Traffic accident detected from image filename';
            $responder_type = 'MDDRMO';
            $confidence = 70;
        } elseif (strpos($file_name, 'crime') !== false || strpos($file_name, 'theft') !== false) {
            $incident_type = 'Crime';
            $description = 'Criminal incident detected from image filename';
            $responder_type = 'PNP';
            $confidence = 70;
        }
        
        // Enhanced analysis based on file size (larger files might indicate more serious incidents)
        if ($file_size > 2000000) { // 2MB+
            $confidence += 10;
            $description .= ' - High resolution image suggests detailed documentation';
        }
        
        // Time-based analysis (current hour can suggest incident type)
        $current_hour = date('H');
        if ($current_hour >= 22 || $current_hour <= 5) {
            // Night time - more likely to be crime or fire
            if ($incident_type === 'Other') {
                $incident_type = 'Crime';
                $description = 'Night-time incident detected - possible security concern';
                $responder_type = 'PNP';
                $confidence = 55;
            }
        }
        
        return [
            'success' => true,
            'incident_type' => $incident_type,
            'description' => $description,
            'responder_type' => $responder_type,
            'confidence' => min(85, $confidence), // Cap at 85%
            'details' => [
                'Analysis method: Simplified detection',
                'File size: ' . round($file_size / 1024, 2) . ' KB',
                'File type: ' . $file_type
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Analysis failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate suggestions based on analysis
 */
function generateSuggestions($analysis_result) {
    if (!$analysis_result['success']) {
        return ['suggestions' => []];
    }
    
    $suggestions = [
        [
            'type' => $analysis_result['incident_type'],
            'description' => $analysis_result['description'],
            'responder_type' => $analysis_result['responder_type'],
            'confidence' => $analysis_result['confidence']
        ]
    ];
    
    // Add alternative suggestions
    if ($analysis_result['confidence'] < 70) {
        $suggestions[] = [
            'type' => 'Other',
            'description' => 'Please verify and manually describe the incident',
            'responder_type' => 'MDDRMO',
            'confidence' => 50
        ];
    }
    
    return ['suggestions' => $suggestions];
}
?>
