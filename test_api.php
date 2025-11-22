<?php
// Simple test script to verify API endpoints are working

echo "<h2>Testing Alerto360 API Endpoints</h2>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    require 'db_connect.php';
    echo "✓ Database connection successful<br>";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Users table exists<br>";
        
        // Count users
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "✓ Total users in database: $count<br>";
    } else {
        echo "✗ Users table not found<br>";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Test login endpoint
echo "<h3>2. Login Endpoint Test</h3>";
echo "Endpoint: api_login.php<br>";
echo "Method: POST<br>";
echo "Expected: JSON response with success field<br>";

// Test register endpoint
echo "<h3>3. Register Endpoint Test</h3>";
echo "Endpoint: api_register.php<br>";
echo "Method: POST<br>";
echo "Expected: JSON response with success field<br>";

echo "<hr>";
echo "<h3>Test Instructions:</h3>";
echo "<ol>";
echo "<li>Make sure your Flutter app's baseUrl is set to: <code>http://localhost/Alerto360-main</code></li>";
echo "<li>Make sure XAMPP/WAMP Apache and MySQL are running</li>";
echo "<li>Try logging in with an existing user account</li>";
echo "<li>If no users exist, try registering a new account first</li>";
echo "</ol>";

echo "<h3>Create Test User (if needed):</h3>";
echo "<form method='post' action='test_create_user.php'>";
echo "Name: <input type='text' name='name' value='Test User'><br>";
echo "Email: <input type='email' name='email' value='test@example.com'><br>";
echo "Password: <input type='password' name='password' value='password123'><br>";
echo "<button type='submit'>Create Test User</button>";
echo "</form>";
?>
