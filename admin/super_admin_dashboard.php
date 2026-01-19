<?php
/**
 * Super Admin Dashboard
 * Master control panel for managing admins, responders, and citizens
 */
session_start();
require '../db_connect.php';
require '../notification_functions.php';

// Check if user is super_admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

// Get counts
$admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$responder_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'responder'")->fetchColumn();
$citizen_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('user', 'citizen')")->fetchColumn();
$incident_count = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
$pending_count = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'pending' OR status IS NULL")->fetchColumn();

// Get notification count
$notification_count = getNotificationCount($pdo, $_SESSION['user_id']);

// Get recent incidents
$recent_incidents = $pdo->query("
    SELECT incidents.*, users.name AS reporter 
    FROM incidents 
    JOIN users ON incidents.user_id = users.id 
    ORDER BY incidents.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent users
$recent_users = $pdo->query("
    SELECT id, name, email, role, responder_type, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        .dashboard-container {
            padding: 2rem;
        }
        .super-header {
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(233, 69, 96, 0.3);
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        .stat-icon.admin { background: linear-gradient(135deg, #e94560, #ff6b6b); color: white; }
        .stat-icon.responder { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-icon.citizen { background: linear-gradient(135deg, #11998e, #38ef7d); color: white; }
        .stat-icon.incident { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
        .stat-icon.pending { background: linear-gradient(135deg, #ffc107, #ff9800); color: white; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #1a1a2e; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .action-card:hover {
            transform: translateX(10px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.15);
        }
        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .section-title {
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e94560;
        }
        .badge-role {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Header -->
    <div class="super-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-shield-lock-fill"></i> Super Admin Dashboard</h2>
                <p class="mb-0 opacity-75">Welcome, <?= htmlspecialchars($_SESSION['name']) ?> | Master Control Panel</p>
            </div>
            <div>
                <a href="../notifications.php" class="btn btn-light btn-sm position-relative me-2">
                    <i class="bi bi-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $notification_count ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="../logout.php" class="btn btn-outline-light btn-sm" onclick="return confirm('Logout?');">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="stat-icon admin"><i class="bi bi-person-gear"></i></div>
                <div class="stat-number"><?= $admin_count ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="stat-icon responder"><i class="bi bi-shield-check"></i></div>
                <div class="stat-number"><?= $responder_count ?></div>
                <div class="stat-label">Responders</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="stat-icon citizen"><i class="bi bi-people"></i></div>
                <div class="stat-number"><?= $citizen_count ?></div>
                <div class="stat-label">Citizens</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon incident"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="stat-number"><?= $incident_count ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon pending"><i class="bi bi-clock-history"></i></div>
                <div class="stat-number"><?= $pending_count ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="section-card">
                <h5 class="section-title"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                
                <a href="admin_accounts.php" class="action-card">
                    <div class="d-flex align-items-center">
                        <div class="action-icon" style="background: linear-gradient(135deg, #e94560, #ff6b6b);">
                            <i class="bi bi-person-gear"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">Manage Admins</h6>
                            <small class="text-muted">Add, edit, delete admin accounts</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </div>
                </a>

                <a href="responder_accounts.php" class="action-card">
                    <div class="d-flex align-items-center">
                        <div class="action-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">Manage Responders</h6>
                            <small class="text-muted">BFP, PNP, MDDRMO accounts</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </div>
                </a>

                <a href="citizen_accounts.php" class="action-card">
                    <div class="d-flex align-items-center">
                        <div class="action-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">Manage Citizens</h6>
                            <small class="text-muted">View registered citizens</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </div>
                </a>

                <a href="admin_dashboard.php" class="action-card">
                    <div class="d-flex align-items-center">
                        <div class="action-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">Incident Reports</h6>
                            <small class="text-muted">View all incident reports</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </div>
                </a>

                <a href="online_users.php" class="action-card">
                    <div class="d-flex align-items-center">
                        <div class="action-icon" style="background: linear-gradient(135deg, #00c6ff, #0072ff);">
                            <i class="bi bi-broadcast"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">Online Users</h6>
                            <small class="text-muted">See who's currently online</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="col-lg-8">
            <div class="section-card">
                <h5 class="section-title"><i class="bi bi-person-plus"></i> Recent Users</h5>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><small><?= htmlspecialchars($user['email']) ?></small></td>
                                <td>
                                    <?php
                                    $role_class = 'bg-secondary';
                                    $role_text = ucfirst($user['role']);
                                    if ($user['role'] === 'admin') $role_class = 'bg-danger';
                                    elseif ($user['role'] === 'super_admin') $role_class = 'bg-dark';
                                    elseif ($user['role'] === 'responder') {
                                        $role_class = 'bg-primary';
                                        $role_text = $user['responder_type'] ?? 'Responder';
                                    }
                                    elseif (in_array($user['role'], ['user', 'citizen'])) $role_class = 'bg-success';
                                    ?>
                                    <span class="badge badge-role <?= $role_class ?>"><?= $role_text ?></span>
                                </td>
                                <td><small><?= date('M j, Y', strtotime($user['created_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Incidents -->
            <div class="section-card">
                <h5 class="section-title"><i class="bi bi-exclamation-triangle"></i> Recent Incidents</h5>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Reporter</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_incidents as $incident): ?>
                            <tr>
                                <td><?= htmlspecialchars($incident['reporter']) ?></td>
                                <td><?= htmlspecialchars($incident['type']) ?></td>
                                <td>
                                    <?php
                                    $status = $incident['status'] ?: 'pending';
                                    $status_class = 'bg-warning text-dark';
                                    if ($status === 'accepted') $status_class = 'bg-info';
                                    elseif (in_array($status, ['done', 'completed'])) $status_class = 'bg-success';
                                    elseif ($status === 'declined') $status_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= $status ?></span>
                                </td>
                                <td><small><?= date('M j, g:i A', strtotime($incident['created_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
