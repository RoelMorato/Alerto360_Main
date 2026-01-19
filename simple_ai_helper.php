<?php
/**
 * Simple AI helper for image analysis
 * Pure AI Vision - NO color-based fallback
 * Supports: Gemini (Primary) + Grok (Fallback)
 */

require_once __DIR__ . '/config_ai.php';

/**
 * Main function - Analyze image using AI only
 */
function simpleAIAnalysis($imagePath) {
    $imageData = @file_get_contents($imagePath);
    if (!$imageData) {
        return null;
    }
    
    $base64Image = base64_encode($imageData);
    
    // Get mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        $mimeType = 'image/jpeg';
    }
    
    // Try Gemini first
    if (isGeminiConfigured()) {
        $result = tryGeminiAnalysis($base64Image, $mimeType);
        if ($result && $result['confidence'] >= 0.5) {
            $result['ai_provider'] = 'gemini';
            return $result;
        }
    }
    
    // Try Grok as fallback
    if (isGrokConfigured()) {
        $result = tryGrokAnalysis($base64Image, $mimeType);
        if ($result && $result['confidence'] >= 0.5) {
            $result['ai_provider'] = 'grok';
            return $result;
        }
    }
    
    // Both AI failed - return null (no color fallback!)
    return null;
}

/**
 * Analyze with Gemini AI
 */
function tryGeminiAnalysis($base64Image, $mimeType = 'image/jpeg') {
    try {
        $url = GEMINI_API_URL . "?key=" . GEMINI_API_KEY;
        
        $prompt = getAIPrompt();
        
        $data = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $base64Image
                    ]]
                ]
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 512
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                return parseAIResponse($result['candidates'][0]['content']['parts'][0]['text']);
            }
        }
    } catch (Exception $e) {
        error_log("Gemini AI error: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Analyze with Grok AI
 */
function tryGrokAnalysis($base64Image, $mimeType = 'image/jpeg') {
    try {
        $prompt = getAIPrompt();
        
        $data = [
            'model' => GROK_MODEL,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:$mimeType;base64,$base64Image",
                            'detail' => 'high'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ]
                ]
            ]],
            'temperature' => 0.1,
            'max_tokens' => 512
        ];
        
        $ch = curl_init(GROK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . GROK_API_KEY
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                return parseAIResponse($result['choices'][0]['message']['content']);
            }
        }
    } catch (Exception $e) {
        error_log("Grok AI error: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Get AI prompt for emergency detection
 */
function getAIPrompt() {
    return "You are an expert emergency incident classifier. Analyze this image carefully.

CLASSIFY into ONE of these categories:
- Fire: flames, smoke, burning structures
- Flood: standing water, submerged areas, flooding
- Accident: vehicle crashes, collisions, traffic incidents
- Crime: violence, robbery, suspicious activity
- Landslide: mud/debris flow, slope collapse
- Other: unclear or doesn't match above

RESPOND ONLY with this JSON format:
{
  \"type\": \"Fire|Flood|Accident|Crime|Landslide|Other\",
  \"confidence\": 0.0-1.0,
  \"description\": \"Brief description of what you see\",
  \"suggestions\": [\"Safety tip 1\", \"Safety tip 2\", \"Safety tip 3\"]
}

Be accurate. Only high confidence when evidence is clear.";
}

/**
 * Parse AI response to structured data
 */
function parseAIResponse($text) {
    // Clean markdown
    $text = preg_replace('/```json\s*/', '', $text);
    $text = preg_replace('/```\s*/', '', $text);
    $text = trim($text);
    
    // Try JSON parse
    $analysis = json_decode($text, true);
    
    if (!$analysis) {
        preg_match('/\{.*\}/s', $text, $matches);
        if (!empty($matches)) {
            $analysis = json_decode($matches[0], true);
        }
    }
    
    if (!$analysis || !isset($analysis['type'])) {
        return null;
    }
    
    // Validate type
    $validTypes = ['Fire', 'Flood', 'Landslide', 'Accident', 'Crime', 'Other'];
    $detectedType = ucfirst(strtolower(trim($analysis['type'])));
    
    if (!in_array($detectedType, $validTypes)) {
        $detectedType = 'Other';
    }
    
    // Default suggestions
    $defaultSuggestions = [
        'Fire' => ['Evacuate immediately', 'Call BFP', 'Stay low to avoid smoke'],
        'Flood' => ['Move to higher ground', 'Avoid flood water', 'Call MDDRMO'],
        'Accident' => ['Check for injuries', 'Call emergency services', 'Secure the area'],
        'Crime' => ['Stay safe', 'Call PNP police', 'Note descriptions'],
        'Landslide' => ['Evacuate the area', 'Stay away from slopes', 'Call MDDRMO'],
        'Other' => ['Assess the situation', 'Call appropriate services', 'Stay alert']
    ];
    
    return [
        'type' => $detectedType,
        'confidence' => floatval($analysis['confidence'] ?? 0.75),
        'description' => $analysis['description'] ?? 'Emergency situation detected',
        'suggestions' => $analysis['suggestions'] ?? $defaultSuggestions[$detectedType]
    ];
}
