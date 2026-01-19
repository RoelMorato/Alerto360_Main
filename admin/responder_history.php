<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

$responder_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$responder_id) {
    header("Location: responder_accounts.php");
    exit;
}

$stmt = $pdo->prepare("SELECT name, email, responder_type, created_at FROM users WHERE id = ? AND role = 'responder'");
$stmt->execute([$responder_id]);
$responder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$responder) {
    die("Responder not found.");
}

// Get incidents handled by this responder from responder_history table
try {
    $incidents = $pdo->prepare("
        SELECT rh.*, i.*, u.name as reporter_name
        FROM responder_history rh
        JOIN incidents i ON rh.incident_id = i.id
        LEFT JOIN users u ON i.user_id = u.id 
        WHERE rh.responder_id = ?
        ORDER BY rh.action_timestamp DESC
    ");
    $incidents->execute([$responder_id]);
    $incidents = $incidents->fetchAll(PDO::FETCH_ASSOC);
    
    // Count stats from responder_history table
    $stats = $pdo->prepare("
        SELECT action_type, COUNT(*) as count 
        FROM responder_history 
        WHERE responder_id = ? 
        GROUP BY action_type
    ");
    $stats->execute([$responder_id]);
    $stats_data = $stats->fetchAll(PDO::FETCH_ASSOC);
    
    $completed = $active = $declined = 0;
    foreach ($stats_data as $stat) {
        if ($stat['action_type'] === 'completed') $completed = $stat['count'];
        elseif ($stat['action_type'] === 'accepted') $active = $stat['count'];
        elseif ($stat['action_type'] === 'declined') $declined = $stat['count'];
    }
    $total = count($incidents);
    $use_history_table = true;
    
} catch (Exception $e) {
    // Fallback to old method using incidents table
    $incidents = $pdo->prepare("
        SELECT i.*, u.name as reporter_name 
        FROM incidents i 
        LEFT JOIN users u ON i.user_id = u.id 
        WHERE i.accepted_by = ? OR i.declined_by = ?
        ORDER BY i.created_at DESC
    ");
    $incidents->execute([$responder_id, $responder_id]);
    $incidents = $incidents->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($incidents);
    $completed = $active = $declined = 0;
    foreach ($incidents as $inc) {
        if ($inc['status'] === 'completed') $completed++;
        elseif ($inc['status'] === 'accepted') $active++;
        elseif ($inc['status'] === 'declined' && $inc['declined_by'] == $responder_id) $declined++;
    }
    $use_history_table = false;
}
$type_lower = strtolower($responder['responder_type'] ?? 'mddrmo');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Responder History - <?= htmlspecialchars($responder['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
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
        .main-container { max-width: 1400px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
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
        
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        .avatar-pnp { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .avatar-bfp { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .avatar-mddrmo { background: linear-gradient(135deg, #10b981, #059669); }
        .profile-info { flex: 1; min-width: 200px; }
        .profile-info h2 { font-size: 24px; font-weight: 700; margin: 0 0 4px; color: var(--gray-800); }
        .profile-info p { font-size: 14px; color: var(--gray-600); margin: 0; }
        .profile-meta { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 8px; align-items: center; }
        .profile-meta span { font-size: 13px; color: var(--gray-600); display: flex; align-items: center; gap: 6px; }
        .type-badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .type-pnp { background: #dbeafe; color: #1d4ed8; }
        .type-bfp { background: #fee2e2; color: #dc2626; }
        .type-mddrmo { background: #d1fae5; color: #059669; }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .stat-card h3 { font-size: 32px; font-weight: 700; margin: 0 0 4px; }
        .stat-card h3.green { color: var(--success); }
        .stat-card h3.blue { color: var(--info); }
        .stat-card h3.gray { color: var(--gray-600); }
        .stat-card p { font-size: 13px; color: var(--gray-600); margin: 0; }
        
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
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header h2 { font-size: 18px; font-weight: 600; margin: 0; color: var(--gray-800); }
        .search-box { position: relative; }
        .search-box input {
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            width: 220px;
            font-size: 14px;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
        }
        
        .incidents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        .incident-card {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--gray-200);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .incident-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .incident-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .incident-id { font-size: 13px; color: var(--gray-600); font-weight: 500; }
        .incident-type {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .type-fire { background: #fee2e2; color: #dc2626; }
        .type-flood { background: #dbeafe; color: #1d4ed8; }
        .type-crime { background: #f3f4f6; color: #374151; }
        .type-accident { background: #fef3c7; color: #d97706; }
        .type-landslide { background: #e5e7eb; color: #4b5563; }
        .type-medical { background: #fce7f3; color: #be185d; }
        
        .incident-desc {
            font-size: 14px;
            color: var(--gray-800);
            margin-bottom: 12px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .incident-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            color: var(--gray-600);
        }
        .incident-meta span { display: flex; align-items: center; gap: 8px; }
        .incident-meta i { width: 16px; }
        
        .incident-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted { background: #dbeafe; color: #1d4ed8; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-declined { background: #fee2e2; color: #dc2626; }
        
        .incident-reporter {
            background: white;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .reporter-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: white;
        }
        .reporter-info { flex: 1; }
        .reporter-info h5 { font-size: 13px; font-weight: 600; margin: 0; color: var(--gray-800); }
        .reporter-info p { font-size: 11px; color: var(--gray-600); margin: 0; }
        
        .incident-times {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }
        .time-box {
            background: white;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .time-box .label { font-size: 10px; color: var(--gray-600); text-transform: uppercase; margin-bottom: 4px; }
        .time-box .value { font-size: 12px; color: var(--gray-800); font-weight: 500; }
        
        .btn-view-details {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: opacity 0.2s;
        }
        .btn-view-details:hover { opacity: 0.9; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-600);
            grid-column: 1/-1;
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        
        @media (max-width: 768px) {
            .incidents-grid { grid-template-columns: 1fr; }
            .profile-card { flex-direction: column; text-align: center; }
            .profile-meta { justify-content: center; }
            .search-box input { width: 100%; }
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="header">
        <a href="responder_accounts.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Responders</a>
    </div>
    
    <div class="profile-card">
        <div class="profile-avatar avatar-<?= $type_lower ?>"><?= strtoupper(substr($responder['name'], 0, 2)) ?></div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($responder['name']) ?></h2>
            <p><?= htmlspecialchars($responder['email']) ?></p>
            <div class="profile-meta">
                <span class="type-badge type-<?= $type_lower ?>"><?= htmlspecialchars($responder['responder_type'] ?? 'N/A') ?></span>
                <span><i class="bi bi-calendar3"></i> Joined <?= date('M d, Y', strtotime($responder['created_at'])) ?></span>
            </div>
        </div>
    </div>
    
    <div class="stats-row">
        <div class="stat-card">
            <h3 class="green"><?= $completed ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card">
            <h3 class="blue"><?= $active ?></h3>
            <p>Active</p>
        </div>
        <div class="stat-card">
            <h3 class="red" style="color: var(--danger);"><?= $declined ?></h3>
            <p>Declined</p>
        </div>
        <div class="stat-card">
            <h3 class="gray"><?= $total ?></h3>
            <p>Total Handled</p>
        </div>
    </div>
    
    <div class="content-card">
        <div class="card-header">
            <h2><i class="bi bi-clock-history"></i> Incident History</h2>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Search incidents..." onkeyup="filterIncidents()">
            </div>
        </div>
        
        <div class="incidents-grid" id="incidentsGrid">
            <?php if (empty($incidents)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h4>No Incidents Yet</h4>
                    <p>This responder hasn't handled any incidents.</p>
                </div>
            <?php else: ?>
                <?php foreach ($incidents as $inc): 
                    $type_lower = strtolower($inc['type'] ?? 'other');
                    $type_class = 'type-' . $type_lower;
                    $status_class = 'status-' . $inc['status'];
                    
                    // Handle both history table and fallback data
                    if (isset($use_history_table) && $use_history_table) {
                        $action_type = $inc['action_type'];
                        $action_reason = $inc['action_reason'];
                        $action_timestamp = $inc['action_timestamp'];
                    } else {
                        // Fallback: determine action from incident data
                        if ($inc['declined_by'] == $responder_id) {
                            $action_type = 'declined';
                            $action_reason = $inc['decline_reason'];
                            $action_timestamp = $inc['declined_at'];
                        } elseif ($inc['status'] === 'completed') {
                            $action_type = 'completed';
                            $action_reason = null;
                            $action_timestamp = $inc['completed_at'];
                        } else {
                            $action_type = 'accepted';
                            $action_reason = null;
                            $action_timestamp = $inc['accepted_at'];
                        }
                    }
                ?>
                <div class="incident-card" data-type="<?= $type_lower ?>" data-desc="<?= strtolower($inc['description'] ?? '') ?>">
                    <div class="incident-header">
                        <span class="incident-id">#<?= $inc['id'] ?></span>
                        <span class="incident-type <?= $type_class ?>"><?= htmlspecialchars($inc['type'] ?? 'Unknown') ?></span>
                    </div>
                    
                    <p class="incident-desc"><?= htmlspecialchars($inc['description'] ?? 'No description') ?></p>
                    
                    <div class="incident-meta">
                        <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($inc['location'] ?? 'Unknown location') ?></span>
                        <span><i class="bi bi-clock"></i> <?= date('M d, Y h:i A', strtotime($inc['created_at'])) ?></span>
                    </div>
                    
                    <span class="incident-status <?= $status_class ?>">
                        <?php
                        $status_icons = [
                            'pending' => 'bi-hourglass-split',
                            'accepted' => 'bi-arrow-repeat',
                            'completed' => 'bi-check-circle',
                            'declined' => 'bi-x-circle'
                        ];
                        $icon = $status_icons[$inc['status']] ?? 'bi-question-circle';
                        
                        // Show action type instead of incident status for better clarity
                        $display_status = ucfirst($action_type);
                        if ($action_type === 'accepted' && $inc['status'] === 'completed') {
                            $display_status = 'Completed';
                            $icon = 'bi-check-circle';
                        }
                        ?>
                        <i class="bi <?= $icon ?>"></i>
                        <?= $display_status ?>
                    </span>
                    
                    <?php if ($inc['reporter_name']): ?>
                    <div class="incident-reporter">
                        <div class="reporter-icon">
                            <?= strtoupper(substr($inc['reporter_name'], 0, 2)) ?>
                        </div>
                        <div class="reporter-info">
                            <h5><?= htmlspecialchars($inc['reporter_name']) ?></h5>
                            <p>Reporter</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($action_type === 'declined' && !empty($action_reason)): ?>
                    <div class="incident-decline-reason" style="background: #fee2e2; border-radius: 10px; padding: 12px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <i class="bi bi-x-circle" style="color: #dc2626;"></i>
                            <strong style="color: #dc2626; font-size: 12px;">DECLINE REASON</strong>
                        </div>
                        <p style="margin: 0; font-size: 13px; color: #7f1d1d;"><?= htmlspecialchars($action_reason) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="incident-times">
                        <div class="time-box">
                            <div class="label"><?= ucfirst($action_type) ?></div>
                            <div class="value"><?= $action_timestamp ? date('M d, h:i A', strtotime($action_timestamp)) : '-' ?></div>
                        </div>
                        <div class="time-box">
                            <div class="label">Reported</div>
                            <div class="value"><?= date('M d, h:i A', strtotime($inc['created_at'])) ?></div>
                        </div>
                    </div>
                    
                    <a href="../view_incident_details.php?id=<?= $inc['id'] ?>" class="btn-view-details" target="_blank">
                        <i class="bi bi-eye"></i> View Details
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterIncidents() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.incident-card').forEach(card => {
        const type = card.dataset.type;
        const desc = card.dataset.desc;
        card.style.display = (type.includes(search) || desc.includes(search)) ? '' : 'none';
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
