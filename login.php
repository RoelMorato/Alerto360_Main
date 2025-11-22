<?php
session_start();
require 'db_connect.php';

$login_msg = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $login_msg = '<div class="alert alert-success">You have been successfully logged out.</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin/admin_dashboard.php');
                exit;
            } elseif ($user['role'] === 'responder') {
                header('Location: user/responder_dashboard.php');
                exit;
            } else {
                header('Location: user/user_dashboard.php');
                exit;
            }
        } else {
            $login_msg = '<div style="color:red;">Invalid email or password.</div>';
        }
    } else {
        $login_msg = '<div style="color:red;">All fields are required.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Alerto360</title>
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
        .login-container {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.15);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 370px;
            width: 100%;
            text-align: center;
        }
        .login-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem auto;
        }
        .login-logo svg {
            width: 38px;
            height: 38px;
            color: #fff;
        }
        .form-control:focus {
            box-shadow: 0 0 0 2px #a18cd1;
        }
        .info-text {
            font-size: 0.95rem;
            color: #666;
            margin-top: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <!-- Shield SVG icon -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5.25-3.5 9.74-7 11-3.5-1.26-7-5.75-7-11V7l7-4z"/></svg>
        </div>
        <h2 class="fw-bold mb-1">Alerto360</h2>
        <div class="mb-2" style="font-size:1.05rem; color:#555;">Emergency Reporting & Coordination System<br><span style="font-size:0.95rem;">Hagonoy, Davao del Sur</span></div>
        <?= $login_msg ?>
        <form method="post" class="mt-3 text-start">
            <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="email" class="form-control" name="email" placeholder="Enter your username or email" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                    <span class="input-group-text" style="cursor:pointer;" onclick="togglePassword()"><i class="bi bi-eye" id="eyeIcon"></i></span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-semibold" style="background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%); border: none;"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
        </form>
        <div class="mt-3">
            <span>Don't have an account? <a href="register.php" style="color:#7b7be0;">Register here</a></span>
        </div>
        <div class="info-text mt-2">
            <i class="bi bi-info-circle"></i> For emergency responders, contact your administrator for account access.
        </div>
    </div>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script>
    function togglePassword() {
        var pwd = document.querySelector('input[name="password"]');
        var eye = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            eye.classList.remove('bi-eye');
            eye.classList.add('bi-eye-slash');
        } else {
            pwd.type = 'password';
            eye.classList.remove('bi-eye-slash');
            eye.classList.add('bi-eye');
        }
    }
    </script>
</body>
</html>