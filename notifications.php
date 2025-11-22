<?php
/**
 * Notifications Page for Alerto360
 * Displays notifications for responders and admins
 */

session_start();
require 'db_connect.php';
require 'notification_functions.php';

// Check if user is logged in and is responder or admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['responder', 'admin'])) {
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

// Get all notifications for the user (both read and unread)
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            max-width: 900px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .back-btn {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        .notification-item {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .notification-item.unread {
            border-left-color: #dc3545;
            background: #fff8f8;
        }
        .notification-item.read {
            opacity: 0.8;
            border-left-color: #28a745;
        }
        .notification-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .notification-time {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .notification-message {
            white-space: pre-line;
            line-height: 1.6;
        }
        .mark-read-btn {
            background: #28a745;
            border: none;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .mark-read-btn:hover {
            background: #218838;
            transform: scale(1.05);
        }
        .no-notifications {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .mark-all-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .mark-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header">
            <a href="<?= $user_role === 'admin' ? 'admin_dashboard.php' : 'responder_dashboard.php' ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <div class="subtitle"><?= ucfirst($user_role) ?> Dashboard</div>
        </div>
        
        <div class="container py-4">
            <!-- Stats Card -->
            <div class="stats-card">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h3 class="text-primary"><?= $unread_count ?></h3>
                        <p class="mb-0">Unread Notifications</p>
                    </div>
                    <div class="col-md-4">
                        <h3 class="text-success"><?= count($notifications) - $unread_count ?></h3>
                        <p class="mb-0">Read Notifications</p>
                    </div>
                    <div class="col-md-4">
                        <h3 class="text-info"><?= count($notifications) ?></h3>
                        <p class="mb-0">Total Notifications</p>
                    </div>
                </div>
                
                <?php if ($unread_count > 0): ?>
                    <div class="text-center mt-3">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="mark_all_read" value="1">
                            <button type="submit" class="mark-all-btn">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <i class="fas fa-bell-slash fa-3x mb-3"></i>
                    <h4>No Notifications</h4>
                    <p>You don't have any notifications yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>">
                        <div class="notification-header">
                            <div>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="badge bg-danger me-2">NEW</span>
                                <?php else: ?>
                                    <span class="badge bg-success me-2">READ</span>
                                <?php endif; ?>
                                <span class="notification-time">
                                    <i class="fas fa-clock"></i> 
                                    <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                </span>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                    <input type="hidden" name="mark_read" value="1">
                                    <button type="submit" class="mark-read-btn">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // Only refresh if there are unread notifications
            if (<?= $unread_count ?> > 0) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
