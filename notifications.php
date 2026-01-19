<?php
/**
 * Notifications Page for Alerto360
 * Redesigned to match admin dashboard style
 */

session_start();
require 'db_connect.php';
require 'notification_functions.php';

// Check if user is logged in and is responder or admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['responder', 'admin', 'super_admin'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['notification_id']);
    markNotificationAsRead($pdo, $notification_id, $user_id);
    header('Location: notifications.php');
    exit;
}

// Handle mark all as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit;
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    header('Location: notifications.php');
    exit;
}

// Get all notifications for the user (both read and unread)
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unread_count = getNotificationCount($pdo, $user_id);
$read_count = count($notifications) - $unread_count;

// Determine back URL based on role
$back_url = 'admin_dashboard.php';
if ($user_role === 'responder') {
    $back_url = 'responder_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications - Alerto360</title>
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
        
        .nav-section {
            margin-bottom: 1.5rem;
        }
        
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
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
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
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.6rem 1.2rem;
            background: white;
            border: none;
            border-radius: 10px;
            color: var(--dark);
            font-weight: 500;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
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
        
        /* Stat Cards */
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
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.unread { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-icon.read { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.total { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
        
        .stat-info h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .stat-info p {
            color: #64748b;
            margin: 0;
            font-size: 0.875rem;
        }
        
        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .mark-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .mark-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(99, 102, 241, 0.4);
        }
        
        /* Notifications Card */
        .notifications-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Notification Item */
        .notification-item {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            gap: 1rem;
            transition: all 0.2s;
        }
        
        .notification-item:hover {
            background: #f8fafc;
        }
        
        .notification-item.unread {
            background: #fef2f2;
            border-left: 4px solid var(--danger);
        }
        
        .notification-item.read {
            opacity: 0.8;
            border-left: 4px solid var(--success);
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .notification-icon.alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #d97706;
        }
        
        .notification-icon.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #059669;
        }
        
        .notification-icon.info {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: #4f46e5;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .notification-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .notification-badge.new {
            background: var(--danger);
            color: white;
        }
        
        .notification-badge.read {
            background: var(--success);
            color: white;
        }
        
        .notification-message {
            color: var(--dark);
            font-size: 0.95rem;
            line-height: 1.5;
            white-space: pre-line;
            margin-bottom: 0.75rem;
        }
        
        .notification-time {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 0.8rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-btn.mark-read {
            background: #d1fae5;
            color: #059669;
        }
        
        .action-btn.mark-read:hover {
            background: #059669;
            color: white;
        }
        
        .action-btn.delete {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .action-btn.delete:hover {
            background: #dc2626;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-toggle {
                display: block !important;
            }
        }
        
        .mobile-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-right: 1rem;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="sidebar-brand-text">
            Alerto360
            <small>Emergency Response</small>
        </div>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">Main Menu</div>
        <a href="<?= $back_url ?>" class="nav-link">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        <a href="notifications.php" class="nav-link active">
            <i class="bi bi-bell-fill"></i>
            <span>Notifications</span>
            <?php if ($unread_count > 0): ?>
                <span class="nav-badge"><?= $unread_count ?></span>
            <?php endif; ?>
        </a>
    </div>
    
    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
    <div class="nav-section">
        <div class="nav-section-title">Management</div>
        <a href="admin/responder_accounts.php" class="nav-link">
            <i class="bi bi-shield-fill"></i>
            <span>Responders</span>
        </a>
        <a href="admin/citizen_accounts.php" class="nav-link">
            <i class="bi bi-people-fill"></i>
            <span>Citizens</span>
        </a>
    </div>
    <?php endif; ?>
    
    <div class="nav-section" style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
        <a href="logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div style="display: flex; align-items: center;">
            <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h1 class="page-title">
                    <i class="bi bi-bell-fill text-primary"></i> Notifications
                    <small>Stay updated with your alerts</small>
                </h1>
            </div>
        </div>
        <div class="user-menu">
            <a href="<?= $back_url ?>" class="back-btn">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
            </div>
        </div>
    </div>
    
    <!-- Stat Cards -->
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-icon unread">
                <i class="bi bi-envelope-exclamation-fill"></i>
            </div>
            <div class="stat-info">
                <h3><?= $unread_count ?></h3>
                <p>Unread</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon read">
                <i class="bi bi-envelope-check-fill"></i>
            </div>
            <div class="stat-info">
                <h3><?= $read_count ?></h3>
                <p>Read</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="bi bi-envelope-fill"></i>
            </div>
            <div class="stat-info">
                <h3><?= count($notifications) ?></h3>
                <p>Total</p>
            </div>
        </div>
    </div>
    
    <!-- Action Bar -->
    <?php if ($unread_count > 0): ?>
    <div class="action-bar">
        <form method="post" style="display: inline;">
            <input type="hidden" name="mark_all_read" value="1">
            <button type="submit" class="mark-all-btn">
                <i class="bi bi-check2-all"></i> Mark All as Read
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Notifications Card -->
    <div class="notifications-card">
        <div class="card-header">
            <h5 class="card-title">
                <i class="bi bi-bell text-warning"></i>
                All Notifications
            </h5>
            <span class="text-muted"><?= count($notifications) ?> notifications</span>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="bi bi-bell-slash"></i>
                <h4>No Notifications</h4>
                <p>You don't have any notifications yet. They will appear here when you receive them.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): 
                $is_unread = !$notification['is_read'];
                $icon_class = 'info';
                $icon = 'bi-info-circle-fill';
                
                // Determine icon based on message content
                $msg_lower = strtolower($notification['message']);
                if (strpos($msg_lower, 'emergency') !== false || strpos($msg_lower, 'alert') !== false || strpos($msg_lower, '🚨') !== false) {
                    $icon_class = 'alert';
                    $icon = 'bi-exclamation-triangle-fill';
                } elseif (strpos($msg_lower, 'completed') !== false || strpos($msg_lower, 'success') !== false || strpos($msg_lower, 'accepted') !== false) {
                    $icon_class = 'success';
                    $icon = 'bi-check-circle-fill';
                }
            ?>
            <div class="notification-item <?= $is_unread ? 'unread' : 'read' ?>">
                <div class="notification-icon <?= $icon_class ?>">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-header">
                        <span class="notification-badge <?= $is_unread ? 'new' : 'read' ?>">
                            <?= $is_unread ? 'NEW' : 'READ' ?>
                        </span>
                    </div>
                    <div class="notification-message">
                        <?= htmlspecialchars($notification['message']) ?>
                    </div>
                    <div class="notification-time">
                        <i class="bi bi-clock"></i>
                        <?= date('M j, Y \a\t g:i A', strtotime($notification['created_at'])) ?>
                    </div>
                </div>
                <div class="notification-actions">
                    <?php if ($is_unread): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                        <input type="hidden" name="mark_read" value="1">
                        <button type="submit" class="action-btn mark-read" title="Mark as Read">
                            <i class="bi bi-check2"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete this notification?');">
                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                        <input type="hidden" name="delete_notification" value="1">
                        <button type="submit" class="action-btn delete" title="Delete">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-refresh every 60 seconds if there are unread notifications
    <?php if ($unread_count > 0): ?>
    setTimeout(function() {
        location.reload();
    }, 60000);
    <?php endif; ?>
</script>
</body>
</html>
""