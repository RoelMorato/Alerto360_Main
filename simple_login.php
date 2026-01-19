<?php
// Simple Login API - No fancy headers, just pure PHP
error_reporting(0);
ini_set('display_errors', 0);

// Clean any output
if (ob_get_level()) {
    ob_clean();
}

// Set basic headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '{"success":false,"message":"POST only"}';
    exit;
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || empty($password)) {
    echo '{"success":false,"message":"Email and password required"}';
    exit;
}

// Database connection
$host = 'sql308.infinityfree.com';
$db = 'if0_40657921_alerto360';
$user = 'if0_40657921';
$pass = 'P5TtWhm5OuHrP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 30
    ]);
    
    // Check user
    $stmt = $pdo->prepare("SELECT id, name, email, password, role, email_verified, verification_required FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userRecord = $stmt->fetch();
    
    if ($userRecord && password_verify($password, $userRecord['password'])) {
        // Check verification
        if ($userRecord['verification_required'] == 1 && $userRecord['email_verified'] == 0) {
            echo '{"success":false,"message":"Please verify your email","requires_verification":true,"email":"' . $userRecord['email'] . '"}';
            exit;
        }
        
        // Success
        echo '{"success":true,"message":"Login successful","user":{"id":' . $userRecord['id'] . ',"name":"' . $userRecord['name'] . '","email":"' . $userRecord['email'] . '","role":"' . $userRecord['role'] . '","email_verified":' . ($userRecord['email_verified'] ? 'true' : 'false') . '}}';
    } else {
        echo '{"success":false,"message":"Invalid email or password"}';
    }
    
} catch (Exception $e) {
    echo '{"success":false,"message":"Database error"}';
}
?>