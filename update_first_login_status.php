<?php
session_start();
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to update first login status.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_first_login') {
            // Update database to mark first_login as completed
            $stmt = $conn->prepare("UPDATE user_form SET first_login = 0 WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'First login status updated successfully.';
                
                // Log the password change completion
                try {
                    require_once 'audit_logger.php';
                    logAuditAction($user_id, $_SESSION['user_name'] ?? 'Unknown', $_SESSION['user_type'] ?? 'unknown', 'First Login Complete', 'Security', 'User completed first-time password change', 'success', 'medium');
                } catch (Exception $e) {
                    // Log error but don't fail the update
                    error_log("Audit logging failed: " . $e->getMessage());
                }
            } else {
                $response['message'] = 'Failed to update first login status. Please try again.';
            }
        } else {
            $response['message'] = 'Invalid action.';
        }
    } else {
        $response['message'] = 'Invalid request method.';
    }
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

echo json_encode($response);
?>
