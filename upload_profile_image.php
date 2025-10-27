<?php
session_start();
require_once 'config.php';

// Determine user type and ID
if (isset($_SESSION['user_type']) && isset($_SESSION['user_id'])) {
    $user_type = $_SESSION['user_type'];
    $user_id = $_SESSION['user_id'];
} else {
    die('Not logged in.');
}

// Check if file is uploaded
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

$allowed = ['jpg', 'jpeg', 'png', 'gif'];
$max_size = 2 * 1024 * 1024; // 2MB
$file = $_FILES['profile_image'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    $_SESSION['profile_upload_error'] = 'Invalid file type.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}
if ($file['size'] > $max_size) {
    $_SESSION['profile_upload_error'] = 'File too large.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Set upload folder
$folder = 'uploads/' . $user_type . '/';
if (!is_dir($folder)) mkdir($folder, 0777, true);
$filename = $user_id . '_' . time() . '.' . $ext;
$target = $folder . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    $_SESSION['profile_upload_error'] = 'Upload failed.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Update DB
$stmt = $conn->prepare("UPDATE user_form SET profile_image=? WHERE id=?");
$stmt->bind_param('si', $target, $user_id);
$stmt->execute();

// Optionally, update session if you use it for image
$_SESSION['profile_image'] = $target;

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit(); 