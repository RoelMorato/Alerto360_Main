<?php
session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Only allow responders
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'responder') {
    header('Location: login.php');
    exit;
}

// Get responder info
$responder_stmt = $pdo->prepare("SELECT name, responder_type FROM users WHERE id = ?");
$responder_stmt->execute([$_SESSION['user_id']]);
$responder = $responder_stmt->fetch(PDO::FETCH_ASSOC);
if (!$responder) {
    die('Responder not found.');
}
$responder_type = $responder['responder_type'] ?? '';
$responder_name = $responder['name'] ?? '';

// Messages
$message = '';
if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['error_message']);
}

// Get notification count
$notification_count = getNotificationCount($pdo, $_SESSION['user_id']);

// Check if assigned_to column exists
$hasAssignedTo = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'assigned_to'");
    $hasAssignedTo = $check->rowCount() > 0;
} catch (Exception $e) {
    $hasAssignedTo = false;
}

// Fetch incidents - show assigned to me OR my responder type (pending) OR accepted by me
if ($hasAssignedTo) {
    $stmt = $pdo->prepare("
        SELECT incidents.*, users.name AS reporter 
        FROM incidents 
        JOIN users ON incidents.user_id = users.id 
        WHERE incidents.assigned_to = ? 
           OR incidents.accepted_by = ?
           OR (incidents.responder_type = ? AND incidents.status = 'pending' AND incidents.assigned_to IS NULL)
        ORDER BY 
            CASE incidents.status WHEN 'pending' THEN 1 WHEN 'accepted' THEN 2 ELSE 3 END,
            incidents.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $responder_type]);
} else {
    // Fallback query without assigned_to column
    $stmt = $pdo->prepare("
        SELECT incidents.*, users.name AS reporter 
        FROM incidents 
        JOIN users ON incidents.user_id = users.id 
        WHERE incidents.responder_type = ? OR incidents.accepted_by = ?
        ORDER BY 
            CASE incidents.status WHEN 'pending' THEN 1 WHEN 'accepted' THEN 2 ELSE 3 END,
            incidents.created_at DESC
    ");
    $stmt->execute([$responder_type, $_SESSION['user_id']]);
}
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build base URL for images
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_path = dirname(dirname($_SERVER['SCRIPT_NAME']));
$base_url = "{$protocol}://{$host}{$base_path}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Responder Dashboard - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
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
        .btn-row {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn-row .btn { flex: 1; }
        .btn-notifications {
            border: 1px solid #7B7BE0;
            color: #7B7BE0;
            background: white;
        }
        .btn-logout {
            border: 1px solid #dc3545;
            color: #dc3545;
            background: white;
        }
        .section-title {
            color: #7B7BE0;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .incident-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .info-row {
            display: flex;
            margin-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 12px;
        }
        .info-col { flex: 1; }
        .info-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .status-pending { background: #FF9800; }
        .status-accepted { background: #00BCD4; }
        .status-completed { background: #4CAF50; }
        .status-declined { background: #9E9E9E; }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        .btn-accept {
            flex: 1;
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-decline {
            flex: 1;
            background: white;
            color: #F44336;
            border: 2px solid #F44336;
            padding: 12px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-complete {
            width: 100%;
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 25px;
            font-weight: 600;
        }
        .location-buttons {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        .btn-map {
            flex: 1;
            background: white;
            color: #00BCD4;
            border: 2px solid #00BCD4;
            padding: 10px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
        }
        .btn-directions {
            flex: 1;
            background: #00BCD4;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
        }
        .incident-image {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 12px;
            cursor: pointer;
        }
        .accepted-box {
            background: rgba(0, 188, 212, 0.1);
            border: 2px solid #00BCD4;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            color: #00BCD4;
            font-weight: bold;
            margin-bottom: 12px;
        }
        .no-incidents {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        .no-incidents i { font-size: 48px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="main-container">
    <div class="header-logo">
        <i class="fas fa-shield-alt"></i>
    </div>
    <div class="header-title">Responder Dashboard</div>
    
    <div class="btn-row">
        <a href="notifications.php" class="btn btn-notifications position-relative">
            <i class="fas fa-bell"></i> Notifications
            <?php if ($notification_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $notification_count ?></span>
            <?php endif; ?>
        </a>
        <a href="logout.php" class="btn btn-logout" onclick="return confirm('Logout?');">Logout</a>
    </div>
    
    <?= $message ?>
    
    <div class="section-title">Incident Reports</div>
    
    <?php if (empty($incidents)): ?>
        <div class="no-incidents">
            <i class="fas fa-inbox"></i>
            <p>No incidents found</p>
        </div>
    <?php else: ?>
        <?php foreach ($incidents as $incident): 
            $status = $incident['status'] ?: 'pending';
            $isAcceptedByMe = $incident['accepted_by'] == $_SESSION['user_id'];
            $isAssignedToMe = !empty($incident['assigned_to']) && $incident['assigned_to'] == $_SESSION['user_id'];
            $lat = $incident['latitude'];
            $lng = $incident['longitude'];
            
            // Image URL
            $image_url = '';
            if (!empty($incident['image_path'])) {
                $img_path = preg_replace('/^\.\.\//', '', $incident['image_path']);
                $img_path = preg_replace('/^\.\//', '', $img_path);
                $image_url = "{$base_url}/{$img_path}";
            }
        ?>
        <div class="incident-card">
            <!-- Reporter & Type -->
            <div class="info-row">
                <div class="info-col">
                    <div class="info-label">Reporter</div>
                    <div class="info-value"><?= htmlspecialchars($incident['reporter']) ?></div>
                </div>
                <div class="info-col">
                    <div class="info-label">Type</div>
                    <div class="info-value"><?= htmlspecialchars($incident['type']) ?></div>
                </div>
            </div>
            
            <!-- Description -->
            <div class="info-row">
                <div class="info-col">
                    <div class="info-label">Description</div>
                    <div class="info-value"><?= htmlspecialchars($incident['description'] ?: '[Auto-detected incident]') ?></div>
                </div>
            </div>
            
            <!-- Status & Actions -->
            <div class="info-row" style="flex-direction: column;">
                <div class="info-label">Status / Action</div>
                <span class="status-badge status-<?= $status ?>"><?= ucfirst($status) ?></span>
                
                <?php if ($status === 'pending'): ?>
                    <?php if ($isAssignedToMe): ?>
                    <div class="assigned-box" style="background: rgba(123, 123, 224, 0.1); border: 2px solid #7B7BE0; border-radius: 8px; padding: 12px; text-align: center; color: #7B7BE0; font-weight: bold; margin: 8px 0;">
                        <i class="fas fa-user-tag"></i> Assigned to you - Please respond
                    </div>
                    <?php endif; ?>
                    <div class="action-buttons">
                        <form method="post" action="accept_request.php" style="flex:1; display:flex;">
                            <input type="hidden" name="incident_id" value="<?= $incident['id'] ?>">
                            <input type="hidden" name="accept_incident" value="1">
                            <button type="submit" class="btn-accept" onclick="return confirm('Accept this incident?');">
                                <i class="fas fa-check"></i> Accept
                            </button>
                        </form>
                        <button type="button" class="btn-decline" onclick="showDeclineModal(<?= $incident['id'] ?>, '<?= htmlspecialchars($incident['type']) ?>')">
                            <i class="fas fa-times"></i> Decline
                        </button>
                    </div>
                <?php elseif ($status === 'accepted' && $isAcceptedByMe): ?>
                    <div class="accepted-box">
                        <i class="fas fa-check-circle"></i> Accepted by you
                    </div>
                    <form method="post" action="accept_request.php">
                        <input type="hidden" name="incident_id" value="<?= $incident['id'] ?>">
                        <input type="hidden" name="complete_incident" value="1">
                        <button type="submit" class="btn-complete" onclick="return confirm('Mark as complete?');">
                            <i class="fas fa-check-circle"></i> Mark as Complete
                        </button>
                    </form>
                <?php elseif ($status === 'accepted'): ?>
                    <div class="text-warning mt-2"><i class="fas fa-user-clock"></i> Accepted by another responder</div>
                <?php elseif ($status === 'completed'): ?>
                    <div class="text-success mt-2"><i class="fas fa-check-circle"></i> Completed <?= $isAcceptedByMe ? '(by you)' : '' ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Date & Location -->
            <div class="info-row">
                <div class="info-col">
                    <div class="info-label">Date</div>
                    <div class="info-value"><?= htmlspecialchars($incident['created_at']) ?></div>
                </div>
                <div class="info-col">
                    <div class="info-label">Location</div>
                    <div class="info-value"><?= $lat && $lng ? number_format($lat, 4) . ', ' . number_format($lng, 4) : 'N/A' ?></div>
                </div>
            </div>
            
            <!-- Location Buttons -->
            <?php if ($lat && $lng): ?>
            <div class="location-buttons">
                <a href="https://www.google.com/maps?q=<?= $lat ?>,<?= $lng ?>" target="_blank" class="btn-map">
                    <i class="fas fa-map"></i> View Map
                </a>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $lat ?>,<?= $lng ?>" target="_blank" class="btn-directions">
                    <i class="fas fa-directions"></i> Directions
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Image -->
            <?php if ($image_url): ?>
            <div class="info-row" style="flex-direction: column; border-bottom: none;">
                <div class="info-label">Image</div>
                <img src="<?= htmlspecialchars($image_url) ?>" alt="Incident" class="incident-image" onclick="showImageModal('<?= htmlspecialchars($image_url) ?>')">
            </div>
            <?php endif; ?>
            
            <!-- Responder Type -->
            <div class="info-row" style="border-bottom: none;">
                <div class="info-col">
                    <div class="info-label">Responder Type</div>
                    <div class="info-value"><?= htmlspecialchars(strtoupper($incident['responder_type'] ?? 'N/A')) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Decline Modal -->
<div class="modal fade" id="declineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Decline Incident</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="decline_request.php">
                <div class="modal-body">
                    <input type="hidden" name="incident_id" id="declineIncidentId">
                    <input type="hidden" name="decline_incident" value="1">
                    <p>Declining <strong id="declineIncidentType"></strong> incident.</p>
                    <div class="mb-3">
                        <label class="form-label">Reason:</label>
                        <select class="form-select mb-2" onchange="document.getElementById('declineReason').value = this.value === 'other' ? '' : this.value;">
                            <option value="">-- Select --</option>
                            <option value="Currently responding to another incident">Currently responding to another incident</option>
                            <option value="Too far from location">Too far from location</option>
                            <option value="Not enough resources">Not enough resources</option>
                            <option value="Off duty">Off duty</option>
                            <option value="other">Other</option>
                        </select>
                        <textarea class="form-control" id="declineReason" name="decline_reason" rows="3" placeholder="Enter reason..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Decline</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-body text-center p-0">
                <img id="modalImage" src="" class="img-fluid" style="border-radius: 8px;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showDeclineModal(id, type) {
    document.getElementById('declineIncidentId').value = id;
    document.getElementById('declineIncidentType').textContent = type;
    document.getElementById('declineReason').value = '';
    new bootstrap.Modal(document.getElementById('declineModal')).show();
}
function showImageModal(url) {
    document.getElementById('modalImage').src = url;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}

// ============================================
// ONLINE STATUS TRACKING FOR RESPONDERS
// ============================================
const USER_ID = <?= json_encode($_SESSION['user_id']) ?>;
let onlineStatusInterval = null;

// Update online status
function updateOnlineStatus(isOnline = true) {
    const formData = new FormData();
    formData.append('user_id', USER_ID);
    formData.append('is_online', isOnline ? 1 : 0);
    formData.append('device_info', 'Web Browser - Responder Dashboard');
    
    fetch('../api_update_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Online status updated:', isOnline ? 'online' : 'offline');
        }
    })
    .catch(error => {
        console.error('Failed to update online status:', error);
    });
}

// Start online status tracking
function startOnlineStatusTracking() {
    // Update immediately on page load
    updateOnlineStatus(true);
    
    // Update every 2 minutes (120000ms)
    onlineStatusInterval = setInterval(function() {
        updateOnlineStatus(true);
    }, 120000);
}

// Stop online status tracking and mark as offline
function stopOnlineStatusTracking() {
    if (onlineStatusInterval) {
        clearInterval(onlineStatusInterval);
        onlineStatusInterval = null;
    }
    updateOnlineStatus(false);
}

// Start tracking when page loads
document.addEventListener('DOMContentLoaded', function() {
    startOnlineStatusTracking();
});

// Mark as offline when page is closed/navigated away
window.addEventListener('beforeunload', function() {
    // Use sendBeacon for reliable offline status update
    const formData = new FormData();
    formData.append('user_id', USER_ID);
    formData.append('is_online', 0);
    formData.append('device_info', 'Web Browser - Responder Dashboard');
    navigator.sendBeacon('../api_update_status.php', formData);
});

// Handle visibility change (tab switching)
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        updateOnlineStatus(true);
    }
});
</script>
</body>
</html>
