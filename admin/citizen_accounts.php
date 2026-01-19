<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

$msg = '';
// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM user_online_status WHERE user_id = ?")->execute([$id]);
        // Keep incidents but remove user reference
        $pdo->prepare("UPDATE incidents SET user_id = NULL WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'citizen'")->execute([$id]);
        $pdo->commit();
        $msg = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Citizen deleted successfully.</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Error: ' . $e->getMessage() . '</div>';
    }
}

// Get citizens with detailed stats
$citizens = $pdo->query("
    SELECT u.*, 
        COUNT(i.id) as total_reports,
        SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
        SUM(CASE WHEN i.status IN ('accepted', 'in_progress') THEN 1 ELSE 0 END) as active_reports,
        SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_reports,
        SUM(CASE WHEN i.status = 'declined' THEN 1 ELSE 0 END) as declined_reports
    FROM users u 
    LEFT JOIN incidents i ON u.id = i.user_id
    WHERE u.role = 'citizen' 
    GROUP BY u.id, u.name, u.email, u.created_at, u.email_verified
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_citizens = count($citizens);
$total_reports = array_sum(array_column($citizens, 'total_reports'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Citizen Accounts - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
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
            flex-wrap: wrap;
            gap: 16px;
        }
        .header h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .stat-icon.purple { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-info h3 { font-size: 28px; font-weight: 700; margin: 0; color: var(--gray-800); }
        .stat-info p { font-size: 14px; color: var(--gray-600); margin: 0; }
        
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
            width: 250px;
            font-size: 14px;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
        }
        
        .citizens-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        .citizen-card {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--gray-200);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .citizen-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .citizen-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }
        .citizen-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: white;
        }
        .citizen-info { flex: 1; }
        .citizen-info h4 { font-size: 16px; font-weight: 600; margin: 0 0 4px; color: var(--gray-800); }
        .citizen-info p { font-size: 13px; color: var(--gray-600); margin: 0; }
        .verified-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .verified { background: #d1fae5; color: #059669; }
        .unverified { background: #fee2e2; color: #dc2626; }
        
        .citizen-stats {
            margin-bottom: 16px;
        }
        .mini-stat {
            text-align: center;
            padding: 12px;
            background: white;
            border-radius: 10px;
        }
        .mini-stat .num { font-size: 20px; font-weight: 700; }
        .mini-stat .num.orange { color: #f59e0b; }
        .mini-stat .num.green { color: #10b981; }
        .mini-stat .label { font-size: 11px; color: var(--gray-600); text-transform: uppercase; }
        
        .citizen-meta {
            font-size: 12px;
            color: var(--gray-600);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .citizen-actions { display: flex; gap: 8px; }
        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: opacity 0.2s;
            text-decoration: none;
        }
        .btn-action:hover { opacity: 0.85; }
        .btn-view { background: var(--primary); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-600);
            grid-column: 1/-1;
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        
        .alert {
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .citizens-grid { grid-template-columns: 1fr; }
            .search-box input { width: 100%; }
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="header">
        <h1><i class="bi bi-people"></i> Citizen Accounts</h1>
        <a href="admin_dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
    
    <?= $msg ?>
    
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <h3><?= $total_citizens ?></h3>
                <p>Total Citizens</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <h3><?= array_sum(array_column($citizens, 'completed_reports')) ?></h3>
                <p>Completed Reports</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-clock"></i></div>
            <div class="stat-info">
                <h3><?= array_sum(array_column($citizens, 'pending_reports')) ?></h3>
                <p>Pending Reports</p>
            </div>
        </div>
    </div>
    
    <div class="content-card">
        <div class="card-header">
            <h2><i class="bi bi-list-ul"></i> All Citizens</h2>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Search citizens..." onkeyup="filterCitizens()">
            </div>
        </div>
        
        <div class="citizens-grid" id="citizensGrid">
            <?php if (empty($citizens)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h4>No Citizens Yet</h4>
                    <p>Citizens will appear here when they register.</p>
                </div>
            <?php else: ?>
                <?php foreach ($citizens as $c): 
                    $initials = strtoupper(substr($c['name'], 0, 2));
                    $verified = !empty($c['email_verified']);
                ?>
                <div class="citizen-card" data-name="<?= strtolower($c['name']) ?>" data-email="<?= strtolower($c['email']) ?>">
                    <div class="citizen-header">
                        <div class="citizen-avatar"><?= $initials ?></div>
                        <div class="citizen-info">
                            <h4><?= htmlspecialchars($c['name']) ?></h4>
                            <p><?= htmlspecialchars($c['email']) ?></p>
                        </div>
                        <span class="verified-badge <?= $verified ? 'verified' : 'unverified' ?>">
                            <?= $verified ? '✓ Verified' : 'Unverified' ?>
                        </span>
                    </div>
                    
                    <div class="citizen-stats">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                            <div class="mini-stat">
                                <div class="num green"><?= $c['completed_reports'] ?></div>
                                <div class="label">Completed</div>
                            </div>
                            <div class="mini-stat">
                                <div class="num orange"><?= $c['pending_reports'] ?></div>
                                <div class="label">Pending</div>
                            </div>
                            <div class="mini-stat">
                                <div class="num"><?= $c['total_reports'] ?></div>
                                <div class="label">Total</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="citizen-meta">
                        <i class="bi bi-calendar3"></i>
                        Joined <?= date('M d, Y', strtotime($c['created_at'])) ?>
                    </div>
                    
                    <div class="citizen-actions">
                        <a href="citizen_history.php?id=<?= $c['id'] ?>" class="btn-action btn-view"><i class="bi bi-eye"></i> View Reports</a>
                        <form method="post" style="flex:1;display:flex;" onsubmit="return confirm('Delete this citizen?')">
                            <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-action btn-delete" style="width:100%"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterCitizens() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.citizen-card').forEach(card => {
        const name = card.dataset.name;
        const email = card.dataset.email;
        card.style.display = (name.includes(search) || email.includes(search)) ? '' : 'none';
    });
}
</script>
</body>
</html>
