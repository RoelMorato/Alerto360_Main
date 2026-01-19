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

// Try database connection first
$useDatabase = false;
$user = null;

// Check for Vercel Postgres or other database
if (isset($_ENV['POSTGRES_URL']) || isset($_ENV['DATABASE_URL'])) {
    try {
        $databaseUrl = $_ENV['POSTGRES_URL'] ?? $_ENV['DATABASE_URL'];
        $pdo = new PDO($databaseUrl);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Try to get user from database
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $useDatabase = true;
        
    } catch (Exception $e) {
        $useDatabase = false;
    }
}

// If database works and user found
if ($useDatabase && $user && password_verify($password, $user['password'])) {
    echo json_encode([
        'success' => true,
        'message' => 'Login successful (Database)',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'email_verified' => true
        ]
    ]);
    exit;
}

// Fallback to demo users if no database or user not found
$demoUsers = [
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
    ],
    'responder@alerto360.com' => [
        'id' => 3,
        'name' => 'Emergency Responder',
        'email' => 'responder@alerto360.com',
        'password' => password_hash('responder123', PASSWORD_DEFAULT),
        'role' => 'responder'
    ]
];

if (isset($demoUsers[$email]) && password_verify($password, $demoUsers[$email]['password'])) {
    $user = $demoUsers[$email];
    echo json_encode([
        'success' => true,
        'message' => 'Login successful (Demo)',
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