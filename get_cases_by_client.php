<?php
/**
 * Get Cases by Client API Endpoint
 * Returns cases that belong to a specific client for dropdown population
 */

require_once 'session_manager.php';
require_once 'config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if client_id is provided
if (!isset($_POST['client_id']) || !is_numeric($_POST['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit();
}

$client_id = intval($_POST['client_id']);
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    $cases = [];
    
    if ($user_type === 'admin') {
        // Admin can see all PENDING and ACTIVE cases for any client
        $stmt = $conn->prepare("
            SELECT id, title, case_type, status, created_at 
            FROM attorney_cases 
            WHERE client_id = ? AND status IN ('pending', 'active', 'Pending', 'Active')
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('i', $client_id);
        
    } elseif ($user_type === 'attorney') {
        // Attorney can see all PENDING and ACTIVE cases for the client (shared clients)
        $stmt = $conn->prepare("
            SELECT id, title, case_type, status, created_at 
            FROM attorney_cases 
            WHERE client_id = ? AND status IN ('pending', 'active', 'Pending', 'Active')
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('i', $client_id);
        
    } elseif ($user_type === 'employee') {
        // Employee can see all PENDING and ACTIVE cases for any client
        $stmt = $conn->prepare("
            SELECT id, title, case_type, status, created_at 
            FROM attorney_cases 
            WHERE client_id = ? AND status IN ('pending', 'active', 'Pending', 'Active')
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('i', $client_id);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $cases[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'case_type' => $row['case_type'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'formatted_date' => date('M j, Y', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cases' => $cases,
        'total_cases' => count($cases)
    ]);
    
} catch (Exception $e) {
    error_log("Get cases by client API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
