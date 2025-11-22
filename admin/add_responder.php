<?php
session_start();
require '../db_connect.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

// Handle responder account creation
$add_responder_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_responder'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name && $email && $password) {
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $add_responder_msg = '<div style="color:red;">Email already exists.</div>';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $responder_type = $_POST['responder_type'] ?? '';
            if (!in_array($responder_type, ['PNP', 'BFP', 'MDDRMO'])) {
                $add_responder_msg = '<div style="color:red;">Please select a valid responder type.</div>';
            } else {
                // Ensure responder_type column exists
                try { $pdo->exec("ALTER TABLE users ADD COLUMN responder_type VARCHAR(20) NULL"); } catch (Exception $e) {}
                $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, responder_type) VALUES (?, ?, ?, 'responder', ?)");
                if ($insert->execute([$name, $email, $hashed, $responder_type])) {
                    $add_responder_msg = '<div style="color:green;">Responder account created successfully.</div>';
                } else {
                    $add_responder_msg = '<div style="color:red;">Failed to create account.</div>';
                }
            }
        }
    } else {
        $add_responder_msg = '<div style="color:red;">All fields are required.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Responder Account - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        .form-container {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.15);
            padding: 2.5rem;
            max-width: 500px;
            width: 100%;
        }
        .form-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem auto;
        }
        .form-logo svg {
            width: 32px;
            height: 32px;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="form-container">
    <div class="form-logo">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5.25-3.5 9.74-7 11-3.5-1.26-7-5.75-7-11V7l7-4z"/></svg>
    </div>
    <h2 class="text-center mb-4">Add Responder Account</h2>
    <?= $add_responder_msg ?>
    <form method="post" class="row g-3">
        <input type="hidden" name="add_responder" value="1">
        <div class="col-12">
            <input type="text" class="form-control" name="name" placeholder="Name" required>
        </div>
        <div class="col-12">
            <input type="email" class="form-control" name="email" placeholder="Email" required>
        </div>
        <div class="col-12">
            <input type="password" class="form-control" name="password" placeholder="Password" required>
        </div>
        <div class="col-12">
            <select name="responder_type" class="form-select" required>
                <option value="">Select Responder Type</option>
                <option value="PNP">PNP</option>
                <option value="BFP">BFP</option>
                <option value="MDDRMO">MDDRMO</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary w-100">Add Responder</button>
        </div>
    </form>
    <div class="text-center mt-3">
        <a href="admin_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>