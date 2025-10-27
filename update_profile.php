<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['client', 'attorney', 'employee', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'config.php';
// require_once 'audit_logger.php'; // Commented out since function doesn't exist

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$response = ['success' => false, 'message' => ''];

// Rate limiting check
$rate_limit_key = 'profile_update_' . $user_id;
if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'last_update' => time()];
}

$rate_limit = $_SESSION[$rate_limit_key];
if ($rate_limit['count'] >= 5 && (time() - $rate_limit['last_update']) < 300) { // 5 attempts per 5 minutes
    echo json_encode(['success' => false, 'message' => 'Too many profile update attempts. Please wait 5 minutes.']);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Security token validation
        if (!isset($_POST['security_token']) || empty($_POST['security_token'])) {
            $response['message'] = "Security token missing.";
        } else {
            // Password verification
            $current_password = $_POST['current_password'] ?? '';
            if (empty($current_password)) {
                $response['message'] = "Current password is required for security verification.";
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM user_form WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
                if (!$user_data || !password_verify($current_password, $user_data['password'])) {
                    // Increment failed attempts
                    $_SESSION[$rate_limit_key]['count']++;
                    $_SESSION[$rate_limit_key]['last_update'] = time();
                    
                    $response['message'] = "Invalid password. Please try again.";
                } else {
                    // Password verified, proceed with update
                    $name = trim($_POST['name'] ?? '');
                    $phone_number = trim($_POST['phone_number'] ?? '');
                    
                    // For attorneys, ensure "Atty." prefix is maintained
                    if ($user_type === 'attorney' || $user_type === 'admin_attorney') {
                        if (!preg_match('/^Atty\.\s*/i', $name)) {
                            $name = 'Atty. ' . $name;
                        }
                    }
                    
                    // Input validation and sanitization
                    if (empty($name)) {
                        $response['message'] = "Name is required.";
                    } elseif (strlen($name) > 100) {
                        $response['message'] = "Name is too long. Maximum 100 characters allowed.";
                    } elseif (strlen($phone_number) > 20) {
                        $response['message'] = "Phone number is too long. Maximum 20 characters allowed.";
                    } elseif (!empty($phone_number) && !preg_match('/^09\d{9}$/', $phone_number)) {
                        $response['message'] = "Phone number must be exactly 11 digits starting with 09 (e.g., 09123456789).";
                    } else {
                        // Reset rate limit on successful verification
                        $_SESSION[$rate_limit_key] = ['count' => 0, 'last_update' => time()];
                        
                        // Get current profile image
                        $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id = ?");
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $current_data = $result->fetch_assoc();
                        $profile_image = $current_data['profile_image'];
                        
                        // Handle profile image upload with security checks
                        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                            $file_type = $_FILES['profile_image']['type'];
                            $file_size = $_FILES['profile_image']['size'];
                            
                            if (!in_array($file_type, $allowed_types)) {
                                $response['message'] = "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
                            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                                $response['message'] = "File size too large. Maximum 5MB allowed.";
                            } else {
                                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                                $new_filename = $user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
                                
                                // Determine upload path based on user type
                                switch ($user_type) {
                                    case 'client':
                                        $upload_path = 'uploads/client/' . $new_filename;
                                        break;
                                    case 'attorney':
                                        $upload_path = 'uploads/attorney/' . $new_filename;
                                        break;
                                    case 'employee':
                                        $upload_path = 'uploads/employee/' . $new_filename;
                                        break;
                                    case 'admin':
                                        $upload_path = 'uploads/admin/' . $new_filename;
                                        break;
                                    default:
                                        $upload_path = 'uploads/client/' . $new_filename;
                                }
                                
                                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                                    // Delete old profile image if it exists and is not the default
                                    $default_image = 'images/default-avatar.jpg';
                                    if ($profile_image && $profile_image !== $default_image && file_exists($profile_image)) {
                                        unlink($profile_image);
                                    }
                                    $profile_image = $upload_path;
                                }
                            }
                        }
                        
                        if (empty($response['message'])) {
                            // Update user data with prepared statement
                            $stmt = $conn->prepare("UPDATE user_form SET name = ?, phone_number = ?, profile_image = ? WHERE id = ?");
                            $stmt->bind_param('sssi', $name, $phone_number, $profile_image, $user_id);
                            
                            if ($stmt->execute()) {
                                // Update appropriate session variable based on user type
                                switch ($user_type) {
                                    case 'client':
                                        $_SESSION['client_name'] = $name;
                                        break;
                                    case 'attorney':
                                        $_SESSION['attorney_name'] = $name;
                                        break;
                                    case 'employee':
                                        $_SESSION['employee_name'] = $name;
                                        break;
                                    case 'admin':
                                        $_SESSION['user_name'] = $name;
                                        break;
                                }
                                
                                // Log the profile update for audit trail (if function exists)
                                try {
                                    if (function_exists('logAuditTrail')) {
                                        $old_data = [
                                            'name' => $_SESSION['user_name'] ?? 'Unknown',
                                            'phone_number' => $current_data['phone_number'] ?? ''
                                        ];
                                        $new_data = [
                                            'name' => $name,
                                            'phone_number' => $phone_number
                                        ];
                                        
                                        logAuditTrail($user_id, $user_type, 'PROFILE_UPDATE', 'Profile information updated', $old_data, $new_data);
                                    }
                                } catch (Exception $auditError) {
                                    // Log audit error but don't fail the profile update
                                    error_log("Audit logging error: " . $auditError->getMessage());
                                }
                                
                                $response['success'] = true;
                                $response['message'] = "Profile updated successfully!";
                            } else {
                                $response['message'] = "Failed to update profile. Please try again.";
                            }
                        }
                    }
                }
            }
        }
    } else {
        $response['message'] = "Invalid request method.";
    }
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    $response['message'] = "An error occurred while updating the profile.";
}

// Debug log the response
error_log("Profile update response: " . json_encode($response));

echo json_encode($response);
?>
