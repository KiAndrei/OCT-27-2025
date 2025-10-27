<?php
require_once 'session_manager.php';
validateUserAccess('employee');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_pdf_direct') {
    $documentType = $_POST['document_type'];
    $formData = json_decode($_POST['form_data'], true);
    
    // Debug logging
    error_log("Employee Document Handler - Document Type: " . $documentType);
    error_log("Employee Document Handler - Form Data: " . print_r($formData, true));
    
    if (!$formData) {
        error_log("Employee Document Handler - Invalid form data");
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
            case 'swornAffidavitMother':
                include 'files-generation/generate_sworn_affidavit_of_mother_simple.php';
                break;
            case 'pwdLoss':
                include 'files-generation/generate_affidavit_of_loss_pwd_id.php';
                break;
            case 'boticabLoss':
                include 'files-generation/generate_affidavit_of_loss_boticab.php';
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
                throw new Exception('Unknown document type: ' . $documentType);
        }
        
        // Log the action
        $auditLogger = new AuditLogger($conn);
        $auditLogger->logAction(
            $_SESSION['user_id'],
            $_SESSION['employee_name'] ?? 'Employee',
            'employee',
            'Document Generated',
            'Document Generation',
            "Generated {$documentType} document",
            'success',
            'medium'
        );
        
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate PDF: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>
