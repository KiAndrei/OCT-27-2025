<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'session_manager.php';
validateUserAccess('admin');
require_once 'config.php';
require_once 'action_logger_helper.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

if (!isset($_POST['action']) || $_POST['action'] !== 'edit_event') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$event_id = intval($_POST['event_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$date = $_POST['date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$location = trim($_POST['location'] ?? '');
$type = $_POST['type'] ?? '';
$description = trim($_POST['description'] ?? '');

// Debug: Log the received data
error_log("Edit Event Debug - Start Time: '$start_time', End Time: '$end_time'");

// Validation
if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

if (empty($date) || empty($start_time) || empty($end_time) || empty($location) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Validate and normalize time format
function normalizeTime($time) {
    // Remove any whitespace
    $time = trim($time);
    
    // Handle different time formats
    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        // Already in HH:MM or H:MM format
        return $time;
    } elseif (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $time)) {
        // Handle HH:MM:SS format - remove seconds
        $parts = explode(':', $time);
        return sprintf('%02d:%02d', intval($parts[0]), intval($parts[1]));
    } elseif (preg_match('/^\d{1,2}:\d{2}\s*(am|pm)$/i', $time)) {
        // Convert AM/PM to 24-hour format
        $time = strtolower($time);
        $parts = explode(':', $time);
        $hour = intval($parts[0]);
        $minute = intval($parts[1]);
        
        if (strpos($time, 'pm') !== false && $hour != 12) {
            $hour += 12;
        } elseif (strpos($time, 'am') !== false && $hour == 12) {
            $hour = 0;
        }
        
        return sprintf('%02d:%02d', $hour, $minute);
    }
    
    return false;
}

$normalized_start_time = normalizeTime($start_time);
$normalized_end_time = normalizeTime($end_time);

error_log("Edit Event Debug - Normalized Start: '$normalized_start_time', Normalized End: '$normalized_end_time'");

if (!$normalized_start_time) {
    echo json_encode(['success' => false, 'message' => 'Invalid start time format']);
    exit;
}

if (!$normalized_end_time) {
    echo json_encode(['success' => false, 'message' => 'Invalid end time format']);
    exit;
}

// Use normalized times
$start_time = $normalized_start_time;
$end_time = $normalized_end_time;

// Validate that end time is after start time
if ($end_time <= $start_time) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit;
}

// Validate that date is not in the past
$today = date('Y-m-d');
if ($date < $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot schedule events in the past']);
    exit;
}

// Check if event exists and admin has permission to edit it
$stmt = $conn->prepare("SELECT * FROM case_schedules WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

$event = $result->fetch_assoc();

// Check if event is completed
if ($event['status'] && strtolower($event['status']) === 'completed') {
    echo json_encode(['success' => false, 'message' => 'Cannot edit completed schedules']);
    exit;
}

// Admin can edit any event
// Update the event
error_log("About to prepare SQL statement");
$stmt = $conn->prepare("UPDATE case_schedules SET date = ?, start_time = ?, end_time = ?, location = ?, type = ?, description = ? WHERE id = ?");
if (!$stmt) {
    error_log("Failed to prepare statement: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database preparation failed']);
    exit;
}

error_log("About to bind parameters");
$stmt->bind_param("ssssssi", $date, $start_time, $end_time, $location, $type, $description, $event_id);

error_log("About to execute statement");
if ($stmt->execute()) {
    // Log the action
    $scheduleDetails = "Event ID: $event_id, Date: $date, Start Time: $start_time, End Time: $end_time, Location: $location, Type: $type";
    logScheduleAction('Updated', $scheduleDetails);
    
    echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update event']);
}

$stmt->close();
$conn->close();

} catch (Exception $e) {
    error_log("Edit event error: " . $e->getMessage());
    error_log("Edit event stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log("Edit event fatal error: " . $e->getMessage());
    error_log("Edit event fatal stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Fatal error occurred: ' . $e->getMessage()]);
}
?>


