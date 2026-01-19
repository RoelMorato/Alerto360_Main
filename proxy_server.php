<?php
// Proxy Server - Bypasses all CloudFlare protection
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get the action and forward to appropriate handler
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function handleLogin() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        return;
    }
    
    // Direct database connection without any external dependencies
    $host = 'sql308.infinityfree.com';
    $db = 'if0_40657921_alerto360';
    $user = 'if0_40657921';
    $pass = 'P5TtWhm5OuHrP';
    
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $userRecord = $stmt->fetch();
        
        if ($userRecord && password_verify($password, $userRecord['password'])) {
            $response = [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => (int)$userRecord['id'],
                    'name' => $userRecord['name'],
                    'email' => $userRecord['email'],
                    'role' => $userRecord['role'],
                    'email_verified' => true
                ]
            ];
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    }
}

function handleRegister() {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password too short']);
        return;
    }
    
    $host = 'sql308.infinityfree.com';
    $db = 'if0_40657921_alerto360';
    $user = 'if0_40657921';
    $pass = 'P5TtWhm5OuHrP';
    
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        // Insert new user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified, verification_required, created_at) VALUES (?, ?, ?, 'citizen', 1, 0, NOW())");
        $stmt->execute([$name, $email, $hashedPassword]);
        
        $userId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => (int)$userId
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
}
?>