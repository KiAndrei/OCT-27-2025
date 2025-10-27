<?php
// Start output buffering to prevent unwanted output
ob_start();

require_once 'session_manager.php';
validateUserAccess('employee');
require_once 'config.php';

// Fetch pending requests count for notification badge
$stmt = $conn->prepare("SELECT COUNT(*) FROM client_request_form WHERE status = 'Pending'");
$stmt->execute();
$pending_requests_count = $stmt->get_result()->fetch_row()[0];
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
require_once __DIR__ . '/vendor/autoload.php';

$employee_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    
    // Validate session and connection
    if (!$employee_id || !$conn) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid session or database connection']);
        exit;
    }
    
    // Get employee name for logging
    $stmt = $conn->prepare("SELECT name FROM user_form WHERE id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $employee_name = 'Employee';
    if ($res && $row = $res->fetch_assoc()) {
        $employee_name = $row['name'];
    }
    
    switch ($_POST['action']) {
        case 'get_document_data':
            $file_id = intval($_POST['file_id']);
            $stmt = $conn->prepare("SELECT document_data FROM client_document_generation WHERE id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $data = json_decode($row['document_data'], true);
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
            }
            exit;
            
        case 'generate_pdf':
            $file_id = intval($_POST['file_id']);
            $document_type = $_POST['document_type'];
            
            // Get document data
            $stmt = $conn->prepare("SELECT document_data, request_id FROM client_document_generation WHERE id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $form_data = json_decode($row['document_data'], true);
                $request_id = $row['request_id'];
                
                // Generate PDF using the existing document generation logic
                try {
                    // Define TCPDF constants if not already defined
                    if (!defined('PDF_PAGE_ORIENTATION')) {
                        define('PDF_PAGE_ORIENTATION', 'P');
                    }
                    if (!defined('PDF_UNIT')) {
                        define('PDF_UNIT', 'mm');
                    }
                    if (!defined('PDF_PAGE_FORMAT')) {
                        define('PDF_PAGE_FORMAT', 'A4');
                    }
                    
                    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                    
                    // Set document information
                    $pdf->SetCreator('Opiña Law Office');
                    $pdf->SetAuthor('Opiña Law Office');
                    $pdf->SetTitle($document_type);
                    $pdf->SetSubject($document_type);
                    
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
                    $html = generateDocumentHTML($document_type, $form_data);
                    
                    // Debug: Log the HTML being generated
                    error_log("PDF Generation Debug - HTML: " . $html);
                    
                    $pdf->writeHTML($html, true, false, true, false, '');
                    
                    // Generate filename and path
                    $pdf_filename = $document_type . '_' . $request_id . '.pdf';
                    $pdf_path = 'uploads/documents/' . $pdf_filename;
                    
                    // Create uploads directory if it doesn't exist
                    if (!file_exists('uploads/documents/')) {
                        mkdir('uploads/documents/', 0755, true);
                    }
                    
                    // Save PDF to file
                    $pdf->Output($pdf_path, 'F');
                    
                    // Update database with PDF path
                    $update_stmt = $conn->prepare("UPDATE client_document_generation SET pdf_file_path = ?, pdf_filename = ? WHERE id = ?");
                    $update_stmt->bind_param("ssi", $pdf_path, $pdf_filename, $file_id);
                    $update_stmt->execute();
                    
                    echo json_encode([
                        'status' => 'success', 
                        'pdf_path' => $pdf_path, 
                        'pdf_filename' => $pdf_filename,
                        'message' => 'PDF generated successfully',
                        'debug_info' => [
                            'file_id' => $file_id,
                            'document_type' => $document_type,
                            'request_id' => $request_id,
                            'pdf_path' => $pdf_path
                        ]
                    ]);
                } catch (Exception $e) {
                    error_log("PDF Generation Error: " . $e->getMessage());
                    echo json_encode([
                        'status' => 'error', 
                        'message' => 'Failed to generate PDF: ' . $e->getMessage(),
                        'debug_info' => [
                            'file_id' => $file_id,
                            'document_type' => $document_type,
                            'error_line' => $e->getLine(),
                            'error_file' => $e->getFile()
                        ]
                    ]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
            }
            exit;
            
        case 'approve_document':
            $file_id = intval($_POST['file_id']);
            
            // Get document and client information before updating
            $stmt = $conn->prepare("SELECT crf.*, u.name as client_name, u.email as client_email FROM client_document_generation crf JOIN user_form u ON crf.client_id = u.id WHERE crf.id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $document_info = $result->fetch_assoc();
            
            if (!$document_info) {
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
                exit;
            }
            
            // Update document status
            $stmt = $conn->prepare("UPDATE client_document_generation SET status = 'Approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $employee_id, $file_id);
            
            if ($stmt->execute()) {
                // Send notification to client
                sendDocumentStatusNotification($conn, $document_info, 'approved');
                
                // Log to audit trail
                try {
                    $auditLogger = new AuditLogger($conn);
                    $auditLogger->logAction(
                        $employee_id,
                        $employee_name,
                        'employee',
                        'Document Approval',
                        'Document Management',
                        "Approved document with request ID: {$document_info['request_id']} (Type: {$document_info['document_type']}) for client: {$document_info['client_name']}",
                        'success',
                        'medium'
                    );
                } catch (Exception $e) {
                    error_log("Audit logging failed: " . $e->getMessage());
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Document approved successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to approve document']);
            }
            exit;
            
        case 'reject_document':
            $file_id = intval($_POST['file_id']);
            $reason = $_POST['reason'] ?? '';
            
            // Get document and client information before updating
            $stmt = $conn->prepare("SELECT crf.*, u.name as client_name, u.email as client_email FROM client_document_generation crf JOIN user_form u ON crf.client_id = u.id WHERE crf.id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $document_info = $result->fetch_assoc();
            
            if (!$document_info) {
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
                exit;
            }
            
            // Update document status with rejection reason
            $stmt = $conn->prepare("UPDATE client_document_generation SET status = 'Rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            $stmt->bind_param("sii", $reason, $employee_id, $file_id);
            
            if ($stmt->execute()) {
                // Send notification to client
                sendDocumentStatusNotification($conn, $document_info, 'rejected', $reason);
                
                // Log to audit trail
                try {
                    $auditLogger = new AuditLogger($conn);
                    $auditLogger->logAction(
                        $employee_id,
                        $employee_name,
                        'employee',
                        'Document Rejection',
                        'Document Management',
                        "Rejected document with request ID: {$document_info['request_id']} (Type: {$document_info['document_type']}) for client: {$document_info['client_name']}. Reason: $reason",
                        'warning',
                        'medium'
                    );
                } catch (Exception $e) {
                    error_log("Audit logging failed: " . $e->getMessage());
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Document rejected successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to reject document']);
            }
            exit;
            
        case 'download_pdf':
            $file_id = intval($_POST['file_id']);
            $stmt = $conn->prepare("SELECT pdf_file_path, pdf_filename FROM client_document_generation WHERE id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $pdf_path = $row['pdf_file_path'];
                $pdf_filename = $row['pdf_filename'];
                
                if (file_exists($pdf_path)) {
                    // Set headers for download
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $pdf_filename . '"');
                    header('Content-Length: ' . filesize($pdf_path));
                    readfile($pdf_path);
                    exit;
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'PDF file not found']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
            }
            exit;
            
        case 'debug_data':
            $file_id = intval($_POST['file_id']);
            
            // Get document data
            $stmt = $conn->prepare("SELECT document_data, request_id, document_type FROM client_document_generation WHERE id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $form_data = json_decode($row['document_data'], true);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'file_id' => $file_id,
                        'request_id' => $row['request_id'],
                        'document_type' => $row['document_type'],
                        'form_data' => $form_data,
                        'raw_data' => $row['document_data']
                    ]
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
            }
            exit;
            
        case 'generate_pdf_download':
            $file_id = intval($_POST['file_id']);
            $document_type = $_POST['document_type'];
            
            // Get document data
            $stmt = $conn->prepare("SELECT document_data, request_id FROM client_document_generation WHERE id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $form_data = json_decode($row['document_data'], true);
                $request_id = $row['request_id'];
                
                // Generate filename
                $pdf_filename = $document_type . '_' . $request_id . '.pdf';
                
                try {
                    // Use the exact same TCPDF setup as the working generate_affidavit_of_loss.php
                    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                    
                    // Set document information
                    $pdf->SetCreator('Opiña Law Office');
                    $pdf->SetAuthor('Opiña Law Office');
                    $pdf->SetTitle($document_type);
                    $pdf->SetSubject($document_type);
                    
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
                    $html = generateDocumentHTML($document_type, $form_data);
                    
                    $pdf->writeHTML($html, true, false, true, false, '');
                    
                    // Output PDF directly for download (same as working file)
                    $pdf->Output($pdf_filename, 'D');
                    exit;
                    
                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'error', 
                        'message' => 'Failed to generate PDF: ' . $e->getMessage()
                    ]);
                    exit;
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
                exit;
            }
            
        case 'delete_document':
            try {
                $file_id = intval($_POST['file_id']);
                
                // Get document info for logging
                $stmt = $conn->prepare("SELECT request_id, document_type, client_id FROM client_document_generation WHERE id = ?");
                $stmt->bind_param("i", $file_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $request_id = $row['request_id'];
                    $document_type = $row['document_type'];
                    $client_id = $row['client_id'];
                    
                    // Delete the document
                    $delete_stmt = $conn->prepare("DELETE FROM client_document_generation WHERE id = ?");
                    $delete_stmt->bind_param("i", $file_id);
                    
                    if ($delete_stmt->execute()) {
                        // Log to audit trail (optional - don't fail if this fails)
                        try {
                            $auditLogger = new AuditLogger($conn);
                            $auditLogger->logAction(
                                $employee_id,
                                $employee_name,
                                'employee',
                                'Document Deletion',
                                'Document Management',
                                "Deleted document with request ID: $request_id (Type: $document_type)",
                                'success',
                                'high'
                            );
                        } catch (Exception $e) {
                            // Log audit error but don't fail the deletion
                            error_log("Audit logging failed: " . $e->getMessage());
                        }
                        
                        echo json_encode(['status' => 'success', 'message' => 'Document deleted successfully']);
                    } else {
                        echo json_encode([
                            'status' => 'error', 
                            'message' => 'Failed to delete document: ' . $delete_stmt->error,
                            'debug_info' => [
                                'file_id' => $file_id,
                                'sql_error' => $delete_stmt->error
                            ]
                        ]);
                    }
                } else {
                    echo json_encode([
                        'status' => 'error', 
                        'message' => 'Document not found',
                        'debug_info' => [
                            'file_id' => $file_id
                        ]
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'An error occurred while deleting the document: ' . $e->getMessage(),
                    'debug_info' => [
                        'file_id' => $file_id ?? 'unknown',
                        'error' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]
                ]);
            }
            exit;
    }
}

// Function to send document status notifications to clients
function sendDocumentStatusNotification($conn, $document_info, $status, $reason = '') {
    try {
        $client_id = $document_info['client_id'];
        $document_type = $document_info['document_type'];
        $request_id = $document_info['request_id'];
        
        // Format document type for display
        $document_type_display = ucwords(str_replace(['affidavitLoss', 'soloParent', 'pwdLoss', 'boticabLoss', 'seniorIDLoss', 'jointAffidavit', 'jointAffidavitSoloParent', 'swornAffidavitMother'], 
            ['Affidavit of Loss', 'Solo Parent', 'PWD ID Loss', 'Boticab Loss', 'Senior ID Loss', 'Two Disinterested Persons', 'Joint Affidavit (Solo Parent)', 'Sworn Affidavit of Mother'], 
            $document_type));
        
        if ($status === 'approved') {
            $title = 'Document Approved';
            $message = "Your {$document_type_display} document (Request ID: {$request_id}) has been approved by our legal team. You can now proceed with the next steps.";
            $notification_type = 'success';
        } else {
            $title = 'Document Rejected';
            $message = "Your {$document_type_display} document (Request ID: {$request_id}) has been rejected. ";
            if (!empty($reason)) {
                $message .= "Reason: " . htmlspecialchars($reason) . ". ";
            }
            $message .= "Please review the requirements and submit a new request if needed.";
            $notification_type = 'error';
        }
        
        // Insert notification into database
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'client', ?, ?, ?)");
        $stmt->bind_param('isss', $client_id, $title, $message, $notification_type);
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to send document status notification: " . $e->getMessage());
        return false;
    }
}

// Function to generate document HTML (simplified version)
function generateDocumentHTML($document_type, $form_data) {
    switch ($document_type) {
        case 'affidavitLoss':
            return generateAffidavitLossHTML($form_data);
        case 'soloParent':
            return generateSoloParentHTML($form_data);
        case 'pwdLoss':
            return generatePWDLossHTML($form_data);
        case 'boticabLoss':
            return generateBoticabLossHTML($form_data);
        case 'jointAffidavit':
            return generateJointAffidavitHTML($form_data);
        case 'swornAffidavitMother':
            return generateSwornAffidavitMotherHTML($form_data);
        case 'seniorIDLoss':
            return generateSeniorIDLossHTML($form_data);
        case 'jointAffidavitSoloParent':
            return generateJointAffidavitSoloParentHTML($form_data);
        default:
            return '<p>Document type not supported</p>';
    }
}

function generateAffidavitLossHTML($data) {
    $fullName = $data['fullName'] ?? '';
    $completeAddress = $data['completeAddress'] ?? '';
    $specifyItemLost = $data['specifyItemLost'] ?? '';
    $itemLost = $data['itemLost'] ?? '';
    $itemDetails = $data['itemDetails'] ?? '';
    $dateOfNotary = $data['dateOfNotary'] ?? '';
    
    // Use the exact same HTML format as the working generate_affidavit_of_loss.php
    return <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
        PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
    </div>
    
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
        AFFIDAVIT OF LOSS
    </div>
    <br/>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>{$fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; post office address at <u><b>{$completeAddress}</b></u>, after <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; being duly sworn in accordance with law hereby depose and say that:
    </div>
    <br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That &nbsp;&nbsp;&nbsp; I &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; am &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; true &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and lawful owner/possessor of <br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>{$specifyItemLost}</b></u>;<br>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That unfortunately the said <u><b>{$itemLost}</b></u> was lost under the following<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; circumstance:<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>{$itemDetails}</b></u><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;&nbsp;Despite diligent effort to search for the missing item, the same can no longer <br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;be found;
    </div>
    <br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;&nbsp;I am executing this affidavit to attest the truth of the foregoing facts and<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;for whatever intents it may serve in accordance with law.
    <br/>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, &nbsp; I &nbsp; have &nbsp; hereunto &nbsp; set my hand this ________ day of<br/>
        <u><b>{$dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
    </div>
    
    <br/>
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <br/>
    <div style="text-align:justify; margin-bottom:15px;">
SUBSCRIBED AND SWORN TO before me this date above mentioned at the City of <br>
Cabuyao, Laguna, affiant exhibiting to me his/her respective proofs of identity, <br>
indicated below their names personally attesting that the foregoing statements is true <br>
to their best of knowledge and belief.
    </div>
    
    <br/>
    <div style="text-align:left; margin-left: -5px;">
Doc. No._______<br/>
        Page No._______<br/>
        Book No._______<br/>
        Series of _______
    </div>
</div>
EOD;
}

function generateSoloParentHTML($data) {
    $fullName = isset($data['fullName']) ? htmlspecialchars($data['fullName']) : '';
    $completeAddress = isset($data['completeAddress']) ? htmlspecialchars($data['completeAddress']) : '';
    $childrenNames = isset($data['childrenNames']) ? $data['childrenNames'] : [];
    $childrenAges = isset($data['childrenAges']) ? $data['childrenAges'] : [];
    $yearsUnderCase = isset($data['yearsUnderCase']) ? htmlspecialchars($data['yearsUnderCase']) : '';
    $reasonSection = isset($data['reasonSection']) ? $data['reasonSection'] : '';
    $otherReason = isset($data['otherReason']) ? htmlspecialchars($data['otherReason']) : '';
    $employmentStatus = isset($data['employmentStatus']) ? $data['employmentStatus'] : '';
    $employeeAmount = isset($data['employeeAmount']) ? htmlspecialchars($data['employeeAmount']) : '';
    $selfEmployedAmount = isset($data['selfEmployedAmount']) ? htmlspecialchars($data['selfEmployedAmount']) : '';
    $unemployedDependent = isset($data['unemployedDependent']) ? htmlspecialchars($data['unemployedDependent']) : '';
    $dateOfNotary = isset($data['dateOfNotary']) ? htmlspecialchars($data['dateOfNotary']) : '';

    // Normalize children arrays
    if (!is_array($childrenNames)) { $childrenNames = $childrenNames ? [$childrenNames] : []; }
    if (!is_array($childrenAges)) { $childrenAges = $childrenAges ? [$childrenAges] : []; }

    // Build children rows
    $childrenRows = '';
    if (!empty($childrenNames)) {
        $count = max(count($childrenNames), count($childrenAges));
        for ($i = 0; $i < $count; $i++) {
            $n = htmlspecialchars(trim($childrenNames[$i] ?? ''));
            $a = htmlspecialchars(trim($childrenAges[$i] ?? ''));
            if ($n === '' && $a === '') { continue; }
            $childrenRows .= '<tr>'
                . '<td style="width:80%; padding:8px 5px; border:1px solid #000;">' . ($n !== '' ? htmlspecialchars($n) : '&nbsp;') . '</td>'
                . '<td style="width:20%; padding:8px 5px; border:1px solid #000; text-align:center;">' . ($a !== '' ? htmlspecialchars($a) : '&nbsp;') . '</td>'
                . '</tr>';
        }
    }
    if ($childrenRows === '') {
        for ($i = 0; $i < 5; $i++) {
            $childrenRows .= '<tr><td style="width:80%; padding:8px 5px; border:1px solid #000;">&nbsp;</td><td style="width:20%; padding:8px 5px; border:1px solid #000;">&nbsp;</td></tr>';
        }
    }

    // Reasons checkboxes (mark the selected one)
    $isLeft = $reasonSection === 'Left the family home and abandoned us';
    $isDied = $reasonSection === 'Died last';
    $isOther = $reasonSection === 'Other reason, please state';

    $reasonOtherText = $isOther ? $otherReason : '';

    // Employment details
    $isEmp = $employmentStatus === 'Employee and earning';
    $isSelf = $employmentStatus === 'Self-employed and earning';
    $isUnemp = $employmentStatus === 'Un-employed and dependent upon';

    $empAmt = $isEmp ? ($employeeAmount ?: '__________') : '__________';
    $selfAmt = $isSelf ? ($selfEmployedAmount ?: '__________') : '__________';
    $unempDep = $isUnemp ? ($unemployedDependent ?: '__________') : '__________';

    // Helper to draw checkbox with optional X (always show boxed outline)
    $box = function($checked) {
        return '<span style="display:inline-block; width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px; vertical-align:middle;">' . ($checked ? 'X' : '&nbsp;') . '</span>';
    };

    // Build HTML mirroring the detailed Solo Parent TCPDF layout
    $html = '<div style="font-size:11pt; line-height:1.2;">'
        . '<br/>'
        . '<div style="margin-top:10px;">'
        . 'REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;'
        . 'PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;'
        . 'CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)'
        . '</div>'
        . '<div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">SWORN AFFIDAVIT OF SOLO PARENT</div>'
        . '<br/>'
        . '<div style="text-align:justify; margin-bottom:15px;">'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;That &nbsp;&nbsp; I, &nbsp; <span style="display:inline-block; border-bottom:1px solid #000; min-width: 300px;">&nbsp;<u><b>' . $fullName . '</b></u>&nbsp;</span>, &nbsp; Filipino &nbsp; Citizen, &nbsp; of &nbsp; legal &nbsp; age, single/ married /<br/>'
        . 'widow, with residence and postal address at<span style="display:inline-block; border-bottom:1px solid #000; min-width: 300px;">&nbsp;<u><b>' . $completeAddress . '</b></u>&nbsp;</span><br/>'
        . 'City ppf Cabuyao, Laguna after having been duly sworn in accordance with law hereby depose<br/>'
        . 'and state that;'
        . '</div>'
        . '<div style="margin-left: 40px;">'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am a single parent and the Mother/Father of the following child/children namely:'
        . '</div>'
        . '<div style="margin-left: 60px; margin-right: 60px;">'
        . '<table style="width:100%; border-collapse:collapse; margin-bottom:2px;"><tr><td style="width:80%; text-align:center; border:none;"><b>Name</b></td><td style="width:20%; text-align:center; border:none;"><b>Age</b></td></tr></table>'
        . '<table style="width:100%; border-collapse: collapse;">' . $childrenRows . '</table>'
        . '<br/>'
        . '<div style="margin-left: -20px;">'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That I am solely taking care and providing for my said child\'s / children\'s needs and <br>'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;everything indispensable for his/her/their wellbeing for <span style="display:inline-block; border-bottom:1px solid #000; min-width: 140px;">&nbsp;<u><b>' . $yearsUnderCase . '</b></u>&nbsp;</span> year/s now<br>'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;since his/her / their biological Mother/Father'
        . '</div>'
        . '</div>'
        . '<div style="margin-left: 60px;">'
        . '<table style="border-collapse:collapse;">'
        . '<tr><td style="width:16px; vertical-align:top; padding-top:2px;">' . $box($isLeft) . '</td><td>left the family home and abandoned us;</td></tr>'
        . '<tr><td style="width:16px; vertical-align:top; padding-top:2px;">' . $box($isDied) . '</td><td>died last <span style="display:inline-block; border-bottom:1px solid #000; width: 180px;">&nbsp;</span>;</td></tr>'
        . '<tr><td style="width:16px; vertical-align:top; padding-top:2px;">' . $box($isOther) . '</td><td>(other reason please state) <span style="display:inline-block; border-bottom:1px solid #000; width: 220px;">&nbsp;' . ($isOther && $reasonOtherText !== '' ? '<u><b>' . $reasonOtherText . '</b></u>' : '') . '&nbsp;</span>;</td></tr>'
        . '</table>'
        . '</div>'
        . '<div style="margin-left: 40px;">'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. I am attesting to the fact that I am not cohabiting with anybody to date;'
        . '</div>'
        . '<div style="margin-left: 40px;">'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am currently:<br>'
        . '</div>'
        . '<br>'
        . '<div style="margin-left: 60px;">'
        . '<table style="border-collapse:collapse;">'
        . '<tr><td style="width:16px; vertical-align:top; padding-top:2px;">' . $box($isEmp) . '</td><td>Employed and earning Php <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">&nbsp;' . ($isEmp ? '<u><b>' . $empAmt . '</b></u>' : '__________') . '&nbsp;</span> per month;</td></tr>'
        . '<tr><td style="width:16px; vertical-align:top; padding-top:2px;">' . $box($isSelf) . '</td><td><div>Self-employed and earning Php <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">&nbsp;' . ($isSelf ? '<u><b>' . $selfAmt . '</b></u>' : '__________') . '&nbsp;</span> per month, from</div><div>my job as <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">&nbsp;</span>;</div></td></tr>'
        . '<tr><td style="width:16px; vertical-align:top; padding-top:2px;">' . $box($isUnemp) . '</td><td>Un-employed and dependent upon <span style="display:inline-block; border-bottom:1px solid #000; width: 200px;">&nbsp;' . ($isUnemp ? '<u><b>' . $unempDep . '</b></u>' : '__________') . '&nbsp;</span>;</td></tr>'
        . '</table>'
        . '</div>'
        . '<div style="text-align:justify; margin-bottom:15px; margin-top:14px;">'
        . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto affixed my signature this<br/>'
        . '<span style="display:inline-block; border-bottom:1px solid #000; min-width: 220px;">&nbsp;<u><b>' . $dateOfNotary . '</b></u>&nbsp;</span> at the City of Cabuyao, Laguna.'
        . '</div>'
        . '<div style="text-align:center; margin:15px 0;">____________________________<br/><b>AFFIANT</b></div>'
        . '<div style="text-align:justify; margin-bottom:15px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBSCRIBED AND SWORN to before me this _____________________ at the City of Cabuyao, Laguna, affiant personally appeared and exhibiting to me his/her _____________________ with ID No. _____________________ as competent proof of identity.</div>'
        . '<div style="text-align:left; margin-left: -5px;">Doc. No. _______<br/>Page No. _______<br/>Book No. _______<br/>Series of 2025</div>'
        . '</div>';

    return $html;
}

function generatePWDLossHTML($data) {
    $fullName = $data['fullName'] ?? '';
    $fullAddress = $data['fullAddress'] ?? '';
    $detailsOfLoss = $data['detailsOfLoss'] ?? '';
    $dateOfNotary = $data['dateOfNotary'] ?? '';
    
    // Use the exact same HTML format as the working generate_affidavit_of_loss_pwd_id.php
    return <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
        PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
    </div>
    
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
        AFFIDAVIT OF LOSS<br>
        (PWD)
    </div>
    <br/>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>{$fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; currently residing at <u><b>{$fullAddress}</b></u>, after <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; having been duly sworn to in accordance with law do hereby depose and state:
    </div>
    <br>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That I am the owner/possessor of a Person with Disability (PWD) <br> 
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Identification card;<br>
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That unfortunately, the said PWD id was lost under the following <br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;circumstances:<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>{$detailsOfLoss}</b></u><br>
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;That despite diligent effort to retrive the said PWD id, the same can no longer <br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;be restored and therefore considered lost;
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;That I am executing this statement to attest to all above facts and for whatever <br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal purpose it may serve in accordance with law;
    </div>
    <br/>
    
AFFIANT FURTHER SAYETH NAUGHT. <br/>
    
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this <u><b>{$dateOfNotary}</b></u>, in <br/>
        the City of Cabuyao, Laguna.
    </div>
    
    <br/>
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <br/>
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; SUBSCRIBED AND SWORN, To before me this______________ at the city of <br>
        Cabuyao, Laguna, affiant exhibiting to me his/her____________________________ as <br>
        respective proofs of identity, <br>
    </div>
    
    <br/>
    <div style="text-align:left;">
Doc. No._______<br/>
        Page No._______<br/>
        Book No._______<br/>
        Series of _______
    </div>
</div>
EOD;
}

function generateBoticabLossHTML($data) {
    $fullName = $data['fullName'] ?? '';
    $fullAddress = $data['fullAddress'] ?? '';
    $detailsOfLoss = $data['detailsOfLoss'] ?? '';
    $dateOfNotary = $data['dateOfNotary'] ?? '';
    
    // Use the exact same HTML format as the working generate_affidavit_of_loss_boticab.php
    return <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
        PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
    </div>
    
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
        AFFIDAVIT OF LOSS<br>
        (BOTICAB BOOKLET/ID)
    </div>
    <br/>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>{$fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; post office address at <u><b>{$fullAddress}</b></u>, after <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; being duly sworn in accordance with law hereby depose and say that:
    </div>
    <br>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That I am the lawful owner of a Boticab Booklet/ID;
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That the said Boticab Booklet/ID was lost under the following<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; circumstances:<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>{$detailsOfLoss}</b></u><br>
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;That despite diligent efforts to retrieve the said Boticab Booklet/ID, the same can no<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; longer be restored and therefore considered lost;
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;That I am executing this statement to attest to all above facts and for whatever<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal purpose it may serve in accordance with law;
    </div>
    <br/>
    
AFFIANT FURTHER SAYETH NAUGHT.
    <br/>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this <u><b>{$dateOfNotary}</b></u>, in<br/>
        the City of Cabuyao, Laguna.
    </div>
    
    <br/>
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <br/>
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; SUBSCRIBED AND SWORN, To before me this______________ at the city of <br>
        Cabuyao, Laguna, affiant exhibiting to me his/her____________________________ as <br>
        respective proofs of identity, <br>
    </div>
    
    <br/>
    <div style="text-align:left; margin-left: -5px;">
Doc. No._______<br/>
        Page No._______<br/>
        Book No._______<br/>
        Series of _______
    </div>
</div>
EOD;
}

function generateJointAffidavitHTML($data) {
    $firstPersonName = $data['firstPersonName'] ?? '';
    $secondPersonName = $data['secondPersonName'] ?? '';
    $firstPersonAddress = $data['firstPersonAddress'] ?? '';
    $secondPersonAddress = $data['secondPersonAddress'] ?? '';
    $childName = $data['childName'] ?? '';
    $dateOfBirth = $data['dateOfBirth'] ?? '';
    $placeOfBirth = $data['placeOfBirth'] ?? '';
    $fatherName = $data['fatherName'] ?? '';
    $motherName = $data['motherName'] ?? '';
    $childNameNumber4 = $data['childNameNumber4'] ?? '';
    $dateOfNotary = $data['dateOfNotary'] ?? '';
    
    // Use the exact same HTML format as the working generate_two_disinterested.php
    return <<<EOD
<div style="text-align:left; font-size:11pt;">
REPUBLIC OF THE PHILIPPINES)<br/>
    PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) SS<br/>
    CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/><br/>
</div>

<div style="text-align:center; font-size:13pt; font-weight:bold;">
    <b>JOINT AFFIDAVIT<br/>(Two Disinterested Person)</b>
</div>
<br/>

<div style="text-align:left; font-size:11pt;">
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;WE, <u><b>{$firstPersonName}</b></u> and <u><b>{$secondPersonName}</b></u><br/>
    Filipinos, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; both &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; age &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; , &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; permanent &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; residents &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of <br/>
    <u><b>{$firstPersonAddress}</b></u> and <u><b>{$secondPersonAddress}</b></u> both in the<br/>
    City of Cabuyao, Laguna after being duly sworn in accordance with law hereby depose<br/>
    and say that;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. We &nbsp;&nbsp; are &nbsp; not &nbsp; related &nbsp;&nbsp; by &nbsp;&nbsp; affinity &nbsp;&nbsp; or &nbsp;&nbsp;&nbsp; consanguinity &nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp; the &nbsp;&nbsp; child &nbsp; :<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$childName}</b></u>, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; who was born on<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$dateOfBirth}</b></u> in &nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$placeOfBirth}</b></u>;<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Laguna, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Philippines, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; his/her &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; parents:<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$fatherName}</b></u> and &nbsp;<u><b>{$motherName}</b></u><br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. We &nbsp; are &nbsp; well &nbsp; acquainted &nbsp;&nbsp; with &nbsp;&nbsp;their family, being neighbors and friends that <br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; we know that circumstances surroundding his/her birth ;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. However, &nbsp;&nbsp; such &nbsp;&nbsp; facts &nbsp;&nbsp; of &nbsp;&nbsp; birth &nbsp;&nbsp;were &nbsp;&nbsp; not &nbsp;&nbsp; registered &nbsp;&nbsp;as &nbsp; evidenced by a<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; certification issued by the philippine Statistics Authority;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. We &nbsp;&nbsp;&nbsp; execute &nbsp;&nbsp; this &nbsp;&nbsp; affidavit &nbsp;&nbsp; to attest to the truth of the foregoing facts based<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;on &nbsp;&nbsp; our &nbsp;&nbsp; personal &nbsp;&nbsp; knowledge &nbsp; and experience, and let this instrument be use as<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; requirement &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; for &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Late &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Registration &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;said<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$childNameNumber4}</b></u> .<br/><br/>
    
AFFIANTS FURTHER SAYETH NAUGHT.<br><br/>

    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao City, Laguna, <u><b>{$dateOfNotary}</b></u> .<br/><br/>
    
    <table style="width:100%;">
        <tr>
            <td style="width:50%; text-align:center;"><u><b>{$firstPersonName}</b></u><br/>Affiant<br/>ID Presented: _________________</td>
            <td style="width:50%; text-align:center;"><u><b>{$secondPersonName}</b></u><br/>Affiant<br/>ID Presented: _________________</td>
        </tr>
    </table><br/>
    <br/>
    <br/>
    
WITNESS my hand and seal the date and place above-written.<br/><br/>
    
    
Doc. No. _____<br/>
    Page No. _____<br/>
    Book No. _____<br/>
    Series of _____<br/>
</div>
EOD;
}

function generateSwornAffidavitMotherHTML($data) {
    $fullName = $data['fullName'] ?? '';
    $completeAddress = $data['completeAddress'] ?? '';
    $childName = $data['childName'] ?? '';
    $birthDate = $data['birthDate'] ?? '';
    $birthPlace = $data['birthPlace'] ?? '';
    $dateOfNotary = $data['dateOfNotary'] ?? '';
    
    // Use the exact same HTML format as the working generate_sworn_statement_of_mother.php
    return <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES&nbsp;&nbsp;&nbsp;)<br/>&nbsp;
        PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;S.S
    </div>
    <br>
    
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
        SWORN STATEMENT OF MOTHER
    </div>
    <br>
    <br>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>{$fullName}</b></u>, Filipino, married/single, and with residence<br/>
        and postal address at <u><b>{$completeAddress}</b></u>, after<br>
        being duly sworn in accordance with law, hereby depose and say that;
    </div>
    <br>
    <br>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the biological mother of <u><b>{$childName}</b></u>, who was<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;born on <u><b>{$birthDate}</b></u> in <u><b>{$birthPlace}</b></u>;<br> 
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That the birth of the above-stated child was not registered with the Local Civil<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Registry of Cabuyao City, due to negligence on our part;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That I am now taking the appropriate action to register the birth of my said child.
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am executing this affidavit to attest to the truth of the foregoing facts and be<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;use for whatever legal purpose it may serve.
    </div>
    <br>
    
    <div style="margin-left: 60px;">
        IN WITNESS WHEREOF, I have hereunto set my hands this <u><b>{$dateOfNotary}</b></u>, in the<br>
        City of Cabuyao, Laguna.
        <br>
        <br>
    </div>
    
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <br>
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBCRIBED AND SWORN to before me this <u><b>{$dateOfNotary}</b></u> in the City of <br>
        Cabuyao, Province of Laguna, affiant personally appeared, exhibiting to me her 
        Valid ID/No. _______________________________ as respective proof of identification.
    </div>
    <br>
    
    <div style="text-align:left; margin-left: -5px;">
        Doc. No. _______<br/>
        Book No. _______<br/>
        Page No. _______<br/>
        Series of _______
    </div>
</div>
EOD;
}

function generateSeniorIDLossHTML($data) {
    $fullName = $data['fullName'] ?? '';
    $completeAddress = $data['completeAddress'] ?? '';
    $relationship = $data['relationship'] ?? '';
    $seniorCitizenName = $data['seniorCitizenName'] ?? '';
    $detailsOfLoss = $data['detailsOfLoss'] ?? '';
    $dateOfNotary = $data['dateOfNotary'] ?? '';
    
    // Use the exact same HTML format as the working generate_affidavit_of_loss_senior_id.php
    return <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
        PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;S.S<br>
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)
    </div>
    
    <br>
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
        AFFIDAVIT OF LOSS<br/>
        (SENIOR ID)
    </div>
    <br>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>{$fullName}</b></u>, Filipino, of legal age, and with<br/>
        residence and currently residing at <u><b>{$completeAddress}</b></u>, after having been sworn<br/>
        in accordance with law hereby depose and state:
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the <u><b>{$relationship}</b></u> of <u><b>{$seniorCitizenName}</b></u>, who is<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;the lawful owner of a Senior Citizen ID issued by OSCA-Cabuyao;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That unfortunately, the said Senior ID was lost under the following<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;circumstances:<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>{$detailsOfLoss}</b></u><br>
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That despite diligent efforts to retrieve the said Senior ID, the same can no<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;longer be restored and therefore considered lost;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am executing this affidavit to attest to the truth of the foregoing facts and<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;for whatever legal intents and purposes whatever legal intents and<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;purposes.
    </div>
    
    <div style="margin-left: 40px; margin-top:15px;">
AFFIANT FURTHER SAYETH NAUGHT.
    </div>
    
    <div style="margin-left: 40px; margin-top:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this<br/>
        <u><b>{$dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
        <br>
    
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBSCRIBED AND SWORN to before me, this<br/> 
_____________________________________, in the City of Cabuyao, Laguna, affiant exhibiting<br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;to me his/her ___________________________ as valid proof of identification.
    </div>
    <br>

    <div style="text-align:left; margin-left: -5px;">
Doc. No. _______<br/>
        Page No. _______<br/>
        Book No. _______<br/>
        Series of _______
    </div>
</div>
EOD;
}

function generateJointAffidavitSoloParentHTML($data) {
    $affiant1Name = $data['affiant1Name'] ?? '';
    $affiant2Name = $data['affiant2Name'] ?? '';
    $affiantsAddress = $data['affiantsAddress'] ?? '';
    $soloParentName = $data['soloParentName'] ?? '';
    $soloParentAddress = $data['soloParentAddress'] ?? '';
    $childrenNames = $data['childrenNames'] ?? [];
    $childrenAges = $data['childrenAges'] ?? [];
    $affiant1ValidId = $data['affiant1ValidId'] ?? '';
    $affiant2ValidId = $data['affiant2ValidId'] ?? '';
    $dateOfNotary = $data['dateOfNotary'] ?? '';
    
    // Build children table rows
    $childrenRows = '';
    if (!empty($childrenNames)) {
        $count = max(count($childrenNames), count($childrenAges));
        for ($i = 0; $i < $count; $i++) {
            $name = htmlspecialchars(trim($childrenNames[$i] ?? ''));
            $age = htmlspecialchars(trim($childrenAges[$i] ?? ''));
            if ($name === '' && $age === '') { continue; }
            $childrenRows .= '<tr><td style="border:1px solid black; padding:5px; height:20px;">' . ($name !== '' ? $name : '&nbsp;') . '</td><td style="border:1px solid black; padding:5px; height:20px;">' . ($age !== '' ? $age : '&nbsp;') . '</td></tr>';
        }
    }
    if ($childrenRows === '') {
        for ($i = 0; $i < 4; $i++) {
            $childrenRows .= '<tr><td style="border:1px solid black; padding:5px; height:20px;">&nbsp;</td><td style="border:1px solid black; padding:5px; height:20px;">&nbsp;</td></tr>';
        }
    }
    
    // Use the exact same HTML format as the working generate_joint_affidavit_solo_parent.php
    return <<<EOD
<div style="text-align:left; font-size:11pt;">
REPUBLIC OF THE PHILIPPINES)<br/>
    PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) SS<br/>
    CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/><br/>
</div>

<div style="text-align:center; font-size:11pt; font-weight:bold;">
    JOINT AFFIDAVIT OF TWO DISINTERESTED PERSON<br/>(SOLO PARENT)
</div>
<br/>

<div style="text-align:left; font-size:11pt;">
WE, <u><b>{$affiant1Name}</b></u> and <u><b>{$affiant2Name}</b></u>, Filipinos, both
    of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;legal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;age, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;and &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;permanent &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;residents &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;of<br/>
    <u><b>{$affiantsAddress}</b></u> after being duly sworn in 
    accordance with law hereby depose and say:<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That &nbsp;&nbsp;&nbsp;&nbsp;we &nbsp;&nbsp;&nbsp;&nbsp;are &nbsp;&nbsp;&nbsp;&nbsp;not &nbsp;&nbsp;&nbsp;&nbsp;in &nbsp;&nbsp;&nbsp;&nbsp;any &nbsp;&nbsp;&nbsp;&nbsp;way &nbsp;&nbsp;related &nbsp;&nbsp;by &nbsp;&nbsp;affinity &nbsp;&nbsp;or &nbsp;&nbsp;consanguinity &nbsp;&nbsp;to<br> 
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$soloParentName}</b></u>, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;resident &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;of <br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$soloParentAddress}</b></u> City of Cabuyao, Laguna;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That &nbsp;we &nbsp;know &nbsp;her / him &nbsp;as &nbsp;a &nbsp;single &nbsp;parent and the &nbsp;Mother / Father &nbsp;of this children:<br><br>
    
    <table style="width:100%; border:1px solid black;">
        <tr>
            <td style="width:80%; text-align:center; border:1px solid black; padding:5px; background-color:#f0f0f0;">Name</td>
            <td style="width:20%; text-align:center; border:1px solid black; padding:5px; background-color:#f0f0f0;">Age</td>
        </tr>
        {$childrenRows}
    </table><br><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That &nbsp;&nbsp;she/he &nbsp;&nbsp;is &nbsp;&nbsp;solely &nbsp;&nbsp;taking &nbsp;&nbsp;care &nbsp;&nbsp;and &nbsp;&nbsp;providing &nbsp;&nbsp;for her/his children's needs and<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;everything &nbsp;&nbsp;indispensable &nbsp;&nbsp;for &nbsp;&nbsp;her / his &nbsp;&nbsp;well-being &nbsp;&nbsp;since &nbsp;&nbsp;the &nbsp;&nbsp;&nbsp;&nbsp;biological &nbsp;&nbsp;&nbsp;&nbsp;Father<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/Mother abandoned her / his children;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. That &nbsp;&nbsp; we &nbsp;&nbsp;know &nbsp;&nbsp;for &nbsp;&nbsp;a &nbsp;&nbsp;fact &nbsp;&nbsp;that &nbsp;&nbsp;she/he &nbsp;&nbsp;is &nbsp;&nbsp;not &nbsp;&nbsp;cohabitating with any other man / <br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;woman since she / he become a solo parent until present;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5. That&nbsp;&nbsp; we &nbsp;&nbsp;execute &nbsp;&nbsp;this &nbsp;&nbsp;affidavit &nbsp;&nbsp;to &nbsp;&nbsp;attest &nbsp;&nbsp;to &nbsp;&nbsp;the truth of the foregoing and let this<br> 
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;instrument be useful for whatever legal intents it may serve.<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, we have hereunto set our hands this <u><b>{$dateOfNotary}</b></u> in the City of Cabuyao, Laguna.<br/><br/>
    
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$affiant2Name}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    <br>
    
<table style="width:100%;">
        <tr>
<td style="width:50%; text-align:left;">Valid ID No. <u><b>{$affiant1ValidId}</b></u></td>
<td style="width:25%; text-align:right;">Valid ID No. <u><b>{$affiant2ValidId}</b></u></td>
        </tr>
    </table><br/>
    <br>
SUBSCRIBED AND SWORN TO before me this date above mentioned at the City of Cabuyao, Laguna, affiants exhibiting to me their respective proofs of identity personally attesting that the foregoing statements are true to the best of their knowledge and beliefs.<br/><br/>
    
    Doc. No. _____<br/>
    Book No. _____<br/>
    Page No. _____<br/>
    Series of _____<br/>
</div>
EOD;
}

// Fetch employee profile image, email, and name
$stmt = $conn->prepare("SELECT profile_image, email, name FROM user_form WHERE id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$employee_email = '';
$employee_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $employee_email = $row['email'];
    $employee_name = $row['name'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'images/default-avatar.jpg';
}

// Fetch files sent by clients (documents submitted through document generation)
$stmt = $conn->prepare("
    SELECT 
        cdg.*,
        u.name as client_name,
        u.email as client_email,
        COALESCE(cdg.submitted_at, NOW()) as sent_date,
        cdg.status as file_status
    FROM client_document_generation cdg
    JOIN user_form u ON cdg.client_id = u.id
    ORDER BY COALESCE(cdg.submitted_at, NOW()) DESC
");
$stmt->execute();
$res = $stmt->get_result();
$sent_files = [];
while ($row = $res->fetch_assoc()) {
    $sent_files[] = $row;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Files - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/employee-send-files.css?v=<?= time() ?>">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar employee-sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="employee_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="employee_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-file-alt"></i><span>Document Generations</span><i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu">
                    <li><a href="employee_document_generation.php"><i class="fas fa-file-plus"></i><span>Generate Documents</span></a></li>
                    <li><a href="employee_send_files.php" class="active"><i class="fas fa-paper-plane"></i><span>Sent Files</span></a></li>
                </ul>
            </li>
            <li><a href="employee_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="employee_clients.php"><i class="fas fa-users"></i><span>Client Management</span></a></li>
            <li><a href="employee_request_management.php"><i class="fas fa-clipboard-check"></i><span>Request Review</span><?php if ($pending_requests_count > 0): ?><span class="notification-badge"><?= $pending_requests_count ?></span><?php endif; ?></a></li>
            <li><a href="employee_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        $page_title = 'Document Generation';
        $page_subtitle = 'View and download PDF documents sent by clients';
        include 'components/profile_header.php'; 
        ?>

        <div class="content-container">
            <!-- Files Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count($sent_files) ?></h3>
                        <p>Total Documents</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($sent_files, function($file) { return $file['document_type'] === 'affidavitLoss'; })) ?></h3>
                        <p>Affidavit of Loss</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($sent_files, function($file) { return $file['document_type'] === 'soloParent'; })) ?></h3>
                        <p>Solo Parent Documents</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-wheelchair"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($sent_files, function($file) { return $file['document_type'] === 'pwdLoss' || $file['document_type'] === 'boticabLoss'; })) ?></h3>
                        <p>ID Loss Documents</p>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="action-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search files..." onkeyup="filterFiles()">
                </div>
                <div class="filter-options">
                    <select id="typeFilter" onchange="filterFiles()">
                        <option value="">All Document Types</option>
                        <option value="affidavitLoss">Affidavit of Loss</option>
                        <option value="soloParent">Solo Parent</option>
                        <option value="pwdLoss">PWD ID Loss</option>
                        <option value="boticabLoss">Boticab Loss</option>
                        <option value="seniorIDLoss">Senior ID Loss</option>
                        <option value="jointAffidavit">Two Disinterested Persons</option>
                        <option value="jointAffidavitSoloParent">Joint Affidavit (Solo Parent)</option>
                        <option value="swornAffidavitMother">Sworn Affidavit of Mother</option>
                    </select>
                    <select id="statusFilter" onchange="filterFiles()">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select id="limitFilter" onchange="filterFiles()">
                        <option value="10">Show 10</option>
                        <option value="25">Show 25</option>
                        <option value="50">Show 50</option>
                        <option value="100">Show 100</option>
                        <option value="all">Show All</option>
                    </select>
                </div>
            </div>

            <!-- Files List -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-file-pdf"></i> Documents Sent by Clients</h2>
                    <div class="document-counter">
                        <span id="documentCount">Loading...</span>
                    </div>
                </div>
                
                <?php if (empty($sent_files)): ?>
                    <div class="empty-state" style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                        <i class="fas fa-inbox"></i>
                        <h3>No Files Received</h3>
                        <p>Files sent by clients will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="files-table-container">
                        <table class="files-table" id="filesTable">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Document Type</th>
                                    <th>Request ID</th>
                                    <th>Status</th>
                                    <th>Sent Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sent_files as $file): ?>
                                    <tr data-status="<?= strtolower($file['file_status']) ?>">
                                        <td>
                                            <div class="client-info">
                                                <div class="client-name"><?= htmlspecialchars($file['client_name']) ?></div>
                                                <div class="client-email"><?= htmlspecialchars($file['client_email']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="document-type">
                                                <?= htmlspecialchars(ucwords(str_replace(['affidavitLoss', 'soloParent', 'pwdLoss', 'boticabLoss', 'seniorIDLoss', 'jointAffidavit', 'jointAffidavitSoloParent'], ['Affidavit of Loss', 'Solo Parent', 'PWD ID Loss', 'Boticab Loss', 'Senior ID Loss', 'Two Disinterested Persons', 'Joint Affidavit (Solo Parent)'], $file['document_type']))) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="request-id"><?= htmlspecialchars($file['request_id']) ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($file['file_status']) ?>">
                                                <span class="status-indicator status-<?= strtolower($file['file_status']) ?>"></span>
                                                <?= $file['file_status'] ?>
                                            </span>
                                        </td>
                                        <td class="date-cell">
                                            <?= date('M d, Y H:i', strtotime($file['sent_date'])) ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($file['file_status'] === 'Pending'): ?>
                                                    <?php if (isset($file['document_data']) && !empty($file['document_data'])): ?>
                                                        <button onclick="viewDocumentData(<?= $file['id'] ?>, '<?= htmlspecialchars($file['document_type']) ?>')" class="btn btn-info btn-sm" title="View document data">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button onclick="updateDocumentStatus(<?= $file['id'] ?>, 'approved')" class="btn btn-success btn-sm" title="Approve document">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button onclick="updateDocumentStatus(<?= $file['id'] ?>, 'rejected')" class="btn btn-danger btn-sm" title="Reject document">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php elseif ($file['file_status'] === 'Approved'): ?>
                                                    <?php if (isset($file['document_data']) && !empty($file['document_data'])): ?>
                                                        <button onclick="generatePDF(<?= $file['id'] ?>, '<?= htmlspecialchars($file['document_type']) ?>')" class="btn btn-primary btn-sm" title="Download PDF">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php elseif ($file['file_status'] === 'Rejected'): ?>
                                                    <span class="text-muted" style="font-size: 12px; color: #6c757d;">
                                                        <i class="fas fa-ban"></i> No actions available
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>



    <!-- Include JavaScript file -->
    <script src="assets/js/employee-send-files.js?v=<?= time() ?>"></script>
    
    <!-- Sidebar Dropdown Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const submenu = this.parentElement;
                    submenu.classList.toggle('open');
                });
            });
        });
    </script>
<script src="assets/js/unread-messages.js?v=1761535513"></script></body>
</html>
