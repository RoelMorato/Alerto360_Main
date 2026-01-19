<?php
/**
 * Incident Reports Page - Alerto360
 * Separate page for viewing all incident reports
 */

session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Check if user is admin or super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

// Handle status filter
$status_filter = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'pending', 'accepted', 'done', 'resolved', 'accept and complete', 'completed', 'declined'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Build query based on filter
if ($status_filter === 'all') {
    $stmt = $pdo->query("
        SELECT incidents.*, 
               users.name AS reporter, 
               responder_users.name AS responder_name,
               COALESCE(declined_users.name, '') AS declined_by_name
        FROM incidents 
        JOIN users ON incidents.user_id = users.id 
        LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id 
        LEFT JOIN users AS declined_users ON incidents.declined_by = declined_users.id
        ORDER BY incidents.created_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT incidents.*, 
               users.name AS reporter, 
               responder_users.name AS responder_name,
               COALESCE(declined_users.name, '') AS declined_by_name
        FROM incidents 
        JOIN users ON incidents.user_id = users.id 
        LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id 
        LEFT JOIN users AS declined_users ON incidents.declined_by = declined_users.id
        WHERE incidents.status = ? 
        ORDER BY incidents.created_at DESC
    ");
    $stmt->execute([$status_filter]);
}
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notification count
$notification_count = getNotificationCount($pdo, $_SESSION['user_id']);

// Get incident counts for each status
$counts = ['all' => 0, 'pending' => 0, 'accepted' => 0, 'completed' => 0, 'declined' => 0];
$count_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM incidents GROUP BY status");
while ($row = $count_stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_key = $row['status'] ?: 'pending';
    if ($status_key === 'done' || $status_key === 'resolved' || $status_key === 'accept and complete') {
        $counts['completed'] = ($counts['completed'] ?? 0) + $row['count'];
    } else {
        $counts[$status_key] = $row['count'];
    }
}
$counts['all'] = array_sum($counts);

// Get online users count
$online_users = $pdo->query("SELECT COUNT(*) FROM user_online_status WHERE is_online = 1 AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incident Reports - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1e293b;
            --light: #f1f5f9;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--light);
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, var(--dark) 0%, #0f172a 100%);
            padding: 1.5rem;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1.5rem;
        }
        
        .sidebar-brand-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .sidebar-brand-text {
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .sidebar-brand-text small {
            display: block;
            font-size: 0.7rem;
            font-weight: 400;
            opacity: 0.7;
        }
        
        .nav-section { margin-bottom: 1.5rem; }
        
        .nav-section-title {
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.75rem;
            padding-left: 0.75rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.7);
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        
        .nav-link i { width: 20px; text-align: center; }
        
        .nav-badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 2rem;
        }
        
        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .page-title small {
            display: block;
            font-size: 0.875rem;
            font-weight: 400;
            color: #64748b;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .notification-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: white;
            border: none;
            color: #64748b;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.65rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: none;
            background: white;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-tab:hover, .filter-tab.active {
            background: var(--primary);
            color: white;
        }
        
        .filter-tab .count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        
        .filter-tab.active .count {
            background: rgba(255,255,255,0.2);
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .table { margin: 0; }
        
        .table thead th {
            background: #f8fafc;
            border: none;
            padding: 1rem 1.25rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
        }
        
        .table tbody td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .table tbody tr:hover { background: #f8fafc; }
        
        /* Status Badges */
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted { background: #cffafe; color: #0891b2; }
        .status-completed, .status-done { background: #d1fae5; color: #059669; }
        .status-declined { background: #fee2e2; color: #dc2626; }
        
        /* Incident Image */
        .incident-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid #e2e8f0;
        }
        
        .incident-img:hover {
            transform: scale(1.1);
            border-color: var(--primary);
        }
        
        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            transition: all 0.2s;
            margin-right: 4px;
        }
        
        .action-btn.view { background: #e0e7ff; color: #4f46e5; }
        .action-btn.view:hover { background: #4f46e5; color: white; }
        .action-btn.assign { background: #dbeafe; color: #2563eb; }
        .action-btn.assign:hover { background: #2563eb; color: white; }
        
        /* Reporter Info */
        .reporter-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reporter-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .reporter-name { font-weight: 500; color: var(--dark); }
        
        /* Type Badge */
        .type-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .type-fire { background: #fee2e2; color: #dc2626; }
        .type-crime { background: #f3e8ff; color: #7c3aed; }
        .type-flood { background: #cffafe; color: #0891b2; }
        .type-accident { background: #fef3c7; color: #d97706; }
        .type-landslide { background: #fce7f3; color: #be185d; }
        .type-other { background: #f1f5f9; color: #64748b; }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block !important; }
        }
        
        .mobile-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        /* Image Modal */
        .modal-img { max-width: 100%; max-height: 80vh; }
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
        <a href="admin_dashboard.php" class="nav-link">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>
        <a href="incident_reports.php" class="nav-link active">
            <i class="bi bi-exclamation-triangle-fill"></i><span>Incident Reports</span>
            <?php if ($counts['pending'] > 0): ?>
                <span class="nav-badge"><?= $counts['pending'] ?></span>
            <?php endif; ?>
        </a>
        <a href="../notifications.php" class="nav-link">
            <i class="bi bi-bell-fill"></i><span>Notifications</span>
            <?php if ($notification_count > 0): ?>
                <span class="nav-badge"><?= $notification_count ?></span>
            <?php endif; ?>
        </a>
        <a href="online_users.php" class="nav-link">
            <i class="bi bi-circle-fill text-success"></i><span>Online Users</span>
            <span class="nav-badge" style="background: var(--success);"><?= $online_users ?></span>
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">Management</div>
        <a href="add_responder.php" class="nav-link"><i class="bi bi-person-plus-fill"></i><span>Add Responder</span></a>
        <a href="responder_accounts.php" class="nav-link"><i class="bi bi-shield-fill"></i><span>Responders</span></a>
        <a href="citizen_accounts.php" class="nav-link"><i class="bi bi-people-fill"></i><span>Citizens</span></a>
    </div>
    
    <?php if ($_SESSION['role'] === 'super_admin'): ?>
    <div class="nav-section">
        <div class="nav-section-title">Super Admin</div>
        <a href="super_admin_dashboard.php" class="nav-link"><i class="bi bi-shield-lock-fill"></i><span>Super Admin Panel</span></a>
        <a href="admin_accounts.php" class="nav-link"><i class="bi bi-person-gear"></i><span>Admin Accounts</span></a>
    </div>
    <?php endif; ?>
    
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
                <i class="bi bi-exclamation-triangle-fill text-warning"></i> Incident Reports
                <small>View and manage all incident reports</small>
            </h1>
        </div>
        <div class="user-menu">
            <a href="../notifications.php" class="notification-btn">
                <i class="bi bi-bell"></i>
                <?php if ($notification_count > 0): ?><span class="notification-badge"><?= $notification_count ?></span><?php endif; ?>
            </a>
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?status=all" class="filter-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
            <i class="bi bi-list-ul"></i> All <span class="count"><?= $counts['all'] ?></span>
        </a>
        <a href="?status=pending" class="filter-tab <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="bi bi-clock"></i> Pending <span class="count"><?= $counts['pending'] ?? 0 ?></span>
        </a>
        <a href="?status=accepted" class="filter-tab <?= $status_filter === 'accepted' ? 'active' : '' ?>">
            <i class="bi bi-person-check"></i> Accepted <span class="count"><?= $counts['accepted'] ?? 0 ?></span>
        </a>
        <a href="?status=completed" class="filter-tab <?= $status_filter === 'completed' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i> Completed <span class="count"><?= $counts['completed'] ?? 0 ?></span>
        </a>
        <a href="?status=declined" class="filter-tab <?= $status_filter === 'declined' ? 'active' : '' ?>">
            <i class="bi bi-x-circle"></i> Declined <span class="count"><?= $counts['declined'] ?? 0 ?></span>
        </a>
    </div>
    
    <!-- Incidents Table -->
    <div class="table-card">
        <div class="table-header">
            <h5 class="table-title"><i class="bi bi-file-earmark-text me-2"></i>All Incidents</h5>
            <span class="text-muted"><?= count($incidents) ?> records</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reporter</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($incidents)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No incidents found</p>
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($incidents as $incident): 
                    $status = $incident['status'] ?: 'pending';
                    $type_lower = strtolower($incident['type']);
                    $type_class = 'type-other';
                    if (strpos($type_lower, 'fire') !== false) $type_class = 'type-fire';
                    elseif (strpos($type_lower, 'crime') !== false) $type_class = 'type-crime';
                    elseif (strpos($type_lower, 'flood') !== false) $type_class = 'type-flood';
                    elseif (strpos($type_lower, 'accident') !== false) $type_class = 'type-accident';
                    elseif (strpos($type_lower, 'landslide') !== false) $type_class = 'type-landslide';
                    
                    $status_class = 'status-pending';
                    if ($status === 'accepted') $status_class = 'status-accepted';
                    elseif (in_array($status, ['done', 'completed', 'resolved', 'accept and complete'])) $status_class = 'status-completed';
                    elseif ($status === 'declined') $status_class = 'status-declined';
                ?>
                <tr>
                    <td><strong>#<?= $incident['id'] ?></strong></td>
                    <td>
                        <div class="reporter-info">
                            <div class="reporter-avatar"><?= strtoupper(substr($incident['reporter'], 0, 1)) ?></div>
                            <span class="reporter-name"><?= htmlspecialchars($incident['reporter']) ?></span>
                        </div>
                    </td>
                    <td><span class="type-badge <?= $type_class ?>"><?= htmlspecialchars($incident['type']) ?></span></td>
                    <td style="max-width: 200px;">
                        <span class="text-truncate d-inline-block" style="max-width: 180px;" title="<?= htmlspecialchars($incident['description']) ?>">
                            <?= htmlspecialchars(substr($incident['description'], 0, 50)) ?><?= strlen($incident['description']) > 50 ? '...' : '' ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?= $status_class ?>">
                            <i class="bi bi-circle-fill" style="font-size: 6px;"></i>
                            <?= ucfirst($status) ?>
                        </span>
                        <?php if ($status === 'accepted' && !empty($incident['responder_name'])): ?>
                            <br><small class="text-muted">by <?= htmlspecialchars($incident['responder_name']) ?></small>
                        <?php elseif ($status === 'declined' && !empty($incident['declined_by_name'])): ?>
                            <br><small class="text-muted">declined by <?= htmlspecialchars($incident['declined_by_name']) ?></small>
                            <?php if (!empty($incident['decline_reason'])): ?>
                                <br><small class="text-danger" title="<?= htmlspecialchars($incident['decline_reason']) ?>">
                                    Reason: <?= htmlspecialchars(substr($incident['decline_reason'], 0, 30)) ?><?= strlen($incident['decline_reason']) > 30 ? '...' : '' ?>
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= date('M j', strtotime($incident['created_at'])) ?></strong><br>
                        <small class="text-muted"><?= date('g:i A', strtotime($incident['created_at'])) ?></small>
                    </td>
                    <td>
                        <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                            <a href="https://www.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-geo-alt"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($incident['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($incident['image_path']) ?>" alt="Incident" class="incident-img" 
                                 onclick="showImageModal('../<?= htmlspecialchars($incident['image_path']) ?>', 'Incident #<?= $incident['id'] ?>')">
                        <?php else: ?>
                            <span class="text-muted">No image</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="../view_incident_details.php?id=<?= $incident['id'] ?>" class="action-btn view" title="View Details">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if ($status === 'pending'): ?>
                        <a href="assign_incident.php?id=<?= $incident['id'] ?>" class="action-btn assign" title="Assign Responder">
                            <i class="bi bi-person-plus"></i>
                        </a>
                        <?php elseif ($status === 'declined'): ?>
                        <a href="assign_incident.php?id=<?= $incident['id'] ?>&reassign=1" class="action-btn assign" title="Assign New Responder" style="background: #10b981; color: white;">
                            <i class="bi bi-person-plus"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($incident['declined_by'])): ?>
                        <button class="action-btn" style="background: #f59e0b; color: white;" title="Reassign Incident" onclick="reassignIncident(<?= $incident['id'] ?>)">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
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
                <img src="" id="modalImage" class="modal-img">
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

function reassignIncident(incidentId) {
    if (!confirm('Reassign this declined incident? It will be made available for responders again.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('incident_id', incidentId);
    formData.append('admin_id', <?= $_SESSION['user_id'] ?>);
    
    fetch('../api_reassign_incident.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Incident reassigned successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}
</script>
</body>
</html>
