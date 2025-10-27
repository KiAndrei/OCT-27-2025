<?php
session_start();
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'send_password_email.php';
require_once 'color_manager.php';

// Disable error reporting for clean JSON responses
error_reporting(0);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit();
    }

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit();
    }

    // Get form data
    $surname = trim($_POST['surname'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    
    // Combine names into full name
    $name = trim($surname . ', ' . $first_name . ($middle_name ? ' ' . $middle_name : ''));
    
    $email = trim($_POST['email'] ?? '');
    $confirm_email = trim($_POST['confirm_email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $confirm_phone_number = trim($_POST['confirm_phone_number'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    $errors = [];

    // Validation
    if (empty($surname)) {
        $errors[] = "Surname is required.";
    }

    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($confirm_email)) {
        $errors[] = "Confirm email is required.";
    } elseif ($email !== $confirm_email) {
        $errors[] = "Email addresses do not match.";
    }

    if (empty($phone_number)) {
        $errors[] = "Phone number is required.";
    } elseif (strlen($phone_number) !== 11 || !preg_match('/^[0-9]{11}$/', $phone_number)) {
        $errors[] = "Phone number must be exactly 11 digits.";
    } elseif (!str_starts_with($phone_number, '09')) {
        $errors[] = "Phone number must start with '09'.";
    }

    if (empty($confirm_phone_number)) {
        $errors[] = "Confirm phone number is required.";
    } elseif ($phone_number !== $confirm_phone_number) {
        $errors[] = "Phone numbers do not match.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_+={}[\]:";\'<>.,?\/\\|~]).+$/', $password)) {
        $errors[] = "Password must include uppercase, lowercase, number, and special character.";
    }

    if (empty($confirm_password)) {
        $errors[] = "Confirm password is required.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM user_form WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists. Please use a different email address.";
        }
    }

    if (empty($errors)) {
        // EMAIL-FIRST LOGIC: Send email BEFORE creating account
        $email_sent = send_password_email($email, $name, $password, 'client');

        if (!$email_sent) {
            // If email fails, don't create the account
            echo json_encode([
                'success' => false, 
                'message' => "Failed to send password email to $email. Account creation cancelled. Please check the email address and try again."
            ]);
            exit();
        }

        // Email sent successfully, now create the account
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $user_type = 'client';
        $created_by = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO user_form (name, email, phone_number, password, user_type, created_by, first_login) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssssi", $name, $email, $phone_number, $hashed_password, $user_type, $created_by);

        if ($stmt->execute()) {
            $new_user_id = $conn->insert_id; // Get the ID of the newly created user
            
            // Automatically assign colors for admin and attorney users (clients don't get colors)
            if ($user_type === 'admin' || $user_type === 'attorney') {
                $colorManager = new ColorManager($conn);
                $assignedColors = $colorManager->assignUserColors($new_user_id, $user_type);
                
                if ($assignedColors) {
                    error_log("Auto-assigned colors to new $user_type (ID: $new_user_id): {$assignedColors['color_name']}");
                }
            }
            
            // Initialize audit logger
            $auditLogger = new AuditLogger($conn);
            
            // Log the activity
            $creator_id = $_SESSION['user_id'];
            $creator_name = $_SESSION['admin_name'] ?? $_SESSION['attorney_name'] ?? 'Unknown';
            $creator_type = $_SESSION['user_type'] ?? 'unknown';
            
            $auditLogger->logAction(
                $creator_id,
                $creator_name,
                $creator_type,
                'Client Create',
                'Client Management',
                "Created new client account: $name ($email) - Email sent successfully",
                'success',
                'medium'
            );

            echo json_encode([
                'success' => true, 
                'message' => "Client '$name' has been successfully created! Password email has been sent to $email.",
                'email' => $email
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => "Database error: " . $stmt->error
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => implode(' ', $errors)
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}
?>