<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

// For demo purposes - replace with your actual database
$users = [
    'admin@alerto360.com' => [
        'id' => 1,
        'name' => 'Admin User',
        'email' => 'admin@alerto360.com',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin'
    ],
    'test@alerto360.com' => [
        'id' => 2,
        'name' => 'Test User',
        'email' => 'test@alerto360.com',
        'password' => password_hash('test123', PASSWORD_DEFAULT),
        'role' => 'citizen'
    ]
];

if (isset($users[$email]) && password_verify($password, $users[$email]['password'])) {
    $user = $users[$email];
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'email_verified' => true
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password'
    ]);
}
?>