<?php
/**
 * Gemini AI Configuration
 * 
 * To use Gemini AI for image detection:
 * 1. Get a free API key from: https://makersuite.google.com/app/apikey
 * 2. Replace 'YOUR_GEMINI_API_KEY' below with your actual key
 * 3. Keep this file secure - don't commit to public repositories
 */

// Gemini AI API Key
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY');


// Gemini AI Model - Use latest gemini-2.5-flash for best accuracy
// Options: gemini-2.5-flash (recommended), gemini-2.0-flash, gemini-1.5-pro
define('GEMINI_MODEL', 'gemini-2.5-flash');

// API Endpoint - Use v1 API
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1/models/' . GEMINI_MODEL . ':generateContent');

/**
 * Check if Gemini AI is configured
 */
function isGeminiConfigured() {
    return GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY' && !empty(GEMINI_API_KEY);
}
