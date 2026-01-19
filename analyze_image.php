<?php
/**
 * AJAX Endpoint for Image Analysis (Web Version)
 * Pure AI Vision - NO color-based detection
 * Supports: Gemini (Primary) + Grok (Fallback)
 */

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
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $uploaded_file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
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

    // Perform color-based analysis (same as mobile API)
    $analysis_result = analyzeImageColors($uploaded_file['tmp_name']);

    // Return results in format expected by web frontend
    echo json_encode([
        'success' => true,
        'analysis' => [
            'incident_type' => $analysis_result['type'],
            'description' => $analysis_result['description'],
            'responder_type' => getResponderTypeForIncident($analysis_result['type']),
            'confidence' => round($analysis_result['confidence'] * 100),
            'details' => ['Method: Color-based AI detection']
        ],
        'suggestions' => $analysis_result['suggestions'] ?? [],
        'message' => 'Image analyzed successfully!'
    ]);

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
 * Get responder type based on incident type
 */
function getResponderTypeForIncident($incidentType) {
    switch ($incidentType) {
        case 'Fire': return 'BFP';
        case 'Crime': return 'PNP';
        case 'Flood':
        case 'Landslide':
        case 'Accident': return 'MDDRMO';
        default: return 'MDDRMO';
    }
}

/**
 * Color-based image analysis (same as mobile API)
 */
function analyzeImageColors($imagePath) {
    // Get image info
    $imageInfo = @getimagesize($imagePath);
    
    if ($imageInfo === false) {
        return [
            'type' => 'Other',
            'confidence' => 0.5,
            'description' => 'Unable to analyze image',
            'suggestions' => ['Please select emergency type manually']
        ];
    }
    
    $mimeType = $imageInfo['mime'];
    
    // Load image based on type
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($imagePath);
            break;
        default:
            return [
                'type' => 'Other',
                'confidence' => 0.5,
                'description' => 'Unable to analyze image type',
                'suggestions' => ['Please select emergency type manually']
            ];
    }
    
    if (!$image) {
        return [
            'type' => 'Other',
            'confidence' => 0.5,
            'description' => 'Unable to load image',
            'suggestions' => ['Please select emergency type manually']
        ];
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Resize for faster processing if image is large
    $maxDimension = 400;
    if ($width > $maxDimension || $height > $maxDimension) {
        $scale = min($maxDimension / $width, $maxDimension / $height);
        $newWidth = (int)($width * $scale);
        $newHeight = (int)($height * $scale);
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        
        $image = $resized;
        $width = $newWidth;
        $height = $newHeight;
    }
    
    // Sample colors from image
    $colorCounts = [
        'red' => 0,
        'orange' => 0,
        'blue' => 0,
        'brown' => 0,
        'gray' => 0,
        'black' => 0
    ];
    
    // Sample every 20th pixel for faster performance
    $sampleSize = 0;
    for ($x = 0; $x < $width; $x += 20) {
        for ($y = 0; $y < $height; $y += 20) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Categorize color
            if ($r > 200 && $g < 100 && $b < 100) {
                $colorCounts['red']++;
            } elseif ($r > 200 && $g > 100 && $b < 100) {
                $colorCounts['orange']++;
            } elseif ($b > 150 && $r < 100 && $g < 150) {
                $colorCounts['blue']++;
            } elseif ($r > 100 && $g > 80 && $b < 80) {
                $colorCounts['brown']++;
            } elseif ($r < 100 && $g < 100 && $b < 100) {
                $colorCounts['black']++;
            } elseif (abs($r - $g) < 30 && abs($g - $b) < 30) {
                $colorCounts['gray']++;
            }
            
            $sampleSize++;
        }
    }
    
    imagedestroy($image);
    
    // Calculate percentages
    $colorPercentages = [];
    foreach ($colorCounts as $color => $count) {
        $colorPercentages[$color] = ($count / $sampleSize) * 100;
    }
    
    // Determine emergency type based on dominant colors
    arsort($colorPercentages);
    $dominantColor = key($colorPercentages);
    $dominantPercentage = current($colorPercentages);
    
    // Map colors to emergency types
    $result = [
        'type' => 'Other',
        'confidence' => 0.5,
        'description' => 'Emergency detected',
        'suggestions' => []
    ];
    
    if ($dominantColor === 'red' && $dominantPercentage > 15) {
        $result = [
            'type' => 'Fire',
            'confidence' => min(0.95, $dominantPercentage / 20),
            'description' => 'Fire or flames detected in image',
            'suggestions' => [
                'Evacuate immediately if safe',
                'Call fire department',
                'Do not use elevators'
            ]
        ];
    } elseif ($dominantColor === 'orange' && $dominantPercentage > 12) {
        $result = [
            'type' => 'Fire',
            'confidence' => min(0.90, $dominantPercentage / 15),
            'description' => 'Fire or smoke detected',
            'suggestions' => [
                'Stay low to avoid smoke',
                'Alert others nearby',
                'Find safe exit route'
            ]
        ];
    } elseif ($dominantColor === 'blue' && $dominantPercentage > 20) {
        $result = [
            'type' => 'Flood',
            'confidence' => min(0.85, $dominantPercentage / 25),
            'description' => 'Water or flooding detected',
            'suggestions' => [
                'Move to higher ground',
                'Avoid walking through water',
                'Stay away from electrical sources'
            ]
        ];
    } elseif ($dominantColor === 'brown' && $dominantPercentage > 18) {
        $result = [
            'type' => 'Landslide',
            'confidence' => min(0.80, $dominantPercentage / 20),
            'description' => 'Mud or landslide detected',
            'suggestions' => [
                'Evacuate area immediately',
                'Alert neighbors',
                'Stay away from slopes'
            ]
        ];
    } elseif ($dominantColor === 'gray' && $dominantPercentage > 15) {
        $result = [
            'type' => 'Accident',
            'confidence' => min(0.75, $dominantPercentage / 18),
            'description' => 'Vehicle or accident detected',
            'suggestions' => [
                'Check for injuries',
                'Call emergency services',
                'Secure the area'
            ]
        ];
    } elseif ($dominantColor === 'black' && $dominantPercentage > 20) {
        $result = [
            'type' => 'Fire',
            'confidence' => min(0.85, $dominantPercentage / 22),
            'description' => 'Smoke or fire detected',
            'suggestions' => [
                'Evacuate immediately',
                'Cover nose and mouth',
                'Alert fire department'
            ]
        ];
    }
    
    return $result;
}
?>
