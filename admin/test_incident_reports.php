<?php
session_start();
$_SESSION["user_id"] = 8;
$_SESSION["role"] = "admin";
$_SESSION["name"] = "Test Admin";

require "../db_connect.php";

echo "<h1>Test Incident Reports</h1>";

try {
    $stmt = $pdo->query("
        SELECT incidents.*, 
               users.name AS reporter, 
               responder_users.name AS responder_name,
               COALESCE(declined_users.name, '') AS declined_by_name
        FROM incidents 
        JOIN users ON incidents.user_id = users.id 
        LEFT JOIN users AS responder_users ON incidents.accepted_by = responder_users.id 
        LEFT JOIN users AS declined_users ON incidents.declined_by = declined_users.id
        ORDER BY incidents.created_at DESC
        LIMIT 10
    ");
    
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($incidents) . " incidents</p>";
    echo "<table border=1>";
    echo "<tr><th>ID</th><th>Status</th><th>Reporter</th><th>Responder</th><th>Declined By</th></tr>";
    
    foreach ($incidents as $inc) {
        echo "<tr>";
        echo "<td>#{$inc['id']}</td>";
        echo "<td>{$inc['status']}</td>";
        echo "<td>{$inc['reporter']}</td>";
        echo "<td>" . ($inc['responder_name'] ?: '-') . "</td>";
        echo "<td>" . ($inc['declined_by_name'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>