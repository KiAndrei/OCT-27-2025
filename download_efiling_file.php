<?php
session_start();
require_once 'config.php';

// Security: allow attorney, admin_attorney, and admin (admin can download all)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'] ?? '', ['attorney', 'admin_attorney', 'admin'])) {
    error_log("Access denied - Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . ", user_type: " . ($_SESSION['user_type'] ?? 'not set'));
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$current_user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['user_type'] ?? '') === 'admin';
$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Debug logging
error_log("Download request - User ID: $current_user_id, File ID: $file_id");

if ($file_id <= 0) {
    error_log("Invalid file ID: $file_id");
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file ID');
}

// Get file info - if admin, no attorney restriction; otherwise restrict to own
if ($is_admin) {
    $stmt = $conn->prepare("SELECT stored_file_path, file_name FROM efiling_history WHERE id=?");
    $stmt->bind_param("i", $file_id);
} else {
    $stmt = $conn->prepare("SELECT stored_file_path, file_name FROM efiling_history WHERE id=? AND attorney_id=?");
    $stmt->bind_param("ii", $file_id, $current_user_id);
}
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

$row = $res->fetch_assoc();
$filePath = $row['stored_file_path'];
$fileName = $row['file_name'];

if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found on disk');
}

// Get file info
$mimeType = mime_content_type($filePath);
$fileSize = filesize($filePath);

// Set headers for download
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
header('Cache-Control: private, max-age=0');
header('Pragma: public');

// Output file
readfile($filePath);
exit();
?>
