<?php
/**
 * Grok AI Configuration (xAI)
 * 
 * To use Grok AI for image detection:
 * 1. Get API key from: https://console.x.ai/
 * 2. Replace 'YOUR_GROK_API_KEY' below with your actual key
 * 3. Keep this file secure - don't commit to public repositories
 */

// Grok AI API Key
define('GROK_API_KEY', 'YOUR_GROK_API_KEY');

// Grok Vision Model - grok-2-vision-latest for image analysis
define('GROK_MODEL', 'grok-2-vision-1212');

// API Endpoint
define('GROK_API_URL', 'https://api.x.ai/v1/chat/completions');

/**
 * Check if Grok AI is configured
 */
function isGrokConfigured() {
    return GROK_API_KEY !== 'YOUR_GROK_API_KEY' && !empty(GROK_API_KEY);
}
