<?php
/**
 * Dual AI Configuration for Alerto360
 * Supports: Gemini (Primary) → Grok (Fallback)
 */

// Load AI configs
require_once __DIR__ . '/config_gemini.php';
require_once __DIR__ . '/config_grok.php';

// AI Provider Priority
define('AI_PROVIDERS', ['gemini', 'grok']);

// Timeout settings (seconds) - increased for reliability
define('AI_TIMEOUT', 60);
define('AI_CONNECT_TIMEOUT', 20);

// Minimum confidence threshold
define('AI_MIN_CONFIDENCE', 0.5);

/**
 * Get available AI providers
 */
function getAvailableAIProviders() {
    $available = [];
    
    if (isGeminiConfigured()) {
        $available[] = 'gemini';
    }
    
    if (isGrokConfigured()) {
        $available[] = 'grok';
    }
    
    return $available;
}

/**
 * Check if any AI is configured
 */
function isAnyAIConfigured() {
    return isGeminiConfigured() || isGrokConfigured();
}

/**
 * Get AI status
 */
function getAIStatus() {
    return [
        'gemini' => [
            'configured' => isGeminiConfigured(),
            'model' => GEMINI_MODEL
        ],
        'grok' => [
            'configured' => isGrokConfigured(),
            'model' => GROK_MODEL
        ],
        'available_providers' => getAvailableAIProviders()
    ];
}
