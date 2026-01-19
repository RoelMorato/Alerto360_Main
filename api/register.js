const { query, hashPassword } = require('./database');

export default async function handler(req, res) {
  // Set CORS headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  
  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }
  
  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Method not allowed' });
  }
  
  const { name, email, password, role } = req.body || {};
  
  if (!name || !email || !password) {
    return res.status(400).json({ 
      success: false, 
      message: 'Name, email and password required' 
    });
  }
  
  try {
    // Check if user already exists
    const existingUsers = await query(
      'SELECT id FROM users WHERE email = $1 LIMIT 1',
      [email]
    );
    
    if (existingUsers.length > 0) {
      return res.status(400).json({
        success: false,
        message: 'Email already registered'
      });
    }
    
    // Hash password
    const hashedPassword = await hashPassword(password);
    
    // Insert new user
    const newUsers = await query(
      'INSERT INTO users (name, email, password, role, email_verified, verification_required, created_at) VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING *',
      [name, email, hashedPassword, role || 'citizen', 1, 0, new Date().toISOString()]
    );
    
    const newUser = newUsers[0];
    
    return res.status(201).json({
      success: true,
      message: 'Registration successful',
      user: {
        id: newUser.id,
        name: newUser.name,
        email: newUser.email,
        role: newUser.role,
        email_verified: newUser.email_verified
      }
    });
    
  } catch (error) {
    console.error('Registration error:', error);
    return res.status(500).json({
      success: false,
      message: 'Database error occurred'
    });
  }
}