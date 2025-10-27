<?php
require_once 'session_manager.php';
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    $unread_count = 0;
    
    switch ($user_type) {
        case 'admin':
            // Admin sees unread messages from clients in conversations where admin is assigned as attorney
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unread_count
                FROM client_attorney_messages cam
                JOIN client_attorney_conversations cac ON cam.conversation_id = cac.id
                JOIN client_attorney_assignments caa ON cac.assignment_id = caa.id
                WHERE caa.attorney_id = ? AND cam.sender_type = 'client' AND cam.is_seen = 0
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['unread_count'] ?? 0;
            break;
            
        case 'attorney':
            // Attorney sees unread messages from clients in their assigned conversations
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unread_count
                FROM client_attorney_messages cam
                JOIN client_attorney_conversations cac ON cam.conversation_id = cac.id
                JOIN client_attorney_assignments caa ON cac.assignment_id = caa.id
                WHERE caa.attorney_id = ? AND cam.sender_type = 'client' AND cam.is_seen = 0
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['unread_count'] ?? 0;
            break;
            
        case 'employee':
            // Employee sees unread messages from clients in their assigned conversations
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unread_count
                FROM client_employee_messages cem
                JOIN client_employee_conversations cec ON cem.conversation_id = cec.id
                WHERE cec.employee_id = ? AND cem.sender_type = 'client' AND cem.is_seen = 0
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['unread_count'] ?? 0;
            break;
            
        case 'client':
            // Client sees unread messages from both employees and attorneys
            $stmt = $conn->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM client_employee_messages cem 
                     JOIN client_employee_conversations cec ON cem.conversation_id = cec.id 
                     WHERE cec.client_id = ? AND cem.sender_type = 'employee' AND cem.is_seen = 0) +
                    (SELECT COUNT(*) FROM client_attorney_messages cam 
                     JOIN client_attorney_conversations cac ON cam.conversation_id = cac.id 
                     JOIN client_attorney_assignments caa ON cac.assignment_id = caa.id
                     WHERE caa.client_id = ? AND cam.sender_type = 'attorney' AND cam.is_seen = 0) as total_unread
            ");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['total_unread'] ?? 0;
            break;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$unread_count,
        'user_type' => $user_type,
        'has_unread' => $unread_count > 0
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
