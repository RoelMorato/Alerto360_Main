<?php
session_start();
require 'db_connect.php';

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

echo "<h3>Debug: Incident Status Check</h3>";

// Get all incidents and their current status
$stmt = $pdo->query("SELECT id, status, created_at FROM incidents ORDER BY created_at DESC LIMIT 10");
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Status</th><th>Created At</th></tr>";

foreach ($incidents as $incident) {
    echo "<tr>";
    echo "<td>" . $incident['id'] . "</td>";
    echo "<td>" . htmlspecialchars($incident['status'] ?: 'NULL/EMPTY') . "</td>";
    echo "<td>" . $incident['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test manual status update
if (isset($_GET['test_update']) && isset($_GET['incident_id'])) {
    $test_id = intval($_GET['incident_id']);
    $update_stmt = $pdo->prepare("UPDATE incidents SET status = 'accept and complete' WHERE id = ?");
    $result = $update_stmt->execute([$test_id]);
    
    echo "<br><strong>Manual Update Test for ID $test_id:</strong> " . ($result ? "SUCCESS" : "FAILED");
    
    // Check the result
    $check_stmt = $pdo->prepare("SELECT status FROM incidents WHERE id = ?");
    $check_stmt->execute([$test_id]);
    $new_status = $check_stmt->fetchColumn();
    
    echo "<br><strong>New Status:</strong> " . htmlspecialchars($new_status ?: 'NULL/EMPTY');
}

echo "<br><br><a href='debug_status.php?test_update=1&incident_id=1'>Test Update Incident ID 1</a>";
echo "<br><a href='admin_dashboard.php'>Back to Admin Dashboard</a>";
?>
