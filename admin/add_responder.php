<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $type = $_POST['responder_type'] ?? '';
    
    if ($name && $email && $password && $type) {
        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Email already exists.</div>';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, responder_type, email_verified) VALUES (?, ?, ?, 'responder', ?, 1)");
            if ($stmt->execute([$name, $email, $hash, $type])) {
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Responder added successfully!</div>';
            } else {
                $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Failed to add responder.</div>';
            }
        }
    } else {
        $msg = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> All fields are required.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Responder - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .form-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .form-header .icon {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 32px;
        }
        .form-header h1 { font-size: 24px; font-weight: 700; margin: 0 0 8px; }
        .form-header p { opacity: 0.9; margin: 0; font-size: 14px; }
        .form-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        .input-icon input, .input-icon select {
            padding-left: 46px;
        }
        .type-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .type-option {
            position: relative;
        }
        .type-option input {
            position: absolute;
            opacity: 0;
        }
        .type-option label {
            display: block;
            padding: 16px 10px;
            text-align: center;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .type-option label i {
            font-size: 24px;
            display: block;
            margin-bottom: 6px;
        }
        .type-option label span {
            font-size: 12px;
            font-weight: 600;
        }
        .type-option input:checked + label {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .type-option.pnp label i { color: #3b82f6; }
        .type-option.bfp label i { color: #ef4444; }
        .type-option.mddrmo label i { color: #10b981; }
        .type-option.pnp input:checked + label { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .type-option.bfp input:checked + label { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .type-option.mddrmo input:checked + label { border-color: #10b981; background: rgba(16, 185, 129, 0.1); }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-back:hover { color: #667eea; }
        .alert {
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
<div class="form-container">
    <div class="form-header">
        <div class="icon"><i class="bi bi-person-plus"></i></div>
        <h1>Add New Responder</h1>
        <p>Create a new emergency responder account</p>
    </div>
    <div class="form-body">
        <?= $msg ?>
        <form method="post">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <div class="input-icon">
                    <i class="bi bi-person"></i>
                    <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Create password" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Responder Type</label>
                <div class="type-selector">
                    <div class="type-option pnp">
                        <input type="radio" name="responder_type" value="PNP" id="typePNP" required>
                        <label for="typePNP"><i class="bi bi-shield-shaded"></i><span>PNP</span></label>
                    </div>
                    <div class="type-option bfp">
                        <input type="radio" name="responder_type" value="BFP" id="typeBFP">
                        <label for="typeBFP"><i class="bi bi-fire"></i><span>BFP</span></label>
                    </div>
                    <div class="type-option mddrmo">
                        <input type="radio" name="responder_type" value="MDDRMO" id="typeMDDRMO">
                        <label for="typeMDDRMO"><i class="bi bi-life-preserver"></i><span>MDDRMO</span></label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="bi bi-plus-circle"></i> Add Responder</button>
        </form>
        <a href="responder_accounts.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Responder Accounts</a>
    </div>
</div>
</body>
</html>
