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
  
  const { name, email, password, role } = req.body || {};
  
  if (!name || !email || !password) {
    return res.status(400).json({ 
      success: false, 
      message: 'Name, email and password required' 
    });
  }
  
  // Demo registration (in real app, save to database)
  const newUser = {
    id: Math.floor(Math.random() * 1000) + 100,
    name: name,
    email: email,
    role: role || 'citizen',
    email_verified: true,
    created_at: new Date().toISOString()
  };
  
  return res.status(201).json({
    success: true,
    message: 'Registration successful',
    user: newUser
  });
}