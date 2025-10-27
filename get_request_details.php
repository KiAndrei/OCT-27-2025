<?php
require_once 'session_manager.php';
validateUserAccess('employee');
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit();
}

$request_id = intval($_GET['id']);

try {
    // Fetch request details with attorney assignment and review information
    $stmt = $conn->prepare("
        SELECT crf.*, u.name as client_name, u.email as client_email,
               cec.id as conversation_id, cec.conversation_status, cec.concern_identified,
               caa.id as assignment_id, caa.attorney_id, caa.seen_status,
               att.name as attorney_name, err.action as review_action, err.review_notes,
               emp.name as reviewed_by_name
        FROM client_request_form crf
        JOIN user_form u ON crf.client_id = u.id
        LEFT JOIN client_employee_conversations cec ON crf.id = cec.request_form_id
        LEFT JOIN client_attorney_assignments caa ON crf.client_id = caa.client_id
        LEFT JOIN user_form att ON caa.attorney_id = att.id
        LEFT JOIN employee_request_reviews err ON crf.id = err.request_form_id
        LEFT JOIN user_form emp ON err.employee_id = emp.id
        WHERE crf.id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    $request = $result->fetch_assoc();
    
    // Convert privacy_consent to boolean for JavaScript
    $request['privacy_consent'] = (bool)$request['privacy_consent'];
    
    echo json_encode([
        'success' => true,
        'request' => $request
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
