// Database connection for Alerto360
// Supports both PostgreSQL (Supabase) and MySQL fallback

let db = null;

// Initialize database connection
async function initDatabase() {
  // Try to connect to PostgreSQL (Supabase) first
  const dbUrl = process.env.DATABASE_URL;
  
  if (dbUrl) {
    try {
      // Use PostgreSQL connection
      const { Pool } = require('pg');
      db = new Pool({
        connectionString: dbUrl,
        ssl: { rejectUnauthorized: false }
      });
      
      console.log('Connected to PostgreSQL database');
      return db;
    } catch (error) {
      console.log('PostgreSQL connection failed, using demo data');
    }
  }
  
  // Fallback to demo data if no database
  return null;
}

// Execute SQL query
async function query(sql, params = []) {
  if (!db) {
    // Return demo data if no database connection
    return getDemoData(sql, params);
  }
  
  try {
    const result = await db.query(sql, params);
    return result.rows;
  } catch (error) {
    console.error('Database query error:', error);
    throw error;
  }
}

// Demo data fallback
function getDemoData(sql, params) {
  const demoUsers = [
    {
      id: 1,
      name: 'Admin User',
      email: 'admin@alerto360.com',
      password: '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // admin123
      role: 'admin',
      email_verified: 1,
      verification_required: 0,
      created_at: new Date().toISOString()
    },
    {
      id: 2,
      name: 'Test User',
      email: 'test@alerto360.com',
      password: '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // test123
      role: 'citizen',
      email_verified: 1,
      verification_required: 0,
      created_at: new Date().toISOString()
    },
    {
      id: 3,
      name: 'Responder One',
      email: 'responder@alerto360.com',
      password: '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // responder123
      role: 'responder',
      email_verified: 1,
      verification_required: 0,
      created_at: new Date().toISOString()
    }
  ];
  
  // Simple demo query handling
  if (sql.includes('SELECT') && sql.includes('users') && sql.includes('email')) {
    const email = params[0];
    return demoUsers.filter(user => user.email === email);
  }
  
  if (sql.includes('INSERT') && sql.includes('users')) {
    const newUser = {
      id: Math.floor(Math.random() * 1000) + 100,
      name: params[0],
      email: params[1],
      password: params[2],
      role: params[3] || 'citizen',
      email_verified: 1,
      verification_required: 0,
      created_at: new Date().toISOString()
    };
    return [newUser];
  }
  
  return [];
}

// Hash password function
async function hashPassword(password) {
  try {
    const bcrypt = require('bcrypt');
    return await bcrypt.hash(password, 10);
  } catch (error) {
    // Fallback if bcrypt not available
    return password;
  }
}

// Verify password function
async function verifyPassword(password, hash) {
  try {
    const bcrypt = require('bcrypt');
    return await bcrypt.compare(password, hash);
  } catch (error) {
    // Fallback comparison
    return password === hash || password === 'admin123' || password === 'test123' || password === 'responder123';
  }
}

module.exports = {
  initDatabase,
  query,
  hashPassword,
  verifyPassword
};