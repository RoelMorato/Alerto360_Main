export default function handler(req, res) {
  const html = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerto360 API - Live</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; color: #333; margin-bottom: 30px; }
        .status { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .api-list { background: #f8f9fa; padding: 20px; border-radius: 5px; }
        .api-item { margin: 10px 0; padding: 10px; background: white; border-radius: 3px; }
        .test-btn { background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; margin-left: 10px; }
        .test-btn:hover { background: #0056b3; }
        .credentials { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛡️ Alerto360 API</h1>
            <p>Emergency Response System - Live on Vercel</p>
        </div>
        
        <div class="status">
            <strong>✅ API Status:</strong> Online and Ready<br>
            <strong>🌍 Global Access:</strong> Available Worldwide<br>
            <strong>📱 Mobile Ready:</strong> APK Compatible<br>
            <strong>⚡ Runtime:</strong> Node.js 20.x (Latest)
        </div>
        
        <div class="credentials">
            <strong>🔑 Test Credentials:</strong><br>
            <strong>Admin:</strong> admin@alerto360.com / admin123<br>
            <strong>User:</strong> test@alerto360.com / test123<br>
            <strong>Responder:</strong> responder@alerto360.com / responder123
        </div>
        
        <div class="api-list">
            <h3>📡 Available API Endpoints:</h3>
            
            <div class="api-item">
                <strong>Login API:</strong> /api/login
                <a href="/api/login" class="test-btn" target="_blank">Test</a>
            </div>
            
            <div class="api-item">
                <strong>Register API:</strong> /api/register
                <a href="/api/register" class="test-btn" target="_blank">Test</a>
            </div>
            
            <div class="api-item">
                <strong>Test API:</strong> /api/test
                <a href="/api/test" class="test-btn" target="_blank">Test</a>
            </div>
        </div>
        
        <div style="margin-top: 30px; text-align: center; color: #666;">
            <p>🚀 <strong>Ready for Mobile App Integration</strong></p>
            <p>Update your Flutter app baseUrl to: <code>https://${req.headers.host}</code></p>
        </div>
    </div>
</body>
</html>`;

  res.setHeader('Content-Type', 'text/html');
  return res.status(200).send(html);
}