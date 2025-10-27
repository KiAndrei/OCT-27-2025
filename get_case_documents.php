<?php
/**
 * Get Case Documents Endpoint
 * Returns all documents associated with a specific case
 */

require_once 'session_manager.php';
require_once 'config.php';
require_once 'document_upload_permissions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isSessionValid()) {
    http_response_code(401);
    echo json_encode(['error' => 'Session expired. Please login again.']);
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? 'client';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get case ID from request
$case_id = intval($_POST['case_id'] ?? 0);

if ($case_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid case ID']);
    exit;
}

try {
    $permission = new DocumentUploadPermission($conn);
    
    // Verify user has access to this case
    $hasAccess = false;
    
    if ($user_type === 'admin') {
        // Admin can access all cases
        $hasAccess = true;
    } elseif ($user_type === 'attorney') {
        // Attorney can access all cases (shared clients)
        $hasAccess = true;
    } elseif ($user_type === 'client') {
        // Client can only access their own cases
        $stmt = $conn->prepare("SELECT id FROM attorney_cases WHERE id = ? AND client_id = ?");
        $stmt->bind_param("ii", $case_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasAccess = $result->num_rows > 0;
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this case']);
        exit;
    }
    
    // Fetch documents for the case from case_documents table
    $documents = [];
    
    // Check if case_documents table exists
    $table_result = $conn->query("SHOW TABLES LIKE 'case_documents'");
    if ($table_result->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT 
                cd.id,
                cd.file_name,
                cd.file_path,
                cd.category,
                cd.description,
                cd.file_size,
                cd.file_type,
                cd.uploaded_at as upload_date,
                uf.name as uploaded_by_name,
                uf.user_type as uploaded_by_type
            FROM case_documents cd
            LEFT JOIN user_form uf ON cd.uploaded_by = uf.id
            WHERE cd.case_id = ?
            ORDER BY cd.uploaded_at DESC
        ");
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
    } else {
        // Fallback to attorney_documents if case_documents doesn't exist
        $columns_result = $conn->query("SHOW COLUMNS FROM attorney_documents LIKE 'case_id'");
        if ($columns_result->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    ad.id,
                    ad.file_name,
                    ad.file_path,
                    ad.category,
                    ad.description,
                    ad.file_size,
                    ad.file_type,
                    ad.upload_date,
                    uf.name as uploaded_by_name,
                    'attorney' as uploaded_by_type
                FROM attorney_documents ad
                LEFT JOIN user_form uf ON ad.uploaded_by = uf.id
                WHERE ad.case_id = ?
                ORDER BY ad.upload_date DESC
            ");
            $stmt->bind_param("i", $case_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $documents[] = $row;
            }
        }
    }
    
    // Sort all documents by upload date
    usort($documents, function($a, $b) {
        return strtotime($b['upload_date']) - strtotime($a['upload_date']);
    });
    
    // Format file sizes
    foreach ($documents as &$doc) {
        $doc['file_size_formatted'] = formatFileSize($doc['file_size']);
        $doc['upload_date_formatted'] = date('M j, Y g:i A', strtotime($doc['upload_date']));
    }
    
    // Return documents
    echo json_encode($documents);
    
} catch (Exception $e) {
    error_log("Error fetching case documents: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
