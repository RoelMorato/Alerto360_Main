<?php
session_start();
require 'db_connect.php';

// Only allow citizens
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'citizen') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's incidents
$stmt = $pdo->prepare("SELECT i.*, u.name as responder_name 
                       FROM incidents i 
                       LEFT JOIN users u ON i.assigned_to = u.id 
                       WHERE i.user_id = ? 
                       ORDER BY i.created_at DESC");
$stmt->execute([$user_id]);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Incidents - Alerto360</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }
        
        .app-bar {
            background: #667eea;
            color: white;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .back-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
        }
        
        .app-bar-title {
            font-size: 20px;
            font-weight: 500;
        }
        
        .content {
            padding: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 14px;
        }
        
        .incident-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .incident-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .incident-type {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .type-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .type-info h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .type-info p {
            font-size: 12px;
            color: #999;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .status-accepted {
            background: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .status-completed {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }
        
        .incident-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        
        .incident-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 12px;
            color: #999;
        }
        
        .meta-row {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .incident-image {
            margin-top: 12px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .incident-image img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="app-bar">
        <button class="back-btn" onclick="window.location.href='user/user_dashboard.php'">
            <i class="fas fa-arrow-left"></i>
        </button>
        <div class="app-bar-title">My Incidents</div>
    </div>
    
    <div class="content">
        <?php if (empty($incidents)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No incidents reported yet</h3>
            <p>Your reported incidents will appear here</p>
        </div>
        <?php else: ?>
            <?php foreach ($incidents as $incident): 
                $status = strtolower($incident['status']);
                $statusClass = 'status-' . ($status === 'resolved' ? 'completed' : $status);
                
                $iconColors = [
                    'Fire' => '#f44336',
                    'Crime' => '#9c27b0',
                    'Flood' => '#2196f3',
                    'Landslide' => '#795548',
                    'Accident' => '#ff9800',
                    'Other' => '#607d8b'
                ];
                $iconColor = $iconColors[$incident['type']] ?? '#607d8b';
                
                $icons = [
                    'Fire' => 'fire',
                    'Crime' => 'shield',
                    'Flood' => 'water',
                    'Landslide' => 'mountain',
                    'Accident' => 'car-crash',
                    'Other' => 'circle-exclamation'
                ];
                $icon = $icons[$incident['type']] ?? 'circle-exclamation';
            ?>
            <div class="incident-card">
                <div class="incident-header">
                    <div class="incident-type">
                        <div class="type-icon" style="background: <?= $iconColor ?>1a;">
                            <i class="fas fa-<?= $icon ?>" style="color: <?= $iconColor ?>;"></i>
                        </div>
                        <div class="type-info">
                            <h4><?= htmlspecialchars($incident['type']) ?></h4>
                            <p>ID: #<?= $incident['id'] ?></p>
                        </div>
                    </div>
                    <div class="status-badge <?= $statusClass ?>">
                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                        <?= strtoupper($status) ?>
                    </div>
                </div>
                
                <?php if (!empty($incident['description'])): ?>
                <div class="incident-description">
                    <?= htmlspecialchars($incident['description']) ?>
                </div>
                <?php endif; ?>
                
                <div class="incident-meta">
                    <div class="meta-row">
                        <i class="fas fa-clock"></i>
                        <?= date('M d, Y h:i A', strtotime($incident['created_at'])) ?>
                    </div>
                    <?php if (!empty($incident['responder_name'])): ?>
                    <div class="meta-row">
                        <i class="fas fa-user"></i>
                        Responder: <?= htmlspecialchars($incident['responder_name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($incident['image_path'])): ?>
                <div class="incident-image">
                    <img src="<?= htmlspecialchars($incident['image_path']) ?>" alt="Incident photo">
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
