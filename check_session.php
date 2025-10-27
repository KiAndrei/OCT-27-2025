<?php
/**
 * Simple Session Check Script
 * Used by JavaScript to check if user is logged in
 */

session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
$logged_in = false;
$dashboard_url = '';

if (isset($_SESSION['admin_name']) && $_SESSION['user_type'] === 'admin') {
    $logged_in = true;
    $dashboard_url = 'admin_dashboard.php';
} elseif (isset($_SESSION['attorney_name']) && $_SESSION['user_type'] === 'attorney') {
    $logged_in = true;
    $dashboard_url = 'attorney_dashboard.php';
} elseif (isset($_SESSION['employee_name']) && $_SESSION['user_type'] === 'employee') {
    $logged_in = true;
    $dashboard_url = 'employee_dashboard.php';
} elseif (isset($_SESSION['client_name']) && $_SESSION['user_type'] === 'client') {
    $logged_in = true;
    $dashboard_url = 'client_dashboard.php';
}

// Return JSON response
echo json_encode([
    'logged_in' => $logged_in,
    'dashboard_url' => $dashboard_url,
    'user_type' => $_SESSION['user_type'] ?? 'guest'
]);
?>