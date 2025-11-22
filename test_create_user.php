<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($name && $email && $password) {
        // Check if user exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->fetch()) {
            echo "<h3>User already exists!</h3>";
            echo "<p>Email: $email</p>";
            echo "<a href='test_api.php'>Back to test page</a>";
        } else {
            // Create user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            
            if ($insert->execute([$name, $email, $hashed])) {
                echo "<h3>✓ Test user created successfully!</h3>";
                echo "<p>Name: $name</p>";
                echo "<p>Email: $email</p>";
                echo "<p>Password: $password</p>";
                echo "<p>You can now use these credentials to login from the Flutter app.</p>";
                echo "<a href='test_api.php'>Back to test page</a>";
            } else {
                echo "<h3>✗ Failed to create user</h3>";
                echo "<a href='test_api.php'>Back to test page</a>";
            }
        }
    } else {
        echo "<h3>✗ All fields are required</h3>";
        echo "<a href='test_api.php'>Back to test page</a>";
    }
} else {
    header('Location: test_api.php');
}
?>
