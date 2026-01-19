export default function handler(req, res) {
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
  
  const { email, password } = req.body || {};
  
  if (!email || !password) {
    return res.status(400).json({ success: false, message: 'Email and password required' });
  }
  
  // Demo users (same as PHP version)
  const users = {
    'admin@alerto360.com': {
      id: 1,
      name: 'Admin User',
      email: 'admin@alerto360.com',
      password: 'admin123', // In real app, this would be hashed
      role: 'admin'
    },
    'test@alerto360.com': {
      id: 2,
      name: 'Test User',
      email: 'test@alerto360.com',
      password: 'test123',
      role: 'citizen'
    },
    'responder@alerto360.com': {
      id: 3,
      name: 'Responder One',
      email: 'responder@alerto360.com',
      password: 'responder123',
      role: 'responder'
    }
  };
  
  const user = users[email];
  
  if (user && user.password === password) {
    return res.status(200).json({
      success: true,
      message: 'Login successful',
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role,
        email_verified: true
      }
    });
  } else {
    return res.status(401).json({
      success: false,
      message: 'Invalid email or password'
    });
  }
}