<?php
session_start();
require 'db_connect.php';

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

$incident_id = intval($_GET['id'] ?? 0);

if (!$incident_id) {
    echo '<div class="alert alert-danger">Invalid incident ID.</div>';
    exit;
}

// Fetch detailed incident information
$stmt = $pdo->prepare("
    SELECT 
        incidents.*,
        reporter.name AS reporter_name,
        reporter.email AS reporter_email,
        responder.name AS responder_name,
        responder.email AS responder_email,
        responder.responder_type AS responder_type_assigned
    FROM incidents 
    JOIN users AS reporter ON incidents.user_id = reporter.id 
    LEFT JOIN users AS responder ON incidents.accepted_by = responder.id 
    WHERE incidents.id = ?
");

$stmt->execute([$incident_id]);
$incident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$incident) {
    echo '<div class="alert alert-danger">Incident not found.</div>';
    exit;
}

// Get acceptance log if exists (with error handling for missing table)
$logs = [];
try {
    $log_stmt = $pdo->prepare("SELECT * FROM acceptance_log WHERE incident_id = ? ORDER BY timestamp DESC");
    $log_stmt->execute([$incident_id]);
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist yet - this is okay, just no logs to show
    $logs = [];
}

// Status badge styling
$status_class = '';
switch ($incident['status']) {
    case 'pending':
        $status_class = 'bg-warning text-dark';
        break;
    case 'accepted':
        $status_class = 'bg-info text-white';
        break;
    case 'done':
        $status_class = 'bg-success text-white';
        break;
    case 'resolved':
        $status_class = 'bg-secondary text-white';
        break;
    default:
        $status_class = 'bg-light text-dark';
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Basic Information -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Basic Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Incident ID:</strong></td>
                            <td>#<?= $incident['id'] ?></td>
                        </tr>
                        <tr>
                            <td><strong>Type:</strong></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($incident['type']) ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge <?= $status_class ?>"><?= htmlspecialchars($incident['status']) ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Reported:</strong></td>
                            <td><?= date('M d, Y h:i A', strtotime($incident['created_at'])) ?></td>
                        </tr>
                        <?php if (isset($incident['accepted_at']) && $incident['accepted_at']): ?>
                        <tr>
                            <td><strong>Accepted:</strong></td>
                            <td><?= date('M d, Y h:i A', strtotime($incident['accepted_at'])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Responder Type:</strong></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($incident['responder_type']) ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reporter Information -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-person"></i> Reporter Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td><?= htmlspecialchars($incident['reporter_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?= htmlspecialchars($incident['reporter_email']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>User ID:</strong></td>
                            <td>#<?= $incident['user_id'] ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="card mb-3">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0"><i class="bi bi-file-text"></i> Incident Description</h6>
        </div>
        <div class="card-body">
            <p class="mb-0"><?= nl2br(htmlspecialchars($incident['description'])) ?></p>
        </div>
    </div>

    <div class="row">
        <!-- Location Information -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Location</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                        <p><strong>Coordinates:</strong><br>
                        Lat: <?= htmlspecialchars($incident['latitude']) ?><br>
                        Lng: <?= htmlspecialchars($incident['longitude']) ?></p>
                        <a href="https://www.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" 
                           target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-map"></i> View on Google Maps
                        </a>
                    <?php else: ?>
                        <p class="text-muted">No location data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Image -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="bi bi-image"></i> Incident Image</h6>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($incident['image_path'])): ?>
                        <img src="<?= htmlspecialchars($incident['image_path']) ?>" 
                             alt="Incident Image" 
                             class="img-fluid rounded" 
                             style="max-height: 200px; cursor: pointer;"
                             onclick="window.open(this.src, '_blank')">
                        <p class="mt-2 small text-muted">Click image to view full size</p>
                    <?php else: ?>
                        <p class="text-muted">No image uploaded</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Responder Information -->
    <?php if ($incident['responder_name']): ?>
    <div class="card mb-3">
        <div class="card-header bg-dark text-white">
            <h6 class="mb-0"><i class="bi bi-shield-check"></i> Assigned Responder</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>Name:</strong><br>
                    <?= htmlspecialchars($incident['responder_name']) ?>
                </div>
                <div class="col-md-4">
                    <strong>Email:</strong><br>
                    <?= htmlspecialchars($incident['responder_email']) ?>
                </div>
                <div class="col-md-4">
                    <strong>Type:</strong><br>
                    <span class="badge bg-info"><?= htmlspecialchars($incident['responder_type_assigned']) ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Activity Log -->
    <?php if (!empty($logs)): ?>
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Activity Log</h6>
        </div>
        <div class="card-body">
            <?php foreach ($logs as $log): ?>
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <strong><?= htmlspecialchars($log['action']) ?></strong>
                    <small class="text-muted">by Responder #<?= $log['responder_id'] ?></small>
                </div>
                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
