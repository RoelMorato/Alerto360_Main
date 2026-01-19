export default function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  
  return res.status(200).json({
    status: 'success',
    message: 'Alerto360 API is working!',
    timestamp: new Date().toISOString(),
    server: 'Vercel',
    runtime: 'Node.js 20.x'
  });
}