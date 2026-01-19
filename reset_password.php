<?php
session_start();
require 'db_connect.php';
require 'password_reset_functions.php';

$email = $_SESSION['reset_email'] ?? '';
$token = $_SESSION['reset_token'] ?? '';

if (empty($email)) {
    header('Location: forgot_password.php');
    exit;
}

$message = '';
$step = 'verify'; // verify or reset

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code']) && !isset($_POST['reset_password'])) {
    $code = trim($_POST['code'] ?? '');
    
    if (!empty($code) && strlen($code) === 6) {
        $result = verifyResetCode($email, $code);
        
        if ($result['success']) {
            $_SESSION['reset_token'] = $result['token'];
            $token = $result['token'];
            $step = 'reset';
            $message = '<div class="alert alert-success">✅ Code verified! Now set your new password.</div>';
        } else {
            $message = '<div class="alert alert-danger">❌ ' . htmlspecialchars($result['message']) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">⚠️ Please enter the reset code.</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if ($password !== $confirm) {
        $message = '<div class="alert alert-danger">Passwords do not match.</div>';
    } elseif (strlen($password) < 6) {
        $message = '<div class="alert alert-danger">Password must be at least 6 characters.</div>';
    } else {
        $result = resetPassword($token, $password);
        
        if ($result['success']) {
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_token']);
            $_SESSION['password_reset_success'] = true;
            header('Location: login.php');
            exit;
        } else {
            $message = '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Alerto360</title>
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
        .reset-container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        .reset-icon {
            width: 80px;
            height: 80px;
            background: rgba(123, 123, 224, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .reset-icon i {
            font-size: 40px;
            color: #7b7be0;
        }
        .code-input {
            font-size: 24px;
            text-align: center;
            letter-spacing: 10px;
            font-weight: bold;
        }
        .btn-reset {
            background: #7b7be0;
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-reset:hover {
            background: #6a6ad0;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($step === 'verify'): ?>
            <div class="reset-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            
            <h2 class="text-center mb-2">Enter Reset Code</h2>
            <p class="text-center text-muted mb-4">
                We sent a 6-digit code to<br>
                <strong><?= htmlspecialchars($email) ?></strong>
            </p>
            
            <?= $message ?>
            
            <form method="post">
                <div class="mb-3">
                    <input 
                        type="text" 
                        name="code" 
                        class="form-control code-input" 
                        placeholder="000000" 
                        maxlength="6" 
                        pattern="[0-9]{6}"
                        required
                        autofocus
                    >
                </div>
                <button type="submit" name="verify_code" class="btn btn-reset btn-primary w-100">
                    <i class="fas fa-check-circle"></i> Verify Code
                </button>
            </form>
        <?php else: ?>
            <div class="reset-icon">
                <i class="fas fa-lock"></i>
            </div>
            
            <h2 class="text-center mb-2">Set New Password</h2>
            <p class="text-center text-muted mb-4">
                Enter your new password below
            </p>
            
            <?= $message ?>
            
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Enter new password"
                        minlength="6"
                        required
                        autofocus
                    >
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        class="form-control" 
                        placeholder="Confirm new password"
                        minlength="6"
                        required
                    >
                </div>
                <button type="submit" name="reset_password" class="btn btn-reset btn-primary w-100">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>
        
        <div class="text-center mt-3">
            <a href="login.php" class="text-muted text-decoration-none">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
    
    <script>
        // Auto-submit when 6 digits entered
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            const submitBtn = codeInput.form.querySelector('button[type="submit"]');
            
            codeInput.addEventListener('input', function(e) {
                // Only allow numbers
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                
                if (e.target.value.length === 6) {
                    // Show loading
                    if (submitBtn) {
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
                        submitBtn.disabled = true;
                    }
                    
                    // Submit form
                    setTimeout(() => {
                        e.target.form.submit();
                    }, 300);
                }
            });
        }
    </script>
</body>
</html>
