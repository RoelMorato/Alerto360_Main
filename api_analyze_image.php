<?php
/**
 * Dual AI Image Analysis API
 * Gemini (Primary) → Grok (Fallback)
 */

require_once 'api_init.php';
require_once 'config_ai.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
    exit;
}

try {
    $imagePath = $_FILES['image']['tmp_name'];
    $imageData = file_get_contents($imagePath);
    $base64Image = base64_encode($imageData);
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        $mimeType = 'image/jpeg';
    }
    
    $result = null;
    $usedProvider = null;
    $errors = [];
    
    // Try Gemini first
    if (isGeminiConfigured()) {
        $result = analyzeWithGemini($base64Image, $mimeType);
        if ($result && $result['confidence'] >= AI_MIN_CONFIDENCE) {
            $usedProvider = 'gemini';
        } else {
            $errors['gemini'] = $result ? 'Low confidence' : 'Analysis failed';
            $result = null;
        }
    }
    
    // Try Grok as fallback
    if (!$result && isGrokConfigured()) {
        $result = analyzeWithGrok($base64Image, $mimeType);
        if ($result && $result['confidence'] >= AI_MIN_CONFIDENCE) {
            $usedProvider = 'grok';
        } else {
            $errors['grok'] = $result ? 'Low confidence' : 'Analysis failed';
            $result = null;
        }
    }
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'AI analysis failed. Please select emergency type manually.',
            'errors' => $errors
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'detected_type' => $result['type'],
        'confidence' => $result['confidence'],
        'description' => $result['description'],
        'suggestions' => $result['suggestions'],
        'ai_provider' => $usedProvider,
        'method' => 'ai-vision'
    ]);
    
} catch (Exception $e) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Analyze with Gemini AI
 */
function analyzeWithGemini($base64Image, $mimeType) {
    $url = GEMINI_API_URL . "?key=" . GEMINI_API_KEY;
    $prompt = getEmergencyPrompt();
    
    $data = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]]
            ]
        ]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 512]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => AI_TIMEOUT
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) return null;
    
    $result = json_decode($response, true);
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) return null;
    
    return parseAIResponse($result['candidates'][0]['content']['parts'][0]['text']);
}

/**
 * Analyze with Grok AI
 */
function analyzeWithGrok($base64Image, $mimeType) {
    $prompt = getEmergencyPrompt();
    
    $data = [
        'model' => GROK_MODEL,
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$base64Image", 'detail' => 'high']],
                ['type' => 'text', 'text' => $prompt]
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
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . GROK_API_KEY],
        CURLOPT_TIMEOUT => AI_TIMEOUT
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) return null;
    
    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) return null;
    
    return parseAIResponse($result['choices'][0]['message']['content']);
}

/**
 * Emergency detection prompt
 */
function getEmergencyPrompt() {
    return "Analyze this image and classify the emergency type.

Categories: Fire, Flood, Accident, Crime, Landslide, Other

Respond ONLY with JSON:
{\"type\":\"Fire|Flood|Accident|Crime|Landslide|Other\",\"confidence\":0.0-1.0,\"description\":\"Brief description\",\"suggestions\":[\"Tip 1\",\"Tip 2\",\"Tip 3\"]}";
}

/**
 * Parse AI response
 */
function parseAIResponse($text) {
    $text = preg_replace('/```json\s*|```\s*/', '', trim($text));
    
    $analysis = json_decode($text, true);
    if (!$analysis) {
        preg_match('/\{.*\}/s', $text, $matches);
        if (!empty($matches)) $analysis = json_decode($matches[0], true);
    }
    
    if (!$analysis || !isset($analysis['type'])) return null;
    
    $validTypes = ['Fire', 'Flood', 'Landslide', 'Accident', 'Crime', 'Other'];
    $type = ucfirst(strtolower(trim($analysis['type'])));
    if (!in_array($type, $validTypes)) $type = 'Other';
    
    $defaultTips = [
        'Fire' => ['Evacuate immediately', 'Call BFP', 'Stay low'],
        'Flood' => ['Move to higher ground', 'Avoid flood water', 'Call MDDRMO'],
        'Accident' => ['Check for injuries', 'Call emergency services', 'Secure area'],
        'Crime' => ['Stay safe', 'Call PNP', 'Note descriptions'],
        'Landslide' => ['Evacuate area', 'Stay away from slopes', 'Call MDDRMO'],
        'Other' => ['Assess situation', 'Call emergency services', 'Stay alert']
    ];
    
    return [
        'type' => $type,
        'confidence' => floatval($analysis['confidence'] ?? 0.75),
        'description' => $analysis['description'] ?? 'Emergency detected',
        'suggestions' => $analysis['suggestions'] ?? $defaultTips[$type]
    ];
}
