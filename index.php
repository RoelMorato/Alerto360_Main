<?php
/**
 * Alerto360 - Emergency Response System
 * Index/Landing Page - Redirects to Login
 */

session_start();

// Check if user is already logged in and redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/admin_dashboard.php');
            exit;
        case 'responder':
            header('Location: user/responder_dashboard.php');
            exit;
        case 'citizen':
            header('Location: user/user_dashboard.php');
            exit;
        default:
            // Invalid role, clear session and redirect to login
            session_destroy();
            header('Location: login.php');
            exit;
    }
}

// If not logged in, redirect to login page
header('Location: login.php');
exit;
?>
