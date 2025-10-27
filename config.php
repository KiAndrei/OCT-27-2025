<?php
$host = "localhost";
$username = "root";
$password = "";  // Empty string since no password is set
$database = "lawfirm";

$conn = mysqli_connect("localhost", "root", "", "lawfirm", "3307");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Email sender config for OTP/notifications
if (!defined('MAIL_HOST')) define('MAIL_HOST', 'smtp.gmail.com');
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', 'refreamarjohn91@gmail.com'); // <-- Palitan ng Gmail mo
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', 'twtg fvpi humi eplp');    // <-- Palitan ng App Password mo
if (!defined('MAIL_FROM')) define('MAIL_FROM', 'refreamarjohn91@gmail.com');         // <-- Palitan ng Gmail mo
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Opina Law Office');
// Optional: enable to log SMTP conversation to PHP error log for troubleshooting deliverability
if (!defined('MAIL_DEBUG')) define('MAIL_DEBUG', false); // Disabled - emails working perfectly

// User role constants
if (!defined('USER_ROLE_ADMIN')) define('USER_ROLE_ADMIN', 'admin');
if (!defined('USER_ROLE_ATTORNEY')) define('USER_ROLE_ATTORNEY', 'attorney');
if (!defined('USER_ROLE_ADMIN_ATTORNEY')) define('USER_ROLE_ADMIN_ATTORNEY', 'admin_attorney');
if (!defined('USER_ROLE_EMPLOYEE')) define('USER_ROLE_EMPLOYEE', 'employee');
if (!defined('USER_ROLE_CLIENT')) define('USER_ROLE_CLIENT', 'client');

// Function to check if user has admin privileges
function hasAdminPrivileges($user_type) {
    return in_array($user_type, [USER_ROLE_ADMIN, USER_ROLE_ADMIN_ATTORNEY]);
}

// Function to check if user has attorney privileges
function hasAttorneyPrivileges($user_type) {
    return in_array($user_type, [USER_ROLE_ATTORNEY, USER_ROLE_ADMIN_ATTORNEY]);
}

// Function to get user's primary role for display purposes
function getPrimaryRole($user_type) {
    switch ($user_type) {
        case USER_ROLE_ADMIN_ATTORNEY:
            return 'Admin & Attorney';
        case USER_ROLE_ADMIN:
            return 'Administrator';
        case USER_ROLE_ATTORNEY:
            return 'Attorney';
        case USER_ROLE_EMPLOYEE:
            return 'Employee';
        case USER_ROLE_CLIENT:
            return 'Client';
        default:
            return 'Unknown';
    }
}
?>
