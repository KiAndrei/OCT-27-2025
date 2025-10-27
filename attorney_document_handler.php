<?php
// Debug: Log that the handler is being accessed
error_log("Attorney Document Handler - Starting execution");

require_once 'session_manager.php';
error_log("Attorney Document Handler - Session manager loaded");

// Check session manually to avoid redirect
if (!isSessionValid()) {
    error_log("Attorney Document Handler - Invalid session");
    http_response_code(401);
    echo json_encode(['error' => 'Session expired. Please login again.']);
    exit;
}

if ($_SESSION['user_type'] !== 'attorney') {
    error_log("Attorney Document Handler - Invalid user type: " . $_SESSION['user_type']);
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Attorney access required.']);
    exit;
}

error_log("Attorney Document Handler - User access validated");

require_once 'config.php';
error_log("Attorney Document Handler - Config loaded");

require_once 'audit_logger.php';
error_log("Attorney Document Handler - Audit logger loaded");

require_once 'action_logger_helper.php';
error_log("Attorney Document Handler - Action logger helper loaded");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_pdf_direct') {
    $documentType = $_POST['document_type'];
    $formData = json_decode($_POST['form_data'], true);
    
    // Debug logging
    error_log("Attorney Document Handler - Document Type: " . $documentType);
    error_log("Attorney Document Handler - Form Data: " . print_r($formData, true));
    
    if (!$formData) {
        error_log("Attorney Document Handler - Invalid form data");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid form data']);
        exit;
    }
    
    try {
        // Convert form data to GET parameters for the PDF generation files
        $_GET = array_merge($_GET, $formData);
        
        // Set content type for PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="generated_document.pdf"');
        
        // Include the appropriate PDF generation file
        switch ($documentType) {
            case 'affidavitLoss':
                include 'files-generation/generate_affidavit_of_loss.php';
                break;
            case 'seniorIDLoss':
                include 'files-generation/generate_affidavit_of_loss_senior_id.php';
                break;
            case 'soloParent':
                include 'files-generation/generate_sworn_affidavit_of_solo_parent.php';
                break;
            case 'pwdLoss':
                include 'files-generation/generate_affidavit_of_loss_pwd_id.php';
                break;
            case 'boticabLoss':
                include 'files-generation/generate_affidavit_of_loss_boticab.php';
                break;
            case 'swornAffidavitMother':
                include 'files-generation/generate_sworn_affidavit_of_mother_simple.php';
                break;
            case 'jointAffidavit':
                include 'files-generation/generate_joint_affidavit_two-disinterested-person.php';
                break;
            case 'jointAffidavitSoloParent':
                include 'files-generation/generate_joint_affidavit_solo_parent.php';
                break;
            case 'swornAffidavitSoloParent':
                include 'files-generation/generate_sworn_affidavit_of_solo_parent.php';
                break;
            default:
                error_log("Attorney Document Handler - Unknown document type: " . $documentType);
                http_response_code(400);
                echo json_encode(['error' => 'Unknown document type']);
                exit;
        }
        
        // Log successful generation
        if (function_exists('logAction')) {
            logAction($_SESSION['user_id'], 'PDF_GENERATED', 'Generated ' . $documentType . ' PDF', [
                'document_type' => $documentType,
                'form_data' => $formData
            ]);
        } else {
            error_log("Attorney Document Handler - PDF generated successfully: " . $documentType);
        }
        
    } catch (Exception $e) {
        error_log("Attorney Document Handler - Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]);
        exit;
    }
} else {
    error_log("Attorney Document Handler - Invalid request method or action");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
?>
