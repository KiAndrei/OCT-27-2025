<?php
session_start();

// AUDIT LOGGING: Log logout action before clearing session
if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_type'])) {
    require_once 'audit_logger.php';
    
    logAuditAction(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_type'],
        'User Logout',
        'Authentication',
        'User logged out successfully'
    );
}

// Clear all session data
session_unset();
session_destroy();

// Clear any cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Set cache control headers to prevent back button access
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect to landing page (index.php)
header('Location: index.php');
exit();
?>
