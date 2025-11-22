<?php
// Run this script ONCE to create an admin account, then delete it for security.
require 'db_connect.php';

$name = 'Admin';
$email = 'admin@admin';
$password = 'admin123'; // Change this to a strong password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo "Admin account already exists.";
} else {
    $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    if ($insert->execute([$name, $email, $hashed])) {
        echo "Admin account created!<br>Email: $email<br>Password: $password";
    } else {
        echo "Failed to create admin account.";
    }
}
?>
