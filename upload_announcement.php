<?php
session_start();
if (!isset($_SESSION['employee_name']) || $_SESSION['user_type'] !== 'employee') {
    header('Location: login_form.php');
    exit();
}

require_once 'config.php';

// Check if audit_logger.php exists, if not create a simple log function
if (!file_exists('audit_logger.php')) {
    function logAction($action, $description, $data = []) {
        error_log("Audit Log - Action: $action, Description: $description, Data: " . json_encode($data));
    }
} else {
    require_once 'audit_logger.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Log the request
        error_log("Upload announcement request received");
        error_log("POST data: " . json_encode($_POST));
        error_log("FILES data: " . json_encode($_FILES));
        
        // Check session
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('User ID not found in session');
        }
        
        // Validate required fields
        if (empty($_POST['description'])) {
            throw new Exception('Description is required');
        }
        
        if (empty($_FILES['image']['name'])) {
            throw new Exception('Image is required');
        }
        
        // Validate image file
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            throw new Exception('Invalid image format. Only JPG, JPEG, and PNG are allowed.');
        }
        
        if ($_FILES['image']['size'] > $max_size) {
            throw new Exception('Image size too large. Maximum 5MB allowed.');
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/announcements/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'announcement_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
            throw new Exception('Failed to upload image');
        }
        
        // Check if announcements table exists and has correct structure
        $table_check = $conn->query("SHOW TABLES LIKE 'announcements'");
        if ($table_check->num_rows == 0) {
            // Create announcements table if it doesn't exist
            $create_table = "CREATE TABLE `announcements` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `description` text NOT NULL,
                `image_path` varchar(500) NOT NULL,
                `created_by` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            if (!$conn->query($create_table)) {
                unlink($file_path);
                throw new Exception('Failed to create announcements table: ' . $conn->error);
            }
        } else {
            // Check if created_by column exists
            $column_check = $conn->query("SHOW COLUMNS FROM announcements LIKE 'created_by'");
            if ($column_check->num_rows == 0) {
                // Add missing column
                $add_column = "ALTER TABLE announcements ADD COLUMN created_by int(11) NOT NULL AFTER image_path";
                if (!$conn->query($add_column)) {
                    unlink($file_path);
                    throw new Exception('Failed to add created_by column: ' . $conn->error);
                }
            }
        }
        
        // Insert announcement into database
        $stmt = $conn->prepare("INSERT INTO announcements (description, image_path, created_by, created_at) VALUES (?, ?, ?, NOW())");
        $employee_id = $_SESSION['user_id'];
        $stmt->bind_param("ssi", $_POST['description'], $file_path, $employee_id);
        
        if (!$stmt->execute()) {
            // Delete uploaded file if database insert fails
            unlink($file_path);
            throw new Exception('Failed to save announcement to database: ' . $stmt->error);
        }
        
        // Log the action
        if (function_exists('logAction')) {
            logAction('announcement_upload', 'Employee uploaded new announcement', [
                'announcement_id' => $conn->insert_id,
                'description' => $_POST['description'],
                'image_path' => $file_path
            ]);
        } else {
            error_log("Audit Log - Action: announcement_upload, Description: Employee uploaded new announcement, Data: " . json_encode([
                'announcement_id' => $conn->insert_id,
                'description' => $_POST['description'],
                'image_path' => $file_path
            ]));
        }
        
        echo "Announcement uploaded successfully!";
        
    } catch (Exception $e) {
        error_log("Upload announcement error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(400);
        echo "Error: " . $e->getMessage();
    }
} else {
    http_response_code(405);
    echo "Method not allowed";
}
?>
