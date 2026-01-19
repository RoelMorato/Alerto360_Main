<?php
session_start();
require '../db_connect.php';

// Check if user is admin or super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit;
}

// Get incident statistics by type
$incident_by_type = $pdo->query("
    SELECT type, COUNT(*) as count 
    FROM incidents 
    GROUP BY type 
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get incident statistics by status
$incident_by_status = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM incidents 
    GROUP BY status 
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get incident statistics by responder type
$incident_by_responder = $pdo->query("
    SELECT responder_type, COUNT(*) as count 
    FROM incidents 
    WHERE responder_type IS NOT NULL AND responder_type != ''
    GROUP BY responder_type 
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get monthly incident trends (last 6 months)
$monthly_trends = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM incidents
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get total counts
$total_incidents = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
$total_pending = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'pending'")->fetchColumn();
$total_completed = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('completed', 'resolved')")->fetchColumn();
$total_declined = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'declined'")->fetchColumn();

// Prepare data for charts
$type_labels = json_encode(array_column($incident_by_type, 'type'));
$type_data = json_encode(array_column($incident_by_type, 'count'));

$status_labels = json_encode(array_column($incident_by_status, 'status'));
$status_data = json_encode(array_column($incident_by_status, 'count'));

$responder_labels = json_encode(array_column($incident_by_responder, 'responder_type'));
$responder_data = json_encode(array_column($incident_by_responder, 'count'));

$month_labels = json_encode(array_column($monthly_trends, 'month'));
$month_data = json_encode(array_column($monthly_trends, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statistics & Reports - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 2rem;
        }
        .page-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
        }
        canvas {
            max-height: 400px;
        }
    </style>
</head>
<body>
<div class="page-container">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-0"><i class="bi bi-graph-up"></i> Statistics & Reports</h3>
                <small class="opacity-75">Incident analytics and trends</small>
            </div>
            <a href="admin_dashboard.php" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 4px solid #667eea;">
                <p class="stat-number" style="color: #667eea;"><?= number_format($total_incidents) ?></p>
                <p class="stat-label">Total Incidents</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 4px solid #ff9800;">
                <p class="stat-number" style="color: #ff9800;"><?= number_format($total_pending) ?></p>
                <p class="stat-label">Pending</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 4px solid #4caf50;">
                <p class="stat-number" style="color: #4caf50;"><?= number_format($total_completed) ?></p>
                <p class="stat-label">Completed</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 4px solid #f44336;">
                <p class="stat-number" style="color: #f44336;"><?= number_format($total_declined) ?></p>
                <p class="stat-label">Declined</p>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title"><i class="bi bi-pie-chart"></i> Incidents by Type</h5>
                <canvas id="typeChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title"><i class="bi bi-pie-chart"></i> Incidents by Status</h5>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title"><i class="bi bi-pie-chart"></i> Incidents by Responder Type</h5>
                <canvas id="responderChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title"><i class="bi bi-graph-up"></i> Monthly Incident Trends</h5>
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Color palettes
const typeColors = ['#1a1a2e', '#2196f3', '#f44336', '#795548', '#ff9800', '#9c27b0', '#607d8b'];
const statusColors = ['#ff9800', '#2196f3', '#4caf50', '#f44336', '#9e9e9e'];
const responderColors = ['#f44336', '#2196f3', '#4caf50'];

// Incidents by Type - Pie Chart
new Chart(document.getElementById('typeChart'), {
    type: 'pie',
    data: {
        labels: <?= $type_labels ?>,
        datasets: [{
            data: <?= $type_data ?>,
            backgroundColor: typeColors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Incidents by Status - Pie Chart
new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: <?= $status_labels ?>,
        datasets: [{
            data: <?= $status_data ?>,
            backgroundColor: statusColors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Incidents by Responder Type - Doughnut Chart
new Chart(document.getElementById('responderChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $responder_labels ?>,
        datasets: [{
            data: <?= $responder_data ?>,
            backgroundColor: responderColors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Monthly Trends - Line Chart
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= $month_labels ?>,
        datasets: [{
            label: 'Incidents',
            data: <?= $month_data ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
