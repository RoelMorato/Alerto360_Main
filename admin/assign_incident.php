<?php
session_start();
require '../db_connect.php';

// Check if admin or super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$incident_id = intval($_GET['id'] ?? 0);
$is_reassign = isset($_GET['reassign']) && $_GET['reassign'] == '1';

if ($incident_id <= 0) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get incident details with declined info
$stmt = $pdo->prepare("
    SELECT incidents.*, 
           users.name AS reporter_name,
           declined_user.name AS declined_by_name
    FROM incidents 
    JOIN users ON incidents.user_id = users.id 
    LEFT JOIN users AS declined_user ON incidents.declined_by = declined_user.id
    WHERE incidents.id = ?
");
$stmt->execute([$incident_id]);
$incident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$incident) {
    header('Location: admin_dashboard.php');
    exit;
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $responder_id = intval($_POST['responder_id'] ?? 0);
    
    if ($responder_id > 0) {
        try {
            // Check if assigned_to column exists
            $hasAssignedTo = false;
            try {
                $check = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'assigned_to'");
                $hasAssignedTo = $check->rowCount() > 0;
            } catch (Exception $e) {
                $hasAssignedTo = false;
            }
            
            // Update incident - keep status as 'pending' so responder can Accept/Decline
            // Also clear any previous decline info when reassigning
            if ($hasAssignedTo) {
                $update = $pdo->prepare("
                    UPDATE incidents 
                    SET assigned_to = ?, 
                        status = 'pending',
                        declined_by = NULL,
                        decline_reason = NULL,
                        declined_at = NULL,
                        accepted_by = NULL
                    WHERE id = ?
                ");
            } else {
                // Fallback: use accepted_by but keep status pending
                $update = $pdo->prepare("
                    UPDATE incidents 
                    SET accepted_by = ?, 
                        status = 'pending',
                        declined_by = NULL,
                        decline_reason = NULL,
                        declined_at = NULL
                    WHERE id = ?
                ");
            }
            $update->execute([$responder_id, $incident_id]);
            
            // Try to create notification (if table exists)
            try {
                // Check if notifications table exists
                $check_table = $pdo->query("SHOW TABLES LIKE 'notifications'");
                if ($check_table->rowCount() > 0) {
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, incident_id, message, type, created_at) 
                        VALUES (?, ?, ?, 'assignment', NOW())
                    ");
                    $is_reassign_post = isset($_POST['is_reassign']) && $_POST['is_reassign'] == '1';
                    if ($is_reassign_post) {
                        $notif_message = "REASSIGNED: Incident #{$incident_id} ({$incident['type']}) has been reassigned to you - Please Accept or Decline";
                        $_SESSION['success_msg'] = 'Incident reassigned successfully! New responder notified.';
                    } else {
                        $notif_message = "New incident #{$incident_id} ({$incident['type']}) assigned to you - Please Accept or Decline";
                        $_SESSION['success_msg'] = 'Incident assigned successfully! Responder notified and can now Accept or Decline.';
                    }
                    $notif_stmt->execute([$responder_id, $incident_id, $notif_message]);
                } else {
                    $_SESSION['success_msg'] = 'Incident assigned successfully! (Note: Notifications table not found - run setup_notifications.php)';
                }
            } catch (PDOException $notif_error) {
                // Notification failed but assignment succeeded
                $_SESSION['success_msg'] = 'Incident assigned successfully! (Notification failed: ' . $notif_error->getMessage() . ')';
            }
            
            header('Location: admin_dashboard.php');
            exit;
        } catch (PDOException $e) {
            $error_msg = 'Error assigning incident: ' . $e->getMessage();
        }
    } else {
        $error_msg = 'Please select a responder';
    }
}

// Map incident types to required responder types
function getRequiredResponderType($incidentType) {
    $typeMap = [
        'Fire' => 'BFP',
        'Crime' => 'PNP',
        'Flood' => 'MDDRMO',
        'Landslide' => 'MDDRMO',
        'Accident' => 'MDDRMO',
        'Other' => null // Any responder can handle
    ];
    return $typeMap[$incidentType] ?? null;
}

// Get responder type label
function getResponderTypeLabel($type) {
    $labels = [
        'BFP' => 'Bureau of Fire Protection',
        'PNP' => 'Philippine National Police',
        'MDDRMO' => 'Municipal Disaster Risk Reduction Management Office'
    ];
    return $labels[$type] ?? $type;
}

$required_responder_type = getRequiredResponderType($incident['type']);
$required_type_label = $required_responder_type ? getResponderTypeLabel($required_responder_type) : 'Any';

// Get available responders - filtered by incident type
if ($required_responder_type) {
    // Filter by specific responder type
    $responders_stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.responder_type,
            s.is_online,
            s.on_duty,
            s.last_seen,
            CASE 
                WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 'Online'
                WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 30 THEN 'Away'
                ELSE 'Offline'
            END as status
        FROM users u
        LEFT JOIN user_online_status s ON u.id = s.user_id
        WHERE u.role = 'responder' AND u.responder_type = ?
        ORDER BY 
            s.on_duty DESC,
            CASE 
                WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 1
                ELSE 2
            END,
            s.last_seen DESC
    ");
    $responders_stmt->execute([$required_responder_type]);
} else {
    // Get all responders for "Other" type incidents
    $responders_stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.responder_type,
            s.is_online,
            s.on_duty,
            s.last_seen,
            CASE 
                WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 'Online'
                WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 30 THEN 'Away'
                ELSE 'Offline'
            END as status
        FROM users u
        LEFT JOIN user_online_status s ON u.id = s.user_id
        WHERE u.role = 'responder'
        ORDER BY 
            s.on_duty DESC,
            CASE 
                WHEN s.is_online = 1 AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 1
                ELSE 2
            END,
            s.last_seen DESC
    ");
}
$responders = $responders_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Incident - Alerto360</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .responder-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .responder-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .responder-card.selected {
            border-color: #7b7be0;
            background: #f8f9ff;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-online { background: #d4edda; color: #155724; }
        .badge-away { background: #fff3cd; color: #856404; }
        .badge-offline { background: #f8d7da; color: #721c24; }
        .badge-duty { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-lg">
            <div class="card-header <?= $is_reassign ? 'bg-warning' : 'bg-primary' ?> text-<?= $is_reassign ? 'dark' : 'white' ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <?php if ($is_reassign): ?>
                            <i class="bi bi-arrow-repeat"></i> Reassign Incident
                        <?php else: ?>
                            <i class="bi bi-person-check"></i> Assign Incident to Responder
                        <?php endif; ?>
                    </h4>
                    <a href="admin_dashboard.php" class="btn btn-light btn-sm">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
                <?php endif; ?>

                <?php if ($is_reassign && !empty($incident['declined_by'])): ?>
                <!-- Previous Decline Info -->
                <div class="alert alert-danger">
                    <h5><i class="bi bi-x-circle"></i> Previous Responder Declined</h5>
                    <p class="mb-1"><strong>Declined by:</strong> <?= htmlspecialchars($incident['declined_by_name'] ?? 'Unknown') ?></p>
                    <?php if (!empty($incident['decline_reason'])): ?>
                        <p class="mb-1"><strong>Reason:</strong> <?= htmlspecialchars($incident['decline_reason']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($incident['declined_at'])): ?>
                        <p class="mb-0"><strong>Declined at:</strong> <?= date('M d, Y h:i A', strtotime($incident['declined_at'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Incident Details -->
                <div class="alert alert-info">
                    <h5><i class="bi bi-exclamation-triangle"></i> Incident Details</h5>
                    <p class="mb-1"><strong>Type:</strong> <?= htmlspecialchars($incident['type']) ?></p>
                    <p class="mb-1"><strong>Reporter:</strong> <?= htmlspecialchars($incident['reporter_name']) ?></p>
                    <p class="mb-1"><strong>Description:</strong> <?= htmlspecialchars($incident['description']) ?></p>
                    <p class="mb-0"><strong>Reported:</strong> <?= date('M d, Y h:i A', strtotime($incident['created_at'])) ?></p>
                </div>

                <!-- Required Responder Type Info -->
                <div class="alert <?= $required_responder_type ? 'alert-warning' : 'alert-secondary' ?>">
                    <h6 class="mb-1">
                        <i class="bi bi-person-badge"></i> Required Responder Type:
                        <strong><?= htmlspecialchars($required_responder_type ?: 'Any') ?></strong>
                        <?php if ($required_responder_type): ?>
                            <small class="text-muted">(<?= htmlspecialchars($required_type_label) ?>)</small>
                        <?php endif; ?>
                    </h6>
                    <small class="text-muted">
                        <?php if ($required_responder_type): ?>
                            Only <?= htmlspecialchars($required_responder_type) ?> responders are shown below based on the incident type "<?= htmlspecialchars($incident['type']) ?>".
                        <?php else: ?>
                            All responders are shown because this is an "Other" type incident.
                        <?php endif; ?>
                    </small>
                </div>

                <h5 class="mt-4 mb-3">
                    Select <?= $required_responder_type ? htmlspecialchars($required_responder_type) : '' ?> Responder:
                </h5>

                <form method="POST" id="assignForm">
                    <input type="hidden" name="is_reassign" value="<?= $is_reassign ? '1' : '0' ?>">
                    <div class="row">
                        <?php foreach ($responders as $responder): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card responder-card" onclick="selectResponder(<?= $responder['id'] ?>)">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($responder['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($responder['email']) ?></small>
                                            </div>
                                            <input type="radio" name="responder_id" value="<?= $responder['id'] ?>" class="form-check-input" id="resp_<?= $responder['id'] ?>">
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <?php
                                            // Color code responder types
                                            $type_badge_class = 'bg-secondary';
                                            if ($responder['responder_type'] === 'BFP') $type_badge_class = 'bg-danger';
                                            elseif ($responder['responder_type'] === 'PNP') $type_badge_class = 'bg-primary';
                                            elseif ($responder['responder_type'] === 'MDDRMO') $type_badge_class = 'bg-success';
                                            ?>
                                            <span class="badge <?= $type_badge_class ?>"><?= htmlspecialchars($responder['responder_type']) ?></span>
                                            <?php
                                            $statusClass = 'badge-offline';
                                            if ($responder['status'] === 'Online') $statusClass = 'badge-online';
                                            elseif ($responder['status'] === 'Away') $statusClass = 'badge-away';
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <i class="bi bi-circle-fill"></i> <?= $responder['status'] ?>
                                            </span>
                                            <?php if ($responder['on_duty']): ?>
                                                <span class="status-badge badge-duty">
                                                    <i class="bi bi-shield-check"></i> On Duty
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($responders)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <?php if ($required_responder_type): ?>
                                No <strong><?= htmlspecialchars($required_responder_type) ?></strong> responders available at the moment.
                                <br><small>This incident type (<?= htmlspecialchars($incident['type']) ?>) requires <?= htmlspecialchars($required_type_label) ?> responders.</small>
                            <?php else: ?>
                                No responders available at the moment.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn <?= $is_reassign ? 'btn-warning' : 'btn-primary' ?> btn-lg" <?= empty($responders) ? 'disabled' : '' ?>>
                            <?php if ($is_reassign): ?>
                                <i class="bi bi-arrow-repeat"></i> Reassign Incident
                            <?php else: ?>
                                <i class="bi bi-check-circle"></i> Assign Incident
                            <?php endif; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function selectResponder(id) {
            // Remove selected class from all cards
            document.querySelectorAll('.responder-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select the radio button
            const radio = document.getElementById('resp_' + id);
            radio.checked = true;
            
            // Add selected class to clicked card
            radio.closest('.responder-card').classList.add('selected');
        }
    </script>
</body>
</html>
