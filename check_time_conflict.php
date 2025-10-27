<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session manually to avoid conflicts
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Check if user is logged in and is an attorney
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'attorney') {
    http_response_code(403);
    echo json_encode(['hasConflict' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['hasConflict' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get parameters from POST data
$date = $_POST['date'] ?? null;
$time = $_POST['time'] ?? null;
$attorney_id = $_POST['attorney_id'] ?? $_SESSION['user_id'];

// Validate input
if (!$date || !$time) {
    echo json_encode(['hasConflict' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Check for time conflicts
    $stmt = $conn->prepare("SELECT id, title, type, date, time FROM case_schedules WHERE attorney_id = ? AND date = ? AND time = ? AND status != 'Cancelled'");
    $stmt->bind_param("iss", $attorney_id, $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conflict_event = $result->fetch_assoc();
        echo json_encode([
            'hasConflict' => true,
            'conflictEvent' => [
                'id' => $conflict_event['id'],
                'title' => $conflict_event['title'],
                'type' => $conflict_event['type'],
                'date' => $conflict_event['date'],
                'time' => $conflict_event['time']
            ]
        ]);
    } else {
        echo json_encode(['hasConflict' => false]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error checking time conflict: " . $e->getMessage());
    echo json_encode(['hasConflict' => false, 'message' => 'Database error occurred']);
}

$conn->close();
?>
