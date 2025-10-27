<?php
// Suppress all errors and warnings for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to prevent any unwanted output
ob_start();

// Start session manually to avoid header issues
session_start();

require_once 'config.php';
require_once 'audit_logger.php';
require_once __DIR__ . '/vendor/autoload.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Validate user access manually
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client') {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$client_id = $_SESSION['user_id'];

// Get client name for logging
$stmt = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$client_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $client_name = $row['name'];
}

// Get form data
$form_type = $_POST['form_type'] ?? '';
$form_data_json = $_POST['form_data'] ?? '';

// Debug logging
error_log("Document Handler Debug - Form Type: " . $form_type);
error_log("Document Handler Debug - Form Data JSON: " . $form_data_json);

// Parse JSON form data
$form_data = json_decode($form_data_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    error_log("Document Handler Debug - JSON Parse Error: " . json_last_error_msg());
    echo json_encode(['status' => 'error', 'message' => 'Invalid form data format']);
    exit;
}

error_log("Document Handler Debug - Parsed Form Data: " . print_r($form_data, true));

if (empty($form_type) || empty($form_data)) {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
    exit;
}

// Generate unique request ID with timestamp to prevent duplicates
$request_id = 'DOC_' . date('YmdHis') . '_' . str_pad($client_id, 4, '0', STR_PAD_LEFT) . '_' . rand(1000, 9999);

// Prepare data for database insertion
$full_name = $form_data['fullName'] ?? '';
$address = $form_data['completeAddress'] ?? $form_data['fullAddress'] ?? '';
$gender = $form_data['gender'] ?? '';

// Generate the actual PDF file
$pdf_filename = '';
$pdf_path = '';

try {
    // Create PDF based on document type
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Opiña Law Office');
    $pdf->SetAuthor('Opiña Law Office');
    $pdf->SetTitle($form_type);
    $pdf->SetSubject($form_type);
    
    // Set default header data
    $pdf->SetHeaderData('', '', '', '');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(28, 5, 28);
    $pdf->SetAutoPageBreak(FALSE);
    
    // Set font
    $pdf->SetFont('times', '', 11);
    
    // Add a page
    $pdf->AddPage();
    
    // Generate HTML content based on document type
    $html = generateDocumentHTML($form_type, $form_data);
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Generate filename and path
    $pdf_filename = $form_type . '_' . $request_id . '.pdf';
    $pdf_path = __DIR__ . '/uploads/documents/' . $pdf_filename;
    
    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Save PDF to file
    $pdf->Output($pdf_path, 'F');
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate PDF: ' . $e->getMessage()]);
    exit;
}

// Table client_document_generation already has all required columns

// Check for duplicate submission within the last 5 minutes (only for pending documents)
$check_stmt = $conn->prepare("
    SELECT id FROM client_document_generation 
    WHERE client_id = ? AND document_type = ? AND status = 'Pending' AND submitted_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY submitted_at DESC LIMIT 1
");
$check_stmt->bind_param("is", $client_id, $form_type);
$check_stmt->execute();
$duplicate_check = $check_stmt->get_result();

if ($duplicate_check->num_rows > 0) {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'You already have a pending submission for this document type. Please wait for it to be reviewed before submitting again.']);
    exit;
}

// Insert document request into database
$stmt = $conn->prepare("
    INSERT INTO client_document_generation 
    (request_id, client_id, document_type, document_data, pdf_file_path, pdf_filename, status, submitted_at) 
    VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())
");

$document_type = $form_type;
$document_data = json_encode($form_data);

$stmt->bind_param("sissss", 
    $request_id, 
    $client_id, 
    $document_type,
    $document_data,
    $pdf_path,
    $pdf_filename
);

if ($stmt->execute()) {
    // Debug logging
    error_log("Document Handler Debug - Database insertion successful for request ID: " . $request_id);
    
    // Log to audit trail
    try {
        $auditLogger = new AuditLogger($conn);
        $auditLogger->logAction(
            $client_id,
            $client_name,
            'client',
            'Document Submission',
            'Document Generation',
            "Submitted $form_type document with request ID: $request_id",
            'success',
            'medium'
        );
    } catch (Exception $e) {
        error_log("Audit logging failed: " . $e->getMessage());
    }
    
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Document sent successfully!',
        'request_id' => $request_id,
        'pdf_path' => $pdf_path,
        'debug_info' => [
            'client_id' => $client_id,
            'form_type' => $form_type,
            'pdf_filename' => $pdf_filename,
            'document_type' => $document_type
        ]
    ]);
} else {
    // Debug logging
    error_log("Document Handler Debug - Database insertion failed: " . $conn->error);
    
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to send document. Please try again.',
        'debug_info' => [
            'error' => $conn->error,
            'client_id' => $client_id,
            'form_type' => $form_type
        ]
    ]);
}

// Function to generate HTML content based on document type
function generateDocumentHTML($form_type, $form_data) {
    switch($form_type) {
        case 'affidavitLoss':
            return generateAffidavitLossHTML($form_data);
        case 'soloParent':
            return generateSoloParentHTML($form_data);
        case 'pwdLoss':
            return generatePWDLossHTML($form_data);
        case 'boticabLoss':
            return generateBoticabLossHTML($form_data);
        case 'swornAffidavitMother':
            return generateSwornAffidavitMotherHTML($form_data);
        case 'seniorIDLoss':
            return generateSeniorIDLossHTML($form_data);
        case 'jointAffidavit':
            return generateJointAffidavitHTML($form_data);
        default:
            return '<p>Unknown document type</p>';
    }
}

function generateAffidavitLossHTML($data) {
    $today = new DateTime();
    return "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='font-size: 11pt; margin-bottom: 20px;'>
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style='font-size: 14pt; font-weight: bold; margin-bottom: 30px;'>
                AFFIDAVIT OF LOSS
            </div>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            I, <strong>{$data['fullName']}</strong>, of legal age, Filipino, and residing at <strong>{$data['completeAddress']}</strong>, after having been duly sworn to in accordance with law, hereby depose and say:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am the lawful owner of <strong>{$data['specifyItemLost']}</strong> described as follows:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            <strong>{$data['itemLost']}</strong>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That the said <strong>{$data['itemLost']}</strong> was lost on <strong>{$data['itemDetails']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I have exerted all efforts to locate the same but to no avail;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am executing this affidavit to attest to the truth of the foregoing and for whatever legal purpose it may serve.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            IN WITNESS WHEREOF, I have hereunto set my hand this <strong>{$data['dateOfNotary']}</strong> at Cabuyao City, Laguna.
        </div>

        <div style='text-align: center; margin-top: 50px;'>
            <div style='margin-bottom: 50px;'>
                <strong>{$data['fullName']}</strong><br/>
                Affiant
            </div>
            
            <div style='margin-top: 30px;'>
                <div style='border-bottom: 1px solid black; width: 200px; margin: 0 auto;'></div>
                <div style='margin-top: 5px;'>
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / {$today->format('Y')}<br/>
                    IBP No. 123456 / {$today->format('Y')}<br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
            </div>
        </div>
    ";
}

function generateSoloParentHTML($data) {
    $today = new DateTime();
    $reason = $data['reasonSection'] ?? '';
    if ($reason === 'Other reason, please state') {
        $reason = $data['otherReason'] ?? '';
    }
    
    $employment = $data['employmentStatus'] ?? '';
    $income = '';
    if ($employment === 'Employee and earning') {
        $income = $data['employeeAmount'] ?? '';
    } elseif ($employment === 'Self-employed and earning') {
        $income = $data['selfEmployedAmount'] ?? '';
    } elseif ($employment === 'Un-employed and dependent upon') {
        $income = $data['unemployedDependent'] ?? '';
    }
    
    return "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='font-size: 11pt; margin-bottom: 20px;'>
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style='font-size: 14pt; font-weight: bold; margin-bottom: 30px;'>
                SWORN AFFIDAVIT OF SOLO PARENT
            </div>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            I, <strong>{$data['fullName']}</strong>, of legal age, Filipino, and residing at <strong>{$data['completeAddress']}</strong>, after having been duly sworn to in accordance with law, hereby depose and say:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am a solo parent of the following child/children: <strong>{$data['childrenNames']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I have been a solo parent for <strong>{$data['yearsUnderCase']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That the reason for being a solo parent is: <strong>{$reason}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That my employment status is: <strong>{$employment}</strong>";
    
    if ($income) {
        $html .= "<br/>That my monthly income/dependency is: <strong>{$income}</strong>";
    }
    
    $html .= ";
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am executing this affidavit to attest to the truth of the foregoing and for whatever legal purpose it may serve.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            IN WITNESS WHEREOF, I have hereunto set my hand this <strong>{$data['dateOfNotary']}</strong> at Cabuyao City, Laguna.
        </div>

        <div style='text-align: center; margin-top: 50px;'>
            <div style='margin-bottom: 50px;'>
                <strong>{$data['fullName']}</strong><br/>
                Affiant
            </div>
            
            <div style='margin-top: 30px;'>
                <div style='border-bottom: 1px solid black; width: 200px; margin: 0 auto;'></div>
                <div style='margin-top: 5px;'>
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / {$today->format('Y')}<br/>
                    IBP No. 123456 / {$today->format('Y')}<br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
            </div>
        </div>
    ";
    
    return $html;
}

function generatePWDLossHTML($data) {
    $today = new DateTime();
    return "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='font-size: 11pt; margin-bottom: 20px;'>
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style='font-size: 14pt; font-weight: bold; margin-bottom: 30px;'>
                AFFIDAVIT OF LOSS (PWD ID)
            </div>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            I, <strong>{$data['fullName']}</strong>, of legal age, Filipino, and residing at <strong>{$data['fullAddress']}</strong>, after having been duly sworn to in accordance with law, hereby depose and say:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am the lawful owner of a Person with Disability (PWD) ID card;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That the said PWD ID card was lost under the following circumstances: <strong>{$data['detailsOfLoss']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I have exerted all efforts to locate the same but to no avail;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am executing this affidavit to attest to the truth of the foregoing and for whatever legal purpose it may serve.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            IN WITNESS WHEREOF, I have hereunto set my hand this <strong>{$data['dateOfNotary']}</strong> at Cabuyao City, Laguna.
        </div>

        <div style='text-align: center; margin-top: 50px;'>
            <div style='margin-bottom: 50px;'>
                <strong>{$data['fullName']}</strong><br/>
                Affiant
            </div>
            
            <div style='margin-top: 30px;'>
                <div style='border-bottom: 1px solid black; width: 200px; margin: 0 auto;'></div>
                <div style='margin-top: 5px;'>
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / {$today->format('Y')}<br/>
                    IBP No. 123456 / {$today->format('Y')}<br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
            </div>
        </div>
    ";
}

function generateBoticabLossHTML($data) {
    $today = new DateTime();
    return "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='font-size: 11pt; margin-bottom: 20px;'>
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style='font-size: 14pt; font-weight: bold; margin-bottom: 30px;'>
                AFFIDAVIT OF LOSS (BOTICAB BOOKLET/ID)
            </div>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            I, <strong>{$data['fullName']}</strong>, of legal age, Filipino, and residing at <strong>{$data['fullAddress']}</strong>, after having been duly sworn to in accordance with law, hereby depose and say:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am the lawful owner of a Boticab booklet/ID card;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That the said Boticab booklet/ID card was lost under the following circumstances: <strong>{$data['detailsOfLoss']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I have exerted all efforts to locate the same but to no avail;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am executing this affidavit to attest to the truth of the foregoing and for whatever legal purpose it may serve.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            IN WITNESS WHEREOF, I have hereunto set my hand this <strong>{$data['dateOfNotary']}</strong> at Cabuyao City, Laguna.
        </div>

        <div style='text-align: center; margin-top: 50px;'>
            <div style='margin-bottom: 50px;'>
                <strong>{$data['fullName']}</strong><br/>
                Affiant
            </div>
            
            <div style='margin-top: 30px;'>
                <div style='border-bottom: 1px solid black; width: 200px; margin: 0 auto;'></div>
                <div style='margin-top: 5px;'>
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / {$today->format('Y')}<br/>
                    IBP No. 123456 / {$today->format('Y')}<br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
            </div>
        </div>
    ";
}

function generateSwornAffidavitMotherHTML($data) {
    $today = new DateTime();
    $birthDate = isset($data['birthDate']) ? date('F j, Y', strtotime($data['birthDate'])) : '';
    
    return "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='font-size: 11pt; margin-bottom: 20px;'>
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style='font-size: 14pt; font-weight: bold; margin-bottom: 30px;'>
                SWORN AFFIDAVIT OF MOTHER
            </div>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            I, <strong>{$data['fullName']}</strong>, of legal age, Filipino, and residing at <strong>{$data['completeAddress']}</strong>, after having been duly sworn to in accordance with law, hereby depose and say:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am the mother of <strong>{$data['childName']}</strong>, who was born on <strong>{$birthDate}</strong> at <strong>{$data['birthPlace']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I have personal knowledge of the birth of the said child and I am competent to testify on the matters stated herein;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am executing this affidavit to attest to the truth of the foregoing and for whatever legal purpose it may serve.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            IN WITNESS WHEREOF, I have hereunto set my hand this <strong>{$data['dateOfNotary']}</strong> at Cabuyao City, Laguna.
        </div>

        <div style='text-align: center; margin-top: 50px;'>
            <div style='margin-bottom: 50px;'>
                <strong>{$data['fullName']}</strong><br/>
                Affiant
            </div>
            
            <div style='margin-top: 30px;'>
                <div style='border-bottom: 1px solid black; width: 200px; margin: 0 auto;'></div>
                <div style='margin-top: 5px;'>
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / {$today->format('Y')}<br/>
                    IBP No. 123456 / {$today->format('Y')}<br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
            </div>
        </div>
    ";
}

function generateSeniorIDLossHTML($data) {
    $today = new DateTime();
    return "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='font-size: 11pt; margin-bottom: 20px;'>
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style='font-size: 14pt; font-weight: bold; margin-bottom: 30px;'>
                AFFIDAVIT OF LOSS (SENIOR ID)
            </div>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            I, <strong>{$data['fullName']}</strong>, of legal age, Filipino, and residing at <strong>{$data['completeAddress']}</strong>, after having been duly sworn to in accordance with law, hereby depose and say:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am the <strong>{$data['relationship']}</strong> of <strong>{$data['seniorCitizenName']}</strong>, who is the lawful owner of a Senior Citizen ID issued by OSCA-Cabuyao;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That unfortunately, the said Senior ID was lost under the following circumstances: <strong>{$data['detailsOfLoss']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That despite diligent efforts to retrieve the said Senior ID, the same can no longer be restored and therefore considered lost;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That I am executing this affidavit to attest to the truth of the foregoing and for whatever legal purpose it may serve.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            IN WITNESS WHEREOF, I have hereunto set my hand this <strong>{$data['dateOfNotary']}</strong> at Cabuyao City, Laguna.
        </div>

        <div style='text-align: center; margin-top: 50px;'>
            <div style='margin-bottom: 50px;'>
                <strong>{$data['fullName']}</strong><br/>
                Affiant
            </div>
            
            <div style='margin-top: 30px;'>
                <div style='border-bottom: 1px solid black; width: 200px; margin: 0 auto;'></div>
                <div style='margin-top: 5px;'>
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / {$today->format('Y')}<br/>
                    IBP No. 123456 / {$today->format('Y')}<br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
            </div>
        </div>
    ";
}

function generateJointAffidavitHTML($data) {
    $today = new DateTime();
    $birthDate = isset($data['dateOfBirth']) ? date('F j, Y', strtotime($data['dateOfBirth'])) : '';
    
    return "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='font-size: 11pt; margin-bottom: 20px;'>
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style='font-size: 14pt; font-weight: bold; margin-bottom: 30px;'>
                JOINT AFFIDAVIT (TWO DISINTERESTED PERSONS)
            </div>
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            WE, <strong>{$data['firstPersonName']}</strong> and <strong>{$data['secondPersonName']}</strong>, Filipinos, both of legal age, and permanent residents of <strong>{$data['firstPersonAddress']}</strong> and <strong>{$data['secondPersonAddress']}</strong> both in the City of Cabuyao, Laguna after being duly sworn in accordance with law hereby depose and say:
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That we are not related by affinity or consanguinity to the child: <strong>{$data['childName']}</strong>, who was born on <strong>{$birthDate}</strong> in <strong>{$data['placeOfBirth']}</strong>; Cabuyao, Laguna, Philippines, to his/her parents: <strong>{$data['fatherName']}</strong> and <strong>{$data['motherName']}</strong>;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That we are well acquainted with their family, being neighbors and friends that we know the circumstances surrounding his/her birth;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That however, such facts of birth were not registered as evidenced by a certification issued by the Philippine Statistics Authority;
        </div>

        <div style='text-align: justify; margin-bottom: 20px;'>
            That we execute this affidavit to attest to the truth of the foregoing facts based on our personal knowledge and experience, and let this instrument be used as a requirement for Late Registration of the said <strong>{$data['childNameNumber4']}</strong>.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            AFFIANTS FURTHER SAYETH NAUGHT.
        </div>

        <div style='text-align: justify; margin-bottom: 30px;'>
            Cabuyao City, Laguna, <strong>{$data['dateOfNotary']}</strong>.
        </div>

        <div style='text-align: center; margin-top: 50px;'>
            <table style='width: 100%; margin-bottom: 50px;'>
                <tr>
                    <td style='width: 50%; text-align: center;'>
                        <strong>{$data['firstPersonName']}</strong><br/>
                        Affiant<br/>
                        ID Presented: _________________
                    </td>
                    <td style='width: 50%; text-align: center;'>
                        <strong>{$data['secondPersonName']}</strong><br/>
                        Affiant<br/>
                        ID Presented: _________________
                    </td>
                </tr>
            </table>
            
            <div style='margin-top: 30px;'>
                <div style='border-bottom: 1px solid black; width: 200px; margin: 0 auto;'></div>
                <div style='margin-top: 5px;'>
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / {$today->format('Y')}<br/>
                    IBP No. 123456 / {$today->format('Y')}<br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
            </div>
        </div>
    ";
}
?>
