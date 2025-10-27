<?php
/**
 * Enhanced Document Upload Handler
 * Handles document uploads with case-specific permissions
 */

// Start output buffering to catch any output
ob_start();

// Disable error display to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header first
header('Content-Type: application/json');

// Check for any output before includes
$output_before = ob_get_contents();
if ($output_before) {
    error_log("Output before includes: " . $output_before);
    ob_clean();
}

require_once 'config.php';
require_once 'document_upload_permissions.php';

// Check for any output after includes
$output_after = ob_get_contents();
if ($output_after) {
    error_log("Output after includes: " . $output_after);
    ob_clean();
}

// Check session manually instead of using session_manager
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Session expired. Please login again.']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $permission = new DocumentUploadPermission($conn);
    
    // Get case ID from request
    $case_id = intval($_POST['case_id'] ?? 0);
    
    if ($case_id <= 0) {
        throw new Exception('Invalid case ID');
    }
    
    // Validate upload permission
    $validation = $permission->validateUploadRequest($case_id);
    if (!$validation['success']) {
        http_response_code(403);
        echo json_encode(['error' => $validation['error']]);
        exit;
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['documents']) || empty($_FILES['documents']['name'][0])) {
        throw new Exception('No files uploaded');
    }
    
    $uploaded_count = 0;
    $errors = [];
    $uploaded_files = [];
    
    // Process each uploaded file
    foreach ($_FILES['documents']['name'] as $key => $filename) {
        if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
            // Get form data
            $doc_name = trim($_POST['doc_names'][$key] ?? '');
            $category = trim($_POST['categories'][$key] ?? '');
            $description = trim($_POST['descriptions'][$key] ?? '');
            
            if (empty($doc_name)) {
                $errors[] = "Document name is required for file: " . $filename;
                continue;
            }
            
            if (empty($category)) {
                $errors[] = "Category is required for file: " . $filename;
                continue;
            }
            
            // Validate file
            $file_data = [
                'name' => $filename,
                'type' => $_FILES['documents']['type'][$key],
                'size' => $_FILES['documents']['size'][$key],
                'tmp_name' => $_FILES['documents']['tmp_name'][$key]
            ];
            
            $file_validation = $permission->validateUploadRequest($case_id, $file_data);
            if (!$file_validation['success']) {
                $errors[] = $file_validation['error'] . " (File: " . $filename . ")";
                continue;
            }
            
            // Prepare file for upload
            $fileInfo = pathinfo($filename);
            $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
            $safeDocName = preg_replace('/[^A-Za-z0-9 _\-]/', '', $doc_name);
            $fileName = $safeDocName . $extension;
            
            // Determine upload directory based on user type
            $user_type = $_SESSION['user_type'] ?? 'client';
            $targetDir = 'uploads/' . $user_type . '/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $targetFile = $targetDir . time() . '_' . $key . '_' . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$key], $targetFile)) {
                $uploadedBy = $_SESSION['user_id'];
                $file_size = $_FILES['documents']['size'][$key];
                $file_type = $_FILES['documents']['type'][$key];
                
                // Insert into case_documents table for case-specific tracking
                $stmt = $conn->prepare("INSERT INTO case_documents (case_id, file_name, file_path, category, uploaded_by, file_size, file_type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssiiss', $case_id, $fileName, $targetFile, $category, $uploadedBy, $file_size, $file_type, $description);
                
                $stmt->execute();
                $doc_id = $conn->insert_id;
                
                // Log the upload activity
                $permission->logUploadActivity($case_id, $fileName, $targetFile);
                
                $uploaded_files[] = [
                    'id' => $doc_id,
                    'name' => $fileName,
                    'category' => $category,
                    'size' => $file_size,
                    'type' => $file_type,
                    'description' => $description
                ];
                
                $uploaded_count++;
            } else {
                $errors[] = "Failed to upload file: " . $filename;
            }
        }
    }
    
    // Return response
    if ($uploaded_count > 0) {
        $response = [
            'success' => true,
            'message' => "Successfully uploaded $uploaded_count document(s)!",
            'count' => $uploaded_count,
            'files' => $uploaded_files,
            'errors' => $errors
        ];
    } else {
        $response = [
            'success' => false,
            'error' => 'No files were uploaded successfully.',
            'errors' => $errors
        ];
    }
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit;
    
} catch (Exception $e) {
    error_log("Document upload error: " . $e->getMessage());
    error_log("Document upload error trace: " . $e->getTraceAsString());
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}

// Clear any remaining output and send only JSON
$output = ob_get_contents();
if ($output) {
    error_log("Output before JSON: " . $output);
}
ob_end_clean();
?>
