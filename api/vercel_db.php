<?php
/**
 * Vercel Database Connection
 * Uses PostgreSQL on Vercel or fallback to demo data
 */

try {
    // Get database URL from environment variable
    $database_url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
    
    if ($database_url) {
        // Parse Vercel Postgres URL
        $db = parse_url($database_url);
        
        $host = $db['host'];
        $port = $db['port'];
        $dbname = ltrim($db['path'], '/');
        $username = $db['user'];
        $password = $db['pass'];
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Test connection
        $pdo->query("SELECT 1");
        
    } else {
        // Fallback: Create demo PDO for testing
        $pdo = createDemoConnection();
    }
    
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Fallback to demo data
    $pdo = createDemoConnection();
}

/**
 * Create demo connection with in-memory data
 */
function createDemoConnection() {
    // Create SQLite in-memory database for demo
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create demo tables and data
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT UNIQUE,
            password TEXT,
            role TEXT DEFAULT 'citizen',
            email_verified INTEGER DEFAULT 1,
            verification_required INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert demo users
    $pdo->exec("
        INSERT INTO users (id, name, email, password, role) VALUES 
        (1, 'Admin User', 'admin@alerto360.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin'),
        (2, 'Test User', 'test@alerto360.com', '" . password_hash('test123', PASSWORD_DEFAULT) . "', 'citizen'),
        (3, 'Responder One', 'responder@alerto360.com', '" . password_hash('responder123', PASSWORD_DEFAULT) . "', 'responder')
    ");
    
    // Create incidents table
    $pdo->exec("
        CREATE TABLE incidents (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            title TEXT,
            description TEXT,
            location TEXT,
            latitude REAL,
            longitude REAL,
            status TEXT DEFAULT 'pending',
            priority TEXT DEFAULT 'medium',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    return $pdo;
}

// Make $pdo available globally
$GLOBALS['pdo'] = $pdo;
?>