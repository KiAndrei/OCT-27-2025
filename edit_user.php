<?php
session_start();
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

// Check if user is admin
if (!isset($_SESSION['admin_name']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login_form.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $user_type = $_POST['user_type'] ?? '';
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^\d{11}$/', $phone_number)) {
        $errors[] = "Phone number must be exactly 11 digits";
    }
    
    if (empty($user_type) || !in_array($user_type, ['attorney', 'employee'])) {
        $errors[] = "Invalid user type";
    }
    
    // Check if email already exists for another user
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM user_form WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email address is already in use by another user";
        }
    }
    
    if (empty($errors)) {
        // Add "Atty." prefix for attorney accounts
        if ($user_type === 'attorney' || $user_type === 'admin_attorney') {
            if (!preg_match('/^Atty\.\s*/i', $name)) {
                $name = 'Atty. ' . $name;
            }
        }
        
        // Update user
        $stmt = $conn->prepare("UPDATE user_form SET name = ?, email = ?, phone_number = ? WHERE id = ?");
        $stmt->bind_param('sssi', $name, $email, $phone_number, $user_id);
        
        if ($stmt->execute()) {
            // Log to audit trail
            global $auditLogger;
            $auditLogger->logAction(
                $_SESSION['user_id'],
                $_SESSION['admin_name'],
                'admin',
                'User Update',
                'User Management',
                "Updated user: $name ($email) - Type: $user_type",
                'success',
                'medium'
            );
            
            $_SESSION['success_message'] = "User updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update user. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = implode(", ", $errors);
    }
}

// Redirect back to user management
header('Location: admin_usermanagement.php');
exit();
?>

