<?php
session_start();
require 'db_connect.php';
require 'password_reset_functions.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (!empty($email)) {
        $result = createPasswordResetCode($email);
        
        if ($result['success']) {
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_token'] = $result['token'];
            header('Location: reset_password.php');
            exit;
        } else {
            $message = '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Please enter your email address.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .forgot-container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        .forgot-icon {
            width: 80px;
            height: 80px;
            background: rgba(123, 123, 224, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .forgot-icon i {
            font-size: 40px;
            color: #7b7be0;
        }
        .btn-send {
            background: #7b7be0;
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-send:hover {
            background: #6a6ad0;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-icon">
            <i class="fas fa-key"></i>
        </div>
        
        <h2 class="text-center mb-2">Forgot Password?</h2>
        <p class="text-center text-muted mb-4">
            Enter your email and we'll send you a code to reset your password.
        </p>
        
        <?= $message ?>
        
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input 
                    type="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="Enter your email"
                    required
                    autofocus
                >
            </div>
            <button type="submit" name="send_code" class="btn btn-send btn-primary w-100">
                <i class="fas fa-paper-plane"></i> Send Reset Code
            </button>
        </form>
        
        <div class="text-center mt-3">
            <a href="login.php" class="text-muted text-decoration-none">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</body>
</html>
