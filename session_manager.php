<?php
// Session Manager - Handles all session-related security and timeout
session_start();

// Configuration
$SESSION_TIMEOUT = 3600; // 1 hour in seconds
$WARNING_TIME = 300; // 5 minutes warning before timeout

// Function to check if user is logged in and session is valid
function isSessionValid() {
    global $SESSION_TIMEOUT;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        return false;
    }
    
    // Check if session has expired
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $SESSION_TIMEOUT) {
        // Session expired, destroy it
        session_destroy();
        return false;
    }
    
    return true;
}

// Function to check if session is about to expire (for warning)
function isSessionExpiringSoon() {
    global $SESSION_TIMEOUT, $WARNING_TIME;
    
    if (isset($_SESSION['last_activity'])) {
        $timeLeft = $SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
        return $timeLeft <= $WARNING_TIME;
    }
    
    return false;
}

// Function to get remaining session time
function getSessionTimeLeft() {
    global $SESSION_TIMEOUT;
    
    if (isset($_SESSION['last_activity'])) {
        return $SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
    }
    
    return 0;
}

// Function to redirect to login with message
function redirectToLogin($message = '') {
    if ($message) {
        $_SESSION['error'] = $message;
    }
    
    // Clear all session data
    session_unset();
    session_destroy();
    
    // Redirect to login
    header('Location: login_form.php');
    exit();
}

// Function to validate user access to specific page
function validateUserAccess($required_type = null) {
    // Check if session is valid
    if (!isSessionValid()) {
        redirectToLogin('Session expired. Please login again.');
    }
    
    // Check user type if specified
    if ($required_type && $_SESSION['user_type'] !== $required_type) {
        redirectToLogin('Access denied. You do not have permission to view this page.');
    }
    
    return true;
}

// Function to get user info safely
function getUserInfo() {
    if (!isSessionValid()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'type' => $_SESSION['user_type'],
        'time_left' => getSessionTimeLeft()
    ];
}

// Function to extend session (called on any page activity)
function extendSession() {
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
    }
}

// Auto-extend session on this page load (only for non-AJAX requests)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$isAjax) {
    extendSession();
    
    // If session is invalid and this is not an AJAX request, redirect immediately
    if (!isSessionValid()) {
        redirectToLogin('Please login to continue.');
    }
}
?>
