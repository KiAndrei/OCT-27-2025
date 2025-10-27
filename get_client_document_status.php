<?php
// Disable error reporting to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'session_manager.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated or not a client']);
    exit();
}

$client_id = $_SESSION['user_id'];

try {
    // Get client's document submissions with status
    $stmt = $conn->prepare("
        SELECT 
            id,
            request_id,
            document_type,
            status,
            submitted_at,
            reviewed_at,
            rejection_reason
        FROM client_document_generation 
        WHERE client_id = ?
        ORDER BY submitted_at DESC
    ");
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'documents' => $documents
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching client document status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch document status'
    ]);
}
?>
