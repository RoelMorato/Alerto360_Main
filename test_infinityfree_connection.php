<?php
echo "<h2>InfinityFree Database Connection Test</h2>";
echo "<p>Testing connection to: sql8.infinityfree.com</p>";
echo "<p>Database: if_40027521_alerto360</p>";
echo "<hr>";

// Test 1: Check if PDO MySQL driver is available
echo "<h3>Test 1: PDO MySQL Driver</h3>";
if (extension_loaded('pdo_mysql')) {
    echo "<p style='color: green;'>✅ PDO MySQL driver is loaded</p>";
} else {
    echo "<p style='color: red;'>❌ PDO MySQL driver is NOT loaded</p>";
    exit;
}

// Test 2: Try to connect
echo "<h3>Test 2: Database Connection</h3>";
echo "<p>Attempting to connect (may take 10-30 seconds)...</p>";
flush();

$host = 'sql308.infinityfree.com';
$db   = 'if0_40657921_alerto360';
$user = 'if0_40657921';
$pass = 'P5TtWhm5OuHrP';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 30,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

$startTime = microtime(true);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "<p style='color: green;'>✅ Connection successful! (took {$duration} seconds)</p>";
    
    // Test 3: Check tables
    echo "<h3>Test 3: Database Tables</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<p style='color: green;'>✅ Found " . count($tables) . " tables:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ Database is empty. You need to import alerto360_no_triggers.sql</p>";
    }
    
    // Test 4: Check users table (if exists)
    if (in_array('users', $tables)) {
        echo "<h3>Test 4: Users Table</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "<p style='color: green;'>✅ Users table has {$result['count']} records</p>";
    }
    
} catch (PDOException $e) {
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "<p style='color: red;'>❌ Connection failed after {$duration} seconds</p>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    
    echo "<h3>Possible Solutions:</h3>";
    echo "<ul>";
    echo "<li>InfinityFree servers may be busy - try again in 5-10 minutes</li>";
    echo "<li>Check if database credentials are correct in InfinityFree Control Panel</li>";
    echo "<li>Verify database was created successfully</li>";
    echo "<li>Check if your IP is blocked (unlikely on free hosting)</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Login</a></p>";
?>
