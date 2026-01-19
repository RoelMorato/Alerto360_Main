<?php
session_start();
require "../db_connect.php";

if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ["admin", "super_admin"])) {
    die("Access denied.");
}

$responder_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if (!$responder_id) {
    header("Location: responder_accounts.php");
    exit;
}

$stmt = $pdo->prepare("SELECT name, email, responder_type, created_at FROM users WHERE id = ? AND role = \"responder\"");
$stmt->execute([$responder_id]);
$responder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$responder) {
    die("Responder not found.");
}

// Try responder_history table first, fallback to incidents table
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
    
    // Count stats from responder_history
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
        if ($stat["action_type"] === "completed") $completed = $stat["count"];
        elseif ($stat["action_type"] === "accepted") $active = $stat["count"];
        elseif ($stat["action_type"] === "declined") $declined = $stat["count"];
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
        if ($inc["status"] === "completed") $completed++;
        elseif ($inc["status"] === "accepted") $active++;
        elseif ($inc["status"] === "declined" && $inc["declined_by"] == $responder_id) $declined++;
    }
    $use_history_table = false;
}

$type_lower = strtolower($responder["responder_type"] ?? "mddrmo");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Responder History - <?= htmlspecialchars($responder["name"]) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 1200px;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .btn-back {
            background: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
        }
        .stat-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stat-card h3 { font-size: 2rem; margin: 0; }
        .stat-card p { margin: 0; color: #6c757d; }
        .incident-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #6366f1;
        }
        .badge { padding: 8px 12px; border-radius: 20px; }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-primary { background: #dbeafe; color: #1d4ed8; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-warning { background: #fef3c7; color: #d97706; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><?= htmlspecialchars($responder["name"]) ?></h2>
            <p class="text-muted"><?= htmlspecialchars($responder["email"]) ?> | <?= htmlspecialchars($responder["responder_type"] ?? "N/A") ?></p>
        </div>
        <a href="responder_accounts.php" class="btn-back">← Back</a>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <h3 style="color: #059669;"><?= $completed ?></h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h3 style="color: #1d4ed8;"><?= $active ?></h3>
                <p>Active</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h3 style="color: #dc2626;"><?= $declined ?></h3>
                <p>Declined</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h3 style="color: #6c757d;"><?= $total ?></h3>
                <p>Total</p>
            </div>
        </div>
    </div>
    
    <h4>Incident History</h4>
    <?php if (empty($incidents)): ?>
        <div class="text-center py-5">
            <p class="text-muted">No incidents found for this responder.</p>
        </div>
    <?php else: ?>
        <?php foreach ($incidents as $inc): 
            if ($use_history_table) {
                $action_type = $inc["action_type"];
                $action_reason = $inc["action_reason"];
                $action_timestamp = $inc["action_timestamp"];
            } else {
                // Determine action type from incident data
                if ($inc["declined_by"] == $responder_id) {
                    $action_type = "declined";
                    $action_reason = $inc["decline_reason"];
                    $action_timestamp = $inc["declined_at"];
                } elseif ($inc["status"] === "completed") {
                    $action_type = "completed";
                    $action_reason = null;
                    $action_timestamp = $inc["completed_at"];
                } else {
                    $action_type = "accepted";
                    $action_reason = null;
                    $action_timestamp = $inc["accepted_at"];
                }
            }
            
            $badge_class = "badge-primary";
            if ($action_type === "completed") $badge_class = "badge-success";
            elseif ($action_type === "declined") $badge_class = "badge-danger";
        ?>
        <div class="incident-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5>Incident #<?= $inc["id"] ?> - <?= htmlspecialchars($inc["type"] ?? "Unknown") ?></h5>
                <span class="badge <?= $badge_class ?>"><?= ucfirst($action_type) ?></span>
            </div>
            <p><?= htmlspecialchars($inc["description"] ?? "No description") ?></p>
            <?php if ($action_type === "declined" && $action_reason): ?>
                <div class="alert alert-danger">
                    <strong>Decline Reason:</strong> <?= htmlspecialchars($action_reason) ?>
                </div>
            <?php endif; ?>
            <small class="text-muted">
                <?= ucfirst($action_type) ?> on <?= $action_timestamp ? date("M d, Y h:i A", strtotime($action_timestamp)) : "Unknown" ?>
            </small>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>