<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start output buffering to catch any unexpected output
ob_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to change your password.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_code') {
            // Send verification code
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            
            // Verify the email belongs to the current user
            $stmt = $conn->prepare("SELECT email FROM user_form WHERE id = ? AND email = ?");
            $stmt->bind_param('is', $user_id, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Email address does not match your account.';
            } else {
                // Generate OTP
                $otp = rand(100000, 999999);
                
                // Store OTP in session with expiration (1 minute)
                $_SESSION['password_change_data'] = [
                    'email' => $email,
                    'otp' => (string)$otp, // Store as string to ensure proper comparison
                    'otp_expires' => time() + 60, // 1 minute
                    'user_id' => $user_id
                ];
                
                // Send OTP email
                require_once 'send_password_change_otp.php';
                send_password_change_otp($email, $otp);
                
                $response['success'] = true;
                $response['message'] = 'Verification code sent to your email.';
            }
            
        } elseif ($action === 'verify_otp') {
            // Just verify OTP without changing password
            if (!isset($_SESSION['password_change_data'])) {
                $response['message'] = 'No password change request found. Please start over.';
            } else {
                $stored_data = $_SESSION['password_change_data'];
                
                // Check if OTP has expired
                if (time() > $stored_data['otp_expires']) {
                    unset($_SESSION['password_change_data']);
                    $response['message'] = 'Verification code has expired. Please request a new one.';
                } else {
                    $verification_code = trim($_POST['verification_code'] ?? '');
                    
                    // Validate verification code format
                    if (!preg_match('/^\d{6}$/', $verification_code)) {
                        $response['message'] = 'Verification code must be exactly 6 digits.';
                    } else {
                        // Verify OTP
                        if ($verification_code !== (string)$stored_data['otp']) {
                            $response['message'] = 'Invalid verification code. Please try again.';
                        } else {
                            // OTP is valid, mark as verified
                        // Save current password change session
                        $_SESSION['otp_verified'] = true;
                        $_SESSION['otp_verified_time'] = time();
                            
                            // Clear password change data
                            unset($_SESSION['password_change_data']);
                            
                            // OTP is valid, allow password change stage
                            $response['success'] = true;
                            $response['message'] = 'Verification successful. You can now set a new password.';
                        }
                    }
                }
            }
            
        } elseif ($action === 'verify_and_change') {
            // Change password after OTP verification
            if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
                $response['message'] = 'OTP verification required. Please verify your code first.';
            } else {
                // Check if verification session is still valid (within 5 minutes)
                if (isset($_SESSION['otp_verified_time']) && (time() - $_SESSION['otp_verified_time']) > 300) {
                    unset($_SESSION['otp_verified']);
                    unset($_SESSION['otp_verified_time']);
                    $response['message'] = 'Verification session expired. Please start over.';
                } else {
                    $new_password = $_POST['new_password'] ?? '';
                    
                    // Password validation and change logic
                    if (empty($new_password)) {
                        $response['message'] = 'New password is required.';
                    } else {
                            // Validate password strength
                            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\])[A-Za-z\d!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\]{8,}$/', $new_password)) {
                                $response['message'] = 'Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one allowed special character.';
                            } else {
                                // Check if password history table exists, if not create it
                                $conn->query("CREATE TABLE IF NOT EXISTS password_history (
                                    id int(11) NOT NULL AUTO_INCREMENT,
                                    user_id int(11) NOT NULL,
                                    password_hash varchar(255) NOT NULL,
                                    changed_at timestamp NOT NULL DEFAULT current_timestamp(),
                                    PRIMARY KEY (id),
                                    KEY user_id (user_id)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                                
                                // Get current password
                                $stmt = $conn->prepare("SELECT password FROM user_form WHERE id = ?");
                                $stmt->bind_param('i', $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $user_row = $result->fetch_assoc();
                                $current_password_hash = $user_row['password'];
                                
                                // Check if new password is same as current password
                                if (password_verify($new_password, $current_password_hash)) {
                                    $response['message'] = 'New password must be different from your current password.';
                                } else {
                                    // Check against last 2 passwords in history
                                    $stmt = $conn->prepare("SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY changed_at DESC LIMIT 2");
                                    $stmt->bind_param('i', $user_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    $is_previous_password = false;
                                    while ($row = $result->fetch_assoc()) {
                                        if (password_verify($new_password, $row['password_hash'])) {
                                            $is_previous_password = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($is_previous_password) {
                                        $response['message'] = 'You cannot reuse your recently used password. Please choose a different password.';
                                    } else {
                                    // Hash new password and update database
                                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                    
                                    $update_stmt = $conn->prepare("UPDATE user_form SET password = ?, login_attempts = 0, account_locked = 0, lockout_until = NULL, first_login = 0 WHERE id = ?");
                                    $update_stmt->bind_param('si', $hashed_password, $user_id);
                                    
                                    if ($update_stmt->execute()) {
                                        // Save to password history
                                        $history_stmt = $conn->prepare("INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)");
                                        $history_stmt->bind_param('is', $user_id, $hashed_password);
                                        $history_stmt->execute();
                                        
                                        // Keep only last 2 passwords (delete older ones)
                                        $conn->query("DELETE FROM password_history WHERE user_id = $user_id AND id NOT IN (SELECT id FROM (SELECT id FROM password_history WHERE user_id = $user_id ORDER BY changed_at DESC LIMIT 2) AS temp)");
                                        
                                        // Clear verification session
                                        unset($_SESSION['otp_verified']);
                                        unset($_SESSION['otp_verified_time']);
                                        unset($_SESSION['password_change_data']);
                                        
                                        // Log the password change
                                        try {
                                            require_once 'audit_logger.php';
                                            logAuditAction($user_id, $_SESSION['user_name'] ?? 'Unknown', $_SESSION['user_type'] ?? 'unknown', 'Password Change', 'Security', 'User changed password via email verification', 'success', 'medium');
                                        } catch (Exception $e) {
                                            // Log error but don't fail the password change
                                            error_log("Audit logging failed: " . $e->getMessage());
                                        }
                                        
                                        $response['success'] = true;
                                        $response['message'] = 'Password changed successfully.';
                                    } else {
                                        $response['message'] = 'Failed to update password. Please try again.';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'change_password') {
            // Direct password change (for first-time login)
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate password strength
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\])[A-Za-z\d!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\]{8,}$/', $new_password)) {
                $response['message'] = 'Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one allowed special character.';
            } elseif ($new_password !== $confirm_password) {
                $response['message'] = 'New password and confirm password do not match.';
            } else {
                // Check if password history table exists, if not create it
                $conn->query("CREATE TABLE IF NOT EXISTS password_history (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    user_id int(11) NOT NULL,
                    password_hash varchar(255) NOT NULL,
                    changed_at timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (id),
                    KEY user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Check against last 2 passwords
                $stmt = $conn->prepare("SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY changed_at DESC LIMIT 2");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $is_previous_password = false;
                while ($row = $result->fetch_assoc()) {
                    if (password_verify($new_password, $row['password_hash'])) {
                        $is_previous_password = true;
                        break;
                    }
                }
                
                if ($is_previous_password) {
                    $response['message'] = 'You cannot reuse your recently used password. Please choose a different password.';
                } else {
                    // Hash new password and update database
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $update_stmt = $conn->prepare("UPDATE user_form SET password = ?, first_login = 0 WHERE id = ?");
                    $update_stmt->bind_param('si', $hashed_password, $user_id);
                    
                    if ($update_stmt->execute()) {
                        // Save to password history
                        $history_stmt = $conn->prepare("INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)");
                        $history_stmt->bind_param('is', $user_id, $hashed_password);
                        $history_stmt->execute();
                        
                        // Keep only last 2 passwords (delete older ones)
                        $conn->query("DELETE FROM password_history WHERE user_id = $user_id AND id NOT IN (SELECT id FROM (SELECT id FROM password_history WHERE user_id = $user_id ORDER BY changed_at DESC LIMIT 2) AS temp)");
                        
                        // Log the password change
                        try {
                            require_once 'audit_logger.php';
                            logAuditAction($user_id, $_SESSION['user_name'] ?? 'Unknown', $_SESSION['user_type'] ?? 'unknown', 'Password Change', 'Security', 'User changed password via dashboard modal', 'success', 'medium');
                        } catch (Exception $e) {
                            // Log error but don't fail the password change
                            error_log("Audit logging failed: " . $e->getMessage());
                        }
                        
                        $response['success'] = true;
                        $response['message'] = 'Password changed successfully.';
                    } else {
                        $response['message'] = 'Failed to update password. Please try again.';
                    }
                }
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

// Clean any unexpected output and send JSON response
ob_clean();
echo json_encode($response);
?>
