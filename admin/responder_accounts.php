<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

// Handle delete
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role = 'responder'");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE incidents SET accepted_by = NULL WHERE accepted_by = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM user_online_status WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $pdo->commit();
            $msg = '<div class="alert alert-success">Responder deleted successfully.</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = intval($_POST['edit_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $type = $_POST['responder_type'];
    $pass = $_POST['password'] ?? '';
    
    if ($name && $email && $type) {
        $sql = "UPDATE users SET name = ?, email = ?, responder_type = ?";
        $params = [$name, $email, $type];
        if ($pass) {
            $sql .= ", password = ?";
            $params[] = password_hash($pass, PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $msg = '<div class="alert alert-success">Responder updated successfully.</div>';
    }
}

// Get responders with stats - using responder_history for accurate decline count
$responders = $pdo->query("
    SELECT u.*, 
        COALESCE(completed.count, 0) as completed,
        COALESCE(active.count, 0) as active,
        COALESCE(declined.count, 0) as declined,
        COALESCE(total.count, 0) as total
    FROM users u 
    LEFT JOIN (
        SELECT responder_id, COUNT(*) as count 
        FROM responder_history 
        WHERE action_type = 'completed' 
        GROUP BY responder_id
    ) completed ON u.id = completed.responder_id
    LEFT JOIN (
        SELECT accepted_by as responder_id, COUNT(*) as count 
        FROM incidents 
        WHERE status = 'accepted' 
        GROUP BY accepted_by
    ) active ON u.id = active.responder_id
    LEFT JOIN (
        SELECT responder_id, COUNT(*) as count 
        FROM responder_history 
        WHERE action_type = 'declined' 
        GROUP BY responder_id
    ) declined ON u.id = declined.responder_id
    LEFT JOIN (
        SELECT responder_id, COUNT(*) as count 
        FROM responder_history 
        GROUP BY responder_id
    ) total ON u.id = total.responder_id
    WHERE u.role = 'responder' 
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get totals
$total_responders = count($responders);
$total_completed = array_sum(array_column($responders, 'completed'));
$total_active = array_sum(array_column($responders, 'active'));
$total_declined = array_sum(array_column($responders, 'declined'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Responder Accounts - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .header h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-actions { display: flex; gap: 10px; }
        .btn-add {
            background: white;
            color: var(--primary);
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: var(--primary-dark);
        }
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.3); color: white; }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-icon.purple { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .stat-info h3 { font-size: 28px; font-weight: 700; margin: 0; color: var(--gray-800); }
        .stat-info p { font-size: 14px; color: var(--gray-600); margin: 0; }
        
        /* Main Card */
        .content-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h2 { font-size: 18px; font-weight: 600; margin: 0; color: var(--gray-800); }
        .search-box {
            position: relative;
        }
        .search-box input {
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            width: 250px;
            font-size: 14px;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
        }
        
        /* Responder Cards Grid */
        .responders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        .responder-card {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--gray-200);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .responder-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .responder-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }
        .responder-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: white;
        }
        .avatar-pnp { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .avatar-bfp { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .avatar-mddrmo { background: linear-gradient(135deg, #10b981, #059669); }
        .responder-info h4 { font-size: 16px; font-weight: 600; margin: 0 0 4px 0; color: var(--gray-800); }
        .responder-info p { font-size: 13px; color: var(--gray-600); margin: 0; }
        .responder-type {
            margin-left: auto;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .type-pnp { background: #dbeafe; color: #1d4ed8; }
        .type-bfp { background: #fee2e2; color: #dc2626; }
        .type-mddrmo { background: #d1fae5; color: #059669; }
        
        .responder-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        .mini-stat {
            text-align: center;
            padding: 12px 8px;
            background: white;
            border-radius: 10px;
        }
        .mini-stat .num { font-size: 20px; font-weight: 700; }
        .mini-stat .num.green { color: var(--success); }
        .mini-stat .num.blue { color: #3b82f6; }
        .mini-stat .num.gray { color: var(--gray-600); }
        .mini-stat .label { font-size: 11px; color: var(--gray-600); text-transform: uppercase; }
        
        .responder-actions {
            display: flex;
            gap: 8px;
        }
        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: opacity 0.2s;
        }
        .btn-action:hover { opacity: 0.85; }
        .btn-view { background: var(--primary); color: white; text-decoration: none; }
        .btn-edit { background: #f59e0b; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        
        /* Modal */
        .modal-content { border-radius: 16px; border: none; }
        .modal-header { border-bottom: 1px solid var(--gray-200); padding: 20px 24px; }
        .modal-body { padding: 24px; }
        .modal-footer { border-top: 1px solid var(--gray-200); padding: 16px 24px; }
        .form-label { font-weight: 500; color: var(--gray-800); margin-bottom: 6px; }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 12px 16px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-600);
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        
        @media (max-width: 768px) {
            .responders-grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; }
            .search-box input { width: 100%; }
        }
    </style>
</head>
<body>
<div class="main-container">
    <!-- Header -->
    <div class="header">
        <h1><i class="bi bi-shield-check"></i> Responder Accounts</h1>
        <div class="header-actions">
            <a href="add_responder.php" class="btn-add"><i class="bi bi-plus-lg"></i> Add Responder</a>
            <a href="admin_dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Dashboard</a>
        </div>
    </div>
    
    <?= $msg ?>
    
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <h3><?= $total_responders ?></h3>
                <p>Total Responders</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <h3><?= $total_completed ?></h3>
                <p>Completed Incidents</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-clock-history"></i></div>
            <div class="stat-info">
                <h3><?= $total_active ?></h3>
                <p>Active Incidents</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white;"><i class="bi bi-x-circle"></i></div>
            <div class="stat-info">
                <h3><?= $total_declined ?></h3>
                <p>Declined Incidents</p>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content-card">
        <div class="card-header">
            <h2><i class="bi bi-list-ul"></i> All Responders</h2>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Search responders..." onkeyup="filterResponders()">
            </div>
        </div>
        
        <div class="responders-grid" id="respondersGrid">
            <?php if (empty($responders)): ?>
                <div class="empty-state" style="grid-column: 1/-1;">
                    <i class="bi bi-inbox"></i>
                    <h4>No Responders Yet</h4>
                    <p>Add your first responder to get started.</p>
                </div>
            <?php else: ?>
                <?php foreach ($responders as $r): 
                    $type = strtolower($r['responder_type'] ?? 'mddrmo');
                    $initials = strtoupper(substr($r['name'], 0, 2));
                ?>
                <div class="responder-card" data-name="<?= strtolower($r['name']) ?>" data-email="<?= strtolower($r['email']) ?>">
                    <div class="responder-header">
                        <div class="responder-avatar avatar-<?= $type ?>"><?= $initials ?></div>
                        <div class="responder-info">
                            <h4><?= htmlspecialchars($r['name']) ?></h4>
                            <p><?= htmlspecialchars($r['email']) ?></p>
                        </div>
                        <span class="responder-type type-<?= $type ?>"><?= $r['responder_type'] ?? 'N/A' ?></span>
                    </div>
                    
                    <div class="responder-stats">
                        <div class="mini-stat">
                            <div class="num green"><?= $r['completed'] ?></div>
                            <div class="label">Completed</div>
                        </div>
                        <div class="mini-stat">
                            <div class="num blue"><?= $r['active'] ?></div>
                            <div class="label">Active</div>
                        </div>
                        <div class="mini-stat">
                            <div class="num" style="color: #ef4444;"><?= $r['declined'] ?></div>
                            <div class="label">Declined</div>
                        </div>
                    </div>
                    
                    <div class="responder-actions">
                        <a href="responder_history.php?id=<?= $r['id'] ?>" class="btn-action btn-view"><i class="bi bi-eye"></i> View</a>
                        <button class="btn-action btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)"><i class="bi bi-pencil"></i> Edit</button>
                        <form method="post" style="flex:1;display:flex;" onsubmit="return confirm('Delete this responder?')">
                            <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn-action btn-delete" style="width:100%"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="edit_id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Responder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Responder Type</label>
                        <select name="responder_type" id="editType" class="form-select" required>
                            <option value="PNP">PNP - Police</option>
                            <option value="BFP">BFP - Fire</option>
                            <option value="MDDRMO">MDDRMO - Disaster</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editName').value = data.name;
    document.getElementById('editEmail').value = data.email;
    document.getElementById('editType').value = data.responder_type || 'MDDRMO';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function filterResponders() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.responder-card').forEach(card => {
        const name = card.dataset.name;
        const email = card.dataset.email;
        card.style.display = (name.includes(search) || email.includes(search)) ? '' : 'none';
    });
}
</script>
</body>
</html>
