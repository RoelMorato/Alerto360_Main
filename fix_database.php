<?php
/**
 * Database Fix Script for Alerto360
 * This script adds missing columns to fix the "accepted_at" and "completed_at" error
 */

require 'db_connect.php';

echo "<h2>Alerto360 Database Fix Script</h2>";
echo "<p>Adding missing columns to incidents table...</p>";

try {
    // Add accepted_at column if it doesn't exist
    echo "<p>Adding 'accepted_at' column...</p>";
    $pdo->exec("ALTER TABLE incidents ADD COLUMN accepted_at DATETIME NULL");
    echo "<p style='color: green;'>✅ 'accepted_at' column added successfully!</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<p style='color: orange;'>⚠️ 'accepted_at' column already exists.</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding 'accepted_at' column: " . $e->getMessage() . "</p>";
    }
}

try {
    // Add completed_at column if it doesn't exist
    echo "<p>Adding 'completed_at' column...</p>";
    $pdo->exec("ALTER TABLE incidents ADD COLUMN completed_at DATETIME NULL");
    echo "<p style='color: green;'>✅ 'completed_at' column added successfully!</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<p style='color: orange;'>⚠️ 'completed_at' column already exists.</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding 'completed_at' column: " . $e->getMessage() . "</p>";
    }
}

try {
    // Update status ENUM to include 'completed'
    echo "<p>Updating status ENUM to include 'completed'...</p>";
    $pdo->exec("ALTER TABLE incidents MODIFY COLUMN status ENUM('pending', 'accepted', 'done', 'resolved', 'completed', 'accept and complete') DEFAULT 'pending'");
    echo "<p style='color: green;'>✅ Status ENUM updated successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error updating status ENUM: " . $e->getMessage() . "</p>";
}

try {
    // Create acceptance_log table if it doesn't exist
    echo "<p>Creating acceptance_log table...</p>";
    $sql = "CREATE TABLE IF NOT EXISTS acceptance_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        incident_id INT NOT NULL,
        responder_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        timestamp DATETIME NOT NULL,
        FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
        FOREIGN KEY (responder_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "<p style='color: green;'>✅ acceptance_log table created successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error creating acceptance_log table: " . $e->getMessage() . "</p>";
}

try {
    // Add indexes for better performance
    echo "<p>Adding database indexes...</p>";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_acceptance_log_incident ON acceptance_log(incident_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_acceptance_log_responder ON acceptance_log(responder_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_incidents_accepted_at ON incidents(accepted_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_incidents_completed_at ON incidents(completed_at)");
    echo "<p style='color: green;'>✅ Database indexes added successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error adding indexes: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3 style='color: green;'>🎉 Database Fix Complete!</h3>";
echo "<p>Your database has been updated with the missing columns. The 'accepted_at' and 'completed_at' error should now be fixed.</p>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>✅ Test the Accept & Complete functionality in the responder dashboard</li>";
echo "<li>✅ Check that completed incidents show up properly in the admin dashboard</li>";
echo "<li>✅ Verify that notifications are working correctly</li>";
echo "</ul>";
echo "<p><a href='responder_dashboard.php' class='btn btn-primary'>Go to Responder Dashboard</a> ";
echo "<a href='admin_dashboard.php' class='btn btn-success'>Go to Admin Dashboard</a></p>";

// Verify the fix by checking if columns exist
echo "<hr>";
echo "<h4>Database Verification:</h4>";
try {
    $result = $pdo->query("DESCRIBE incidents");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $has_accepted_at = false;
    $has_completed_at = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'accepted_at') $has_accepted_at = true;
        if ($column['Field'] === 'completed_at') $has_completed_at = true;
    }
    
    echo "<p>✅ accepted_at column: " . ($has_accepted_at ? "EXISTS" : "MISSING") . "</p>";
    echo "<p>✅ completed_at column: " . ($has_completed_at ? "EXISTS" : "MISSING") . "</p>";
    
    if ($has_accepted_at && $has_completed_at) {
        echo "<p style='color: green; font-weight: bold;'>🎉 All required columns are present! The system should work correctly now.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error verifying database: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.btn { padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block; }
.btn-primary { background: #007bff; color: white; }
.btn-success { background: #28a745; color: white; }
</style>
