<?php
/**
 * Citizen Reports Page - Alerto360 Admin
 * View all incidents reported by a specific citizen
 */

session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Check if user is admin or super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

$citizen_id = intval($_GET['id'] ?? 0);

if ($citizen_id <= 0) {
    header('Location: citizen_accounts.php');
    exit;
}

// Get citizen info
$citizen_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'citizen'");
$citizen_stmt->execute([$citizen_id]);
$citizen = $citizen_stmt->fetch(PDO::FETCH_ASSOC);

if (!$citizen) {
    $_SESSION['admin_error'] = 'Citizen not found.';
    header('Location: citizen_accounts.php');
    exit;
}

// Handle status filter
$status_filter = $_GET['status'] ?? 'all';

// Get incidents for this citizen
if ($status_filter === 'all') {
    $stmt = $pdo->prepare("
        SELECT incidents.*, 
               responder_users.name AS responder_name,
               responder_users.responder_type
        FROM incidents 
        LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id 
        WHERE incidents.user_id = ?
        ORDER BY incidents.created_at DESC
    ");
    $stmt->execute([$citizen_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT incidents.*, 
               responder_users.name AS responder_name,
               responder_users.responder_type
        FROM incidents 
        LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id 
        WHERE incidents.user_id = ? AND incidents.status = ?
        ORDER BY incidents.created_at DESC
    ");
    $stmt->execute([$citizen_id, $status_filter]);
}
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts
$counts = ['all' => 0, 'pending' => 0, 'accepted' => 0, 'completed' => 0, 'declined' => 0];
$count_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM incidents WHERE user_id = ? GROUP BY status");
$count_stmt->execute([$citizen_id]);
while ($row = $count_stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_key = $row['status'] ?: 'pending';
    if (in_array($status_key, ['done', 'resolved', 'accept and complete'])) {
        $counts['completed'] = ($counts['completed'] ?? 0) + $row['count'];
    } else {
        $counts[$status_key] = ($counts[$status_key] ?? 0) + $row['count'];
    }
}
$counts['all'] = array_sum($counts);

// Get notification count
$notification_count = getNotificationCount($pdo, $_SESSION['user_id']);
$online_users = $pdo->query("SELECT COUNT(*) FROM user_online_status WHERE is_online = 1 AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports by <?= htmlspecialchars($citizen['name']) ?> - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5; --secondary: #8b5cf6;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444;
            --info: #06b6d4; --dark: #1e293b; --light: #f1f5f9;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--light); min-height: 100vh; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, var(--dark) 0%, #0f172a 100%);
            padding: 1.5rem; z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1.5rem; }
        .sidebar-brand-icon { width: 45px; height: 45px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
        .sidebar-brand-text { color: white; font-size: 1.25rem; font-weight: 700; }
        .sidebar-brand-text small { display: block; font-size: 0.7rem; font-weight: 400; opacity: 0.7; }
        .nav-section { margin-bottom: 1.5rem; }
        .nav-section-title { color: rgba(255,255,255,0.4); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem; padding-left: 0.75rem; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 0.75rem 1rem; color: rgba(255,255,255,0.7); border-radius: 10px; margin-bottom: 4px; transition: all 0.2s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .nav-link i { width: 20px; text-align: center; }
        .nav-badge { margin-left: auto; background: var(--danger); color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; }
        
        .main-content { margin-left: 260px; padding: 2rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: var(--dark); }
        .page-title small { display: block; font-size: 0.875rem; font-weight: 400; color: #64748b; }
        
        .user-menu { display: flex; align-items: center; gap: 1rem; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 0.6rem 1.2rem; background: white; border: none; border-radius: 10px; color: var(--dark); font-weight: 500; text-decoration: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.2s; }
        .back-btn:hover { background: var(--primary); color: white; }
        .user-avatar { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        .citizen-card { background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 1.5rem; }
        .citizen-avatar { width: 70px; height: 70px; border-radius: 16px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 600; }
        .citizen-info h3 { margin: 0 0 0.25rem; font-size: 1.25rem; color: var(--dark); }
        .citizen-info p { margin: 0; color: #64748b; font-size: 0.9rem; }
        .citizen-stats { margin-left: auto; display: flex; gap: 2rem; }
        .citizen-stat { text-align: center; }
        .citizen-stat h4 { margin: 0; font-size: 1.5rem; color: var(--primary); }
        .citizen-stat p { margin: 0; font-size: 0.8rem; color: #64748b; }
        
        .filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-tab { padding: 0.5rem 1rem; border-radius: 20px; border: none; background: white; color: #64748b; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .filter-tab:hover, .filter-tab.active { background: var(--primary); color: white; }
        .filter-tab .count { background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
        .filter-tab.active .count { background: rgba(255,255,255,0.2); }
        
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 1.1rem; font-weight: 600; color: var(--dark); margin: 0; }
        .table { margin: 0; }
        .table thead th { background: #f8fafc; border: none; padding: 1rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; }
        .table tbody td { padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .table tbody tr:hover { background: #f8fafc; }
        
        .status-badge { padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted { background: #cffafe; color: #0891b2; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-declined { background: #fee2e2; color: #dc2626; }
        
        .type-badge { padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600; }
        .type-fire { background: #fee2e2; color: #dc2626; }
        .type-crime { background: #f3e8ff; color: #7c3aed; }
        .type-flood { background: #cffafe; color: #0891b2; }
        .type-accident { background: #fef3c7; color: #d97706; }
        .type-other { background: #f1f5f9; color: #64748b; }
        
        .incident-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; cursor: pointer; border: 2px solid #e2e8f0; }
        .incident-img:hover { border-color: var(--primary); }
        
        .action-btn { width: 32px; height: 32px; border-radius: 8px; border: none; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem; transition: all 0.2s; margin-right: 4px; text-decoration: none; }
        .action-btn.view { background: #e0e7ff; color: #4f46e5; }
        .action-btn.view:hover { background: #4f46e5; color: white; }
        .action-btn.map { background: #d1fae5; color: #059669; }
        .action-btn.map:hover { background: #059669; color: white; }
        
        .empty-state { text-align: center; padding: 4rem 2rem; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block !important; }
            .citizen-card { flex-direction: column; text-align: center; }
            .citizen-stats { margin-left: 0; }
        }
        .mobile-toggle { display: none; background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="sidebar-brand-text">Alerto360<small>Emergency Response</small></div>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main Menu</div>
        <a href="admin_dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
        <a href="incident_reports.php" class="nav-link"><i class="bi bi-exclamation-triangle-fill"></i><span>Incident Reports</span></a>
        <a href="../notifications.php" class="nav-link"><i class="bi bi-bell-fill"></i><span>Notifications</span>
            <?php if ($notification_count > 0): ?><span class="nav-badge"><?= $notification_count ?></span><?php endif; ?>
        </a>
        <a href="online_users.php" class="nav-link"><i class="bi bi-circle-fill text-success"></i><span>Online Users</span>
            <span class="nav-badge" style="background: var(--success);"><?= $online_users ?></span>
        </a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Management</div>
        <a href="responder_accounts.php" class="nav-link"><i class="bi bi-shield-fill"></i><span>Responders</span></a>
        <a href="citizen_accounts.php" class="nav-link active"><i class="bi bi-people-fill"></i><span>Citizens</span></a>
    </div>
    <div class="nav-section" style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
        <a href="../logout.php" class="nav-link" onclick="return confirm('Logout?');"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="top-bar">
        <div>
            <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="bi bi-list"></i></button>
            <h1 class="page-title">
                <i class="bi bi-file-earmark-person text-primary"></i> Citizen Reports
                <small>View all reports by this citizen</small>
            </h1>
        </div>
        <div class="user-menu">
            <a href="citizen_accounts.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back to Citizens</a>
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
        </div>
    </div>
    
    <!-- Citizen Info Card -->
    <div class="citizen-card">
        <div class="citizen-avatar"><?= strtoupper(substr($citizen['name'], 0, 1)) ?></div>
        <div class="citizen-info">
            <h3><?= htmlspecialchars($citizen['name']) ?></h3>
            <p><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($citizen['email']) ?></p>
            <p><i class="bi bi-calendar me-1"></i> Joined: <?= date('M j, Y', strtotime($citizen['created_at'])) ?></p>
        </div>
        <div class="citizen-stats">
            <div class="citizen-stat">
                <h4><?= $counts['all'] ?></h4>
                <p>Total Reports</p>
            </div>
            <div class="citizen-stat">
                <h4 style="color: var(--warning);"><?= $counts['pending'] ?? 0 ?></h4>
                <p>Pending</p>
            </div>
            <div class="citizen-stat">
                <h4 style="color: var(--success);"><?= $counts['completed'] ?? 0 ?></h4>
                <p>Completed</p>
            </div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?id=<?= $citizen_id ?>&status=all" class="filter-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
            <i class="bi bi-list-ul"></i> All <span class="count"><?= $counts['all'] ?></span>
        </a>
        <a href="?id=<?= $citizen_id ?>&status=pending" class="filter-tab <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="bi bi-clock"></i> Pending <span class="count"><?= $counts['pending'] ?? 0 ?></span>
        </a>
        <a href="?id=<?= $citizen_id ?>&status=accepted" class="filter-tab <?= $status_filter === 'accepted' ? 'active' : '' ?>">
            <i class="bi bi-person-check"></i> Accepted <span class="count"><?= $counts['accepted'] ?? 0 ?></span>
        </a>
        <a href="?id=<?= $citizen_id ?>&status=completed" class="filter-tab <?= $status_filter === 'completed' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i> Completed <span class="count"><?= $counts['completed'] ?? 0 ?></span>
        </a>
        <a href="?id=<?= $citizen_id ?>&status=declined" class="filter-tab <?= $status_filter === 'declined' ? 'active' : '' ?>">
            <i class="bi bi-x-circle"></i> Declined <span class="count"><?= $counts['declined'] ?? 0 ?></span>
        </a>
    </div>
    
    <!-- Reports Table -->
    <div class="table-card">
        <div class="table-header">
            <h5 class="table-title"><i class="bi bi-file-earmark-text me-2"></i>Incident Reports</h5>
            <span class="text-muted"><?= count($incidents) ?> reports</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Responder</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($incidents)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h4>No Reports Found</h4>
                                <p>This citizen hasn't submitted any <?= $status_filter !== 'all' ? $status_filter : '' ?> incident reports.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($incidents as $incident): 
                    $type_lower = strtolower($incident['type']);
                    $type_class = 'type-other';
                    if (strpos($type_lower, 'fire') !== false) $type_class = 'type-fire';
                    elseif (strpos($type_lower, 'crime') !== false) $type_class = 'type-crime';
                    elseif (strpos($type_lower, 'flood') !== false) $type_class = 'type-flood';
                    elseif (strpos($type_lower, 'accident') !== false) $type_class = 'type-accident';
                    
                    $status = $incident['status'] ?: 'pending';
                    $status_class = 'status-pending';
                    if ($status === 'accepted') $status_class = 'status-accepted';
                    elseif (in_array($status, ['done', 'completed', 'resolved', 'accept and complete'])) { $status_class = 'status-completed'; $status = 'completed'; }
                    elseif ($status === 'declined') $status_class = 'status-declined';
                ?>
                <tr>
                    <td><strong>#<?= $incident['id'] ?></strong></td>
                    <td><span class="type-badge <?= $type_class ?>"><?= htmlspecialchars($incident['type']) ?></span></td>
                    <td style="max-width: 200px;">
                        <span title="<?= htmlspecialchars($incident['description']) ?>">
                            <?= htmlspecialchars(substr($incident['description'], 0, 50)) ?><?= strlen($incident['description']) > 50 ? '...' : '' ?>
                        </span>
                    </td>
                    <td><span class="status-badge <?= $status_class ?>"><i class="bi bi-circle-fill" style="font-size: 6px;"></i> <?= ucfirst($status) ?></span></td>
                    <td>
                        <strong><?= date('M j', strtotime($incident['created_at'])) ?></strong><br>
                        <small class="text-muted"><?= date('g:i A', strtotime($incident['created_at'])) ?></small>
                    </td>
                    <td>
                        <?php if (!empty($incident['responder_name'])): ?>
                            <?= htmlspecialchars($incident['responder_name']) ?>
                            <?php if (!empty($incident['responder_type'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($incident['responder_type']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($incident['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($incident['image_path']) ?>" alt="Incident" class="incident-img" 
                                 onclick="showImageModal('../<?= htmlspecialchars($incident['image_path']) ?>', 'Incident #<?= $incident['id'] ?>')">
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="../view_incident_details.php?id=<?= $incident['id'] ?>" class="action-btn view" title="View Details"><i class="bi bi-eye"></i></a>
                        <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                        <a href="https://www.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" target="_blank" class="action-btn map" title="View on Map"><i class="bi bi-geo-alt"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalTitle">Incident Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" id="modalImage" style="max-width: 100%; max-height: 80vh; border-radius: 10px;">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showImageModal(src, title) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModalTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}
</script>
</body>
</html>
