<?php
require_once 'session_manager.php';
validateUserAccess('admin');
require_once 'config.php';
require_once 'audit_logger.php';

// Get filters from URL parameters
$userType = $_GET['user_type'] ?? 'all';
$module = $_GET['module'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Apply filters
$filters = [
    'user_type' => $userType,
    'module' => $module,
    'status' => $status,
    'priority' => $priority,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'search' => $search
];

// Get audit trail data
$auditData = $auditLogger->getAuditTrail($filters);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_trail_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Timestamp',
    'User ID',
    'User Name',
    'User Type',
    'Action',
    'Module',
    'Description',
    'Status'
]);

// Add data rows
foreach ($auditData as $record) {
    fputcsv($output, [
        $record['timestamp'],
        $record['user_id'],
        $record['user_name'],
        $record['user_type'],
        $record['action'],
        $record['module'],
        $record['description'],
        $record['status']
    ]);
}

// Close file pointer
fclose($output);
exit;
?>
