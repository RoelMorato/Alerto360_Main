<!DOCTYPE html>
<html>
<head>
    <title>Database Credentials Checker</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 20px auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        td:first-child { font-weight: bold; width: 150px; }
    </style>
</head>
<body>

<div class="box">
    <h2>🔍 Database Credentials Checker</h2>
    
    <?php
    // Current credentials from db_connect.php
    $host = 'sql113.infinityfree.com';
    $db   = 'if0_40657921_alerto360';
    $user = 'if0_40657921';
    $pass = 'P5TtWhm5OuHrP';
    
    echo "<h3>Current Credentials:</h3>";
    echo "<table>";
    echo "<tr><td>Host:</td><td>$host</td></tr>";
    echo "<tr><td>Database:</td><td>$db</td></tr>";
    echo "<tr><td>Username:</td><td>$user</td></tr>";
    echo "<tr><td>Password:</td><td>" . str_repeat('*', strlen($pass)) . " (hidden)</td></tr>";
    echo "</table>";
    
    echo "<h3>Connection Test:</h3>";
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        echo "<p class='success'>✅ CONNECTION SUCCESSFUL!</p>";
        
        // Test query
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p class='success'>✅ Found " . count($tables) . " tables:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        // Check users table
        if (in_array('users', $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            echo "<p class='success'>✅ Users table has {$result['count']} records</p>";
        }
        
        echo "<div class='info'>";
        echo "<strong>✅ DATABASE IS WORKING!</strong><br>";
        echo "Your website should work now. Try visiting:<br>";
        echo "<a href='index.php'>https://alerto360.infinityfreeapp.com/index.php</a>";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ CONNECTION FAILED!</p>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
        
        echo "<div class='info'>";
        echo "<strong>⚠️ POSSIBLE SOLUTIONS:</strong><br><br>";
        
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            echo "1. <strong>Wrong Password</strong><br>";
            echo "   - Go to InfinityFree Control Panel → MySQL Databases<br>";
            echo "   - Click 'Show' button to see correct password<br>";
            echo "   - Update db_connect.php with correct password<br><br>";
            
            echo "2. <strong>Wrong Username</strong><br>";
            echo "   - Check 'MySQL User Name' in Control Panel<br>";
            echo "   - Must match exactly (case-sensitive)<br><br>";
        }
        
        if (strpos($e->getMessage(), 'Connection timed out') !== false) {
            echo "1. <strong>Wrong Hostname</strong><br>";
            echo "   - Check 'MySQL Host Name' in Control Panel<br>";
            echo "   - Update \$host in db_connect.php<br><br>";
            
            echo "2. <strong>InfinityFree Servers Busy</strong><br>";
            echo "   - Wait 10-15 minutes and try again<br>";
            echo "   - Free hosting has limited resources<br><br>";
        }
        
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
            echo "1. <strong>Wrong Database Name</strong><br>";
            echo "   - Check 'MySQL DB Name' in Control Panel<br>";
            echo "   - Must match exactly<br><br>";
        }
        
        echo "</div>";
    }
    ?>
    
    <hr>
    <p><a href="index.php">← Back to Login</a></p>
</div>

</body>
</html>
