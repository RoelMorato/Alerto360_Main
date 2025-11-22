<?php
session_start();
require 'db_connect.php';

$register_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($name && $email && $password && $confirm) {
        if ($password !== $confirm) {
            $register_msg = '<div style="color:red;">Passwords do not match.</div>';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $register_msg = '<div style="color:red;">Email already exists.</div>';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'citizen')");
                if ($insert->execute([$name, $email, $hashed])) {
                    $register_msg = '<div style="color:green;">Registration successful! You can now <a href=\'login.php\'>login</a>.</div>';
                } else {
                    $register_msg = '<div style="color:red;">Registration failed. Please try again.</div>';
                }
            }
        }
    } else {
        $register_msg = '<div style="color:red;">All fields are required.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-container {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.15);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .register-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem auto;
        }
        .register-logo svg {
            width: 38px;
            height: 38px;
            color: #fff;
        }
        .form-control:focus {
            box-shadow: 0 0 0 2px #a18cd1;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-logo">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5.25-3.5 9.74-7 11-3.5-1.26-7-5.75-7-11V7l7-4z"/></svg>
        </div>
        <h2 class="fw-bold mb-1">Register</h2>
        <div class="mb-2" style="font-size:1.05rem; color:#555;">Create your Alerto360 account</div>
        <?= $register_msg ?>
        <form method="post" class="mt-3 text-start">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="name" placeholder="Enter your full name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm your password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-semibold" style="background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%); border: none;">Register</button>
        </form>
        <div class="mt-3">
            <span>Already have an account? <a href="login.php" style="color:#7b7be0;">Login here</a></span>
        </div>
    </div>
</body>
</html>
