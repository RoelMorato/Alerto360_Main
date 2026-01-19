<?php
// Mobile API Bypass - Special endpoint for mobile apps
// This bypasses CloudFlare protection by mimicking browser behavior

// Set headers to mimic browser
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get action from POST data
$action = $_POST['action'] ?? '';

// Include database connection
require_once 'db_connect.php';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'verify_email':
        handleVerifyEmail();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function handleLogin() {
    global $pdo;
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, password, role, email_verified, verification_required 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check if email verification is required
            if ($user['verification_required'] == 1 && $user['email_verified'] == 0) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Please verify your email before logging in',
                    'requires_verification' => true,
                    'email' => $user['email']
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'email_verified' => (bool)$user['email_verified']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password'
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
}

function handleRegister() {
    global $pdo;
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'citizen';

    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            return;
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, email_verified, verification_required, created_at)
            VALUES (?, ?, ?, ?, 0, 1, NOW())
        ");
        $stmt->execute([$name, $email, $hashedPassword, $role]);
        $userId = $pdo->lastInsertId();

        // Create verification code
        require_once 'email_verification_functions.php';
        $verificationResult = createVerificationCode($userId, $email);

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please check your email for verification code.',
            'user_id' => $userId,
            'requires_verification' => true,
            'verification_sent' => $verificationResult['success'] ?? false
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage()
        ]);
    }
}

function handleVerifyEmail() {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (empty($email) || empty($code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and code are required']);
        return;
    }

    require_once 'email_verification_functions.php';
    $result = verifyCode($email, $code);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}
?>