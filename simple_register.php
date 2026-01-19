<?php
// Simple Register API
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '{"success":false,"message":"POST only"}';
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($name) || empty($email) || empty($password)) {
    echo '{"success":false,"message":"All fields required"}';
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '{"success":false,"message":"Invalid email"}';
    exit;
}

if (strlen($password) < 6) {
    echo '{"success":false,"message":"Password too short"}';
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
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo '{"success":false,"message":"Email already exists"}';
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified, verification_required, created_at) VALUES (?, ?, ?, 'citizen', 0, 0, NOW())");
    $stmt->execute([$name, $email, $hashedPassword]);
    $userId = $pdo->lastInsertId();
    
    echo '{"success":true,"message":"Registration successful","user_id":' . $userId . '}';
    
} catch (Exception $e) {
    echo '{"success":false,"message":"Registration failed"}';
}
?>