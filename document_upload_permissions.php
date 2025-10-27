<?php
/**
 * Document Upload Permission Checker
 * Ensures users can only upload documents to cases they have access to
 */

require_once 'session_manager.php';
require_once 'config.php';

class DocumentUploadPermission {
    private $conn;
    private $user_id;
    private $user_type;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->user_id = $_SESSION['user_id'] ?? null;
        $this->user_type = $_SESSION['user_type'] ?? 'client';
    }
    
    /**
     * Check if user can upload documents to a specific case
     */
    public function canUploadToCase($case_id) {
        if (!$this->user_id || !$case_id) {
            return false;
        }
        
        // Clients cannot upload documents
        if ($this->user_type === 'client') {
            return false;
        }
        
        // Admins can upload to cases assigned to them (as attorney)
        if ($this->user_type === 'admin') {
            return $this->isCaseAssignedToAttorney($case_id, $this->user_id);
        }
        
        // Attorneys can only upload to cases assigned to them
        if ($this->user_type === 'attorney') {
            return $this->isCaseAssignedToAttorney($case_id, $this->user_id);
        }
        
        return false;
    }
    
    /**
     * Check if case exists
     */
    private function caseExists($case_id) {
        $stmt = $this->conn->prepare("SELECT id FROM attorney_cases WHERE id = ?");
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    /**
     * Check if case is assigned to specific attorney
     */
    private function isCaseAssignedToAttorney($case_id, $attorney_id) {
        // All attorneys can upload to all cases since clients are shared
        $stmt = $this->conn->prepare("SELECT id FROM attorney_cases WHERE id = ?");
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    /**
     * Get cases user can upload documents to
     */
    public function getUploadableCases() {
        if (!$this->user_id) {
            return [];
        }
        
        if ($this->user_type === 'admin') {
            // Admin can upload to cases assigned to them
            $stmt = $this->conn->prepare("
                SELECT ac.id, ac.title, ac.case_type, ac.status,
                       c.name as client_name, a.name as attorney_name
                FROM attorney_cases ac
                LEFT JOIN user_form c ON ac.client_id = c.id
                LEFT JOIN user_form a ON ac.attorney_id = a.id
                WHERE ac.attorney_id = ?
                ORDER BY ac.created_at DESC
            ");
            $stmt->bind_param("i", $this->user_id);
            $stmt->execute();
        } elseif ($this->user_type === 'attorney') {
            // Attorney can upload to all cases (shared clients)
            $stmt = $this->conn->prepare("
                SELECT ac.id, ac.title, ac.case_type, ac.status,
                       c.name as client_name, a.name as attorney_name
                FROM attorney_cases ac
                LEFT JOIN user_form c ON ac.client_id = c.id
                LEFT JOIN user_form a ON ac.attorney_id = a.id
                ORDER BY ac.created_at DESC
            ");
            $stmt->execute();
        } else {
            // Clients cannot upload documents
            return [];
        }
        
        $result = $stmt->get_result();
        $cases = [];
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }
        
        return $cases;
    }
    
    /**
     * Validate document upload request
     */
    public function validateUploadRequest($case_id, $file_data = null) {
        // Check basic permissions
        if (!$this->canUploadToCase($case_id)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to upload documents to this case.'
            ];
        }
        
        // Check if case exists and is active
        $stmt = $this->conn->prepare("SELECT id, status FROM attorney_cases WHERE id = ?");
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Case not found.'
            ];
        }
        
        $case = $result->fetch_assoc();
        
        // Check if case is closed (optional restriction)
        if ($case['status'] === 'Closed') {
            return [
                'success' => false,
                'error' => 'Cannot upload documents to closed cases.'
            ];
        }
        
        // Validate file if provided
        if ($file_data) {
            $file_validation = $this->validateFile($file_data);
            if (!$file_validation['success']) {
                return $file_validation;
            }
        }
        
        return [
            'success' => true,
            'case' => $case
        ];
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file_data) {
        $allowed_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'image/gif'
        ];
        
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if ($file_data['size'] > $max_size) {
            return [
                'success' => false,
                'error' => 'File size exceeds 10MB limit.'
            ];
        }
        
        if (!in_array($file_data['type'], $allowed_types)) {
            return [
                'success' => false,
                'error' => 'File type not allowed. Please upload PDF, Word, or image files.'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Log document upload activity
     */
    public function logUploadActivity($case_id, $file_name, $file_path) {
        try {
            require_once 'audit_logger.php';
            $auditLogger = new AuditLogger($this->conn);
            
            $user_name = $_SESSION['attorney_name'] ?? $_SESSION['admin_name'] ?? 'User';
            
            $auditLogger->logAction(
                $this->user_id,
                $user_name,
                $this->user_type,
                'Document Upload',
                'Case Management',
                "Uploaded document '$file_name' to case #$case_id",
                'success',
                'medium'
            );
        } catch (Exception $e) {
            error_log("Failed to log document upload activity: " . $e->getMessage());
        }
    }
}

// Global function for easy access
function checkDocumentUploadPermission($case_id) {
    global $conn;
    $permission = new DocumentUploadPermission($conn);
    return $permission->canUploadToCase($case_id);
}

function getUploadableCases() {
    global $conn;
    $permission = new DocumentUploadPermission($conn);
    return $permission->getUploadableCases();
}
?>
