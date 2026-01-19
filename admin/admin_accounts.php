<?php
/**
 * Admin Accounts Management
 * Only accessible by super_admin
 */
session_start();
require '../db_connect.php';

// Check if user is super_admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';

// Handle admin deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin_id'])) {
    $delete_id = intval($_POST['delete_admin_id']);
    
    // Prevent deleting yourself
    if ($delete_id == $_SESSION['user_id']) {
        $message = '<div class="alert alert-danger">You cannot delete your own account!</div>';
    } else {
        $stmt = $pdo->prepare("SELECT role, name FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['role'] === 'admin') {
            try {
                // Clear related records
                try { $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$delete_id]); } catch (PDOException $e) {}
                try { $pdo->prepare("DELETE FROM user_online_status WHERE user_id = ?")->execute([$delete_id]); } catch (PDOException $e) {}
                
                // Delete admin
                $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$delete_id]);
                $message = '<div class="alert alert-success">Admin "' . htmlspecialchars($user['name']) . '" deleted successfully.</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-warning">Admin not found.</div>';
        }
    }
}

// Handle admin creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        $message = '<div class="alert alert-danger">All fields are required!</div>';
    } elseif (strlen($password) < 6) {
        $message = '<div class="alert alert-danger">Password must be at least 6 characters!</div>';
    } else {
        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $message = '<div class="alert alert-danger">Email already exists!</div>';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified) VALUES (?, ?, ?, 'admin', 1)");
            if ($stmt->execute([$name, $email, $hashed])) {
                $message = '<div class="alert alert-success">Admin "' . htmlspecialchars($name) . '" created successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to create admin.</div>';
            }
        }
    }
}

// Handle admin editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin_id'])) {
    $edit_id = intval($_POST['edit_admin_id']);
    $edit_name = trim($_POST['edit_name'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_password = $_POST['edit_password'] ?? '';
    
    if (empty($edit_name) || empty($edit_email)) {
        $message = '<div class="alert alert-danger">Name and email are required!</div>';
    } else {
        // Check if email exists for another user
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$edit_email, $edit_id]);
        if ($check->fetch()) {
            $message = '<div class="alert alert-danger">Email already exists!</div>';
        } else {
            if (!empty($edit_password)) {
                $hashed = password_hash($edit_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'admin'");
                $stmt->execute([$edit_name, $edit_email, $hashed, $edit_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'admin'");
                $stmt->execute([$edit_name, $edit_email, $edit_id]);
            }
            $message = '<div class="alert alert-success">Admin updated successfully!</div>';
        }
    }
}

// Get all admins (exclude super_admin)
$admins = $pdo->query("SELECT id, name, email, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Accounts - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 2rem;
        }
        .page-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .page-header {
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            margin-bottom: 2rem;
        }
        .card { border-radius: 16px; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
<div class="page-container">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-0"><i class="bi bi-person-gear"></i> Admin Accounts</h3>
                <small class="opacity-75">Manage administrator accounts</small>
            </div>
            <a href="super_admin_dashboard.php" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?= $message ?>

    <!-- Add Admin Form -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <i class="bi bi-person-plus"></i> Add New Admin
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="add_admin" value="1">
                <div class="col-md-4">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="Admin Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="admin@example.com">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 chars">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Admin List -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-list"></i> Admin List (<?= count($admins) ?>)
        </div>
        <div class="card-body">
            <?php if (empty($admins)): ?>
                <div class="alert alert-info mb-0">No admin accounts found. Create one above.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <?php if (isset($_GET['edit']) && $_GET['edit'] == $admin['id']): ?>
                                <form method="POST">
                                    <input type="hidden" name="edit_admin_id" value="<?= $admin['id'] ?>">
                                    <td><input type="text" name="edit_name" value="<?= htmlspecialchars($admin['name']) ?>" class="form-control form-control-sm" required></td>
                                    <td><input type="email" name="edit_email" value="<?= htmlspecialchars($admin['email']) ?>" class="form-control form-control-sm" required></td>
                                    <td>
                                        <input type="password" name="edit_password" class="form-control form-control-sm" placeholder="New password (optional)">
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check"></i></button>
                                        <a href="admin_accounts.php" class="btn btn-secondary btn-sm"><i class="bi bi-x"></i></a>
                                    </td>
                                </form>
                            <?php else: ?>
                                <td><?= htmlspecialchars($admin['name']) ?></td>
                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                <td><small><?= date('M j, Y', strtotime($admin['created_at'])) ?></small></td>
                                <td>
                                    <a href="admin_accounts.php?edit=<?= $admin['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_admin_id" value="<?= $admin['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete admin \'<?= htmlspecialchars($admin['name']) ?>\'?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
