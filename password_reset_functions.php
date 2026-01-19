<?php
require_once 'config_email.php';

/**
 * Create password reset code and send email
 */
function createPasswordResetCode($email) {
    global $pdo;
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'Email not found'
        ];
    }
    
    // Generate 6-digit code and token
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(32));
    
    // Calculate expiry (15 minutes)
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Delete old reset codes for this user
    $delete = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $delete->execute([$user['id']]);
    
    // Insert new reset code
    $insert = $pdo->prepare("
        INSERT INTO password_resets (user_id, email, reset_token, reset_code, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($insert->execute([$user['id'], $email, $token, $code, $expires_at])) {
        // Send email
        $emailSent = sendPasswordResetEmail($email, $user['name'], $code);
        
        return [
            'success' => true,
            'message' => 'Reset code sent to your email',
            'email_sent' => $emailSent,
            'token' => $token
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to create reset code'
    ];
}

/**
 * Verify password reset code
 */
function verifyResetCode($email, $code) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets 
        WHERE email = ? AND reset_code = ? AND is_used = 0
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$email, $code]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        return [
            'success' => false,
            'message' => 'Invalid reset code'
        ];
    }
    
    // Check if expired
    if (strtotime($reset['expires_at']) < time()) {
        return [
            'success' => false,
            'message' => 'Reset code has expired. Please request a new one.'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Code verified',
        'token' => $reset['reset_token'],
        'user_id' => $reset['user_id']
    ];
}

/**
 * Reset password with token (legacy)
 */
function resetPassword($token, $newPassword) {
    global $pdo;
    
    // Find valid reset token
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets 
        WHERE reset_token = ? AND is_used = 0
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        return [
            'success' => false,
            'message' => 'Invalid reset token'
        ];
    }
    
    // Check if expired
    if (strtotime($reset['expires_at']) < time()) {
        return [
            'success' => false,
            'message' => 'Reset token has expired. Please request a new one.'
        ];
    }
    
    // Update password
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    
    if ($update->execute([$hashed, $reset['user_id']])) {
        // Mark token as used
        $markUsed = $pdo->prepare("UPDATE password_resets SET is_used = 1, used_at = NOW() WHERE id = ?");
        $markUsed->execute([$reset['id']]);
        
        return [
            'success' => true,
            'message' => 'Password reset successfully'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to reset password'
    ];
}

/**
 * Reset password with email and code (simpler approach)
 */
function resetPasswordWithCode($email, $code, $newPassword) {
    global $pdo;
    
    // Find valid reset code
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets 
        WHERE email = ? AND reset_code = ? AND is_used = 0
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$email, $code]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        return [
            'success' => false,
            'message' => 'Invalid reset code'
        ];
    }
    
    // Check if expired
    if (strtotime($reset['expires_at']) < time()) {
        return [
            'success' => false,
            'message' => 'Reset code has expired. Please request a new one.'
        ];
    }
    
    // Update password
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    
    if ($update->execute([$hashed, $reset['user_id']])) {
        // Mark code as used
        $markUsed = $pdo->prepare("UPDATE password_resets SET is_used = 1, used_at = NOW() WHERE id = ?");
        $markUsed->execute([$reset['id']]);
        
        return [
            'success' => true,
            'message' => 'Password reset successfully'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to reset password'
    ];
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($to, $name, $code) {
    $subject = 'Reset Your Alerto360 Password';
    
    $message = '
    <html>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #7b7be0 0%, #a18cd1 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0;">🔐 Password Reset</h1>
            </div>
            <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
                <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>You requested to reset your password for your Alerto360 account.</p>
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">
                    <p style="margin: 0 0 10px 0; color: #666;">Your password reset code is:</p>
                    <h2 style="margin: 0; color: #7b7be0; font-size: 36px; letter-spacing: 5px;">' . $code . '</h2>
                </div>
                <p><strong>This code will expire in 15 minutes.</strong></p>
                <p>If you didn\'t request this password reset, please ignore this email or contact support if you have concerns.</p>
                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                <p style="color: #666; font-size: 12px; text-align: center;">
                    Alerto360 Emergency Response System<br>
                    This is an automated email, please do not reply.
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, true);
}
