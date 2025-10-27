<?php
/**
 * Action Logger Helper - Automatically log user actions
 * Include this in your pages to automatically track user activities
 */

require_once 'audit_logger.php';

// Function to automatically log page access
function logPageAccess($pageName, $action = 'Page Access') {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_type'])) {
        logAuditAction(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_type'],
            $action,
            'Page Access',
            "Accessed page: $pageName"
        );
    }
}

// Function to log document actions
function logDocumentAction($action, $fileName, $details = '', $category = null) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_type'])) {
        $desc = "$action document: $fileName" . ($category ? " (Category: $category)" : '') . ($details ? " - $details" : '');
        logAuditAction(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_type'],
            "Document $action",
            'Document Management',
            $desc
        );
    }
}

// Function to log case actions
function logCaseAction($action, $caseDetails, $details = '') {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_type'])) {
        logAuditAction(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_type'],
            "Case $action",
            'Case Management',
            "$action case: $caseDetails" . ($details ? " - $details" : '')
        );
    }
}

// Function to log user management actions
function logUserManagementAction($action, $targetUser, $details = '') {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_type'])) {
        logAuditAction(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_type'],
            "User $action",
            'User Management',
            "$action user: $targetUser" . ($details ? " - $details" : '')
        );
    }
}

// Function to log schedule actions
function logScheduleAction($action, $scheduleDetails, $details = '') {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_type'])) {
        logAuditAction(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_type'],
            "Schedule $action",
            'Schedule Management',
            "$action schedule: $scheduleDetails" . ($details ? " - $details" : '')
        );
    }
}

// Function to log message actions
function logMessageAction($action, $recipient, $details = '') {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_type'])) {
        logAuditAction(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_type'],
            "Message $action",
            'Communication',
            "$action message to: $recipient" . ($details ? " - $details" : '')
        );
    }
}

// Auto-log page access when included
if (isset($_SESSION['user_id'])) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    logPageAccess($currentPage);
}
?>
