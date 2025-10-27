<?php
require_once 'session_manager.php';
validateUserAccess('client');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$client_id = $_SESSION['user_id'];

// Fetch client profile image, email, and name
$stmt = $conn->prepare("SELECT profile_image, email, name FROM user_form WHERE id=?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$client_email = '';
$client_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $client_email = $row['email'];
    $client_name = $row['name'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'images/default-avatar.jpg';
}

// Check if client already has a pending or approved request
$stmt = $conn->prepare("SELECT id, status, request_id, review_notes FROM client_request_form WHERE client_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$existing_request = $res->fetch_assoc();

// If client already has a request, show status instead of form
// EXCEPTION: If the request was rejected, allow them to submit a new one
if ($existing_request && $existing_request['status'] !== 'Rejected') {
    $show_form = false;
    $request_status = $existing_request['status'];
    $request_id = $existing_request['request_id'];
} else {
    $show_form = true;
    $request_status = $existing_request ? $existing_request['status'] : null;
    $request_id = $existing_request ? $existing_request['request_id'] : null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    $full_name = trim($_POST['full_name']);
    
    // Combine separate address fields into one complete address
    $street_address = trim($_POST['street_address']);
    $barangay = trim($_POST['barangay']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $zip_code = trim($_POST['zip_code']);
    
    $address = $street_address . ', ' . $barangay . ', ' . $city . ', ' . $province . ' ' . $zip_code;
    
    $sex = $_POST['sex'];
    $concern_description = trim($_POST['concern_description']);
    
    // Generate unique request ID
    $request_id = 'REQ-' . date('Ymd') . '-' . str_pad($client_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
    
    // Handle file uploads
    $valid_id_front_path = '';
    $valid_id_front_filename = '';
    $valid_id_back_path = '';
    $valid_id_back_filename = '';
    
    // Upload front ID
    if (isset($_FILES['valid_id_front']) && $_FILES['valid_id_front']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/client/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['valid_id_front']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        // Validate file extension
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_message = "Invalid file type for front ID. Only JPG, PNG, and PDF files are allowed.";
        }
        // Validate file size
        else if ($_FILES['valid_id_front']['size'] > $max_file_size) {
            $error_message = "Front ID file size exceeds 5MB limit.";
        }
        // Validate MIME type
        else {
            $allowed_mime_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            $file_mime_type = mime_content_type($_FILES['valid_id_front']['tmp_name']);
            
            if (!in_array($file_mime_type, $allowed_mime_types)) {
                $error_message = "Invalid file type for front ID. File content does not match the extension.";
            }
        }
        
        if (!isset($error_message)) {
            $valid_id_front_filename = 'valid_id_front_' . $client_id . '_' . time() . '.' . $file_extension;
            $valid_id_front_path = $upload_dir . $valid_id_front_filename;
            
            if (!move_uploaded_file($_FILES['valid_id_front']['tmp_name'], $valid_id_front_path)) {
                $error_message = "Failed to upload front ID file.";
            }
        }
    } else {
        $error_message = "Please upload front ID file.";
    }
    
    // Upload back ID
    if (!isset($error_message) && isset($_FILES['valid_id_back']) && $_FILES['valid_id_back']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['valid_id_back']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        // Validate file extension
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_message = "Invalid file type for back ID. Only JPG, PNG, and PDF files are allowed.";
        }
        // Validate file size
        else if ($_FILES['valid_id_back']['size'] > $max_file_size) {
            $error_message = "Back ID file size exceeds 5MB limit.";
        }
        // Validate MIME type
        else {
            $allowed_mime_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            $file_mime_type = mime_content_type($_FILES['valid_id_back']['tmp_name']);
            
            if (!in_array($file_mime_type, $allowed_mime_types)) {
                $error_message = "Invalid file type for back ID. File content does not match the extension.";
            }
        }
        
        if (!isset($error_message)) {
            $valid_id_back_filename = 'valid_id_back_' . $client_id . '_' . time() . '.' . $file_extension;
            $valid_id_back_path = $upload_dir . $valid_id_back_filename;
            
            if (!move_uploaded_file($_FILES['valid_id_back']['tmp_name'], $valid_id_back_path)) {
                $error_message = "Failed to upload back ID file.";
            }
        }
    } else if (!isset($error_message)) {
        $error_message = "Please upload back ID file.";
    }
    
    // Check privacy consent
    if (!isset($error_message) && !isset($_POST['privacy_consent'])) {
        $error_message = "You must agree to the Data Privacy Act to continue.";
    }
    
    if (!isset($error_message)) {
        // Insert request into database
        $stmt = $conn->prepare("INSERT INTO client_request_form (request_id, client_id, full_name, address, sex, concern_description, valid_id_front_path, valid_id_front_filename, valid_id_back_path, valid_id_back_filename, privacy_consent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $privacy_consent_value = 1;
        $stmt->bind_param("sissssssssi", $request_id, $client_id, $full_name, $address, $sex, $concern_description, $valid_id_front_path, $valid_id_front_filename, $valid_id_back_path, $valid_id_back_filename, $privacy_consent_value);
        
        if ($stmt->execute()) {
            // Log to audit trail
            global $auditLogger;
            $auditLogger->logAction(
                $client_id,
                $client_name,
                'client',
                'Request Form Submission',
                'Communication',
                "Submitted messaging request form with ID: $request_id",
                'success',
                'medium'
            );
            
            // Notify all employees about the new request
            if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
                // Get all employees
                $stmt_employees = $conn->prepare("SELECT id FROM user_form WHERE user_type = 'employee'");
                $stmt_employees->execute();
                $employees = $stmt_employees->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $nTitle = 'New Client Request';
                $nMsg = "Client $client_name has submitted a new messaging request (ID: $request_id). Please review and process the request.";
                $userType = 'employee';
                $notificationType = 'info';
                
                foreach ($employees as $employee) {
                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, ?, ?, ?, ?)");
                    $stmtN->bind_param('issss', $employee['id'], $userType, $nTitle, $nMsg, $notificationType);
                    $stmtN->execute();
                }
            }
            
            // Redirect to prevent form resubmission and show pending status
            header("Location: client_request_access.php?submitted=1");
            exit();
        } else {
            $error_message = "Failed to submit request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Access - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #5D0E26;
            --secondary-color: #8B1538;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --success-color: #28a745;
            --error-color: #dc3545;
            --warning-color: #ffc107;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 50%, #5D0E26 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .header .logo img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .back-button i {
            margin-right: 8px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 60px;
            box-shadow: 
                0 20px 60px rgba(93, 14, 38, 0.3),
                0 8px 32px rgba(93, 14, 38, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #5D0E26, #8B1538, #5D0E26);
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .form-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.3);
        }

        .form-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .form-header h2 {
            color: var(--primary-color);
            margin: 0 0 15px 0;
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .form-header p {
            color: #666;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }

        .request-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .form-section {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.1);
        }

        .form-section-title i {
            font-size: 1.3rem;
        }

        .full-width-field {
            grid-column: 1 / -1;
        }

        .two-column-field {
            grid-column: span 2;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label::before {
            content: '';
            width: 4px;
            height: 16px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 2px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 16px 20px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.95);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(93, 14, 38, 0.1);
            background: white;
            transform: translateY(-1px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
            line-height: 1.6;
        }

        .field-help {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 8px;
            padding: 12px 16px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 8px;
            border-left: 3px solid #5D0E26;
        }

        .field-help i {
            color: #5D0E26;
            font-size: 0.9rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .field-help span {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .file-upload-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-upload-container input[type="file"] {
            display: none;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            border: 2px dashed rgba(93, 14, 38, 0.3);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.5);
        }

        .file-upload-label:hover {
            border-color: var(--primary-color);
            background: rgba(93, 14, 38, 0.05);
        }

        .file-upload-label i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .file-info {
            margin-top: 5px;
        }

        .file-validation {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            display: none;
            transition: all 0.3s ease;
        }

        .file-validation.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .file-validation.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .file-validation.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            display: block;
        }

        .privacy-checkbox-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .privacy-checkbox-container input[type="checkbox"] {
            display: none;
        }

        .privacy-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            border: 2px solid rgba(93, 14, 38, 0.2);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            color: #5D0E26;
        }

        .privacy-label:hover {
            border-color: #5D0E26;
            background: rgba(93, 14, 38, 0.05);
        }

        .privacy-checkbox-container input[type="checkbox"]:checked + .privacy-label {
            border-color: #5D0E26;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            color: #5D0E26;
        }

        .privacy-label i {
            font-size: 1.2rem;
            color: #5D0E26;
        }

        .privacy-info {
            margin-top: 8px;
            padding: 12px 16px;
            background: rgba(93, 14, 38, 0.05);
            border-radius: 8px;
            border-left: 4px solid #5D0E26;
        }

        .privacy-info small {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .privacy-error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }

        .privacy-error-message i {
            color: #dc3545;
            font-size: 1rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .file-error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }

        .file-error-message i {
            color: #dc3545;
            font-size: 0.9rem;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 18px 40px;
            border: none;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(93, 14, 38, 0.4);
        }

        .btn i {
            font-size: 1.2rem;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(93, 14, 38, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .status-card.pending .status-icon {
            color: #ffc107;
        }

        .status-card.approved .status-icon {
            color: #28a745;
        }

        .status-card.rejected .status-icon {
            color: #dc3545;
        }

        .status-content h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .status-content p {
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.6;
            color: #666;
        }

        .request-details {
            background: rgba(255, 255, 255, 0.7);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .rejection-notes {
            background: rgba(220, 53, 69, 0.1);
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            margin-top: 8px;
            font-style: italic;
            color: #721c24;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px 5px;
            }

            .container {
                padding: 0;
                max-width: 100%;
            }
            
            .form-container {
                padding: 20px 15px;
                border-radius: 16px;
                margin: 0;
            }
            
            .header {
                margin-bottom: 20px;
            }

            .header .logo {
                width: 60px;
                height: 60px;
                margin-bottom: 15px;
            }

            .header .logo img {
                width: 45px;
                height: 45px;
            }
            
            .header h1 {
                font-size: 1.8rem;
                margin-bottom: 8px;
            }

            .header p {
                font-size: 1rem;
                padding: 0 10px;
            }
            
            .back-button {
                position: relative;
                top: auto;
                left: auto;
                margin-bottom: 15px;
                display: inline-block;
                padding: 10px 16px;
                font-size: 0.9rem;
            }

            .request-form {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-section {
                gap: 15px;
            }

            .form-section-title {
                font-size: 1.1rem;
                margin-bottom: 15px;
                padding-bottom: 8px;
            }

            .form-group {
                margin-bottom: 0;
            }

            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 12px 16px;
                font-size: 0.95rem;
            }

            .form-group textarea {
                min-height: 100px;
            }

            .file-upload-label {
                padding: 12px 16px;
            }

            .privacy-label {
                padding: 12px 16px;
            }

            .privacy-info {
                padding: 10px 12px;
            }

            .btn {
                padding: 15px 30px;
                font-size: 1rem;
            }

            .full-width-field,
            .two-column-field {
                grid-column: 1;
            }

            .status-card {
                padding: 25px 20px;
            }

            .status-icon {
                font-size: 3rem;
                margin-bottom: 15px;
            }

            .status-content h3 {
                font-size: 1.5rem;
                margin-bottom: 12px;
            }

            .status-content p {
                font-size: 1rem;
                margin-bottom: 15px;
            }

            .request-details {
                padding: 15px;
                margin: 15px 0;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 5px 2px;
            }

            .form-container {
                padding: 15px 10px;
                border-radius: 12px;
            }

            .header h1 {
                font-size: 1.6rem;
            }

            .header p {
                font-size: 0.9rem;
            }

            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 10px 14px;
                font-size: 0.9rem;
            }

            .form-group textarea {
                min-height: 80px;
            }

            .file-upload-label {
                padding: 10px 14px;
            }

            .privacy-label {
                padding: 10px 14px;
            }

            .btn {
                padding: 12px 25px;
                font-size: 0.95rem;
            }

            .status-card {
                padding: 20px 15px;
            }
        }

        @media (max-width: 1024px) and (min-width: 769px) {
            .container {
                max-width: 1000px;
            }

            .form-container {
                padding: 50px;
            }

            .request-form {
                grid-template-columns: 1fr 1fr;
                gap: 35px;
            }

            .full-width-field {
                grid-column: 1 / -1;
            }

            .two-column-field {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="client_messages.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Messages
        </a>

        <div class="header">
            <div class="logo">
                <img src="images/logo.jpg" alt="Opiña Law Office">
            </div>
            <h1>Request Access</h1>
            <p>Submit your information to start messaging with our legal team</p>
        </div>

        <?php if (isset($_GET['submitted']) && $_GET['submitted'] == '1'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Your request has been submitted successfully! An employee will review your request shortly.
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($existing_request && $existing_request['status'] !== 'Rejected'): ?>
            <?php if ($existing_request['status'] === 'Pending'): ?>
                <div class="status-card pending">
                    <div class="status-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="status-content">
                        <h3>Request Under Review</h3>
                        <p>Your request is currently being reviewed by our team. You will be notified once it's approved.</p>
                        <div class="request-details">
                            <strong>Request ID:</strong> <?= htmlspecialchars($existing_request['request_id']) ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($existing_request['status'] === 'Approved'): ?>
                <div class="status-card approved">
                    <div class="status-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="status-content">
                        <h3>Request Approved!</h3>
                        <p>Your request has been approved. You can now start messaging with our team.</p>
                        <div class="request-details">
                            <strong>Request ID:</strong> <?= htmlspecialchars($existing_request['request_id']) ?>
                        </div>
                        <div class="form-actions">
                            <a href="client_messages.php" class="btn btn-primary">
                                <i class="fas fa-envelope"></i>
                                Go to Messages
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($existing_request && $existing_request['status'] === 'Rejected'): ?>
            <div class="status-card rejected">
                <div class="status-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="status-content">
                    <h3>Previous Request Rejected</h3>
                    <p>Your previous request has been rejected. Please review the feedback below and submit a new request.</p>
                    <div class="request-details">
                        <strong>Request ID:</strong> <?= htmlspecialchars($existing_request['request_id']) ?>
                        <?php if ($existing_request['review_notes']): ?>
                            <br><br><strong>Rejection Reason:</strong><br>
                            <div class="rejection-notes">
                                <?= nl2br(htmlspecialchars($existing_request['review_notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <div class="form-container">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <h2>Legal Consultation Request</h2>
                    <p>Please provide your information and describe your legal concern to begin the consultation process.</p>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="request-form">
                    <input type="hidden" name="action" value="submit_request">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($client_name) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="sex">Sex *</label>
                            <select id="sex" name="sex" required>
                                <option value="">Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Contact Information
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address *</label>
                            <textarea id="address" name="address" rows="4" placeholder="Enter your complete address" required></textarea>
                        </div>
                    </div>
                    
                    <!-- Legal Concern Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-gavel"></i>
                            Legal Concern
                        </div>
                        
                        <div class="form-group">
                            <label for="concern_description">Legal Concern/Issue *</label>
                            <textarea id="concern_description" name="concern_description" rows="6" placeholder="Please describe your legal concern or issue in detail. Include relevant facts, dates, and any specific questions you have. The more information you provide, the better we can assist you." required></textarea>
                            <div class="field-help">
                                <i class="fas fa-info-circle"></i>
                                <span>Include key details such as: What happened? When did it occur? Who is involved? What outcome are you seeking?</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Upload Section -->
                    <div class="form-group full-width-field">
                        <div class="form-section-title">
                            <i class="fas fa-file-upload"></i>
                            Government ID Documents
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="valid_id_front">Government ID Front *</label>
                        <div class="file-upload-container">
                            <input type="file" id="valid_id_front" name="valid_id_front" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf">
                            <label for="valid_id_front" class="file-upload-label">
                                <i class="fas fa-upload"></i>
                                <span>Choose Front Image</span>
                            </label>
                            <div class="file-info">
                                <small>Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                            </div>
                            <div class="file-validation" id="front-validation"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="valid_id_back">Government ID Back *</label>
                        <div class="file-upload-container">
                            <input type="file" id="valid_id_back" name="valid_id_back" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf">
                            <label for="valid_id_back" class="file-upload-label">
                                <i class="fas fa-upload"></i>
                                <span>Choose Back Image</span>
                            </label>
                            <div class="file-info">
                                <small>Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                            </div>
                            <div class="file-validation" id="back-validation"></div>
                        </div>
                    </div>
                    
                    <!-- Privacy Consent Section -->
                    <div class="form-group full-width-field">
                        <div class="form-section-title">
                            <i class="fas fa-shield-alt"></i>
                            Privacy Consent
                        </div>
                        
                        <div class="privacy-checkbox-container">
                            <input type="checkbox" id="privacy_consent" name="privacy_consent">
                            <label for="privacy_consent" class="privacy-label">
                                <i class="fas fa-shield-alt"></i>
                                <span>I agree to the Data Privacy Act (Philippines - RA 10173)</span>
                            </label>
                            <div class="privacy-info">
                                <small>By checking this box, you consent to the collection, processing, and storage of your personal information in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173). Your information will be used solely for legal consultation purposes and will be kept confidential and secure.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="form-actions full-width-field">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Enhanced file validation function
        function validateFile(file, validationElementId) {
            const validationElement = document.getElementById(validationElementId);
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            const allowedExtensions = ['.jpg', '.jpeg', '.png', '.pdf'];
            
            // Clear previous validation
            validationElement.className = 'file-validation';
            validationElement.style.display = 'none';
            
            if (!file) {
                return false;
            }
            
            // Check file type by MIME type
            const isValidMimeType = allowedTypes.includes(file.type);
            
            // Check file extension
            const fileName = file.name.toLowerCase();
            const isValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
            
            // Check file size
            const isValidSize = file.size <= maxSize;
            
            if (!isValidMimeType && !isValidExtension) {
                validationElement.className = 'file-validation error';
                validationElement.innerHTML = '<i class="fas fa-times-circle"></i> Invalid file type. Only JPG, PNG, and PDF files are allowed.';
                validationElement.style.display = 'block';
                return false;
            }
            
            if (!isValidSize) {
                validationElement.className = 'file-validation error';
                validationElement.innerHTML = '<i class="fas fa-times-circle"></i> File size exceeds 5MB limit. Please choose a smaller file.';
                validationElement.style.display = 'block';
                return false;
            }
            
            // Success validation
            validationElement.className = 'file-validation success';
            validationElement.innerHTML = '<i class="fas fa-check-circle"></i> File is valid and ready for upload.';
            validationElement.style.display = 'block';
            return true;
        }

        // File upload validation for front ID
        document.getElementById('valid_id_front').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = document.querySelector('label[for="valid_id_front"] span');
            
            if (file) {
                label.textContent = file.name;
                
                // Validate file
                const isValid = validateFile(file, 'front-validation');
                
                if (!isValid) {
                    e.target.value = '';
                    label.textContent = 'Choose Front Image';
                } else {
                    hideFileError('valid_id_front');
                }
            } else {
                label.textContent = 'Choose Front Image';
                document.getElementById('front-validation').style.display = 'none';
            }
        });

        // File upload validation for back ID
        document.getElementById('valid_id_back').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = document.querySelector('label[for="valid_id_back"] span');
            
            if (file) {
                label.textContent = file.name;
                
                // Validate file
                const isValid = validateFile(file, 'back-validation');
                
                if (!isValid) {
                    e.target.value = '';
                    label.textContent = 'Choose Back Image';
                } else {
                    hideFileError('valid_id_back');
                }
            } else {
                label.textContent = 'Choose Back Image';
                document.getElementById('back-validation').style.display = 'none';
            }
        });

        // Privacy checkbox change event
        document.getElementById('privacy_consent').addEventListener('change', function() {
            if (this.checked) {
                hidePrivacyError();
            }
        });

        // Privacy consent validation
        function validatePrivacyConsent() {
            const privacyCheckbox = document.getElementById('privacy_consent');
            
            if (!privacyCheckbox.checked) {
                showPrivacyError();
                return false;
            }
            
            hidePrivacyError();
            return true;
        }
        
        function showPrivacyError() {
            hidePrivacyError();
            
            const errorDiv = document.createElement('div');
            errorDiv.id = 'privacy-error';
            errorDiv.className = 'privacy-error-message';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>Please check the privacy consent checkbox to continue with your request submission.</span>
            `;
            
            const privacyContainer = document.querySelector('.privacy-checkbox-container');
            privacyContainer.parentNode.insertBefore(errorDiv, privacyContainer.nextSibling);
            
            const checkbox = document.getElementById('privacy_consent');
            checkbox.style.border = '2px solid #dc3545';
            checkbox.style.borderRadius = '4px';
        }
        
        function hidePrivacyError() {
            const errorDiv = document.getElementById('privacy-error');
            if (errorDiv) {
                errorDiv.remove();
            }
            
            const checkbox = document.getElementById('privacy_consent');
            checkbox.style.border = '';
            checkbox.style.borderRadius = '';
        }

        function showFileError(inputId, message) {
            hideFileError(inputId);
            
            const errorDiv = document.createElement('div');
            errorDiv.id = inputId + '-error';
            errorDiv.className = 'file-error-message';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>${message}</span>
            `;
            
            const input = document.getElementById(inputId);
            const container = input.closest('.file-upload-container');
            container.appendChild(errorDiv);
            
            input.style.border = '2px solid #dc3545';
        }
        
        function hideFileError(inputId) {
            const errorDiv = document.getElementById(inputId + '-error');
            if (errorDiv) {
                errorDiv.remove();
            }
            
            const input = document.getElementById(inputId);
            input.style.border = '';
        }

        // Form submission validation
        document.querySelector('.request-form').addEventListener('submit', function(e) {
            const frontFile = document.getElementById('valid_id_front').files[0];
            const backFile = document.getElementById('valid_id_back').files[0];
            
            let hasErrors = false;
            
            // Validate privacy consent first
            if (!validatePrivacyConsent()) {
                hasErrors = true;
            }
            
            // Validate front file
            if (!frontFile) {
                showFileError('valid_id_front', 'Please upload a front ID image.');
                hasErrors = true;
            } else if (!validateFile(frontFile, 'front-validation')) {
                hasErrors = true;
            } else {
                hideFileError('valid_id_front');
            }
            
            // Validate back file
            if (!backFile) {
                showFileError('valid_id_back', 'Please upload a back ID image.');
                hasErrors = true;
            } else if (!validateFile(backFile, 'back-validation')) {
                hasErrors = true;
            } else {
                hideFileError('valid_id_back');
            }
            
            if (hasErrors) {
                e.preventDefault();
                alert('Please fix file upload errors before submitting the form.');
                return false;
            }
        });
    </script>
</body>
</html>
