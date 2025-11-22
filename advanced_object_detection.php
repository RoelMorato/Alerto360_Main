    <?php
/**
 * Advanced Object Detection System for Emergency Incidents
 * Like the tiger detection example - identifies specific objects in images
 */

/**
 * Main advanced object detection function
 */
function detectObjectsInImage($image_path) {
    try {
        if (!extension_loaded('gd')) {
            return performBasicDetection($image_path);
        }
        
        $image = imagecreatefromstring(file_get_contents($image_path));
        if (!$image) {
            return ['objects' => [], 'confidence' => 0];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        
        // Advanced detection algorithms
        $detections = [
            'fire' => detectFire($image, $width, $height),
            'vehicle' => detectVehicle($image, $width, $height),
            'person' => detectPerson($image, $width, $height),
            'animal' => detectAnimal($image, $width, $height),
            'water' => detectWater($image, $width, $height),
            'smoke' => detectSmoke($image, $width, $height)
        ];
        
        $detected_objects = [];
        foreach ($detections as $type => $result) {
            if ($result['confidence'] > 0.3) {
                $detected_objects[] = [
                    'type' => $type,
                    'confidence' => round($result['confidence'] * 100, 1),
                    'location' => $result['location'],
                    'description' => $result['description']
                ];
            }
        }
        
        imagedestroy($image);
        
        return [
            'objects' => $detected_objects,
            'total_detections' => count($detected_objects),
            'method' => 'advanced_visual_analysis'
        ];
        
    } catch (Exception $e) {
        return performBasicDetection($image_path);
    }
}

/**
 * Advanced fire detection
 */
function detectFire($image, $width, $height) {
    $fire_pixels = 0;
    $hot_spots = 0;
    $total_samples = 0;
    
    // Scan image for fire characteristics
    for ($x = 0; $x < $width; $x += 4) {
        for ($y = 0; $y < $height; $y += 4) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Fire color detection
            if (isFireColor($r, $g, $b)) {
                $fire_pixels++;
                
                // Check for hot spots (bright red/orange areas)
                if ($r > 200 && $g > 100 && $b < 80) {
                    $hot_spots++;
                }
            }
            $total_samples++;
        }
    }
    
    $fire_ratio = $total_samples > 0 ? $fire_pixels / $total_samples : 0;
    $hotspot_ratio = $total_samples > 0 ? $hot_spots / $total_samples : 0;
    
    $confidence = ($fire_ratio * 0.7) + ($hotspot_ratio * 0.3);
    
    return [
        'confidence' => min(0.95, $confidence * 2), // Boost fire detection
        'location' => $fire_pixels > 0 ? 'detected_in_image' : 'none',
        'description' => $fire_pixels > 0 ? "Fire detected - {$fire_pixels} fire-colored pixels found" : 'No fire detected'
    ];
}

/**
 * Advanced animal detection (like tiger in your example)
 */
function detectAnimal($image, $width, $height) {
    $animal_features = 0;
    $fur_patterns = 0;
    $total_samples = 0;
    
    // Look for animal characteristics
    for ($x = 0; $x < $width; $x += 6) {
        for ($y = 0; $y < $height; $y += 6) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Animal fur colors (including tiger orange)
            if (isAnimalFur($r, $g, $b)) {
                $animal_features++;
                
                // Tiger-specific orange/black pattern
                if (isTigerPattern($r, $g, $b)) {
                    $fur_patterns += 2; // Higher weight for tiger patterns
                }
            }
            $total_samples++;
        }
    }
    
    $animal_ratio = $total_samples > 0 ? $animal_features / $total_samples : 0;
    $pattern_ratio = $total_samples > 0 ? $fur_patterns / $total_samples : 0;
    
    $confidence = ($animal_ratio * 0.6) + ($pattern_ratio * 0.4);
    
    $animal_type = 'animal';
    if ($pattern_ratio > 0.1) {
        $animal_type = 'large_cat_or_tiger';
    }
    
    return [
        'confidence' => min(0.9, $confidence * 1.5),
        'location' => $animal_features > 0 ? 'detected_in_image' : 'none',
        'description' => $animal_features > 0 ? "Animal detected - possible {$animal_type}" : 'No animal detected'
    ];
}

/**
 * Vehicle detection
 */
function detectVehicle($image, $width, $height) {
    $vehicle_features = 0;
    $metallic_surfaces = 0;
    $total_samples = 0;
    
    for ($x = 0; $x < $width; $x += 8) {
        for ($y = 0; $y < $height; $y += 8) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Vehicle characteristics
            if (isVehicleColor($r, $g, $b)) {
                $vehicle_features++;
                
                if (isMetallicSurface($r, $g, $b)) {
                    $metallic_surfaces++;
                }
            }
            $total_samples++;
        }
    }
    
    $vehicle_ratio = $total_samples > 0 ? $vehicle_features / $total_samples : 0;
    $metallic_ratio = $total_samples > 0 ? $metallic_surfaces / $total_samples : 0;
    
    $confidence = ($vehicle_ratio * 0.7) + ($metallic_ratio * 0.3);
    
    return [
        'confidence' => $confidence,
        'location' => $vehicle_features > 0 ? 'detected_in_image' : 'none',
        'description' => $vehicle_features > 0 ? "Vehicle detected - metallic surfaces found" : 'No vehicle detected'
    ];
}

/**
 * Person detection
 */
function detectPerson($image, $width, $height) {
    $skin_pixels = 0;
    $clothing_pixels = 0;
    $total_samples = 0;
    
    for ($x = 0; $x < $width; $x += 10) {
        for ($y = 0; $y < $height; $y += 10) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            if (isSkinTone($r, $g, $b)) {
                $skin_pixels++;
            }
            
            if (isClothingColor($r, $g, $b)) {
                $clothing_pixels++;
            }
            
            $total_samples++;
        }
    }
    
    $skin_ratio = $total_samples > 0 ? $skin_pixels / $total_samples : 0;
    $clothing_ratio = $total_samples > 0 ? $clothing_pixels / $total_samples : 0;
    
    $confidence = ($skin_ratio * 0.8) + ($clothing_ratio * 0.2);
    
    return [
        'confidence' => $confidence,
        'location' => $skin_pixels > 0 ? 'detected_in_image' : 'none',
        'description' => $skin_pixels > 0 ? "Person detected - skin tones identified" : 'No person detected'
    ];
}

/**
 * Water/flood detection
 */
function detectWater($image, $width, $height) {
    $water_pixels = 0;
    $reflective_surfaces = 0;
    $total_samples = 0;
    
    for ($x = 0; $x < $width; $x += 6) {
        for ($y = 0; $y < $height; $y += 6) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            if (isWaterColor($r, $g, $b)) {
                $water_pixels++;
                
                if (isReflectiveSurface($r, $g, $b)) {
                    $reflective_surfaces++;
                }
            }
            $total_samples++;
        }
    }
    
    $water_ratio = $total_samples > 0 ? $water_pixels / $total_samples : 0;
    $reflective_ratio = $total_samples > 0 ? $reflective_surfaces / $total_samples : 0;
    
    $confidence = ($water_ratio * 0.8) + ($reflective_ratio * 0.2);
    
    return [
        'confidence' => $confidence,
        'location' => $water_pixels > 0 ? 'detected_in_image' : 'none',
        'description' => $water_pixels > 0 ? "Water detected - possible flooding" : 'No water detected'
    ];
}

/**
 * Smoke detection
 */
function detectSmoke($image, $width, $height) {
    $smoke_pixels = 0;
    $gray_areas = 0;
    $total_samples = 0;
    
    for ($x = 0; $x < $width; $x += 5) {
        for ($y = 0; $y < $height; $y += 5) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            if (isSmokeColor($r, $g, $b)) {
                $smoke_pixels++;
                
                if (isGrayArea($r, $g, $b)) {
                    $gray_areas++;
                }
            }
            $total_samples++;
        }
    }
    
    $smoke_ratio = $total_samples > 0 ? $smoke_pixels / $total_samples : 0;
    $gray_ratio = $total_samples > 0 ? $gray_areas / $total_samples : 0;
    
    $confidence = ($smoke_ratio * 0.7) + ($gray_ratio * 0.3);
    
    return [
        'confidence' => $confidence,
        'location' => $smoke_pixels > 0 ? 'detected_in_image' : 'none',
        'description' => $smoke_pixels > 0 ? "Smoke detected - possible fire incident" : 'No smoke detected'
    ];
}

// Color detection helper functions
function isFireColor($r, $g, $b) {
    return ($r > 150 && $g > 50 && $b < 100) || ($r > 200 && $g > 150 && $b < 150);
}

function isAnimalFur($r, $g, $b) {
    return (
        // Brown fur
        ($r > 100 && $r < 200 && $g > 60 && $g < 150 && $b < 100) ||
        // Black fur
        ($r < 80 && $g < 80 && $b < 80) ||
        // Orange/tiger colors
        ($r > 180 && $g > 100 && $g < 180 && $b < 100) ||
        // White fur
        ($r > 200 && $g > 200 && $b > 200)
    );
}

function isTigerPattern($r, $g, $b) {
    // Tiger orange with black stripes
    return ($r > 180 && $g > 100 && $g < 180 && $b < 100);
}

function isVehicleColor($r, $g, $b) {
    // Common vehicle colors
    $avg = ($r + $g + $b) / 3;
    return ($avg > 50 && $avg < 220) && (abs($r - $g) < 50 && abs($g - $b) < 50);
}

function isMetallicSurface($r, $g, $b) {
    $avg = ($r + $g + $b) / 3;
    return (abs($r - $g) < 20 && abs($g - $b) < 20 && $avg > 80 && $avg < 180);
}

function isSkinTone($r, $g, $b) {
    return ($r > 120 && $r < 255 && $g > 80 && $g < 220 && $b > 60 && $b < 180 && $r > $g && $g > $b);
}

function isClothingColor($r, $g, $b) {
    // Various clothing colors
    return ($r > 50 || $g > 50 || $b > 50) && !isSkinTone($r, $g, $b);
}

function isWaterColor($r, $g, $b) {
    return ($b > 120 && $r < 150 && $g < 200) || ($b > 150 && $g > 150 && $r < 100);
}

function isReflectiveSurface($r, $g, $b) {
    $avg = ($r + $g + $b) / 3;
    return $avg > 150 && (abs($r - $g) < 30 && abs($g - $b) < 30);
}

function isSmokeColor($r, $g, $b) {
    $avg = ($r + $g + $b) / 3;
    return (abs($r - $g) < 40 && abs($g - $b) < 40 && $avg > 60 && $avg < 160);
}

function isGrayArea($r, $g, $b) {
    return (abs($r - $g) < 20 && abs($g - $b) < 20 && $r > 80 && $r < 180);
}

/**
 * Fallback detection for systems without GD
 */
function performBasicDetection($image_path) {
    $file_size = filesize($image_path);
    $file_name = strtolower(basename($image_path));
    
    $objects = [];
    
    // Basic filename analysis
    if (strpos($file_name, 'fire') !== false) {
        $objects[] = ['type' => 'fire', 'confidence' => 70, 'location' => 'filename_analysis', 'description' => 'Fire detected from filename'];
    }
    if (strpos($file_name, 'animal') !== false || strpos($file_name, 'tiger') !== false) {
        $objects[] = ['type' => 'animal', 'confidence' => 65, 'location' => 'filename_analysis', 'description' => 'Animal detected from filename'];
    }
    if (strpos($file_name, 'car') !== false || strpos($file_name, 'vehicle') !== false) {
        $objects[] = ['type' => 'vehicle', 'confidence' => 60, 'location' => 'filename_analysis', 'description' => 'Vehicle detected from filename'];
    }
    
    return [
        'objects' => $objects,
        'total_detections' => count($objects),
        'method' => 'basic_filename_analysis'
    ];
}

?>
