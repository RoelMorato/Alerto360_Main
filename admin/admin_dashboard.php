<?php
session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Handle responder redirection after acceptance
$acceptance_message = '';
if (isset($_GET['from_acceptance']) && $_GET['from_acceptance'] == '1' && isset($_GET['incident_id'])) {
    $incident_id = intval($_GET['incident_id']);
    // Get incident details
    $incident_stmt = $pdo->prepare("SELECT incidents.*, users.name AS reporter FROM incidents JOIN users ON incidents.user_id = users.id WHERE incidents.id = ?");
    $incident_stmt->execute([$incident_id]);
    $incident = $incident_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($incident) {
        $acceptance_message = '<div class="alert alert-success"><strong>Request Accepted & Completed!</strong><br>'
            . 'Incident #' . $incident_id . ' reported by ' . htmlspecialchars($incident['reporter']) 
            . ' has been successfully accepted and marked as done.</div>';
    }
}

// NOTE: Admins can only VIEW incidents, not complete them.
// Only responders can complete incidents from their dashboard.


// Check if user is admin (implement your own admin check)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}


// Handle status filter
$status_filter = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'pending', 'accepted', 'done', 'resolved', 'accept and complete', 'completed'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Build query based on filter
if ($status_filter === 'all') {
    $stmt = $pdo->query("SELECT incidents.*, users.name AS reporter, responder_users.name AS responder_name FROM incidents JOIN users ON incidents.user_id = users.id LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id ORDER BY incidents.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT incidents.*, users.name AS reporter, responder_users.name AS responder_name FROM incidents JOIN users ON incidents.user_id = users.id LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id WHERE incidents.status = ? ORDER BY incidents.created_at DESC");
    $stmt->execute([$status_filter]);
}
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notification count for this admin
$notification_count = getNotificationCount($pdo, $_SESSION['user_id']);

// Get incident counts for each status
$counts = [];
$count_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM incidents GROUP BY status");
while ($row = $count_stmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status'] ?: 'pending'] = $row['count'];
}
$counts['all'] = array_sum($counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            max-width: 1200px;
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
            margin-top: 2rem;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 4px 16px rgba(44, 62, 80, 0.08);
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .table img {
            border-radius: 8px;
        }
        .status-filter {
            margin-bottom: 1rem;
        }
        .status-badge {
            font-size: 0.875em;
            padding: 0.375rem 0.75rem;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-accepted { background-color: #17a2b8; color: #fff; }
        .status-done { background-color: #28a745; color: #fff; }
        .status-resolved { background-color: #6c757d; color: #fff; }
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
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="dashboard-logo">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5.25-3.5 9.74-7 11-3.5-1.26-7-5.75-7-11V7l7-4z"/></svg>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="../notifications.php" class="btn btn-outline-primary btn-sm position-relative">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($notification_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $notification_count ?>
                        <span class="visually-hidden">unread notifications</span>
                    </span>
                <?php endif; ?>
            </a>
        </div>
        <h2 class="fw-bold mb-0">Admin Dashboard</h2>
        <a href="../logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to logout?');">Logout</a>
    </div>
    <?= $acceptance_message ?>
    
    <!-- Admin Messages -->
    <?php if (isset($_SESSION['admin_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['admin_message']) ?></div>
        <?php unset($_SESSION['admin_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['admin_error']) ?></div>
        <?php unset($_SESSION['admin_error']); ?>
    <?php endif; ?>

    <div class="text-center mb-4">
        <a href="add_responder.php" class="btn btn-primary me-2">Add Responder Account</a>
        <a href="responder_accounts.php" class="btn btn-secondary">Manage Responder Accounts</a>
    </div>

    <!-- Incident Reports -->
    <div class="card p-4 mt-5">
        <h5 class="section-title mb-3">Incident Reports</h5>
                <?php if (!empty($edit_responder_msg)) echo $edit_responder_msg; ?>
                <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Reporter</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($incidents as $incident): ?>
            <tr>
                <td><?= htmlspecialchars($incident['reporter']) ?></td>
                <td><?= htmlspecialchars($incident['type']) ?></td>
                <td><?= htmlspecialchars($incident['description']) ?></td>
                <td>
                    <?php 
                    $status = $incident['status'] ?: 'pending';
                    $status_class = '';
                    switch ($status) {
                        case 'pending':
                            $status_class = 'status-pending';
                            break;
                        case 'accepted':
                            $status_class = 'status-accepted';
                            break;
                        case 'done':
                            $status_class = 'status-done';
                            break;
                        case 'accept and complete':
                            $status_class = 'status-done';
                            break;
                        case 'completed':
                            $status_class = 'status-done';
                            break;
                        case 'resolved':
                            $status_class = 'status-resolved';
                            break;
                        default:
                            $status_class = 'bg-light text-dark';
                    }
                    ?>
                    <span class="badge status-badge <?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                    <?php if ($status === 'accepted' && isset($incident['responder_name'])): ?>
                        <br><small class="text-muted">by <?= htmlspecialchars($incident['responder_name']) ?></small>
                    <?php elseif ($status === 'done' && isset($incident['responder_name'])): ?>
                        <br><small class="text-success">completed by <?= htmlspecialchars($incident['responder_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($incident['created_at']) ?></td>
                <td>
                    <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                        <a href="https://www.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" target="_blank">View Map</a>
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
                    <button class="btn btn-info btn-sm" onclick="viewIncidentDetails(<?= $incident['id'] ?>)">View Details</button>
                    <?php if ($status === 'completed'): ?>
                        <span class="badge bg-success ms-2">
                            <i class="fas fa-check-circle"></i> Completed by Responder
                        </span>
                    <?php elseif ($status === 'pending'): ?>
                        <span class="badge bg-warning ms-2">
                            <i class="fas fa-clock"></i> Awaiting Responder
                        </span>
                    <?php elseif ($status === 'accepted'): ?>
                        <span class="badge bg-info ms-2">
                            <i class="fas fa-user-check"></i> Responder Assigned
                        </span>
                    <?php endif; ?>
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

<!-- Incident Details Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1" aria-labelledby="incidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incidentModalLabel">Incident Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="incidentModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Function to show image in modal
function showImageModal(imagePath, title) {
    document.getElementById('modalImage').src = imagePath;
    document.getElementById('imageModalLabel').textContent = title;
    document.getElementById('downloadImageBtn').href = imagePath;
    
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
}

function viewIncidentDetails(incidentId) {
    // Show loading message
    document.getElementById('incidentModalBody').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('incidentModal'));
    modal.show();
    
    // Fetch incident details
    fetch('../view_incident_details.php?id=' + incidentId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('incidentModalBody').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('incidentModalBody').innerHTML = '<div class="alert alert-danger">Error loading incident details.</div>';
        });
}
</script>
</body>
</html>