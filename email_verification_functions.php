<?php
require_once 'config_email.php';
require_once 'db_connect.php';

/**
 * Generate a random verification code
 */
function generateVerificationCode($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Create and send verification code
 */
function createVerificationCode($userId, $email) {
    global $pdo;
    
    if (!$pdo) {
        return ['success' => false, 'error' => 'Database connection not available'];
    }
    
    try {
        // Generate code
        $code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
        
        // Calculate expiry time
        $expiryMinutes = VERIFICATION_CODE_EXPIRY_MINUTES;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));
        
        // Delete any existing codes for this user
        $deleteStmt = $pdo->prepare("DELETE FROM email_verification WHERE user_id = ?");
        $deleteStmt->execute([$userId]);
        
        // Insert new verification code
        $insertStmt = $pdo->prepare("
            INSERT INTO email_verification (user_id, email, verification_code, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->execute([$userId, $email, $code, $expiresAt]);
        
        // Send email
        $sent = sendVerificationEmail($email, $code);
        
        return [
            'success' => $sent,
            'code' => $code, // For testing only, remove in production
            'expires_at' => $expiresAt
        ];
        
    } catch (PDOException $e) {
        error_log("Verification code creation failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send verification email
 */
function sendVerificationEmail($email, $code) {
    $subject = "Verify Your Alerto360 Account";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #7b7be0, #a18cd1); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .code-box { background: white; border: 2px dashed #7b7be0; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
            .code { font-size: 32px; font-weight: bold; color: #7b7be0; letter-spacing: 5px; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🛡️ Alerto360</h1>
                <p>Emergency Response System</p>
            </div>
            <div class='content'>
                <h2>Welcome to Alerto360!</h2>
                <p>Thank you for registering. To complete your account setup, please verify your email address.</p>
                
                <div class='code-box'>
                    <p style='margin: 0; color: #666;'>Your Verification Code:</p>
                    <div class='code'>{$code}</div>
                </div>
                
                <p>Enter this code in the app to activate your account.</p>
                
                <div class='warning'>
                    <strong>⚠️ Important:</strong>
                    <ul style='margin: 10px 0;'>
                        <li>This code expires in " . VERIFICATION_CODE_EXPIRY_MINUTES . " minutes</li>
                        <li>Don't share this code with anyone</li>
                        <li>If you didn't request this, please ignore this email</li>
                    </ul>
                </div>
                
                <p>Once verified, you'll be able to:</p>
                <ul>
                    <li>✅ Report emergencies instantly</li>
                    <li>✅ Track your incident reports</li>
                    <li>✅ Receive emergency notifications</li>
                    <li>✅ Get real-time updates from responders</li>
                </ul>
                
                <p>Stay safe!</p>
                <p><strong>The Alerto360 Team</strong></p>
            </div>
            <div class='footer'>
                <p>Alerto360 Emergency Response System<br>
                Hagonoy, Davao del Sur<br>
                This is an automated message, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message, true);
}

/**
 * Verify the code entered by user
 */
function verifyCode($email, $code) {
    global $pdo;
    
    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Database connection not available'
        ];
    }
    
    try {
        // Find verification record (without time check first)
        $stmt = $pdo->prepare("
            SELECT ev.*, u.id as user_id, u.name
            FROM email_verification ev
            JOIN users u ON ev.user_id = u.id
            WHERE ev.email = ? 
            AND ev.verification_code = ?
            AND ev.is_verified = 0
            ORDER BY ev.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email, $code]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            return [
                'success' => false,
                'message' => 'Invalid verification code'
            ];
        }
        
        // Check if expired
        if (strtotime($record['expires_at']) < time()) {
            return [
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.'
            ];
        }
        
        // Mark as verified
        $updateVerification = $pdo->prepare("
            UPDATE email_verification 
            SET is_verified = 1, verified_at = NOW()
            WHERE id = ?
        ");
        $updateVerification->execute([$record['id']]);
        
        // Update user account
        $updateUser = $pdo->prepare("
            UPDATE users 
            SET email_verified = 1, verification_required = 0
            WHERE id = ?
        ");
        $updateUser->execute([$record['user_id']]);
        
        return [
            'success' => true,
            'message' => 'Email verified successfully!',
            'user_id' => $record['user_id'],
            'name' => $record['name']
        ];
        
    } catch (PDOException $e) {
        error_log("Verification failed: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Verification error: ' . $e->getMessage()
        ];
    }
}

/**
 * Resend verification code
 */
function resendVerificationCode($email) {
    global $pdo;
    
    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Database connection not available'
        ];
    }
    
    try {
        // Find user by email
        $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Email not found'
            ];
        }
        
        if ($user['email_verified']) {
            return [
                'success' => false,
                'message' => 'Email already verified'
            ];
        }
        
        // Create new code
        return createVerificationCode($user['id'], $email);
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Check if user needs verification
 */
function needsVerification($userId) {
    global $pdo;
    
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT email_verified, verification_required 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        return $user['verification_required'] == 1 && $user['email_verified'] == 0;
        
    } catch (PDOException $e) {
        return false;
    }
}
