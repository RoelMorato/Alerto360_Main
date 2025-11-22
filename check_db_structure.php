<?php
session_start();
require 'db_connect.php';

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

echo "<h3>Database Structure Check</h3>";

// Check incidents table structure
try {
    $stmt = $pdo->query("DESCRIBE incidents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Incidents Table Structure:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check current incident data
    echo "<h4>Current Incident #12 Data:</h4>";
    $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = 12");
    $stmt->execute();
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($incident) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($incident as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "Incident #12 not found!";
    }
    
    // Test manual update
    echo "<h4>Manual Update Test:</h4>";
    $test_stmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = 12");
    $result = $test_stmt->execute(['accept and complete']);
    echo "Update result: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
    
    // Check again
    $check_stmt = $pdo->prepare("SELECT status FROM incidents WHERE id = 12");
    $check_stmt->execute();
    $new_status = $check_stmt->fetchColumn();
    echo "New status: '" . htmlspecialchars($new_status ?? 'NULL') . "'<br>";
    echo "Status length: " . strlen($new_status ?? '') . " characters<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<br><a href='admin_dashboard.php'>Back to Admin Dashboard</a>";
?>
