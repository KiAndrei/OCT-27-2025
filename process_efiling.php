<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Security: allow attorneys and admins (admin may also act as attorney)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'] ?? '', ['attorney','admin_attorney','admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$attorney_id = (int)$_SESSION['user_id'];

// Get attorney details for email
$stmt = $conn->prepare("SELECT name, email FROM user_form WHERE id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
$attorney_name = '';
$attorney_email = '';
if ($res && $row = $res->fetch_assoc()) {
    $attorney_name = $row['name'];
    $attorney_email = $row['email'];
}

// Handle clear history request
if (isset($_POST['action']) && $_POST['action'] === 'clear_history') {
    // Delete all eFiling history for this attorney
    $stmt = $conn->prepare("DELETE FROM efiling_history WHERE attorney_id=?");
    $stmt->bind_param("i", $attorney_id);
    $stmt->execute();
    
    // Also delete the stored files
    $stmt = $conn->prepare("SELECT stored_file_path FROM efiling_history WHERE attorney_id=?");
    $stmt->bind_param("i", $attorney_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['stored_file_path']) && file_exists($row['stored_file_path'])) {
            unlink($row['stored_file_path']);
        }
    }
    
    // Clean up any orphaned files in efiling directory
    $efilingDir = 'uploads/efiling/';
    if (is_dir($efilingDir)) {
        $files = glob($efilingDir . 'ef_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    echo json_encode(['status' => 'success', 'message' => 'All eFiling history cleared']);
    exit();
}

// Get multi-step form data from Steps 1-5
$service_type = trim($_POST['service_type'] ?? '');
$payor_type = trim($_POST['payor_type'] ?? '');
$court_level = trim($_POST['court_level'] ?? '');
$court_type = trim($_POST['court_type'] ?? '');
$region = trim($_POST['region'] ?? '');
$province = trim($_POST['province'] ?? '');
$court_station = trim($_POST['court_station'] ?? '');
$receiver_email = trim($_POST['receiver_email'] ?? '');
$case_category = trim($_POST['case_category'] ?? '');
$case_type = trim($_POST['case_type'] ?? '');
$case_number = trim($_POST['case_number'] ?? '');
$case_title = trim($_POST['case_title'] ?? '');
$party_type = trim($_POST['party_type'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$middle_initial = trim($_POST['middle_initial'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email_address = trim($_POST['email_address'] ?? '');
$mobile_number = trim($_POST['mobile_number'] ?? '');
$fee_exemption = trim($_POST['fee_exemption'] ?? '');
$fee_condition = trim($_POST['fee_condition'] ?? '');
$defendants_count = trim($_POST['defendants_count'] ?? '');

// Debug: Log all received POST data
error_log('eFiling Debug - All POST data: ' . print_r($_POST, true));
error_log('eFiling Debug - Case Type: ' . $case_type);
error_log('eFiling Debug - Case Title: ' . $case_title);

// Validate required fields from Steps 1-5
if ($service_type === '' || $payor_type === '' || $court_level === '' || $court_type === '' || 
    $region === '' || $province === '' || $court_station === '' || $receiver_email === '' ||
    $case_category === '' || $case_type === '' || $case_number === '' || $case_title === '' ||
    $party_type === '' || $first_name === '' || $last_name === '' || $email_address === '' ||
    $mobile_number === '' || $fee_exemption === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please complete all required fields.']);
    exit();
}

// If fee exemption is "Yes", condition is required
if ($fee_exemption === 'Yes' && $fee_condition === '') {
    echo json_encode(['status' => 'error', 'message' => 'Fee condition is required when fee exemption is Yes.']);
    exit();
}

// Validate email format
if (!filter_var($receiver_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid receiver email address.']);
    exit();
}

if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid personal email address.']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No document uploaded or upload error occurred.']);
    exit();
}

// Validate file size and type
$allowed_ext = ['pdf'];
$uploaded_name = $_FILES['document']['name'];
$uploaded_tmp = $_FILES['document']['tmp_name'];
$uploaded_ext = strtolower(pathinfo($uploaded_name, PATHINFO_EXTENSION));
$original_file_name = $uploaded_name;

if (!in_array($uploaded_ext, $allowed_ext, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Only PDF files are allowed.']);
    exit();
}

if (filesize($uploaded_tmp) > 5 * 1024 * 1024) { // 5MB
    echo json_encode(['status' => 'error', 'message' => 'File too large (max 5MB).']);
    exit();
}

// Generate a unique reference ID for this submission
$reference_id = 'EF-' . date('YmdHis') . '-' . substr(uniqid(), -6);

// Move the uploaded file to a permanent storage directory
$uploadDir = 'uploads/efiling/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Create unique filename to avoid conflicts
$uniqueFilename = uniqid('ef_', true) . '_' . $original_file_name;
$targetPath = $uploadDir . $uniqueFilename;
if (!move_uploaded_file($uploaded_tmp, $targetPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not process the uploaded file.']);
    exit();
}

// Prepare email
$mail = new PHPMailer(true);
$status = 'Failed';

// Set execution time limit for optimal performance
set_time_limit(60); // 1 minute for 5MB max files
ini_set('memory_limit', '128M'); // Sufficient memory for 5MB files
ini_set('max_execution_time', 60); // 1 minute execution time

try {
    if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
        $mail->SMTPDebug = 2;
    }
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    
    // Try STARTTLS first (more reliable for Gmail)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Optimize for faster sending
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    $mail->Timeout = 60; // 1 minute timeout for 5MB max files
    $mail->SMTPKeepAlive = false; // Disable keep-alive to avoid connection issues
    $mail->SMTPAutoTLS = true; // Enable auto TLS for better compatibility
    $mail->Debugoutput = 'error_log'; // Log errors for debugging
    $mail->SMTPDebug = 0; // Disable debug output for production
    
    // Additional connection settings for stability
    $mail->SMTPOptions['ssl']['crypto_method'] = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($receiver_email);
    if (defined('MAIL_FROM') && defined('MAIL_FROM_NAME')) {
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
    }

    // Build comprehensive eFiling submission email body with improved design
    $body = "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Electronic Filing Submission</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f5f5f5; font-family: Arial, sans-serif;'>
        <div style='max-width: 800px; margin: 20px auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;'>
            
            <!-- Header -->
            <div style='background: linear-gradient(135deg, #7C0F2F 0%, #8B1538 100%); padding: 30px; text-align: center; color: white;'>
                <h1 style='margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 1px;'>ELECTRONIC FILING SUBMISSION</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Opiña Law Office</p>
            </div>
            
            <!-- Submission Info -->
            <div style='padding: 25px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>
                <div style='display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;'>
                    <div style='flex: 1; min-width: 200px;'>
                        <strong style='color: #7C0F2F; font-size: 14px;'>TO:</strong><br>
                        <span style='color: #333;'>Honorable Court</span>
                    </div>
                    <div style='flex: 1; min-width: 200px;'>
                        <strong style='color: #7C0F2F; font-size: 14px;'>FROM:</strong><br>
                        <span style='color: #333;'>" . htmlspecialchars($attorney_name) . "</span>
                    </div>
                    <div style='flex: 1; min-width: 200px;'>
                        <strong style='color: #7C0F2F; font-size: 14px;'>DATE:</strong><br>
                        <span style='color: #333;'>" . date('F d, Y \a\t g:i A') . "</span>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div style='padding: 30px;'>
                <p style='font-size: 16px; color: #333; margin-bottom: 25px; line-height: 1.6;'>
                    Respectfully submitted for filing and consideration of this Honorable Court:
                </p>
                
                <!-- Single Consolidated Information Container -->
                <div style='background: #fff; border: 2px solid #e9ecef; border-radius: 10px; margin-bottom: 20px; overflow: hidden;'>
                    <div style='background: #7C0F2F; color: white; padding: 15px; font-weight: 600; font-size: 16px;'>
                        📋 FILING INFORMATION
                    </div>
                    <div style='padding: 25px;'>
                        <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>
                            <!-- Request Details -->
                            <div>
                                <h4 style='color: #7C0F2F; font-size: 14px; margin: 0 0 10px 0; font-weight: 600; border-top: 2px solid #7C0F2F; padding-top: 5px;'>REQUEST DETAILS</h4>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Service Type:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($service_type) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Payor Type:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($payor_type) . "</span>
                                </div>
                            </div>
                            
                            <!-- Court Details -->
                            <div>
                                <h4 style='color: #7C0F2F; font-size: 14px; margin: 0 0 10px 0; font-weight: 600; border-top: 2px solid #7C0F2F; padding-top: 5px;'>COURT DETAILS</h4>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Court Level:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($court_level) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Court Type:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($court_type) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Region:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($region) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Province:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($province) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Court Station:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($court_station) . "</span>
                                </div>
                            </div>
                            
                            <!-- Case Details -->
                            <div>
                                <h4 style='color: #7C0F2F; font-size: 14px; margin: 0 0 10px 0; font-weight: 600; border-top: 2px solid #7C0F2F; padding-top: 5px;'>CASE DETAILS</h4>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Case Category:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($case_category) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Case Type:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($case_type) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Case Number:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($case_number) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Case Title:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($case_title) . "</span>
                                </div>
                            </div>
                            
                            <!-- Personal Information -->
                            <div>
                                <h4 style='color: #7C0F2F; font-size: 14px; margin: 0 0 10px 0; font-weight: 600; border-top: 2px solid #7C0F2F; padding-top: 5px;'>PERSONAL INFORMATION</h4>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Party Type:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($party_type) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Full Name:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($first_name . ' ' . $middle_initial . ' ' . $last_name) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Email Address:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($email_address) . "</span>
                                </div>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Mobile Number:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($mobile_number) . "</span>
                                </div>
                            </div>
                            
                            <!-- Basis of Fees -->
                            <div>
                                <h4 style='color: #7C0F2F; font-size: 14px; margin: 0 0 10px 0; font-weight: 600; border-top: 2px solid #7C0F2F; padding-top: 5px;'>BASIS OF FEES</h4>
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Fee Exemption:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($fee_exemption) . "</span>
                                </div>" .
                                ($fee_exemption === 'Yes' ? "
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Condition:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($fee_condition) . "</span>
                                </div>" : '') . "
                                <div style='margin-bottom: 8px;'>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>No. of Defendants/Respondents:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($defendants_count ?: 'Not specified') . "</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submission Details Row -->
                        <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;'>
                            <h4 style='color: #7C0F2F; font-size: 14px; margin: 0 0 15px 0; font-weight: 600; border-top: 2px solid #7C0F2F; padding-top: 5px;'>SUBMISSION DETAILS</h4>
                            <div style='display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;'>
                                <div>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Submission Date:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . date('F d, Y') . "</span>
                                </div>
                                <div>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Reference ID:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($reference_id) . "</span>
                                </div>
                                <div>
                                    <strong style='color: #7C0F2F; font-size: 13px;'>Receiver Email:</strong><br>
                                    <span style='color: #333; font-size: 14px;'>" . htmlspecialchars($receiver_email) . "</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>" .
                
                "<div style='background: #e8f5e8; border: 2px solid #4caf50; border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 20px;'>
                    <p style='margin: 0; font-size: 16px; color: #2e7d32; font-weight: 600;'>
                        📄 Electronic Filing with Document Attachment
                    </p>
                    <p style='margin: 10px 0 0 0; font-size: 14px; color: #2e7d32;'>
                        Please find the attached document: <strong>" . htmlspecialchars($original_file_name) . "</strong>
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style='background: #f8f9fa; padding: 25px; text-align: center; border-top: 1px solid #e9ecef;'>
                <div style='margin-bottom: 15px;'>
                    <strong style='color: #7C0F2F; font-size: 18px;'>OPIÑA LAW OFFICE</strong>
                </div>
                <div style='color: #666; font-size: 14px; line-height: 1.6;'>
                    Submitted by: " . htmlspecialchars($attorney_name) . "<br>
                    Attorney Email: " . htmlspecialchars($attorney_email) . "<br>
                    Electronic Filing System<br>
                    Submitted on " . date('F d, Y \a\t g:i A') . "
                </div>
                <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999; margin: 0;'>
                    This is an automated submission from Opiña Law Office Electronic Filing System.<br>
                    For any inquiries, please contact our office directly.
                </p>
            </div>
        </div>
    </body>
    </html>";

    $mail->isHTML(true);
    $mail->Subject = 'ELECTRONIC FILING SUBMISSION - ' . $case_title . ' - ' . $reference_id;
    $mail->Body = $body;
    $mail->AltBody = "ELECTRONIC FILING SUBMISSION\n\n" .
                     "═══════════════════════════════════════════════════════════════\n" .
                     "OPIÑA LAW OFFICE - ELECTRONIC FILING SUBMISSION\n" .
                     "═══════════════════════════════════════════════════════════════\n\n" .
                     "TO: Honorable Court\n" .
                     "FROM: " . $attorney_name . " - Opiña Law Office\n" .
                     "DATE: " . date('F d, Y \a\t g:i A') . "\n\n" .
                     "Respectfully submitted for filing and consideration:\n\n" .
                     "📋 FILING INFORMATION\n" .
                     "═══════════════════════════════════════════════════════════════\n\n" .
                     "REQUEST DETAILS:\n" .
                     "─────────────────────────────────────────────────────────────\n" .
                     "Service Type: $service_type\n" .
                     "Payor Type: $payor_type\n\n" .
                     "COURT DETAILS:\n" .
                     "─────────────────────────────────────────────────────────────\n" .
                     "Court Level: $court_level\n" .
                     "Court Type: $court_type\n" .
                     "Region: $region\n" .
                     "Province: $province\n" .
                     "Court Station: $court_station\n" .
                     "Receiver Email: $receiver_email\n\n" .
                     "CASE DETAILS:\n" .
                     "─────────────────────────────────────────────────────────────\n" .
                     "Case Category: $case_category\n" .
                     "Case Type: $case_type\n" .
                     "Case Number: $case_number\n" .
                     "Case Title: $case_title\n\n" .
                     "PERSONAL INFORMATION:\n" .
                     "─────────────────────────────────────────────────────────────\n" .
                     "Party Type: $party_type\n" .
                     "Full Name: $first_name $middle_initial $last_name\n" .
                     "Email Address: $email_address\n" .
                     "Mobile Number: $mobile_number\n\n" .
                     "BASIS OF FEES:\n" .
                     "─────────────────────────────────────────────────────────────\n" .
                     "Fee Exemption: $fee_exemption\n" .
                     ($fee_exemption === 'Yes' ? "Condition: $fee_condition\n" : '') .
                     "No. of Defendants/Respondents: " . ($defendants_count ?: 'Not specified') . "\n\n" .
                     "SUBMISSION DETAILS:\n" .
                     "─────────────────────────────────────────────────────────────\n" .
                     "Submission Date: " . date('F d, Y') . "\n" .
                     "Reference ID: $reference_id\n" .
                     "Receiver Email: $receiver_email\n\n" .
                     "📄 Electronic Filing with Document Attachment\n" .
                     "Please find the attached document: $original_file_name\n\n" .
                     "═══════════════════════════════════════════════════════════════\n" .
                     "OPIÑA LAW OFFICE\n" .
                     "Electronic Filing System\n" .
                     "Submitted by: " . $attorney_name . "\n" .
                     "Attorney Email: " . $attorney_email . "\n" .
                     "Submitted on " . date('F d, Y \a\t g:i A') . "\n" .
                     "═══════════════════════════════════════════════════════════════\n\n" .
                     "This is an automated submission from Opiña Law Office Electronic Filing System.\n" .
                     "For any inquiries, please contact our office directly.";

    // Attach the uploaded file
    $mail->addAttachment($targetPath, $original_file_name);

    if ($mail->send()) {
        $status = 'Sent';
    }
} catch (Exception $e) {
    error_log('eFiling send error: ' . $mail->ErrorInfo . ' Exception: ' . $e->getMessage());
    $status = 'Failed';
}

// Log to history regardless of result
$case_id_null = null;
$message_text = 'Electronic filing with document attachment submitted';
$document_category = 'Electronic Filing';

$stmt = $conn->prepare("INSERT INTO efiling_history (attorney_id, case_id, file_name, original_file_name, stored_file_path, receiver_email, message, status, document_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('iisssssss', $attorney_id, $case_id_null, $reference_id, $original_file_name, $targetPath, $receiver_email, $message_text, $status, $document_category);
$stmt->execute();

if ($status === 'Sent') {
    echo json_encode(['status' => 'success', 'message' => 'Submission sent successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send submission.']);
}
?>


