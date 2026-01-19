<?php
/**
 * Online Users Page - Alerto360 Admin
 * Separate view for Citizens and Responders
 */
session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Check if admin or super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit;
}

// Get notification count
$notification_count = getNotificationCount($pdo, $_SESSION['user_id']);

// Get online responders
$responders = $pdo->query("
    SELECT u.id, u.name, u.email, u.responder_type,
           COALESCE(s.is_online, 0) as is_online,
           COALESCE(s.on_duty, 0) as on_duty,
           s.last_seen, s.device_info,
           CASE 
               WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 'Online'
               WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 30 THEN 'Away'
               WHEN s.last_seen IS NULL THEN 'Never'
               ELSE 'Offline'
           END as status
    FROM users u
    LEFT JOIN user_online_status s ON u.id = s.user_id
    WHERE u.role = 'responder'
    ORDER BY s.on_duty DESC, s.is_online DESC, s.last_seen DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get online citizens
$citizens = $pdo->query("
    SELECT u.id, u.name, u.email,
           COALESCE(s.is_online, 0) as is_online,
           s.last_seen, s.device_info,
           CASE 
               WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 'Online'
               WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 30 THEN 'Away'
               WHEN s.last_seen IS NULL THEN 'Never'
               ELSE 'Offline'
           END as status
    FROM users u
    LEFT JOIN user_online_status s ON u.id = s.user_id
    WHERE u.role = 'citizen'
    ORDER BY s.is_online DESC, s.last_seen DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$resp_online = $resp_away = $resp_offline = $resp_duty = 0;
$cit_online = $cit_away = $cit_offline = 0;

foreach ($responders as $r) {
    if ($r['status'] === 'Online') $resp_online++;
    elseif ($r['status'] === 'Away') $resp_away++;
    else $resp_offline++;
    if ($r['on_duty']) $resp_duty++;
}
foreach ($citizens as $c) {
    if ($c['status'] === 'Online') $cit_online++;
    elseif ($c['status'] === 'Away') $cit_away++;
    else $cit_offline++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Users - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1; --secondary: #8b5cf6; --success: #10b981;
            --warning: #f59e0b; --danger: #ef4444; --info: #06b6d4;
            --dark: #1e293b; --light: #f1f5f9;
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
        .user-avatar { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .refresh-btn { display: inline-flex; align-items: center; gap: 8px; padding: 0.6rem 1.2rem; background: white; border: none; border-radius: 10px; color: var(--dark); font-weight: 500; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.2s; cursor: pointer; }
        .refresh-btn:hover { background: var(--primary); color: white; }
        
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: white; }
        .stat-icon.online { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
        .stat-icon.away { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.offline { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-icon.duty { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .stat-icon.total { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-info h3 { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin: 0; }
        .stat-info p { color: #64748b; margin: 0; font-size: 0.8rem; }

        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e2e8f0; }
        .section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.1rem; }
        .section-icon.responder { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .section-icon.citizen { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .section-title { font-size: 1.1rem; font-weight: 600; color: var(--dark); margin: 0; }
        .section-count { margin-left: auto; background: #f1f5f9; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; color: #64748b; }
        
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .user-card { background: white; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; }
        .user-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .user-card-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .user-avatar-card { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.25rem; position: relative; }
        .user-avatar-card.responder { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .user-avatar-card.citizen { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .status-indicator { position: absolute; bottom: -2px; right: -2px; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; }
        .status-indicator.online { background: #22c55e; }
        .status-indicator.away { background: #f59e0b; }
        .status-indicator.offline { background: #ef4444; }
        .status-indicator.never { background: #94a3b8; }
        .user-info h4 { margin: 0 0 0.25rem; font-size: 1rem; font-weight: 600; color: var(--dark); }
        .user-info p { margin: 0; font-size: 0.8rem; color: #64748b; }
        .user-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .meta-badge { display: inline-flex; align-items: center; gap: 4px; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 500; }
        .meta-badge.status-online { background: #d1fae5; color: #059669; }
        .meta-badge.status-away { background: #fef3c7; color: #d97706; }
        .meta-badge.status-offline { background: #fee2e2; color: #dc2626; }
        .meta-badge.status-never { background: #f1f5f9; color: #64748b; }
        .meta-badge.on-duty { background: #cffafe; color: #0891b2; }
        .meta-badge.off-duty { background: #f1f5f9; color: #64748b; }
        .meta-badge.type { background: #f3e8ff; color: #7c3aed; }
        .user-details { font-size: 0.8rem; color: #64748b; }
        .user-details i { width: 16px; margin-right: 4px; }
        .duty-toggle { width: 100%; margin-top: 0.75rem; padding: 0.5rem; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .duty-toggle.on { background: #d1fae5; color: #059669; }
        .duty-toggle.off { background: #f1f5f9; color: #64748b; }
        .duty-toggle:hover { opacity: 0.8; }
        
        .empty-state { text-align: center; padding: 3rem; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block !important; }
            .user-grid { grid-template-columns: 1fr; }
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
        <a href="online_users.php" class="nav-link active"><i class="bi bi-broadcast"></i><span>Online Users</span>
            <span class="nav-badge" style="background: var(--success);"><?= $resp_online + $cit_online ?></span>
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
                <i class="bi bi-broadcast text-success"></i> Online Users
                <small>Monitor active users in real-time</small>
            </h1>
        </div>
        <div class="user-menu">
            <button class="refresh-btn" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
        </div>
    </div>

    <!-- Responders Section -->
    <div class="section-header">
        <div class="section-icon responder"><i class="bi bi-shield-fill"></i></div>
        <h2 class="section-title">Emergency Responders</h2>
        <span class="section-count"><?= count($responders) ?> total</span>
    </div>
    
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-icon online"><i class="bi bi-circle-fill"></i></div>
            <div class="stat-info"><h3><?= $resp_online ?></h3><p>Online</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon away"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-info"><h3><?= $resp_away ?></h3><p>Away</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon offline"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-info"><h3><?= $resp_offline ?></h3><p>Offline</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon duty"><i class="bi bi-shield-check"></i></div>
            <div class="stat-info"><h3><?= $resp_duty ?></h3><p>On Duty</p></div>
        </div>
    </div>
    
    <div class="user-grid">
        <?php if (empty($responders)): ?>
            <div class="empty-state"><i class="bi bi-shield-x"></i><p>No responders registered</p></div>
        <?php else: ?>
        <?php foreach ($responders as $user): 
            $statusClass = $user['status'] === 'Online' ? 'online' : ($user['status'] === 'Away' ? 'away' : ($user['status'] === 'Never' ? 'never' : 'offline'));
        ?>
        <div class="user-card">
            <div class="user-card-header">
                <div class="user-avatar-card responder">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    <span class="status-indicator <?= $statusClass ?>"></span>
                </div>
                <div class="user-info">
                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
            <div class="user-meta">
                <span class="meta-badge status-<?= $statusClass ?>"><i class="bi bi-circle-fill" style="font-size: 6px;"></i> <?= $user['status'] ?></span>
                <span class="meta-badge <?= $user['on_duty'] ? 'on-duty' : 'off-duty' ?>"><i class="bi bi-shield-<?= $user['on_duty'] ? 'check' : 'x' ?>"></i> <?= $user['on_duty'] ? 'On Duty' : 'Off Duty' ?></span>
                <?php if (!empty($user['responder_type'])): ?>
                <span class="meta-badge type"><i class="bi bi-badge-tm"></i> <?= htmlspecialchars($user['responder_type']) ?></span>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <p><i class="bi bi-clock"></i> Last seen: <?= $user['last_seen'] ? date('M j, g:i A', strtotime($user['last_seen'])) : 'Never' ?></p>
                <?php if (!empty($user['device_info'])): ?>
                <p><i class="bi bi-phone"></i> <?= htmlspecialchars(substr($user['device_info'], 0, 40)) ?></p>
                <?php endif; ?>
            </div>
            <button class="duty-toggle <?= $user['on_duty'] ? 'on' : 'off' ?>" onclick="toggleDuty(<?= $user['id'] ?>, <?= $user['on_duty'] ? 0 : 1 ?>)">
                <i class="bi bi-shield-<?= $user['on_duty'] ? 'x' : 'check' ?>"></i>
                <?= $user['on_duty'] ? 'Set Off Duty' : 'Set On Duty' ?>
            </button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Citizens Section -->
    <div class="section-header">
        <div class="section-icon citizen"><i class="bi bi-people-fill"></i></div>
        <h2 class="section-title">Citizens</h2>
        <span class="section-count"><?= count($citizens) ?> total</span>
    </div>
    
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-icon online"><i class="bi bi-circle-fill"></i></div>
            <div class="stat-info"><h3><?= $cit_online ?></h3><p>Online</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon away"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-info"><h3><?= $cit_away ?></h3><p>Away</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon offline"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-info"><h3><?= $cit_offline ?></h3><p>Offline</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon total"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info"><h3><?= count($citizens) ?></h3><p>Total</p></div>
        </div>
    </div>
    
    <div class="user-grid">
        <?php if (empty($citizens)): ?>
            <div class="empty-state"><i class="bi bi-people"></i><p>No citizens registered</p></div>
        <?php else: ?>
        <?php foreach ($citizens as $user): 
            $statusClass = $user['status'] === 'Online' ? 'online' : ($user['status'] === 'Away' ? 'away' : ($user['status'] === 'Never' ? 'never' : 'offline'));
        ?>
        <div class="user-card">
            <div class="user-card-header">
                <div class="user-avatar-card citizen">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    <span class="status-indicator <?= $statusClass ?>"></span>
                </div>
                <div class="user-info">
                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
            <div class="user-meta">
                <span class="meta-badge status-<?= $statusClass ?>"><i class="bi bi-circle-fill" style="font-size: 6px;"></i> <?= $user['status'] ?></span>
            </div>
            <div class="user-details">
                <p><i class="bi bi-clock"></i> Last seen: <?= $user['last_seen'] ? date('M j, g:i A', strtotime($user['last_seen'])) : 'Never' ?></p>
                <?php if (!empty($user['device_info'])): ?>
                <p><i class="bi bi-phone"></i> <?= htmlspecialchars(substr($user['device_info'], 0, 40)) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh every 30 seconds
setTimeout(() => location.reload(), 30000);

function toggleDuty(userId, onDuty) {
    if (confirm('Change duty status for this responder?')) {
        fetch('../api_toggle_duty.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${userId}&on_duty=${onDuty}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Error: ' + data.message);
        })
        .catch(() => alert('Error updating duty status'));
    }
}
</script>
</body>
</html>