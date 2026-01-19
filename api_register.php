<?php
// Initialize API with clean JSON output
require_once 'api_init.php';
require_once 'db_connect.php';
require_once 'email_verification_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'citizen';

if (empty($name) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // For citizens, require email verification
    $requiresVerification = ($role === 'citizen') ? 1 : 0;
    
    $insert = $pdo->prepare("
        INSERT INTO users (name, email, password, role, email_verified, verification_required) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($insert->execute([$name, $email, $hashed_password, $role, 0, $requiresVerification])) {
        $user_id = $pdo->lastInsertId();
        
        // Send verification email for citizens
        if ($requiresVerification) {
            $verificationResult = createVerificationCode($user_id, $email);
            
            if ($verificationResult['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Registration successful! Please check your email for verification code.',
                    'user_id' => (int)$user_id,
                    'requires_verification' => true,
                    'email' => $email
                ]);
            } else {
                // Registration succeeded but email failed
                echo json_encode([
                    'success' => true,
                    'message' => 'Registration successful but email sending failed. Please contact support.',
                    'user_id' => (int)$user_id,
                    'requires_verification' => true,
                    'email' => $email,
                    'email_error' => true
                ]);
            }
        } else {
            // No verification needed (responders, admins)
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => (int)$user_id,
                'requires_verification' => false
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
