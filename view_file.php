<?php
// File viewer script to serve uploaded files
session_start();

// Debug logging
error_log("View file request: " . print_r($_GET, true));
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    error_log("Access denied - no session");
    http_response_code(403);
    die('Access denied');
}

// Get the file path from the query parameter
$filePath = $_GET['path'] ?? $_GET['file'] ?? '';

// Validate the file path
if (empty($filePath) || !preg_match('/^uploads\//', $filePath)) {
    http_response_code(400);
    die('Invalid file path');
}

// Construct the full file path
$fullPath = __DIR__ . '/' . $filePath;
error_log("Full file path: " . $fullPath);
error_log("File exists: " . (file_exists($fullPath) ? 'YES' : 'NO'));

// Check if file exists
if (!file_exists($fullPath)) {
    error_log("File not found: " . $fullPath);
    http_response_code(404);
    die('File not found: ' . $fullPath);
}

// Get file info
$fileInfo = pathinfo($fullPath);
$extension = strtolower($fileInfo['extension'] ?? '');

// Set appropriate content type
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'webp' => 'image/webp',
    'txt' => 'text/plain',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($fullPath));

// Check if this is a download request
$isDownload = isset($_GET['download']) && $_GET['download'] == '1';
if ($isDownload) {
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
}

header('Cache-Control: private, max-age=3600');

// Output the file
readfile($fullPath);
?>
