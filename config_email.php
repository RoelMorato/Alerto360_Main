<?php
/**
 * Email Configuration for Gmail SMTP
 * 
 * Setup Instructions:
 * 1. Use a Gmail account for sending emails
 * 2. Enable 2-Factor Authentication on your Gmail
 * 3. Generate an App Password: https://myaccount.google.com/apppasswords
 * 4. Replace the credentials below
 */

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // or 'ssl' for port 465
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'your-app-password'); // Gmail App Password (not regular password)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Alerto360 Emergency System');

// Verification Settings
define('VERIFICATION_CODE_LENGTH', 6);
define('VERIFICATION_CODE_EXPIRY_MINUTES', 15);

/**
 * Check if email is configured
 */
function isEmailConfigured() {
    return SMTP_USERNAME !== 'your-email@gmail.com' && 
           !empty(SMTP_USERNAME) && 
           !empty(SMTP_PASSWORD);
}

/**
 * Send email using PHP mail() or SMTP
 */
function sendEmail($to, $subject, $message, $isHtml = true) {
    if (!isEmailConfigured()) {
        error_log('Email not configured. Please update config_email.php');
        return false;
    }

    // Try using PHPMailer if available, otherwise use mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailSMTP($to, $subject, $message, $isHtml);
    } else {
        return sendEmailSimple($to, $subject, $message, $isHtml);
    }
}

/**
 * Send email using PHP mail() function (fallback)
 */
function sendEmailSimple($to, $subject, $message, $isHtml = true) {
    // Since PHPMailer is now installed, always use it
    return sendEmailSMTP($to, $subject, $message, $isHtml);
}

/**
 * Send email using SMTP (requires PHPMailer)
 * Install: composer require phpmailer/phpmailer
 */
function sendEmailSMTP($to, $subject, $message, $isHtml = true) {
    if (!file_exists('vendor/autoload.php')) {
        error_log('PHPMailer not installed. Using simple mail() instead.');
        return sendEmailSimple($to, $subject, $message, $isHtml);
    }
    
    require_once 'vendor/autoload.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        if ($isHtml) {
            $mail->AltBody = strip_tags($message);
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed: {$mail->ErrorInfo}");
        return false;
    }
}
