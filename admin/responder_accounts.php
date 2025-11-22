<?php
session_start();
require '../db_connect.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

// Handle responder account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_responder_id'])) {
    $delete_id = intval($_POST['delete_responder_id']);
    // Prevent admin from deleting themselves or deleting non-responders
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$delete_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && $user['role'] === 'responder') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delete_id]);
        header("Location: responder_accounts.php");
        exit;
    }
}

// Handle responder editing
$edit_responder_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_responder_id'])) {
    $edit_id = intval($_POST['edit_responder_id']);
    $edit_name = trim($_POST['edit_name'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_responder_type = $_POST['edit_responder_type'] ?? '';
    $edit_password = $_POST['edit_password'] ?? '';

    if ($edit_name && $edit_email && $edit_responder_type) {
        // Check if email already exists for another user
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$edit_email, $edit_id]);
        if ($check->fetch()) {
            $edit_responder_msg = '<div style="color:red;">Email already exists.</div>';
        } else {
            if (!in_array($edit_responder_type, ['PNP', 'BFP', 'MDDRMO'])) {
                $edit_responder_msg = '<div style="color:red;">Please select a valid responder type.</div>';
            } else {
                $update_fields = "name = ?, email = ?, responder_type = ?";
                $update_values = [$edit_name, $edit_email, $edit_responder_type];

                if (!empty($edit_password)) {
                    $hashed = password_hash($edit_password, PASSWORD_DEFAULT);
                    $update_fields .= ", password = ?";
                    $update_values[] = $hashed;
                }

                $update_stmt = $pdo->prepare("UPDATE users SET $update_fields WHERE id = ?");
                $update_values[] = $edit_id;

                if ($update_stmt->execute($update_values)) {
                    $edit_responder_msg = '<div style="color:green;">Responder account updated successfully.</div>';
                } else {
                    $edit_responder_msg = '<div style="color:red;">Failed to update account.</div>';
                }
            }
        }
    } else {
        $edit_responder_msg = '<div style="color:red;">All fields are required.</div>';
    }
}

// Get responders
$responders = $pdo->query("SELECT id, name, email, responder_type, created_at FROM users WHERE role = 'responder' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Responder Accounts - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 0;
        }
        .accounts-container {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.15);
            padding: 2.5rem;
            max-width: 1200px;
            width: 100%;
        }
        .accounts-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem auto;
        }
        .accounts-logo svg {
            width: 32px;
            height: 32px;
            color: #fff;
        }
        .section-title {
            font-weight: 600;
            color: #7b7be0;
            margin-top: 2rem;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 4px 16px rgba(44, 62, 80, 0.08);
        }
        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="accounts-container">
    <div class="accounts-logo">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5.25-3.5 9.74-7 11-3.5-1.26-7-5.75-7-11V7l7-4z"/></svg>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Responder Accounts</h2>
        <div>
            <a href="add_responder.php" class="btn btn-primary btn-sm">Add Responder</a>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        </div>
    </div>

    <?php if (!empty($edit_responder_msg)) echo $edit_responder_msg; ?>

    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($responders as $responder): ?>
                <tr>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit'] == $responder['id']): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="edit_responder_id" value="<?= $responder['id'] ?>">
                                <input type="text" name="edit_name" value="<?= htmlspecialchars($responder['name']) ?>" class="form-control form-control-sm" required>
                        <?php else: ?>
                            <?= htmlspecialchars($responder['name']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit'] == $responder['id']): ?>
                                <input type="email" name="edit_email" value="<?= htmlspecialchars($responder['email']) ?>" class="form-control form-control-sm" required>
                        <?php else: ?>
                            <?= htmlspecialchars($responder['email']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit'] == $responder['id']): ?>
                            <select name="edit_responder_type" class="form-select form-select-sm" required>
                                <option value="">Select Responder Type</option>
                                <option value="PNP" <?= (isset($responder['responder_type']) && $responder['responder_type'] == 'PNP') ? 'selected' : '' ?>>PNP</option>
                                <option value="BFP" <?= (isset($responder['responder_type']) && $responder['responder_type'] == 'BFP') ? 'selected' : '' ?>>BFP</option>
                                <option value="MDDRMO" <?= (isset($responder['responder_type']) && $responder['responder_type'] == 'MDDRMO') ? 'selected' : '' ?>>MDDRMO</option>
                            </select>
                        <?php else: ?>
                            <?= htmlspecialchars($responder['responder_type'] ?? '') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($responder['created_at']) ?></td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit'] == $responder['id']): ?>
                                <input type="password" name="edit_password" class="form-control form-control-sm" placeholder="New password (leave blank to keep current)">
                            <button type="submit" class="btn btn-success btn-sm">Save</button>
                            <a href="responder_accounts.php" class="btn btn-secondary btn-sm">Cancel</a>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="delete_responder_id" value="<?= $responder['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this responder?')">Delete</button>
                            </form>
                            <a href="responder_accounts.php?edit=<?= $responder['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>