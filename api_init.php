<?php
/**
 * API Initialization - Include this at the top of all API files
 * Ensures clean JSON responses without PHP errors/warnings
 */

// Suppress ALL errors and warnings to prevent HTML output in JSON responses
error_reporting(0);
ini_set('display_errors', '0');

// Start output buffering to catch any accidental output
ob_start();

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Clear any output that happened before this point
if (ob_get_level() > 1) {
    ob_clean();
}
