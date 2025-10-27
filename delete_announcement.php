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
        // Validate required fields
        if (empty($_POST['announcement_id'])) {
            throw new Exception('Announcement ID is required');
        }
        
        $announcement_id = (int)$_POST['announcement_id'];
        
        // Get announcement details before deletion
        $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Announcement not found');
        }
        
        $announcement = $result->fetch_assoc();
        
        // Delete announcement from database
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete announcement from database: ' . $stmt->error);
        }
        
        // Delete image file if it exists
        if ($announcement['image_path'] && file_exists($announcement['image_path'])) {
            unlink($announcement['image_path']);
        }
        
        // Log the action
        if (function_exists('logAction')) {
            logAction('announcement_delete', 'Employee deleted announcement', [
                'announcement_id' => $announcement_id,
                'description' => $announcement['description'],
                'image_path' => $announcement['image_path']
            ]);
        } else {
            error_log("Audit Log - Action: announcement_delete, Description: Employee deleted announcement, Data: " . json_encode([
                'announcement_id' => $announcement_id,
                'description' => $announcement['description'],
                'image_path' => $announcement['image_path']
            ]));
        }
        
        echo "Announcement deleted successfully!";
        
    } catch (Exception $e) {
        http_response_code(400);
        echo "Error: " . $e->getMessage();
    }
} else {
    http_response_code(405);
    echo "Method not allowed";
}
?>
