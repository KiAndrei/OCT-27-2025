<?php
/**
 * Get Case Schedules Endpoint
 * Returns all schedules associated with a specific case
 */

require_once 'session_manager.php';
require_once 'config.php';

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
    
    // Fetch schedules for the case
    $stmt = $conn->prepare("
        SELECT 
            cs.id,
            cs.type,
            cs.title,
            cs.description,
            cs.date,
            cs.start_time,
            cs.end_time,
            cs.location,
            cs.status,
            cs.created_at,
            CASE 
                WHEN cs.attorney_id IS NOT NULL THEN uf_attorney.name 
                ELSE ac_attorney.name
            END as attorney_name,
            CASE 
                WHEN cs.client_id IS NOT NULL THEN uf_client.name 
                WHEN cs.walkin_client_name IS NOT NULL THEN cs.walkin_client_name
                ELSE 'Walk-in Client'
            END as client_name,
            uf_creator.name as created_by_name
        FROM case_schedules cs
        LEFT JOIN attorney_cases ac ON cs.case_id = ac.id
        LEFT JOIN user_form uf_attorney ON cs.attorney_id = uf_attorney.id
        LEFT JOIN user_form ac_attorney ON ac.attorney_id = ac_attorney.id
        LEFT JOIN user_form uf_client ON cs.client_id = uf_client.id
        LEFT JOIN user_form uf_creator ON cs.created_by_employee_id = uf_creator.id
        WHERE cs.case_id = ?
        ORDER BY cs.date ASC, cs.start_time ASC
    ");
    
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'description' => $row['description'],
            'date' => $row['date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'location' => $row['location'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'attorney_name' => $row['attorney_name'],
            'client_name' => $row['client_name'],
            'created_by_name' => $row['created_by_name']
        ];
    }
    
    // Return schedules
    echo json_encode($schedules);
    
} catch (Exception $e) {
    error_log("Error fetching case schedules: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
