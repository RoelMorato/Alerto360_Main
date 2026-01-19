<?php
session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Check if user is admin or super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

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

// Get user counts
$total_citizens = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'citizen'")->fetchColumn();
$total_responders = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'responder'")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$online_users = $pdo->query("SELECT COUNT(*) FROM user_online_status WHERE is_online = 1 AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();

// Get recent incidents (last 5)
$recent_incidents = $pdo->query("
    SELECT incidents.*, users.name AS reporter 
    FROM incidents 
    JOIN users ON incidents.user_id = users.id 
    ORDER BY incidents.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$today_incidents = $pdo->query("SELECT COUNT(*) FROM incidents WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$week_incidents = $pdo->query("SELECT COUNT(*) FROM incidents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Alerto360</title>
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
        
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
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
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.5rem;
        }
        
        .sidebar-brand-text { color: white; font-size: 1.25rem; font-weight: 700; }
        .sidebar-brand-text small { display: block; font-size: 0.7rem; font-weight: 400; opacity: 0.7; }
        
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
            display: flex; align-items: center; gap: 12px;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.7);
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .nav-link i { width: 20px; text-align: center; }
        
        .nav-badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .main-content { margin-left: 260px; padding: 2rem; }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title { font-size: 1.75rem; font-weight: 700; color: var(--dark); }
        .page-title small { display: block; font-size: 0.875rem; font-weight: 400; color: #64748b; }
        
        .user-menu { display: flex; align-items: center; gap: 1rem; }
        
        .notification-btn {
            position: relative;
            width: 42px; height: 42px;
            border-radius: 12px;
            background: white;
            border: none;
            color: #64748b;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center;
        }
        
        .notification-btn:hover { background: var(--primary); color: white; }
        
        .notification-badge {
            position: absolute;
            top: -5px; right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.65rem;
            width: 18px; height: 18px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        
        .user-avatar {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 600;
        }
        
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex; align-items: center; gap: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white;
        }
        
        .stat-icon.pending { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.accepted { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .stat-icon.completed { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.declined { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-icon.users { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-icon.online { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
        .stat-icon.reports { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
        
        .stat-info h3 { font-size: 1.75rem; font-weight: 700; color: var(--dark); margin: 0; }
        .stat-info p { color: #64748b; margin: 0; font-size: 0.875rem; }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: none;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: transparent;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title { font-size: 1.1rem; font-weight: 600; color: var(--dark); margin: 0; }
        
        .quick-action {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action:hover { background: #f8fafc; }
        
        .quick-action-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }
        
        .quick-action-text h6 { margin: 0; font-weight: 600; color: var(--dark); }
        .quick-action-text p { margin: 0; font-size: 0.8rem; color: #64748b; }
        
        .recent-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .recent-avatar {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 0.875rem;
        }
        
        .type-fire { background: #fee2e2; color: #dc2626; }
        .type-crime { background: #f3e8ff; color: #7c3aed; }
        .type-flood { background: #cffafe; color: #0891b2; }
        .type-accident { background: #fef3c7; color: #d97706; }
        .type-other { background: #f1f5f9; color: #64748b; }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted { background: #cffafe; color: #0891b2; }
        .status-completed { background: #d1fae5; color: #059669; }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block !important; }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        
        .mobile-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
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
        <a href="admin_dashboard.php" class="nav-link active">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>
        <a href="incident_reports.php" class="nav-link">
            <i class="bi bi-exclamation-triangle-fill"></i><span>Incident Reports</span>
            <?php if ($counts['pending'] > 0): ?>
                <span class="nav-badge"><?= $counts['pending'] ?></span>
            <?php endif; ?>
        </a>
        <a href="statistics_reports.php" class="nav-link">
            <i class="bi bi-graph-up"></i><span>Statistics & Reports</span>
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
                Dashboard
                <small>Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!</small>
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
    
    <?php if (isset($_SESSION['admin_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['admin_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['admin_message']); ?>
    <?php endif; ?>
    
    <!-- Stat Cards -->
    <div class="stat-cards">
        <a href="incident_reports.php?status=pending" class="stat-card">
            <div class="stat-icon pending"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-info">
                <h3><?= $counts['pending'] ?? 0 ?></h3>
                <p>Pending</p>
            </div>
        </a>
        <a href="incident_reports.php?status=accepted" class="stat-card">
            <div class="stat-icon accepted"><i class="bi bi-person-check-fill"></i></div>
            <div class="stat-info">
                <h3><?= $counts['accepted'] ?? 0 ?></h3>
                <p>In Progress</p>
            </div>
        </a>
        <a href="incident_reports.php?status=completed" class="stat-card">
            <div class="stat-icon completed"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info">
                <h3><?= $counts['completed'] ?? 0 ?></h3>
                <p>Completed</p>
            </div>
        </a>
        <a href="incident_reports.php?status=declined" class="stat-card">
            <div class="stat-icon declined"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-info">
                <h3><?= $counts['declined'] ?? 0 ?></h3>
                <p>Declined</p>
            </div>
        </a>
        <a href="citizen_accounts.php" class="stat-card">
            <div class="stat-icon users"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info">
                <h3><?= $total_citizens + $total_responders ?></h3>
                <p>Total Users</p>
            </div>
        </a>
        <a href="online_users.php" class="stat-card">
            <div class="stat-icon online"><i class="bi bi-broadcast"></i></div>
            <div class="stat-info">
                <h3><?= $online_users ?></h3>
                <p>Online Now</p>
            </div>
        </a>
    </div>
    
    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body p-0">
                <a href="incident_reports.php" class="quick-action">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="quick-action-text">
                        <h6>View All Incidents</h6>
                        <p><?= $counts['all'] ?> total reports • <?= $counts['pending'] ?> pending</p>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                </a>
                <a href="responder_accounts.php" class="quick-action">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb;">
                        <i class="bi bi-shield-fill"></i>
                    </div>
                    <div class="quick-action-text">
                        <h6>Manage Responders</h6>
                        <p><?= $total_responders ?> registered responders</p>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                </a>
                <a href="citizen_accounts.php" class="quick-action">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #4f46e5;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="quick-action-text">
                        <h6>Manage Citizens</h6>
                        <p><?= $total_citizens ?> registered citizens</p>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                </a>
                <a href="add_responder.php" class="quick-action">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #059669;">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <div class="quick-action-text">
                        <h6>Add New Responder</h6>
                        <p>Register a new emergency responder</p>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                </a>
                <a href="statistics_reports.php" class="quick-action">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); color: #7c3aed;">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="quick-action-text">
                        <h6>View Statistics & Reports</h6>
                        <p>Analytics, charts, and performance data</p>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                </a>
            </div>
        </div>
        
        <!-- Recent Incidents -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-clock-history text-info me-2"></i>Recent Incidents</h5>
                <a href="incident_reports.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_incidents)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">No recent incidents</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_incidents as $incident): 
                        $type_lower = strtolower($incident['type']);
                        $type_class = 'type-other';
                        $type_icon = 'bi-question-circle';
                        if (strpos($type_lower, 'fire') !== false) { $type_class = 'type-fire'; $type_icon = 'bi-fire'; }
                        elseif (strpos($type_lower, 'crime') !== false) { $type_class = 'type-crime'; $type_icon = 'bi-shield-exclamation'; }
                        elseif (strpos($type_lower, 'flood') !== false) { $type_class = 'type-flood'; $type_icon = 'bi-water'; }
                        elseif (strpos($type_lower, 'accident') !== false) { $type_class = 'type-accident'; $type_icon = 'bi-car-front'; }
                        
                        $status = $incident['status'] ?: 'pending';
                        $status_class = 'status-pending';
                        if ($status === 'accepted') $status_class = 'status-accepted';
                        elseif (in_array($status, ['done', 'completed', 'resolved'])) $status_class = 'status-completed';
                    ?>
                    <div class="recent-item">
                        <div class="recent-avatar <?= $type_class ?>"><i class="bi <?= $type_icon ?>"></i></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong style="font-size: 0.9rem;"><?= htmlspecialchars($incident['type']) ?></strong>
                                    <p class="mb-0 text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($incident['reporter']) ?></p>
                                </div>
                                <span class="status-badge <?= $status_class ?>"><?= ucfirst($status) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Activity Summary -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center py-4">
                    <div class="stat-icon reports mx-auto mb-3"><i class="bi bi-calendar-day"></i></div>
                    <h3 class="mb-1"><?= $today_incidents ?></h3>
                    <p class="text-muted mb-0">Today's Reports</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center py-4">
                    <div class="stat-icon accepted mx-auto mb-3"><i class="bi bi-calendar-week"></i></div>
                    <h3 class="mb-1"><?= $week_incidents ?></h3>
                    <p class="text-muted mb-0">This Week</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center py-4">
                    <div class="stat-icon users mx-auto mb-3"><i class="bi bi-person-badge"></i></div>
                    <h3 class="mb-1"><?= $total_admins ?></h3>
                    <p class="text-muted mb-0">Admin Users</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
