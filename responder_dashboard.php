<?php

session_start();
require 'db_connect.php';
require 'notification_functions.php';

// Only allow responders
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'responder') {
    header('Location: login.php');
    exit;
}

// Get responder type for the logged-in responder
$responder_stmt = $pdo->prepare("SELECT responder_type FROM users WHERE id = ?");
$responder_stmt->execute([$_SESSION['user_id']]);
$responder = $responder_stmt->fetch(PDO::FETCH_ASSOC);
if (!$responder) {
    die('Responder not found. Invalid user session.');
}
$responder_type = $responder['responder_type'] ?? '';

// Display messages from acceptance and completion process
$message = '';
if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success">';
    $message .= '<h5><i class="fas fa-check-circle"></i> Success!</h5>';
    $message .= htmlspecialchars($_SESSION['success_message']);
    if (isset($_SESSION['completed_incident_id'])) {
        $message .= '<br><strong>Incident #' . $_SESSION['completed_incident_id'] . '</strong> has been marked as completed.';
    }
    $message .= '</div>';
    unset($_SESSION['success_message']);
    unset($_SESSION['completed_incident_id']);
    unset($_SESSION['responder_name']);
}
if (isset($_SESSION['error_message'])) {
    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}

// Accept incident (with status check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_incident_id'])) {
    $accept_id = intval($_POST['accept_incident_id']);
    // Check if already accepted
    $incident_check = $pdo->prepare("SELECT status FROM incidents WHERE id = ?");
    $incident_check->execute([$accept_id]);
    $row = $incident_check->fetch(PDO::FETCH_ASSOC);
    $current_status = $row['status'] ?? '';
    if ($current_status !== 'pending' && $current_status !== '' && $current_status !== null) {
        die('Incident already accepted or resolved.');
    }
    $pdo->prepare("UPDATE incidents SET status = 'accepted', accepted_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $accept_id]);
    header("Location: responder_dashboard.php");
    exit;
}

// Mark as resolved
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_incident_id'])) {
    $resolve_id = intval($_POST['resolve_incident_id']);
    $pdo->prepare("UPDATE incidents SET status = 'resolved' WHERE id = ?")->execute([$resolve_id]);
    header("Location: responder_dashboard.php");
    exit;
}

// Get notification count for this responder
$notification_count = getNotificationCount($pdo, $_SESSION['user_id']);

// Fetch only incidents for this responder type
$stmt = $pdo->prepare("SELECT incidents.*, users.name AS reporter FROM incidents JOIN users ON incidents.user_id = users.id WHERE incidents.responder_type = ? ORDER BY incidents.created_at DESC");
$stmt->execute([$responder_type]);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Responder Dashboard - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 0;
        }
        .dashboard-container {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.15);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 1100px;
            width: 100%;
        }
        .dashboard-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem auto;
        }
        .dashboard-logo svg {
            width: 32px;
            height: 32px;
            color: #fff;
        }
        .section-title {
            font-weight: 600;
            color: #7b7be0;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .incident-img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
        }
        .image-container {
            position: relative;
            display: inline-block;
        }
        .image-container:hover .incident-img {
            transform: scale(1.05);
            border-color: #7b7be0;
            box-shadow: 0 4px 12px rgba(123, 123, 224, 0.3);
        }
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 8px;
            color: white;
            font-size: 1.2rem;
        }
        .image-container:hover .image-overlay {
            opacity: 1;
        }
        .coords {
            font-size: 0.95em;
            color: #444;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="dashboard-logo">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5.25-3.5 9.74-7 11-3.5-1.26-7-5.75-7-11V7l7-4z"/></svg>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="notifications.php" class="btn btn-outline-primary btn-sm position-relative">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($notification_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $notification_count ?>
                        <span class="visually-hidden">unread notifications</span>
                    </span>
                <?php endif; ?>
            </a>
        </div>
        <h2 class="fw-bold mb-0">Responder Dashboard</h2>
        <a href="logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to logout?');">Logout</a>
    </div>
    <?= $message ?>
    <div class="card p-4">
        <h5 class="section-title mb-3">Incident Reports</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reporter</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Status / Action</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Image</th>
                        <th>Responder</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($incidents as $incident): ?>
                    <?php
                    // Defensive: treat blank status as 'pending'
                    $status = $incident['status'] ?: 'pending';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($incident['reporter']) ?></td>
                        <td><?= htmlspecialchars($incident['type']) ?></td>
                        <td><?= htmlspecialchars($incident['description']) ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($status) {
                                    case 'pending': echo 'warning'; break;
                                    case 'accepted': echo 'info'; break;
                                    case 'completed': echo 'success'; break;
                                    case 'resolved': echo 'secondary'; break;
                                    default: echo 'light';
                                }
                            ?>"><?= ucfirst(htmlspecialchars($status)) ?></span>
                            
                            <?php if ($status === 'pending'): ?>
                                <!-- Step 1: Accept Button -->
                                <form method="post" action="accept_request.php" style="display:inline;" class="mt-2">
                                    <input type="hidden" name="incident_id" value="<?= $incident['id'] ?>">
                                    <input type="hidden" name="accept_incident" value="1">
                                    <button type="submit" class="btn btn-primary btn-sm" 
                                            onclick="return confirm('Accept this incident?\n\nThis will:\n• Assign the incident to you\n• Show directions to location\n• Allow you to complete it later\n\nProceed?');">
                                        <i class="fas fa-hand-paper"></i> Accept
                                    </button>
                                </form>
                                
                                <!-- Preview Location Button -->
                                <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                                    <button type="button" class="btn btn-outline-info btn-sm mt-1" 
                                            onclick="showMapPreview(<?= $incident['id'] ?>, '<?= htmlspecialchars($incident['latitude']) ?>', '<?= htmlspecialchars($incident['longitude']) ?>', '<?= htmlspecialchars($incident['type']) ?>', '<?= htmlspecialchars($incident['description']) ?>')">
                                        <i class="fas fa-map-marker-alt"></i> View Location
                                    </button>
                                <?php endif; ?>
                                
                            <?php elseif ($status === 'accepted' && $incident['accepted_by'] == $_SESSION['user_id']): ?>
                                <!-- Step 2: Complete Button (only for responder who accepted) -->
                                <div class="mt-2">
                                    <div class="text-info mb-2">
                                        <i class="fas fa-user-check"></i> <strong>Accepted by you</strong>
                                    </div>
                                    
                                    <!-- Get Directions Button -->
                                    <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" 
                                           target="_blank" class="btn btn-info btn-sm">
                                            <i class="fas fa-directions"></i> Get Directions
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Complete Button -->
                                    <form method="post" action="accept_request.php" style="display:inline;" class="ms-1">
                                        <input type="hidden" name="incident_id" value="<?= $incident['id'] ?>">
                                        <input type="hidden" name="complete_incident" value="1">
                                        <button type="submit" class="btn btn-success btn-sm" 
                                                onclick="return confirm('Mark this incident as completed?\n\nThis will:\n• Mark the incident as COMPLETED\n• Notify all admins automatically\n• Show as DONE in admin dashboard\n\nProceed?');">
                                            <i class="fas fa-check-circle"></i> Complete
                                        </button>
                                    </form>
                                </div>
                                
                            <?php elseif ($status === 'accepted'): ?>
                                <div class="text-warning mt-2">
                                    <i class="fas fa-user-clock"></i> <strong>Accepted by another responder</strong>
                                </div>
                                
                            <?php elseif ($status === 'completed'): ?>
                                <div class="text-success mt-2">
                                    <i class="fas fa-check-circle"></i> <strong>Completed</strong>
                                    <?php if ($incident['accepted_by'] == $_SESSION['user_id']): ?>
                                        <br><small class="text-muted">by you</small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($incident['created_at']) ?></td>
                        <td>
                            <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                                <?php if ($status === 'accepted' && $incident['accepted_by'] == $_SESSION['user_id']): ?>
                                    <div class="coords">
                                        <?= htmlspecialchars($incident['latitude']) ?>, <?= htmlspecialchars($incident['longitude']) ?>
                                    </div>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" target="_blank" class="btn btn-info btn-sm mt-1">Get Directions</a>
                                <?php else: ?>
                                    <a href="https://www.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" target="_blank">View Map</a>
                                <?php endif; ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($incident['image_path'])): ?>
                                <div class="image-container">
                                    <img src="<?= htmlspecialchars($incident['image_path']) ?>" alt="Incident Image" class="incident-img" 
                                         onclick="showImageModal('<?= htmlspecialchars($incident['image_path']) ?>', 'Incident #<?= $incident['id'] ?> - <?= htmlspecialchars($incident['type']) ?>')" 
                                         style="cursor: pointer;" title="Click to view full size">
                                    <div class="image-overlay">
                                        <i class="fas fa-search-plus"></i>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted"><i class="fas fa-image"></i> No image</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($incident['responder_type'] ?? 'N/A') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Incident Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="Incident Photo" class="img-fluid" style="max-height: 70vh; border-radius: 8px;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a id="downloadImageBtn" href="" download class="btn btn-primary">
                    <i class="fas fa-download"></i> Download Image
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Map Preview Modal -->
<div class="modal fade" id="mapPreviewModal" tabindex="-1" aria-labelledby="mapPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapPreviewModalLabel">Incident Location Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Type:</strong> <span id="incidentType" class="badge bg-primary"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Incident ID:</strong> #<span id="incidentId"></span>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Description:</strong>
                    <p id="incidentDescription" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <strong>Location:</strong>
                    <div id="mapContainer" style="height: 300px; border-radius: 8px;"></div>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Review the incident location above. Click "Accept Incident" to proceed with accepting this incident.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" style="display:inline;" id="acceptForm">
                    <input type="hidden" name="accept_incident_id" id="acceptIncidentId">
                    <button type="submit" class="btn btn-primary">Accept Incident</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script>
let map = null;

// Function to show image in modal
function showImageModal(imagePath, title) {
    document.getElementById('modalImage').src = imagePath;
    document.getElementById('imageModalLabel').textContent = title;
    document.getElementById('downloadImageBtn').href = imagePath;
    
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
}

function showMapPreview(incidentId, latitude, longitude, type, description) {
    // Set incident details
    document.getElementById('incidentId').textContent = incidentId;
    document.getElementById('incidentType').textContent = type;
    document.getElementById('incidentDescription').textContent = description;
    document.getElementById('acceptIncidentId').value = incidentId;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('mapPreviewModal'));
    modal.show();
    
    // Initialize map after modal is shown
    setTimeout(() => {
        if (map) {
            map.remove();
        }
        
        if (latitude && longitude) {
            // Initialize map with incident location
            map = L.map('mapContainer').setView([parseFloat(latitude), parseFloat(longitude)], 15);
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add marker for incident location
            const marker = L.marker([parseFloat(latitude), parseFloat(longitude)]).addTo(map);
            marker.bindPopup(`<b>${type} Incident</b><br>${description}`).openPopup();
            
            // Add circle to show approximate area
            L.circle([parseFloat(latitude), parseFloat(longitude)], {
                color: 'red',
                fillColor: '#f03',
                fillOpacity: 0.2,
                radius: 100
            }).addTo(map);
        } else {
            // No location data available
            document.getElementById('mapContainer').innerHTML = '<div class="alert alert-warning text-center"><i class="bi bi-geo-alt"></i> No location data available for this incident.</div>';
        }
    }, 300);
}

// Clean up map when modal is hidden
document.getElementById('mapPreviewModal').addEventListener('hidden.bs.modal', function () {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>
</body>
</html>