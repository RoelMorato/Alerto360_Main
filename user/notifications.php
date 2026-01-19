<?php
/**
 * Notifications Page for Responders
 */

session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Check if user is logged in and is responder
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'responder') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Get all notifications for the user
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unread_count = getNotificationCount($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: #E8E4F3;
            padding: 20px;
        }
        .main-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header-logo {
            width: 70px;
            height: 70px;
            background: #7B7BE0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .header-logo i { color: white; font-size: 32px; }
        .header-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        .stat-card h3 { margin: 0; font-size: 28px; }
        .stat-card p { margin: 5px 0 0; font-size: 12px; color: #666; }
        .stat-unread h3 { color: #dc3545; }
        .stat-read h3 { color: #28a745; }
        .stat-total h3 { color: #7B7BE0; }
        .notification-item {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 4px solid #7B7BE0;
        }
        .notification-item.unread {
            background: #fff8f8;
            border-left-color: #dc3545;
        }
        .notification-item.read {
            opacity: 0.7;
            border-left-color: #28a745;
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .notification-time {
            font-size: 12px;
            color: #888;
        }
        .notification-message {
            font-size: 14px;
            line-height: 1.5;
        }
        .btn-back {
            background: white;
            border: 2px solid #7B7BE0;
            color: #7B7BE0;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .btn-mark-read {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
        }
        .btn-mark-all {
            background: #7B7BE0;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            width: 100%;
            margin-bottom: 20px;
        }
        .no-notifications {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        .no-notifications i { font-size: 48px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="main-container">
    <a href="responder_dashboard.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    
    <div class="header-logo">
        <i class="fas fa-bell"></i>
    </div>
    <div class="header-title">Notifications</div>
    
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card stat-unread">
            <h3><?= $unread_count ?></h3>
            <p>Unread</p>
        </div>
        <div class="stat-card stat-read">
            <h3><?= count($notifications) - $unread_count ?></h3>
            <p>Read</p>
        </div>
        <div class="stat-card stat-total">
            <h3><?= count($notifications) ?></h3>
            <p>Total</p>
        </div>
    </div>
    
    <?php if ($unread_count > 0): ?>
    <form method="post">
        <input type="hidden" name="mark_all_read" value="1">
        <button type="submit" class="btn-mark-all">
            <i class="fas fa-check-double"></i> Mark All as Read
        </button>
    </form>
    <?php endif; ?>
    
    <?php if (empty($notifications)): ?>
        <div class="no-notifications">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
        <div class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>">
            <div class="notification-header">
                <div>
                    <?php if (!$notification['is_read']): ?>
                        <span class="badge bg-danger">NEW</span>
                    <?php else: ?>
                        <span class="badge bg-success">READ</span>
                    <?php endif; ?>
                    <span class="notification-time">
                        <i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                    </span>
                </div>
                <?php if (!$notification['is_read']): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                    <input type="hidden" name="mark_read" value="1">
                    <button type="submit" class="btn-mark-read">
                        <i class="fas fa-check"></i> Mark Read
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="notification-message">
                <?= htmlspecialchars($notification['message']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
