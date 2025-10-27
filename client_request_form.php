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
    $address = trim($_POST['address']);
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
            header("Location: client_request_form.php?submitted=1");
            exit();
        } else {
            $error_message = "Failed to submit request. Please try again.";
        }
    }
}

// Check if there's an approved request and get conversation status
$conversation_status = null;
$employee_name = null;
$attorney_name = null;

if ($existing_request && $existing_request['status'] === 'Approved') {
    // Get employee conversation
    $stmt = $conn->prepare("
        SELECT cec.id, cec.conversation_status, cec.concern_identified, u.name as employee_name
        FROM client_employee_conversations cec
        JOIN user_form u ON cec.employee_id = u.id
        WHERE cec.request_form_id = (
            SELECT id FROM client_request_form WHERE client_id = ? AND status = 'Approved' ORDER BY submitted_at DESC LIMIT 1
        )
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $employee_conversation = $res->fetch_assoc();
    
    if ($employee_conversation) {
        $conversation_status = $employee_conversation['conversation_status'];
        $employee_name = $employee_conversation['employee_name'];
        
        // Check if attorney is assigned
        if ($employee_conversation['concern_identified']) {
            $stmt = $conn->prepare("
                SELECT caa.id, caa.status, u.name as attorney_name
                FROM client_attorney_assignments caa
                JOIN user_form u ON caa.attorney_id = u.id
                WHERE caa.conversation_id = ?
            ");
            $stmt->bind_param("i", $employee_conversation['id']);
            $stmt->execute();
            $res = $stmt->get_result();
            $attorney_assignment = $res->fetch_assoc();
            
            if ($attorney_assignment) {
                $attorney_name = $attorney_assignment['attorney_name'];
            }
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
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="client_dashboard.php" title="View your case overview, statistics, and recent activities">
                    <div class="button-content">
                        <i class="fas fa-home"></i>
                        <div class="text-content">
                            <span>Dashboard</span>
                            <small>Overview & Statistics</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_documents.php" title="Generate legal documents like affidavits and sworn statements">
                    <div class="button-content">
                        <i class="fas fa-file-alt"></i>
                        <div class="text-content">
                            <span>Document Generation</span>
                            <small>Create Legal Documents</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_cases.php" title="Track your legal cases, view case details, and upload documents">
                    <div class="button-content">
                        <i class="fas fa-gavel"></i>
                        <div class="text-content">
                            <span>My Cases</span>
                            <small>Track Legal Cases</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_schedule.php" title="View your upcoming appointments, hearings, and court schedules">
                    <div class="button-content">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="text-content">
                            <span>My Schedule</span>
                            <small>Appointments & Hearings</small>
                        </div>
                    </div>
                </a>
            </li>
            <li>
                <a href="client_messages.php" title="Communicate with your attorney and legal team">
                    <div class="button-content">
                        <i class="fas fa-envelope"></i>
                        <div class="text-content">
                            <span>Messages</span><small>Chat with Attorney</small></div></div><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a>
            </li>
            <li>
                <a href="client_about.php" title="Learn more about Opiña Law Office and our team">
                    <div class="button-content">
                        <i class="fas fa-info-circle"></i>
                        <div class="text-content">
                            <span>About Us</span>
                            <small>Our Story & Team</small>
                        </div>
                    </div>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1>Request Access</h1>
                <p>Submit your information to start messaging with our legal team</p>
            </div>
            <div class="user-info">
                <div class="profile-dropdown" style="display: flex; align-items: center; gap: 12px;">
                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="Client" style="object-fit:cover;width:42px;height:42px;border-radius:50%;border:2px solid #1976d2;">
                    <div class="user-details">
                        <h3><?php echo $_SESSION['client_name']; ?></h3>
                        <p>Client</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-container">
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
                                <?php if ($employee_name): ?>
                                    <br><strong>Assigned Employee:</strong> <?= htmlspecialchars($employee_name) ?>
                                <?php endif; ?>
                                <?php if ($attorney_name): ?>
                                    <br><strong>Assigned Attorney:</strong> <?= htmlspecialchars($attorney_name) ?>
                                <?php endif; ?>
                                <?php if ($existing_request['review_notes']): ?>
                                    <br><br><strong>Approval Notes:</strong><br>
                                    <div class="approval-notes">
                                        <?= nl2br(htmlspecialchars($existing_request['review_notes'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="action-buttons">
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
                <!-- Show rejection reason and allow resubmission -->
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
                <!-- Request Form -->
                <div class="form-container">
                    <div class="form-header">
                        <h2>Legal Consultation Request Form</h2>
                        <p>Please fill out the form below to request access to our messaging system.</p>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="request-form">
                        <input type="hidden" name="action" value="submit_request">
                        
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($client_name) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address *</label>
                            <textarea id="address" name="address" rows="3" placeholder="Enter your complete address" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="sex">Sex *</label>
                            <select id="sex" name="sex" required>
                                <option value="">Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="concern_description">Legal Concern/Issue *</label>
                            <textarea id="concern_description" name="concern_description" rows="5" placeholder="Please describe your legal concern or issue in detail. Include relevant facts, dates, and any specific questions you have. The more information you provide, the better we can assist you." required></textarea>
                            <div class="field-help">
                                <i class="fas fa-info-circle"></i>
                                <span>Include key details such as: What happened? When did it occur? Who is involved? What outcome are you seeking?</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_id_front">Government ID Front *</label>
                            <div class="file-upload-container">
                                <input type="file" id="valid_id_front" name="valid_id_front" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" required>
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
                                <input type="file" id="valid_id_back" name="valid_id_back" accept="image/jpeg,image/jpg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" required>
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
                        
                        <div class="form-group">
                            <div class="privacy-checkbox-container">
                                <input type="checkbox" id="privacy_consent" name="privacy_consent" required>
                                <label for="privacy_consent" class="privacy-label">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>I agree to the Data Privacy Act (Philippines - RA 10173)</span>
                                </label>
                                <div class="privacy-info">
                                    <small>By checking this box, you consent to the collection, processing, and storage of your personal information in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173). Your information will be used solely for legal consultation purposes and will be kept confidential and secure.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .content-container {
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
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
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(93, 14, 38, 0.12);
            border: 2px solid rgba(93, 14, 38, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .status-card.pending {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }

        .status-card.rejected {
            border-color: #dc3545;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        }

        .status-card.approved {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        }

        .status-icon {
            font-size: 3rem;
            color: var(--primary-color);
        }

        .status-card.pending .status-icon {
            color: #ffc107;
        }

        .status-card.rejected .status-icon {
            color: #dc3545;
        }

        .status-card.approved .status-icon {
            color: #28a745;
        }

        .status-content {
            flex: 1;
        }

        .status-content h3 {
            margin: 0 0 10px 0;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .status-content p {
            margin: 0 0 15px 0;
            color: #666;
            line-height: 1.6;
        }

        .request-details {
            background: rgba(255, 255, 255, 0.7);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
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

        .approval-notes {
            background: rgba(40, 167, 69, 0.1);
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-top: 8px;
            font-style: italic;
            color: #155724;
        }

        .action-buttons {
            margin-top: 20px;
        }

        .form-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(93, 14, 38, 0.12);
            border: 2px solid rgba(93, 14, 38, 0.08);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            color: var(--primary-color);
            margin: 0 0 10px 0;
            font-size: 2rem;
        }

        .form-header p {
            color: #666;
            margin: 0;
            font-size: 1.1rem;
        }

        .request-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 15px 20px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(93, 14, 38, 0.1);
            background: white;
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

        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
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
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.3);
        }

        @media (max-width: 768px) {
            .content-container {
                padding: 20px;
            }
            
            .status-card {
                flex-direction: column;
                text-align: center;
            }
            
            .form-container {
                padding: 25px;
            }
        }

        /* Professional Design Override */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        .content-container {
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 50px;
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

        .form-title h2 {
            color: #5D0E26;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 15px 20px;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            box-shadow: 0 0 0 4px rgba(93, 14, 38, 0.15);
            background: white;
        }

        .file-upload-label {
            padding: 20px;
            border-radius: 12px;
            font-weight: 600;
        }

        .file-upload-label:hover {
            transform: translateY(-2px);
        }

        .file-upload-label i {
            font-size: 1.5rem;
        }

        .privacy-label {
            padding: 20px;
            border-radius: 16px;
            font-weight: 600;
        }

        .privacy-label:hover {
            transform: translateY(-2px);
        }

        .privacy-label i {
            font-size: 1.5rem;
            margin-top: 2px;
        }

        .privacy-label span {
            font-size: 1.1rem;
            line-height: 1.5;
        }

        .privacy-info {
            padding: 20px;
            border-radius: 12px;
        }

        .btn {
            padding: 18px 40px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .btn-primary {
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(93, 14, 38, 0.4);
        }

        .btn i {
            font-size: 1.2rem;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(93, 14, 38, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .status-content h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .status-content p {
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .request-details {
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 30px 20px;
            }
            
            .form-title h2 {
                font-size: 2rem;
            }
        }
    </style>

    <script>
        // Profile dropdown functions removed - profile is non-clickable on this page

        // Privacy consent validation
        function validatePrivacyConsent() {
            const privacyCheckbox = document.getElementById('privacy_consent');
            
            if (!privacyCheckbox.checked) {
                // Show error message
                showPrivacyError();
                return false;
            }
            
            // Hide error message if checkbox is checked
            hidePrivacyError();
            return true;
        }
        
        function showPrivacyError() {
            // Remove existing error message
            hidePrivacyError();
            
            // Create error message
            const errorDiv = document.createElement('div');
            errorDiv.id = 'privacy-error';
            errorDiv.className = 'privacy-error-message';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>Please check the privacy consent checkbox to continue with your request submission.</span>
            `;
            
            // Insert after privacy checkbox container
            const privacyContainer = document.querySelector('.privacy-checkbox-container');
            privacyContainer.parentNode.insertBefore(errorDiv, privacyContainer.nextSibling);
            
            // Add red border to checkbox
            const checkbox = document.getElementById('privacy_consent');
            checkbox.style.border = '2px solid #dc3545';
            checkbox.style.borderRadius = '4px';
        }
        
        function hidePrivacyError() {
            const errorDiv = document.getElementById('privacy-error');
            if (errorDiv) {
                errorDiv.remove();
            }
            
            // Remove red border from checkbox
            const checkbox = document.getElementById('privacy_consent');
            checkbox.style.border = '';
            checkbox.style.borderRadius = '';
        }
        
        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const privacyCheckbox = document.getElementById('privacy_consent');
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                if (!validatePrivacyConsent()) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Real-time validation when checkbox changes
            privacyCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    hidePrivacyError();
                }
            });
        });

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
                }
            } else {
                label.textContent = 'Choose Back Image';
                document.getElementById('back-validation').style.display = 'none';
            }
        });

        // Form submission validation
        document.querySelector('.request-form').addEventListener('submit', function(e) {
            const frontFile = document.getElementById('valid_id_front').files[0];
            const backFile = document.getElementById('valid_id_back').files[0];
            
            let hasErrors = false;
            
            // Validate front file
            if (!frontFile || !validateFile(frontFile, 'front-validation')) {
                hasErrors = true;
            }
            
            // Validate back file
            if (!backFile || !validateFile(backFile, 'back-validation')) {
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
                alert('Please fix file upload errors before submitting the form.');
                return false;
            }
        });
    </script>
<script src="assets/js/unread-messages.js?v=1761535514"></script></body>
</html>
