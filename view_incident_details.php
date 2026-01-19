<?php
/**
 * View Incident Details - Full Page View
 * Alerto360 Emergency Response System
 */
session_start();
require 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$incident_id = intval($_GET['id'] ?? 0);

if ($incident_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch incident with user info
$stmt = $pdo->prepare("
    SELECT i.*, 
           u.name AS reporter_name, 
           u.email AS reporter_email,
           r.name AS responder_name,
           r.responder_type AS responder_unit
    FROM incidents i
    JOIN users u ON i.user_id = u.id
    LEFT JOIN users r ON i.accepted_by = r.id
    WHERE i.id = ?
");
$stmt->execute([$incident_id]);
$incident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$incident) {
    header('Location: index.php');
    exit;
}

// Determine back URL based on role
$back_url = 'index.php';
if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') {
    $back_url = 'admin/incident_reports.php';
} elseif ($_SESSION['role'] === 'responder') {
    $back_url = 'user/responder_dashboard.php';
} elseif ($_SESSION['role'] === 'citizen') {
    $back_url = 'user/my_reports.php';
}

// Device info parsing
$device_icon = 'bi-pc-display';
$device_label = 'Desktop';
if (($incident['device_type'] ?? '') === 'Mobile') {
    $device_icon = 'bi-phone';
    $device_label = 'Mobile';
} elseif (($incident['device_type'] ?? '') === 'Tablet') {
    $device_icon = 'bi-tablet';
    $device_label = 'Tablet';
}

// Browser/OS detection
$browser = 'Unknown';
$os = 'Unknown';
$ua = $incident['device_info'] ?? '';
if (strpos($ua, 'Alerto360 App') !== false) {
    $browser = 'Alerto360 App';
    if (preg_match('/Alerto360 App \| (\w+) (.+)/i', $ua, $matches)) {
        $os = ucfirst($matches[1]) . ' ' . $matches[2];
    }
} else {
    if (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
    elseif (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
    elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
    elseif (strpos($ua, 'Edge') !== false) $browser = 'Edge';
    
    if (strpos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (strpos($ua, 'Mac') !== false) $os = 'macOS';
    elseif (strpos($ua, 'Android') !== false) $os = 'Android';
    elseif (strpos($ua, 'iPhone') !== false) $os = 'iOS';
}

// Status styling
$status = $incident['status'] ?: 'pending';
$status_class = 'status-pending';
$status_icon = 'bi-clock';
if ($status === 'accepted') { $status_class = 'status-accepted'; $status_icon = 'bi-person-check'; }
elseif (in_array($status, ['done', 'completed', 'resolved'])) { $status_class = 'status-completed'; $status_icon = 'bi-check-circle'; $status = 'completed'; }
elseif ($status === 'declined') { $status_class = 'status-declined'; $status_icon = 'bi-x-circle'; }

// Type styling
$type_lower = strtolower($incident['type']);
$type_class = 'type-other'; $type_icon = 'bi-question-circle';
if (strpos($type_lower, 'fire') !== false) { $type_class = 'type-fire'; $type_icon = 'bi-fire'; }
elseif (strpos($type_lower, 'crime') !== false) { $type_class = 'type-crime'; $type_icon = 'bi-shield-exclamation'; }
elseif (strpos($type_lower, 'flood') !== false) { $type_class = 'type-flood'; $type_icon = 'bi-water'; }
elseif (strpos($type_lower, 'accident') !== false) { $type_class = 'type-accident'; $type_icon = 'bi-car-front'; }
elseif (strpos($type_lower, 'landslide') !== false) { $type_class = 'type-landslide'; $type_icon = 'bi-triangle'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incident #<?= $incident_id ?> - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #6366f1; --secondary: #8b5cf6; --success: #10b981;
            --warning: #f59e0b; --danger: #ef4444; --info: #06b6d4;
            --dark: #1e293b; --light: #f1f5f9;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--light); min-height: 100vh; }
        
        .main-container { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }
        
        .page-header {
            background: white; border-radius: 20px; padding: 1.5rem 2rem;
            margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
        }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .header-icon {
            width: 60px; height: 60px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem; color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        .header-icon.type-fire { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .header-icon.type-crime { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .header-icon.type-flood { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .header-icon.type-accident { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .header-icon.type-landslide { background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); }
        .header-icon.type-other { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
        .header-text h1 { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin: 0; }
        .header-text p { color: #64748b; margin: 0; font-size: 0.9rem; }
        
        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 0.6rem 1.2rem; background: var(--light); border: none;
            border-radius: 10px; color: var(--dark); font-weight: 500;
            text-decoration: none; transition: all 0.2s;
        }
        .back-btn:hover { background: var(--primary); color: white; }
        
        .status-badge {
            padding: 0.5rem 1rem; border-radius: 25px; font-size: 0.9rem;
            font-weight: 600; display: inline-flex; align-items: center; gap: 6px;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted { background: #cffafe; color: #0891b2; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-declined { background: #fee2e2; color: #dc2626; }
        
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        
        .detail-card {
            background: white; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden;
        }
        .detail-card.full-width { grid-column: 1 / -1; }
        
        .card-header {
            padding: 1rem 1.5rem; color: white; font-weight: 600;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header.primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .card-header.danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .card-header.info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .card-header.success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .card-header.warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .card-header.purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .card-header.dark { background: linear-gradient(135deg, #475569 0%, #334155 100%); }
        
        .card-body { padding: 1.5rem; }
        .card-body p { margin: 0 0 0.75rem; color: #475569; }
        .card-body p:last-child { margin-bottom: 0; }
        .card-body strong { color: var(--dark); }
        
        .info-label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .info-value { font-size: 1rem; color: var(--dark); font-weight: 500; }
        
        .type-badge {
            padding: 0.5rem 1rem; border-radius: 10px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .type-fire { background: #fee2e2; color: #dc2626; }
        .type-crime { background: #f3e8ff; color: #7c3aed; }
        .type-flood { background: #cffafe; color: #0891b2; }
        .type-accident { background: #fef3c7; color: #d97706; }
        .type-landslide { background: #fce7f3; color: #be185d; }
        .type-other { background: #f1f5f9; color: #64748b; }
        
        .description-box {
            background: #f8fafc; border-left: 4px solid var(--primary);
            padding: 1rem; border-radius: 0 10px 10px 0; margin-top: 0.5rem;
        }
        
        .incident-image {
            width: 100%; max-height: 400px; object-fit: contain;
            border-radius: 12px; cursor: pointer; transition: transform 0.2s;
        }
        .incident-image:hover { transform: scale(1.02); }
        
        .no-image {
            background: #f8fafc; border-radius: 12px; padding: 3rem;
            text-align: center; color: #94a3b8;
        }
        .no-image i { font-size: 3rem; margin-bottom: 1rem; }
        
        .map-container { border-radius: 12px; overflow: hidden; margin-top: 1rem; }
        .map-container iframe { width: 100%; height: 250px; border: none; }
        
        .coord-badge { background: #f1f5f9; padding: 0.5rem 1rem; border-radius: 8px; font-family: monospace; }
        
        .device-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #f1f5f9; padding: 0.4rem 0.8rem; border-radius: 8px;
        }
        
        .responder-card { display: flex; align-items: center; gap: 1rem; }
        .responder-avatar {
            width: 50px; height: 50px; border-radius: 12px;
            background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.25rem;
        }
        
        @media (max-width: 768px) {
            .detail-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="main-container">
    <!-- Header -->
    <div class="page-header">
        <div class="header-left">
            <div class="header-icon <?= $type_class ?>">
                <i class="bi <?= $type_icon ?>"></i>
            </div>
            <div class="header-text">
                <h1>Incident #<?= $incident_id ?></h1>
                <p>Reported on <?= date('F j, Y \a\t g:i A', strtotime($incident['created_at'])) ?></p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span class="status-badge <?= $status_class ?>">
                <i class="bi <?= $status_icon ?>"></i> <?= ucfirst($status) ?>
            </span>
            <a href="<?= $back_url ?>" class="back-btn">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <!-- Detail Grid -->
    <div class="detail-grid">
        <!-- Reporter Info -->
        <div class="detail-card">
            <div class="card-header primary">
                <i class="bi bi-person-fill"></i> Reporter Information
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?= htmlspecialchars($incident['reporter_name']) ?></div>
                </div>
                <div>
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?= htmlspecialchars($incident['reporter_email']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Incident Type -->
        <div class="detail-card">
            <div class="card-header danger">
                <i class="bi bi-exclamation-triangle-fill"></i> Incident Type
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="info-label">Emergency Type</div>
                    <span class="type-badge <?= $type_class ?>">
                        <i class="bi <?= $type_icon ?>"></i> <?= htmlspecialchars($incident['type']) ?>
                    </span>
                </div>
                <div>
                    <div class="info-label">Assigned Unit</div>
                    <div class="info-value"><?= htmlspecialchars($incident['responder_type'] ?? 'Auto-assigned') ?></div>
                </div>
            </div>
        </div>
        
        <!-- Description -->
        <div class="detail-card full-width">
            <div class="card-header dark">
                <i class="bi bi-chat-left-text-fill"></i> Description
            </div>
            <div class="card-body">
                <div class="description-box">
                    <?= nl2br(htmlspecialchars($incident['description'] ?: 'No description provided')) ?>
                </div>
            </div>
        </div>
        
        <!-- Photo Evidence -->
        <div class="detail-card">
            <div class="card-header purple">
                <i class="bi bi-camera-fill"></i> Photo Evidence
            </div>
            <div class="card-body">
                <?php if (!empty($incident['image_path'])): ?>
                    <img src="<?= htmlspecialchars($incident['image_path']) ?>" 
                         alt="Incident Photo" 
                         class="incident-image"
                         onclick="window.open(this.src, '_blank')">
                    <p class="text-muted text-center mt-2 small">
                        <i class="bi bi-zoom-in"></i> Click to view full size
                    </p>
                <?php else: ?>
                    <div class="no-image">
                        <i class="bi bi-image"></i>
                        <p>No photo uploaded</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Location -->
        <div class="detail-card">
            <div class="card-header success">
                <i class="bi bi-geo-alt-fill"></i> Location
            </div>
            <div class="card-body">
                <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                    <div class="mb-3">
                        <div class="info-label">Coordinates</div>
                        <span class="coord-badge"><?= $incident['latitude'] ?>, <?= $incident['longitude'] ?></span>
                    </div>
                    <a href="https://www.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" 
                       target="_blank" class="btn btn-success btn-sm mb-3">
                        <i class="bi bi-map"></i> Open in Google Maps
                    </a>
                    <div class="map-container">
                        <iframe src="https://maps.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>&z=15&output=embed"></iframe>
                    </div>
                <?php else: ?>
                    <div class="no-image">
                        <i class="bi bi-geo-alt"></i>
                        <p>Location not available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Date & Time -->
        <div class="detail-card">
            <div class="card-header info">
                <i class="bi bi-clock-fill"></i> Date & Time
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="info-label">Date Reported</div>
                    <div class="info-value"><?= date('F j, Y', strtotime($incident['created_at'])) ?></div>
                </div>
                <div class="mb-3">
                    <div class="info-label">Time Reported</div>
                    <div class="info-value"><?= date('g:i:s A', strtotime($incident['created_at'])) ?></div>
                </div>
                <?php if (!empty($incident['submitted_at'])): ?>
                <div>
                    <div class="info-label">Submitted At</div>
                    <div class="info-value"><?= date('M j, Y g:i A', strtotime($incident['submitted_at'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Device Info -->
        <div class="detail-card">
            <div class="card-header purple">
                <i class="bi bi-phone-fill"></i> Device Information
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="info-label">Device Type</div>
                    <span class="device-badge">
                        <i class="bi <?= $device_icon ?>"></i> <?= $device_label ?>
                    </span>
                </div>
                <div class="mb-3">
                    <div class="info-label">Browser / App</div>
                    <div class="info-value"><?= htmlspecialchars($browser) ?></div>
                </div>
                <div class="mb-3">
                    <div class="info-label">Operating System</div>
                    <div class="info-value"><?= htmlspecialchars($os) ?></div>
                </div>
                <?php if (!empty($incident['ip_address'])): ?>
                <div>
                    <div class="info-label">IP Address</div>
                    <code class="coord-badge"><?= htmlspecialchars($incident['ip_address']) ?></code>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Responder Info -->
        <?php if (!empty($incident['responder_name'])): ?>
        <div class="detail-card full-width">
            <div class="card-header warning">
                <i class="bi bi-person-badge-fill"></i> Assigned Responder
            </div>
            <div class="card-body">
                <div class="responder-card">
                    <div class="responder-avatar">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <div class="info-value" style="font-size: 1.1rem;"><?= htmlspecialchars($incident['responder_name']) ?></div>
                        <div class="text-muted"><?= htmlspecialchars($incident['responder_unit'] ?? 'Emergency Responder') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
