<?php
session_start();
require_once 'config.php';
require_once 'send_password_email.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
require_once 'color_manager.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_name']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login_form.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $surname = trim($_POST['surname']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    
    // Combine names into full name
    $name = trim($surname . ', ' . $first_name . ($middle_name ? ' ' . $middle_name : ''));
    
    $email = trim($_POST['email']);
    $confirm_email = trim($_POST['confirm_email']);
    $phone_number = trim($_POST['phone_number']);
    $confirm_phone_number = trim($_POST['confirm_phone_number']);
    $user_type = $_POST['user_type'];
    
    // Add "Atty." prefix for attorney accounts
    if ($user_type === 'attorney' || $user_type === 'admin_attorney') {
        // Only add prefix if not already present
        if (!preg_match('/^Atty\.\s*/i', $name)) {
            $name = 'Atty. ' . $name;
        }
    }
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    if (empty($surname)) {
        $errors[] = "Surname is required";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    } elseif (strpos($email, ' ') !== false) {
        $errors[] = "Email cannot contain spaces";
    }
    
    if (empty($confirm_email)) {
        $errors[] = "Confirm email is required";
    } elseif (!filter_var($confirm_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid confirm email address";
    } elseif (strpos($confirm_email, ' ') !== false) {
        $errors[] = "Confirm email cannot contain spaces";
    }
    
    if ($email !== $confirm_email) {
        $errors[] = "Email addresses do not match";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^\d{11}$/', $phone_number)) {
        $errors[] = "Phone number must be exactly 11 digits";
    }
    
    if (empty($confirm_phone_number)) {
        $errors[] = "Confirm phone number is required";
    } elseif (!preg_match('/^\d{11}$/', $confirm_phone_number)) {
        $errors[] = "Confirm phone number must be exactly 11 digits";
    }
    
    if ($phone_number !== $confirm_phone_number) {
        $errors[] = "Phone numbers do not match";
    }
    
    if (empty($user_type)) {
        $errors[] = "User type is required";
    }
    
    // Prevent creating admin accounts through this form
    if ($user_type === 'admin') {
        $errors[] = "Admin accounts cannot be created through this interface";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strpos($password, ' ') !== false) {
        $errors[] = "Password cannot contain spaces";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_+={}[\]:";\'<>.,?\/\\|~])[A-Za-z\d!@#$%^&*()\-_+={}[\]:";\'<>.,?\/\\|~]{8,}$/', $password)) {
        $errors[] = "Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one special character (!@#$%^&*()...etc)";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (strpos($confirm_password, ' ') !== false) {
        $errors[] = "Confirm password cannot contain spaces";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM user_form WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    
    // If no errors, proceed with user creation
    if (empty($errors)) {
        // For employees and attorneys, send email FIRST before creating account
        if ($user_type === 'employee' || $user_type === 'attorney') {
            $email_sent = send_password_email($email, $name, $password, $user_type);
            
            if (!$email_sent) {
                // If email fails, don't create the account
                $errors[] = "Failed to send password email to $email. Account creation cancelled. Please check the email address and try again.";
            } else {
                // Email sent successfully, now create the account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO user_form (name, email, phone_number, password, user_type, first_login) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssss", $name, $email, $phone_number, $hashed_password, $user_type);
                
                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id; // Get the ID of the newly created user
                    
                    // Automatically assign colors for admin and attorney users
                    if ($user_type === 'admin' || $user_type === 'attorney') {
                        $colorManager = new ColorManager($conn);
                        $assignedColors = $colorManager->assignUserColors($new_user_id, $user_type);
                        
                        if ($assignedColors) {
                            error_log("Auto-assigned colors to new $user_type (ID: $new_user_id): {$assignedColors['color_name']}");
                        }
                    }
                    
                    if ($user_type === 'employee') {
                        $_SESSION['success_message'] = "Employee '$name' has been successfully registered! Password email has been sent to $email.";
                    } elseif ($user_type === 'attorney') {
                        $_SESSION['success_message'] = "Attorney '$name' has been successfully registered! Password email has been sent to $email.";
                    } else {
                        $_SESSION['success_message'] = "User '$name' has been successfully created as $user_type! Password email has been sent to $email.";
                    }
                    
                    // Log the activity
                    $admin_id = $_SESSION['user_id'];
                    $admin_name = $_SESSION['admin_name'];
                    
                    // Log to audit trail
                    global $auditLogger;
                    $auditLogger->logAction(
                        $admin_id,
                        $admin_name,
                        'admin',
                        'User Create',
                        'User Management',
                        "Created new $user_type account: $name ($email) - Email sent successfully",
                        'success',
                        'medium'
                    );
                    
                    header('Location: admin_usermanagement.php');
                    exit();
                } else {
                    $errors[] = "Database error: " . $stmt->error;
                }
            }
        } else {
            // For other user types (if any), create account without email
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO user_form (name, email, phone_number, password, user_type, first_login) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssss", $name, $email, $phone_number, $hashed_password, $user_type);
            
            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id; // Get the ID of the newly created user
                
                // Automatically assign colors for admin and attorney users
                if ($user_type === 'admin' || $user_type === 'attorney') {
                    $colorManager = new ColorManager($conn);
                    $assignedColors = $colorManager->assignUserColors($new_user_id, $user_type);
                    
                    if ($assignedColors) {
                        error_log("Auto-assigned colors to new $user_type (ID: $new_user_id): {$assignedColors['color_name']}");
                    }
                }
                
                if ($user_type === 'employee') {
                    $_SESSION['success_message'] = "Employee '$name' has been successfully registered!";
                } elseif ($user_type === 'attorney') {
                    $_SESSION['success_message'] = "Attorney '$name' has been successfully registered!";
                } else {
                    $_SESSION['success_message'] = "User '$name' has been successfully created as $user_type!";
                }
                
                // Log the activity
                $admin_id = $_SESSION['user_id'];
                $admin_name = $_SESSION['admin_name'];
                
                // Log to audit trail
                global $auditLogger;
                $auditLogger->logAction(
                    $admin_id,
                    $admin_name,
                    'admin',
                    'User Create',
                    'User Management',
                    "Created new $user_type account: $name ($email)",
                    'success',
                    'medium'
                );
                
                header('Location: admin_usermanagement.php');
                exit();
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }
        }
    }
    
    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(", ", $errors);
        header('Location: admin_usermanagement.php');
        exit();
    }
} else {
    // If not POST request, redirect to user management
    header('Location: admin_usermanagement.php');
    exit();
}
?> 